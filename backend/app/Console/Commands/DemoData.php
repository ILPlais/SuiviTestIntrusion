<?php

namespace App\Console\Commands;

use App\Enums\PhaseCode;
use App\Enums\RemediationStatus;
use App\Enums\TenantRole;
use App\Enums\TestStatus;
use App\Models\PenetrationTest;
use App\Models\Remediation;
use App\Models\ScopeObject;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vulnerability;
use App\Tenancy\TenantAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée des données de démonstration (version testable, plan du 2026-11-21) :
 * l'organisation fictive « Banque Exemple (démo) », trois comptes à activer
 * (chef de projet, testeur, client) et un test en cours avec son périmètre, ses
 * vulnérabilités cotées et leurs remédiations.
 *
 * Usage : php artisan suivi:demo
 *
 * Les données sont fictives : domaines `.example.test`, adresses de
 * documentation (RFC 5737). Les comptes n'ont pas de mot de passe : chacun reçoit
 * un lien d'activation, comme ceux de suivi:create-admin, car le mot de passe ne
 * doit jamais transiter par le serveur (13.03). La commande refuse de s'exécuter
 * une seconde fois.
 */
class DemoData extends Command
{
	/** Nom de l'organisation de démonstration, qui sert aussi de garde-fou. */
	private const TENANT_NAME = 'Banque Exemple (démo)';

	/**
	 * Signature de la commande.
	 *
	 * @var string
	 */
	protected $signature = 'suivi:demo';

	/**
	 * Description affichée par « php artisan list ».
	 *
	 * @var string
	 */
	protected $description = 'Crée une organisation, trois comptes et un test de démonstration';

	/**
	 * Crée les données et affiche les liens d'activation.
	 *
	 * @return int
	 */
	public function handle(): int
	{
		return TenantAccess::withoutRestriction(function (): int {
			if (Tenant::query()->where('name', self::TENANT_NAME)->exists()) {
				$this->error('Les données de démonstration existent déjà (organisation « '.self::TENANT_NAME.' »).');

				return self::FAILURE;
			}

			/** @var array<string, string> $links */
			$links = DB::transaction(fn (): array => $this->create());

			$hours = (int) config('suivi.authentication.activation_hours');
			$this->info('Organisation créée : '.self::TENANT_NAME.', avec le test PT-DEMO-001.');
			$this->line("Liens d'activation, valables {$hours} heures et utilisables une seule fois :");
			foreach ($links as $account => $link) {
				$this->line("  {$account} : {$link}");
			}

			return self::SUCCESS;
		});
	}

	/**
	 * Crée l'organisation, les comptes et le test.
	 *
	 * @return array<string, string> Lien d'activation par compte (« Nom <adresse> »).
	 */
	private function create(): array
	{
		$tenant = new Tenant();
		$tenant->forceFill([
			'name' => self::TENANT_NAME,
			'legal_name' => 'Banque Exemple SA (organisation fictive)',
			'presentation' => 'Organisation **fictive** créée par `php artisan suivi:demo` pour essayer l\'application.',
		])->save();

		$links = [];
		$manager = $this->account($tenant, 'chef.projet@demo.example.test', 'Camille Martin', TenantRole::ProjectManager, $links);
		$tester = $this->account($tenant, 'testeur@demo.example.test', 'Alex Durand', TenantRole::Tester, $links);
		$client = $this->account($tenant, 'client@demo.example.test', 'Dominique Bernard', TenantRole::Client, $links);

		$test = PenetrationTest::open([
			'tenant_id' => $tenant->id,
			'reference' => 'PT-DEMO-001',
			'title' => 'Test externe du site vitrine',
			'type' => 'grey_box',
			'exposure' => 'external',
			'planned_start' => now()->startOfDay()->addHours(9),
			'planned_end' => now()->startOfDay()->addDays(4)->addHours(18),
		], $manager);
		$test->changeStatus(TestStatus::InProgress, $manager, 'Accès VPN fourni par le client.');

		$scope = $this->scope($test);
		$this->vulnerabilities($test, $scope, $tester, $client);

		return $links;
	}

