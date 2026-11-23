<?php

namespace Tests\Feature\Console;

use App\Enums\RemediationStatus;
use App\Enums\Severity;
use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\Remediation;
use App\Models\ScopeObject;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vulnerability;
use App\Tenancy\TenantAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Données de démonstration (version testable, plan du 2026-11-21) : une
 * organisation fictive, trois comptes à activer (chef de projet, testeur,
 * client) et un test complet, pour dérouler le parcours de bout en bout.
 */
class DemoDataTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * La commande crée l'organisation, les comptes avec leur lien d'activation et
	 * un test avec périmètre, vulnérabilités cotées et remédiations.
	 *
	 * @return void
	 */
	public function test_the_demo_command_creates_a_complete_test(): void
	{
		$this->artisan('suivi:demo')
			->expectsOutputToContain('Banque Exemple (démo)')
			->expectsOutputToContain('/activation/')
			->assertSuccessful();

		TenantAccess::withoutRestriction(function (): void {
			$tenant = Tenant::query()->where('name', 'Banque Exemple (démo)')->firstOrFail();

			$roles = User::query()->whereHas('tenants', fn ($query) => $query->whereKey($tenant->id))->get()
				->mapWithKeys(fn (User $user) => [$user->email => $user->roleIn($tenant)]);
			$this->assertSame(TenantRole::ProjectManager, $roles['chef.projet@demo.example.test']);
			$this->assertSame(TenantRole::Tester, $roles['testeur@demo.example.test']);
			$this->assertSame(TenantRole::Client, $roles['client@demo.example.test']);
			$this->assertNull(User::query()->where('email', 'testeur@demo.example.test')->value('password'));

			$test = PenetrationTest::query()->where('tenant_id', $tenant->id)->firstOrFail();
			$this->assertSame(5, $test->phases()->count());
			$this->assertGreaterThanOrEqual(5, ScopeObject::query()->where('test_id', $test->id)->count());
			$this->assertTrue(ScopeObject::query()->where('test_id', $test->id)->where('included', false)->exists());

			$vulnerabilities = Vulnerability::query()->where('test_id', $test->id)->get();
			$this->assertGreaterThanOrEqual(4, $vulnerabilities->count());
			$this->assertTrue($vulnerabilities->contains(fn (Vulnerability $vulnerability) => $vulnerability->severity === Severity::Critical));
			$this->assertSame($vulnerabilities->count(), Remediation::query()->whereIn('vulnerability_id', $vulnerabilities->pluck('id'))->count());
			$this->assertTrue(Remediation::query()->where('status', RemediationStatus::Fixed->value)->exists());
		});
	}

	/**
	 * Une seconde exécution refuse de dupliquer les données.
	 *
	 * @return void
	 */
	public function test_the_demo_command_does_not_run_twice(): void
	{
		$this->artisan('suivi:demo')->assertSuccessful();

		$this->artisan('suivi:demo')
			->expectsOutputToContain('existent déjà')
			->assertFailed();
	}
}
