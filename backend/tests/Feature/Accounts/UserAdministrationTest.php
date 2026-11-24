<?php

namespace Tests\Feature\Accounts;

use App\Accounts\AccountLifecycle;
use App\Enums\AuditAction;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsVaults;
use Tests\TestCase;

/**
 * Administration des comptes (identite-7, 11.06) : désactivation réversible,
 * suppression pseudonymisée d'un compte désactivé, dernier administrateur
 * protégé, déverrouillage anticipé, lien de réinitialisation.
 */
class UserAdministrationTest extends TestCase
{
	use BuildsVaults;
	use RefreshDatabase;

	/**
	 * Prépare les requêtes de la SPA.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);
	}

	/**
	 * Administrateur d'instance actif.
	 *
	 * @return User
	 */
	private function admin(): User
	{
		return User::factory()->create(['is_instance_admin' => true]);
	}

	/**
	 * Compte actif avec un coffre de clés.
	 *
	 * @param array<string, mixed> $attributes Attributs imposés.
	 * @return User
	 */
	private function account(array $attributes = []): User
	{
		$vault = $this->vaultPayload();

		return User::factory()->create($attributes + [
			'public_key' => $vault['public_key'],
			'key_fingerprint' => $vault['key_fingerprint'],
			'private_key_password_copy' => $vault['password_copy'],
			'private_key_recovery_copy' => $vault['recovery_copy'],
			'key_version' => 1,
		]);
	}

	/**
	 * La liste des comptes est réservée aux administrateurs et donne l'état de chacun.
	 *
	 * @return void
	 */
	public function test_list_shows_states_and_is_admin_only(): void
	{
		$admin = $this->admin();
		User::factory()->create(['locked_until' => now()->addMinutes(10)]);
		User::factory()->create(['deactivated_at' => now()]);
		User::factory()->create(['password' => null]);
		User::factory()->create(['pseudonymized_at' => now()]);

		Sanctum::actingAs($admin);
		$statuses = array_column((array) $this->getJson('/api/v1/users')->assertOk()->json('data'), 'status');
		sort($statuses);
		$this->assertSame(['active', 'deactivated', 'invited', 'locked'], $statuses);

		Sanctum::actingAs(User::factory()->create());
		$this->getJson('/api/v1/users')->assertForbidden();
	}

