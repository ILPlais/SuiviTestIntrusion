<?php

namespace Tests\Feature\Accounts;

use App\Enums\AuditAction;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsVaults;
use Tests\TestCase;

/**
 * Invitations (identite-7, 11.06) : qui invite qui, lien à usage unique de
 * 72 heures dont seul le condensat est stocké, renvoi, annulation, compte
 * existant rattaché sans nouvel enrôlement, compte désactivé refusé.
 */
class InvitationTest extends TestCase
{
	use BuildsVaults;
	use RefreshDatabase;

	/**
	 * Organisation où se font les invitations.
	 *
	 * @var Tenant
	 */
	private Tenant $tenant;

	/**
	 * Prépare une organisation et les requêtes de la SPA.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);
		$this->tenant = Tenant::factory()->create(['name' => 'Alpha']);
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
	 * Compte rattaché à l'organisation avec un rôle.
	 *
	 * @param TenantRole $role Rôle dans l'organisation.
	 * @return User
	 */
	private function member(TenantRole $role): User
	{
		$user = User::factory()->create();
		$user->tenants()->attach($this->tenant->id, ['role' => $role->value]);

		return $user;
	}

	/**
	 * Jeton extrait d'un lien d'activation.
	 *
	 * @param string $link Lien renvoyé par l'API.
	 * @return string
	 */
	private function token(string $link): string
	{
		preg_match('#/activation/([A-Za-z0-9]{64})$#', $link, $matches);

		return $matches[1] ?? '';
	}