	/**
	 * Crée un compte à activer et le rattache à l'organisation avec un rôle ; un
	 * compte existant est seulement rattaché.
	 *
	 * @param Tenant $tenant Organisation de démonstration.
	 * @param string $email Adresse du compte.
	 * @param string $name Nom affiché.
	 * @param TenantRole $role Rôle dans l'organisation.
	 * @param array<string, string> $links Liens d'activation, complétés au passage.
	 * @return User
	 */
	private function account(Tenant $tenant, string $email, string $name, TenantRole $role, array &$links): User
	{
		$user = User::query()->where('email', $email)->first();
		if ($user === null) {
			$token = Str::random(64);
			$user = new User();
			$user->forceFill([
				'name' => $name,
				'email' => $email,
				'password' => null,
				'is_instance_admin' => false,
				'activation_token_hash' => hash('sha256', $token),
				'activation_expires_at' => now()->addHours((int) config('suivi.authentication.activation_hours')),
			])->save();
			$links["{$name} <{$email}>"] = rtrim((string) config('app.url'), '/')."/activation/{$token}";
		}

		$user->tenants()->syncWithoutDetaching([$tenant->id => ['role' => $role->value]]);

		return $user;
	}

	/**
	 * Déclare le périmètre réseau : trois machines incluses, une exclue, leurs
	 * services et deux comptes de test (sans secret).
	 *
	 * @param PenetrationTest $test Test de démonstration.
	 * @return array<string, string> Identifiant des objets, par nom court.
	 */
	private function scope(PenetrationTest $test): array
	{
		$web = ScopeObject::declare($test, ['type' => 'host', 'address' => '203.0.113.10', 'hostname' => 'srv-web01', 'operating_system' => 'Debian 12', 'comment' => 'Site vitrine (CMS)']);
		$api = ScopeObject::declare($test, ['type' => 'host', 'address' => '203.0.113.11', 'hostname' => 'srv-api01', 'constraints' => 'Pas de test de 12 h à 14 h (pic d\'activité)']);
		$mail = ScopeObject::declare($test, ['type' => 'host', 'address' => '203.0.113.20', 'hostname' => 'srv-mail01', 'constraints' => 'Fragile : aucun déni de service']);
		ScopeObject::declare($test, ['type' => 'host', 'address' => '203.0.113.30', 'hostname' => 'srv-paiement', 'included' => false, 'comment' => 'Prestataire de paiement tiers : hors contrat']);

		ScopeObject::declare($test, ['type' => 'service', 'parent_id' => $web->id, 'protocol' => 'tcp', 'port' => 22, 'name' => 'ssh', 'version' => 'OpenSSH 9.2']);
		$https = ScopeObject::declare($test, ['type' => 'service', 'parent_id' => $web->id, 'protocol' => 'tcp', 'port' => 443, 'name' => 'https', 'version' => 'nginx 1.26']);
		$apiHttps = ScopeObject::declare($test, ['type' => 'service', 'parent_id' => $api->id, 'protocol' => 'tcp', 'port' => 443, 'name' => 'https']);
		ScopeObject::declare($test, ['type' => 'service', 'parent_id' => $mail->id, 'protocol' => 'tcp', 'port' => 587, 'name' => 'submission']);

		ScopeObject::declare($test, ['type' => 'account', 'parent_id' => $api->id, 'username' => 'client.test1', 'role_description' => 'Client particulier']);
		ScopeObject::declare($test, ['type' => 'account', 'parent_id' => $web->id, 'username' => 'conseiller.test', 'role_description' => 'Conseiller']);

		return ['web' => $web->id, 'https' => $https->id, 'api' => $api->id, 'apiHttps' => $apiHttps->id, 'mail' => $mail->id];
	}