	/**
	 * Désactivation : connexion refusée, jetons et sessions révoqués, invitations
	 * émises annulées ; réactivation : connexion rétablie.
	 *
	 * @return void
	 */
	public function test_deactivation_revokes_access_and_reactivation_restores_it(): void
	{
		$auth = base64_encode(random_bytes(32));
		$user = $this->account(['email' => 'jo@example.org', 'password' => $auth]);
		$tenant = Tenant::factory()->create();
		$user->tenants()->attach($tenant->id, ['role' => TenantRole::ProjectManager->value]);
		$user->createToken('cli');
		// Pilote de production (13.06) : les sessions du compte sont en base.
		config(['session.driver' => 'database']);
		DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
		Sanctum::actingAs($user);
		$this->postJson('/api/v1/invitations', ['email' => 'c@example.org', 'tenant_id' => $tenant->id, 'role' => 'client'])->assertCreated();

		Sanctum::actingAs($this->admin());
		$this->postJson("/api/v1/users/{$user->id}/deactivate")->assertOk()->assertJsonPath('data.status', 'deactivated');

		$this->assertSame(0, $user->tokens()->count());
		$this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
		$this->assertNotNull(Invitation::query()->sole()->cancelled_at);
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::AccountDeactivated)->count());

		// Une session encore ouverte est fermée à sa requête suivante.
		Sanctum::actingAs($user->refresh());
		$this->getJson('/api/v1/user')->assertUnauthorized();

		$this->app['auth']->forgetGuards();
		$this->postJson('/api/v1/auth/login', ['email' => 'jo@example.org', 'auth' => $auth])
			->assertUnprocessable()
			->assertJsonPath('details.email.0', __('auth.account_disabled'));

		Sanctum::actingAs($this->admin());
		$this->postJson("/api/v1/users/{$user->id}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
		$this->app['auth']->forgetGuards();
		$this->postJson('/api/v1/auth/login', ['email' => 'jo@example.org', 'auth' => $auth])->assertOk();
	}

	/**
	 * Le dernier administrateur actif ne peut pas être désactivé : comme un
	 * administrateur ne se désactive pas lui-même, il en reste toujours un actif.
	 *
	 * @return void
	 */
	public function test_last_active_admin_is_protected(): void
	{
		$admin = $this->admin();
		$other = $this->admin();
		Sanctum::actingAs($admin);

		$this->postJson("/api/v1/users/{$admin->id}/deactivate")
			->assertUnprocessable()
			->assertJsonPath('details.user.0', __('accounts.self'));
		$this->postJson("/api/v1/users/{$other->id}/deactivate")->assertOk();
		$this->deleteJson("/api/v1/users/{$admin->id}")->assertUnprocessable();
		$this->assertSame(1, app(AccountLifecycle::class)->activeAdminCount());
	}

	/**
	 * Suppression : seulement après désactivation ; données d'authentification et
	 * clés effacées, rattachements retirés, compte pseudonymisé, adresse libérée,
	 * empreinte conservée dans le journal.
	 *
	 * @return void
	 */
	public function test_deletion_pseudonymizes_a_deactivated_account(): void
	{
		$user = $this->account(['email' => 'jo@example.org', 'two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()]);
		$fingerprint = $user->key_fingerprint;
		$tenant = Tenant::factory()->create();
		$user->tenants()->attach($tenant->id, ['role' => TenantRole::Tester->value]);
		Sanctum::actingAs($this->admin());

		$this->deleteJson("/api/v1/users/{$user->id}")->assertUnprocessable();
		$this->postJson("/api/v1/users/{$user->id}/deactivate")->assertOk();
		$this->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();

		$user->refresh();
		$this->assertSame("Utilisateur supprimé n° {$user->id}", $user->name);
		$this->assertNotSame('jo@example.org', $user->email);
		$this->assertNull($user->getAttributes()['password']);
		$this->assertNull($user->two_factor_secret);
		$this->assertNull($user->public_key);
		$this->assertNull($user->key_fingerprint);
		$this->assertNull($user->private_key_password_copy);
		$this->assertNull($user->private_key_recovery_copy);
		$this->assertSame([], $user->tenantRoles());
		$this->assertNotNull($user->pseudonymized_at);
		$event = AuditEvent::query()->where('action', AuditAction::AccountDeleted)->sole();
		$this->assertSame($fingerprint, $event->before['key_fingerprint'] ?? null);

		// Le compte supprimé disparaît de la liste ; l'adresse peut être invitée à nouveau.
		$this->getJson('/api/v1/users')->assertJsonMissing(['id' => $user->id]);
		$this->postJson('/api/v1/invitations', ['email' => 'jo@example.org', 'tenant_id' => $tenant->id, 'role' => 'tester'])
			->assertCreated()
			->assertJsonPath('data.status', 'pending');
		$this->deleteJson("/api/v1/users/{$user->id}")->assertNotFound();
	}

	/**
	 * Un administrateur déverrouille un compte avant l'échéance (13.03).
	 *
	 * @return void
	 */
	public function test_admin_unlocks_an_account(): void
	{
		$user = User::factory()->create(['locked_until' => now()->addMinutes(15), 'failed_login_count' => 10]);
		Sanctum::actingAs($this->admin());

		$this->postJson("/api/v1/users/{$user->id}/unlock")->assertOk()->assertJsonPath('data.status', 'active');

		$this->assertSame(0, $user->refresh()->failed_login_count);
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::AccountUnlocked)->count());
	}

	/**
	 * Le lien de réinitialisation : 24 heures, condensat seul stocké, compte actif avec coffre.
	 *
	 * @return void
	 */
	public function test_admin_issues_a_password_reset_link(): void
	{
		$user = $this->account();
		Sanctum::actingAs($this->admin());

		$link = (string) $this->postJson("/api/v1/users/{$user->id}/password-reset")->assertOk()->json('link');

		$this->assertMatchesRegularExpression('#/reinitialisation/[A-Za-z0-9]{64}$#', $link);
		$user->refresh();
		$this->assertSame(hash('sha256', substr($link, -64)), $user->reset_token_hash);
		$this->assertEqualsWithDelta(now()->addHours(24)->getTimestamp(), $user->reset_expires_at?->getTimestamp(), 5);

		// Pas de lien pour un compte sans coffre ni pour un compte désactivé.
		$this->postJson('/api/v1/users/'.User::factory()->create()->id.'/password-reset')->assertUnprocessable();
		$this->postJson('/api/v1/users/'.$this->account(['deactivated_at' => now()])->id.'/password-reset')->assertUnprocessable();
	}
}
