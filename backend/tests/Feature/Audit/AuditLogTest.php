<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditLog;
use App\Enums\AuditAction;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

/**
 * Journal d'audit (identite-6, 11.02) : qui a fait quoi, quand, sur quel objet,
 * avec le résumé avant / après ; jamais de secret en clair ; rétention
 * paramétrable ; consultation réservée aux administrateurs d'instance.
 */
class AuditLogTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Valeur d'authentification du compte d'essai.
	 *
	 * @var string
	 */
	private string $auth;

	/**
	 * Compte d'essai activé.
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * Crée un compte activé et prépare la session SPA.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Requêtes venant de la SPA : Sanctum ouvre une session (statefulApi).
		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);

		$this->auth = base64_encode(random_bytes(32));
		$this->user = User::factory()->create(['email' => 'alice@example.org', 'password' => $this->auth]);
	}

	/**
	 * Crée un administrateur d'instance.
	 *
	 * @return User
	 */
	private function admin(): User
	{
		$admin = User::factory()->create();
		$admin->forceFill(['is_instance_admin' => true])->save();

		return $admin;
	}

	/**
	 * Texte JSON de tout le journal, pour vérifier qu'un secret n'y figure nulle part.
	 *
	 * @return string
	 */
	private function wholeLog(): string
	{
		return (string) json_encode(AuditEvent::query()->get()->toArray());
	}

	/**
	 * Une connexion réussie est consignée avec son auteur, l'horodatage et
	 * l'adresse IP, sans la valeur d'authentification.
	 *
	 * @return void
	 */
	public function test_a_successful_login_is_recorded_without_the_authentication_value(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertOk();

		$event = AuditEvent::query()->where('action', AuditAction::LoginSucceeded)->sole();
		$this->assertSame($this->user->id, $event->user_id);
		$this->assertSame('user', $event->subject_type);
		$this->assertSame((string) $this->user->id, $event->subject_id);
		$this->assertSame('127.0.0.1', $event->ip_address);
		$this->assertStringNotContainsString($this->auth, $this->wholeLog());
	}

	/**
	 * Une connexion refusée est consignée, sans auteur, avec l'adresse tentée,
	 * même pour une adresse inconnue ; la valeur envoyée n'apparaît jamais.
	 *
	 * @return void
	 */
	public function test_failed_logins_are_recorded_without_the_submitted_value(): void
	{
		$wrong = base64_encode(random_bytes(32));
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $wrong])->assertUnprocessable();
		$this->postJson('/api/v1/auth/login', ['email' => 'inconnu@example.org', 'auth' => $wrong])->assertUnprocessable();

		$events = AuditEvent::query()->where('action', AuditAction::LoginFailed)->orderBy('id')->get();
		$this->assertCount(2, $events);
		$this->assertNull($events[0]->user_id);
		$this->assertSame((string) $this->user->id, $events[0]->subject_id);
		$this->assertSame(['email' => 'alice@example.org'], $events[0]->after);
		$this->assertNull($events[1]->subject_id);
		$this->assertSame(['email' => 'inconnu@example.org'], $events[1]->after);
		$this->assertStringNotContainsString($wrong, $this->wholeLog());
	}

	/**
	 * Le verrouillage d'un compte après des échecs répétés est consigné.
	 *
	 * @return void
	 */
	public function test_an_account_lockout_is_recorded(): void
	{
		config(['suivi.authentication.lockout_failures' => 2, 'suivi.authentication.slowdown_after' => 100]);
		$wrong = base64_encode(random_bytes(32));

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $wrong]);
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $wrong]);

		$event = AuditEvent::query()->where('action', AuditAction::AccountLocked)->sole();
		$this->assertSame((string) $this->user->id, $event->subject_id);
		$this->assertArrayHasKey('locked_until', $event->after ?? []);
	}

	/**
	 * La déconnexion est consignée avec son auteur.
	 *
	 * @return void
	 */
	public function test_a_logout_is_recorded(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertOk();
		$this->postJson('/api/v1/auth/logout')->assertNoContent();

		$this->assertSame($this->user->id, AuditEvent::query()->where('action', AuditAction::Logout)->sole()->user_id);
	}

	/**
	 * Un changement de statut de test est consigné avec l'auteur,
	 * l'organisation, le test et les statuts avant / après.
	 *
	 * @return void
	 */
	public function test_a_test_status_change_records_before_and_after(): void
	{
		$tenant = Tenant::factory()->create();
		$manager = User::factory()->create();
		$manager->tenants()->attach($tenant->id, ['role' => TenantRole::ProjectManager->value]);
		$test = PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::factory()->create(['tenant_id' => $tenant->id]));
		Sanctum::actingAs($manager);

		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'in_progress', 'comment' => 'Démarrage'])->assertOk();

		$event = AuditEvent::query()->where('action', AuditAction::TestStatusChanged)->sole();
		$this->assertSame($manager->id, $event->user_id);
		$this->assertSame($tenant->id, $event->tenant_id);
		$this->assertSame('test', $event->subject_type);
		$this->assertSame($test->id, $event->subject_id);
		$this->assertSame(['status' => 'planned'], $event->before);
		$this->assertSame(['status' => 'in_progress', 'comment' => 'Démarrage'], $event->after);
	}

	/**
	 * Le parcours d'un test laisse sa trace : création du test, constat,
	 * cotation, statut de remédiation, puis suppression du test.
	 *
	 * @return void
	 */
	public function test_the_lifecycle_of_a_test_and_its_findings_is_recorded(): void
	{
		$tenant = Tenant::factory()->create();
		$admin = $this->admin();
		Sanctum::actingAs($admin);
		$testId = $this->postJson('/api/v1/tests', [
			'tenant_id' => $tenant->id,
			'reference' => 'PT-1',
			'title' => 'Test externe',
			'type' => 'grey_box',
			'exposure' => 'external',
		])->assertCreated()->json('data.id');

		$tester = User::factory()->create();
		$tester->tenants()->attach($tenant->id, ['role' => TenantRole::Tester->value]);
		Sanctum::actingAs($tester);
		$phase = PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::query()->findOrFail($testId)->phases()->firstOrFail()->id);
		$vulnerabilityId = $this->postJson("/api/v1/tests/{$testId}/vulnerabilities", ['title' => 'Constat', 'phase_id' => $phase])
			->assertCreated()->json('data.id');
		$this->patchJson("/api/v1/vulnerabilities/{$vulnerabilityId}", ['cvss_vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'])->assertOk();
		$this->patchJson("/api/v1/vulnerabilities/{$vulnerabilityId}/remediation", ['status' => 'in_progress'])->assertOk();

		Sanctum::actingAs($admin);
		$this->deleteJson("/api/v1/tests/{$testId}")->assertNoContent();

		$this->assertSame('PT-1', AuditEvent::query()->where('action', AuditAction::TestCreated)->sole()->after['reference'] ?? null);
		$created = AuditEvent::query()->where('action', AuditAction::VulnerabilityCreated)->sole();
		$this->assertSame($tester->id, $created->user_id);
		$this->assertSame($tenant->id, $created->tenant_id);
		$this->assertSame('critical', AuditEvent::query()->where('action', AuditAction::VulnerabilityUpdated)->sole()->after['severity'] ?? null);
		$remediation = AuditEvent::query()->where('action', AuditAction::RemediationStatusChanged)->sole();
		$this->assertSame(['status' => 'todo'], $remediation->before);
		$this->assertSame('in_progress', $remediation->after['status'] ?? null);
		$this->assertSame($testId, AuditEvent::query()->where('action', AuditAction::TestDeleted)->sole()->subject_id);
	}

	/**
	 * La modification d'une organisation ne consigne que les champs changés.
	 *
	 * @return void
	 */
	public function test_an_update_records_only_the_changed_fields(): void
	{
		$tenant = Tenant::factory()->create(['name' => 'Alpha', 'legal_name' => 'Alpha SAS']);
		Sanctum::actingAs($this->admin());

		$this->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => 'Alpha Industries', 'legal_name' => 'Alpha SAS'])->assertOk();

		$event = AuditEvent::query()->where('action', AuditAction::TenantUpdated)->sole();
		$this->assertSame($tenant->id, $event->tenant_id);
		$this->assertSame(['name' => 'Alpha'], $event->before);
		$this->assertSame(['name' => 'Alpha Industries'], $event->after);
	}

	/**
	 * Une suppression d'organisation reste dans le journal après la suppression.
	 *
	 * @return void
	 */
	public function test_a_deleted_tenant_keeps_its_audit_events(): void
	{
		$tenant = Tenant::factory()->create(['name' => 'Gamma']);
		Sanctum::actingAs($this->admin());

		$this->deleteJson("/api/v1/tenants/{$tenant->id}")->assertNoContent();

		$event = AuditEvent::query()->where('action', AuditAction::TenantDeleted)->sole();
		$this->assertSame($tenant->id, $event->tenant_id);
		$this->assertSame('Gamma', $event->before['name'] ?? null);
	}

	/**
	 * Les champs secrets sont masqués à toute profondeur, quel que soit l'appelant ;
	 * les textes trop longs sont tronqués.
	 *
	 * @return void
	 */
	public function test_secrets_are_redacted_at_any_depth(): void
	{
		$event = app(AuditLog::class)->record(AuditAction::ProfileUpdated, $this->user, after: [
			'password' => 'hunter2',
			'auth' => 'valeur-derivee',
			'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
			'name' => 'Alice',
			'keys' => ['private_key' => 'clé', 'wrapped_cek' => 'cek', 'public_key' => 'publique'],
			'description' => str_repeat('a', AuditLog::MAX_TEXT + 10),
		]);

		$after = $event->refresh()->after ?? [];
		$this->assertSame(AuditLog::REDACTED, $after['password']);
		$this->assertSame(AuditLog::REDACTED, $after['auth']);
		$this->assertSame(AuditLog::REDACTED, $after['two_factor_secret']);
		$this->assertSame(AuditLog::REDACTED, $after['keys']['private_key']);
		$this->assertSame(AuditLog::REDACTED, $after['keys']['wrapped_cek']);
		$this->assertSame('publique', $after['keys']['public_key']);
		$this->assertSame('Alice', $after['name']);
		$this->assertSame(AuditLog::MAX_TEXT + 1, mb_strlen($after['description']));
		$this->assertStringNotContainsString('hunter2', $this->wholeLog());
	}

	/**
	 * Un événement ne se modifie ni ne se supprime un à un.
	 *
	 * @return void
	 */
	public function test_events_are_immutable(): void
	{
		$event = app(AuditLog::class)->record(AuditAction::Logout, $this->user, actor: $this->user);

		try {
			$event->update(['action' => AuditAction::LoginSucceeded]);
			$this->fail('La modification aurait dû être refusée.');
		} catch (LogicException) {
		}

		$this->expectException(LogicException::class);
		$event->delete();
	}

	/**
	 * La purge supprime les événements plus anciens que la rétention et garde
	 * les autres ; une rétention nulle garde tout.
	 *
	 * @return void
	 */
	public function test_the_retention_purge_removes_only_old_events(): void
	{
		Date::setTestNow('2026-01-01 12:00:00');
		$old = app(AuditLog::class)->record(AuditAction::Logout, $this->user, actor: $this->user);
		Date::setTestNow('2026-03-01 12:00:00');
		$recent = app(AuditLog::class)->record(AuditAction::Logout, $this->user, actor: $this->user);

		config(['suivi.audit.retention_days' => 0]);
		Artisan::call('model:prune', ['--model' => [AuditEvent::class]]);
		$this->assertSame(2, AuditEvent::query()->count());

		config(['suivi.audit.retention_days' => 30]);
		Artisan::call('model:prune', ['--model' => [AuditEvent::class]]);
		$this->assertNull(AuditEvent::query()->find($old->id));
		$this->assertNotNull(AuditEvent::query()->find($recent->id));
		Date::setTestNow();
	}

	/**
	 * La purge est planifiée chaque jour.
	 *
	 * @return void
	 */
	public function test_the_purge_is_scheduled_daily(): void
	{
		$events = collect(app(Schedule::class)->events())
			->filter(fn ($event) => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'AuditEvent'));

		$this->assertCount(1, $events);
		$this->assertSame('0 0 * * *', $events->first()->expression);
	}

	/**
	 * Un administrateur d'instance consulte le journal, du plus récent au plus
	 * ancien, et le filtre par action.
	 *
	 * @return void
	 */
	public function test_an_instance_admin_lists_and_filters_the_log(): void
	{
		$audit = app(AuditLog::class);
		$audit->record(AuditAction::LoginSucceeded, $this->user, actor: $this->user);
		$audit->record(AuditAction::Logout, $this->user, actor: $this->user);
		Sanctum::actingAs($this->admin());

		$this->getJson('/api/v1/audit-events')
			->assertOk()
			->assertJsonCount(2, 'data')
			->assertJsonPath('data.0.action', 'auth.logout')
			->assertJsonPath('data.0.author.id', $this->user->id)
			->assertJsonPath('data.0.subject.type', 'user');

		$this->getJson('/api/v1/audit-events?filter[action]=auth.login')
			->assertOk()
			->assertJsonCount(1, 'data')
			->assertJsonPath('data.0.action', 'auth.login');
	}

	/**
	 * Le journal est fermé aux autres rôles, même chef de projet.
	 *
	 * @return void
	 */
	public function test_the_log_is_reserved_to_instance_admins(): void
	{
		$tenant = Tenant::factory()->create();
		$manager = User::factory()->create();
		$manager->tenants()->attach($tenant->id, ['role' => TenantRole::ProjectManager->value]);
		Sanctum::actingAs($manager);

		$this->getJson('/api/v1/audit-events')->assertForbidden();
	}
}