	/**
	 * Documente quatre vulnérabilités cotées, de critique à mineure, et fait
	 * avancer leurs remédiations.
	 *
	 * @param PenetrationTest $test Test de démonstration.
	 * @param array<string, string> $scope Identifiant des objets du périmètre.
	 * @param User $tester Testeur auteur des constats.
	 * @param User $client Client qui déclare une correction.
	 * @return void
	 */
	private function vulnerabilities(PenetrationTest $test, array $scope, User $tester, User $client): void
	{
		$phase = fn (PhaseCode $code): string => (string) $test->phases()->where('code', $code->value)->value('id');

		$rce = Vulnerability::record(
			$test,
			[
			'title' => 'Exécution de code à distance sur le CMS',
			'description' => "Le module d'aperçu du CMS (version 4.2.0) désérialise des données fournies par l'utilisateur.\n\nUne charge utile a permis d'exécuter `id` et `hostname` sous le compte `www-data`.",
			'phase_id' => $phase(PhaseCode::Exploitation),
			'cve_ids' => ['CVE-2026-0042'],
			'cwe_ids' => ['CWE-502'],
		] + Vulnerability::impactColumns(['operational' => 3, 'legal' => 2, 'financial' => 1, 'reputation' => 3]),
			'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
			[$scope['web'], $scope['https']],
			$tester
		);
		$this->remediate($rce, 'Mettre à jour le CMS en **4.2.3** et désactiver le module d\'aperçu public.', ['https://cms.example.test/securite/avis-2026-01'], null, $tester);

		$rateLimit = Vulnerability::record(
			$test,
			[
			'title' => 'Absence de limite de débit sur l\'authentification de l\'API',
			'description' => 'Aucune limite ne freine les tentatives de connexion sur `POST /api/v2/auth`.',
			'phase_id' => $phase(PhaseCode::Enumeration),
			'cwe_ids' => ['CWE-307'],
		] + Vulnerability::impactColumns(['operational' => 1, 'reputation' => 2]),
			'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N',
			[$scope['api'], $scope['apiHttps']],
			$tester
		);
		$this->remediate($rateLimit, 'Limiter les tentatives par compte et par adresse, avec un délai croissant.', [], RemediationStatus::Fixed, $client);

		Vulnerability::record(
			$test,
			[
			'title' => 'XSS stockée dans le formulaire de contact',
			'description' => 'Le champ « message » est restitué sans échappement dans l\'interface d\'administration.',
			'phase_id' => $phase(PhaseCode::Exploitation),
			'cwe_ids' => ['CWE-79'],
		] + Vulnerability::impactColumns(['reputation' => 2]),
			'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N',
			[$scope['web']],
			$tester
		);

		Vulnerability::record($test, [
			'title' => 'En-têtes de sécurité HTTP absents',
			'description' => 'Ni `Content-Security-Policy` ni `Strict-Transport-Security` ne sont envoyés.',
			'phase_id' => $phase(PhaseCode::Reconnaissance),
			'cwe_ids' => ['CWE-693'],
		], 'CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N', [$scope['web']], $tester);
	}

	/**
	 * Complète la fiche de remédiation d'une vulnérabilité.
	 *
	 * @param Vulnerability $vulnerability Vulnérabilité enregistrée.
	 * @param string $recommendations Recommandations, en Markdown.
	 * @param list<string> $resources Liens.
	 * @param RemediationStatus|null $status Nouveau statut, ou null pour le laisser « à faire ».
	 * @param User $author Auteur du changement de statut.
	 * @return void
	 */
	private function remediate(Vulnerability $vulnerability, string $recommendations, array $resources, ?RemediationStatus $status, User $author): void
	{
		/** @var Remediation $remediation */
		$remediation = $vulnerability->remediation()->firstOrFail();
		$remediation->fill(['recommendations' => $recommendations, 'resources' => $resources]);
		if ($status !== null) {
			$remediation->changeStatus($status, null, $author);
		}
		$remediation->save();
	}
}