	/**
	 * Un administrateur invite un testeur : compte en attente, lien de 72 heures,
	 * seul le condensat du jeton est stocké, l'invitation est auditée.
	 *
	 * @return void
	 */
	public function test_admin_invites_a_new_account(): void
	{
		Sanctum::actingAs($this->admin());

		$response = $this->postJson('/api/v1/invitations', ['email' => ' Lou.Petit@Example.org ', 'tenant_id' => $this->tenant->id, 'role' => 'tester'])
			->assertCreated()
			->assertJsonPath('data.email', 'lou.petit@example.org')
			->assertJsonPath('data.role', 'tester')
			->assertJsonPath('data.status', 'pending');

		$token = $this->token((string) $response->json('link'));
		$user = User::query()->where('email', 'lou.petit@example.org')->sole();
		$this->assertNull($user->getAttributes()['password']);
		$this->assertSame(hash('sha256', $token), $user->activation_token_hash);
		$this->assertEqualsWithDelta(now()->addHours(72)->getTimestamp(), $user->activation_expires_at?->getTimestamp(), 5);
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::AccountInvited)->count());
		// Rien n'est rattaché avant l'activation.
		$this->assertNull($user->roleIn($this->tenant));
	}

	/**
	 * L'activation par le lien applique le rattachement et le rôle de l'invitation.
	 *
	 * @return void
	 */
	public function test_activation_applies_the_invitation(): void
	{
		Sanctum::actingAs($this->admin());
		$link = (string) $this->postJson('/api/v1/invitations', ['email' => 'lou@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'tester'])->json('link');
		$this->app['auth']->forgetGuards();

		$this->postJson('/api/v1/auth/activate', ['token' => $this->token($link), 'auth' => base64_encode(random_bytes(32))] + $this->vaultPayload())->assertNoContent();

		$user = User::query()->where('email', 'lou@example.org')->sole();
		$this->assertSame(TenantRole::Tester, $user->roleIn($this->tenant));
		$this->assertNotNull(Invitation::query()->sole()->accepted_at);
	}

	/**
	 * Un renvoi émet un nouveau lien et invalide le précédent.
	 *
	 * @return void
	 */
	public function test_resend_invalidates_the_previous_link(): void
	{
		Sanctum::actingAs($this->admin());
		$first = (string) $this->postJson('/api/v1/invitations', ['email' => 'lou@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->json('link');
		$invitation = Invitation::query()->sole();

		$second = (string) $this->postJson("/api/v1/invitations/{$invitation->id}/resend")->assertOk()->json('link');

		$this->assertNotSame($first, $second);
		$this->getJson('/api/v1/auth/activation/'.$this->token($first))->assertNotFound();
		$this->getJson('/api/v1/auth/activation/'.$this->token($second))->assertOk()->assertJsonPath('email', 'lou@example.org');
	}

	/**
	 * L'annulation invalide le lien d'un compte qui n'a pas d'autre invitation.
	 *
	 * @return void
	 */
	public function test_cancel_invalidates_the_link(): void
	{
		Sanctum::actingAs($this->admin());
		$link = (string) $this->postJson('/api/v1/invitations', ['email' => 'lou@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->json('link');
		$invitation = Invitation::query()->sole();

		$this->deleteJson("/api/v1/invitations/{$invitation->id}")->assertNoContent();

		$this->getJson('/api/v1/auth/activation/'.$this->token($link))->assertNotFound();
		$this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data');
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::InvitationCancelled)->count());
		// Une invitation close ne se renvoie plus.
		$this->postJson("/api/v1/invitations/{$invitation->id}/resend")->assertUnprocessable();
	}

	/**
	 * Un compte actif est rattaché aussitôt, sans lien ni nouvel enrôlement.
	 *
	 * @return void
	 */
	public function test_existing_account_is_attached_without_link(): void
	{
		$existing = User::factory()->create(['email' => 'alex@example.org']);
		Sanctum::actingAs($this->admin());

		$this->postJson('/api/v1/invitations', ['email' => 'alex@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'tester'])
			->assertCreated()
			->assertJsonPath('link', null)
			->assertJsonPath('data.status', 'accepted');

		$this->assertSame(TenantRole::Tester, $existing->roleIn($this->tenant));
		$this->assertNotNull($existing->refresh()->getAttributes()['password']);
		// Une seconde invitation dans la même organisation est refusée.
		$this->postJson('/api/v1/invitations', ['email' => 'alex@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['email'], 'details');
	}

	/**
	 * Un compte désactivé ne peut pas être invité.
	 *
	 * @return void
	 */
	public function test_deactivated_account_cannot_be_invited(): void
	{
		User::factory()->create(['email' => 'jo@example.org', 'deactivated_at' => now()]);
		Sanctum::actingAs($this->admin());

		$this->postJson('/api/v1/invitations', ['email' => 'jo@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'tester'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['email'], 'details');
	}

	/**
	 * Un chef de projet invite des clients finaux de son organisation, rien d'autre ;
	 * il ne voit et ne gère que ses invitations.
	 *
	 * @return void
	 */
	public function test_project_manager_invites_only_clients_of_own_tenants(): void
	{
		$manager = $this->member(TenantRole::ProjectManager);
		$other = Tenant::factory()->create();
		Sanctum::actingAs($manager);

		$this->postJson('/api/v1/invitations', ['email' => 'c@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->assertCreated();
		$this->postJson('/api/v1/invitations', ['email' => 't@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'tester'])->assertUnprocessable();
		$this->postJson('/api/v1/invitations', ['email' => 'c2@example.org', 'tenant_id' => $other->id, 'role' => 'client'])->assertUnprocessable();
		$this->postJson('/api/v1/invitations', ['email' => 'a@example.org', 'is_instance_admin' => true])->assertUnprocessable();
		$this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data');

		// L'invitation d'un administrateur lui est introuvable.
		Sanctum::actingAs($this->admin());
		$this->postJson('/api/v1/invitations', ['email' => 'x@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client']);
		$foreign = Invitation::query()->where('invited_by', '!=', $manager->id)->sole();
		Sanctum::actingAs($manager);
		$this->deleteJson("/api/v1/invitations/{$foreign->id}")->assertNotFound();
	}

	/**
	 * Un chef de projet ne peut pas obtenir le lien d'un compte qu'un autre a
	 * invité : il recevrait, en l'activant, le rôle promis par l'administrateur.
	 *
	 * @return void
	 */
	public function test_project_manager_cannot_take_over_a_foreign_pending_invitation(): void
	{
		$manager = $this->member(TenantRole::ProjectManager);
		Sanctum::actingAs($manager);
		$this->postJson('/api/v1/invitations', ['email' => 'x@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->assertCreated();
		$own = Invitation::query()->sole();

		Sanctum::actingAs($this->admin());
		$adminLink = (string) $this->postJson('/api/v1/invitations', ['email' => 'x@example.org', 'is_instance_admin' => true])->assertCreated()->json('link');

		Sanctum::actingAs($manager);
		$this->postJson("/api/v1/invitations/{$own->id}/resend")->assertUnprocessable();
		$other = Tenant::factory()->create();
		$manager->tenants()->attach($other->id, ['role' => TenantRole::ProjectManager->value]);
		$this->postJson('/api/v1/invitations', ['email' => 'x@example.org', 'tenant_id' => $other->id, 'role' => 'client'])->assertUnprocessable();

		// Le lien de l'administrateur vaut toujours.
		$this->getJson('/api/v1/auth/activation/'.$this->token($adminLink))->assertOk();
	}

	/**
	 * Testeurs et clients finaux n'invitent pas ; il n'y a aucune inscription libre.
	 *
	 * @return void
	 */
	public function test_testers_and_clients_cannot_invite(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$this->postJson('/api/v1/invitations', ['email' => 'c@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->assertForbidden();
		$this->getJson('/api/v1/invitations')->assertForbidden();

		Sanctum::actingAs($this->member(TenantRole::Client));
		$this->postJson('/api/v1/invitations', ['email' => 'c@example.org', 'tenant_id' => $this->tenant->id, 'role' => 'client'])->assertForbidden();
	}

	/**
	 * Un administrateur peut inviter un autre administrateur d'instance, sans organisation.
	 *
	 * @return void
	 */
	public function test_admin_invites_an_instance_admin(): void
	{
		Sanctum::actingAs($this->admin());
		$link = (string) $this->postJson('/api/v1/invitations', ['email' => 'admin2@example.org', 'is_instance_admin' => true])->assertCreated()->json('link');
		$this->app['auth']->forgetGuards();

		$this->postJson('/api/v1/auth/activate', ['token' => $this->token($link), 'auth' => base64_encode(random_bytes(32))] + $this->vaultPayload())->assertNoContent();

		$this->assertTrue(User::query()->where('email', 'admin2@example.org')->sole()->is_instance_admin);
	}
}
