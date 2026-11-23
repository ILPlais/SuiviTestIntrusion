<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantRole;
use App\Enums\TestExposure;
use App\Enums\TestStatus;
use App\Enums\TestType;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\PenetrationTest;
use App\Models\Phase;
use App\Models\Tenant;
use App\Models\TestStatusChange;
use App\Models\User;
use App\Policies\PenetrationTestPolicy;
use App\Policies\ScopeObjectPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tests d'intrusion (engagement-2, 11.05, 11.08) : liste, fiche, création,
 * modification, suppression et changement de statut ; droits dans
 * PenetrationTestPolicy.
 *
 * Le filtre global du modèle ne laisse passer que les tests des organisations de
 * l'utilisateur : un test étranger répond 404 comme un identifiant inconnu. Le
 * statut ne se modifie que par la route dédiée, qui trace chaque transition.
 */
class PenetrationTestController extends Controller
{
	/**
	 * Liste paginée des tests accessibles, filtrable par organisation, statut,
	 * type et exposition.
	 *
	 * @param Request $request Requête de liste (filtres, tri, pagination).
	 * @return JsonResponse
	 */
	public function index(Request $request): JsonResponse
	{
		$user = $this->user($request);
		$roles = $user->tenantRoles();

		return ApiListQuery::make(
			$request,
			sortable: ['reference', 'title', 'status', 'planned_start', 'created_at'],
			filterable: ['tenant_id', 'status', 'type', 'exposure'],
			defaultSort: 'created_at',
		)->respond(
			PenetrationTest::query()->with(['tenant', 'creator']),
			fn (PenetrationTest $test) => $this->summary($test, $user, $roles),
		);
	}

	/**
	 * Un test accessible, avec ses phases et l'historique de ses statuts.
	 *
	 * @param Request $request Requête courante.
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function show(Request $request, PenetrationTest $test): JsonResponse
	{
		return $this->detailResponse($request, $test);
	}

	/**
	 * Crée un test « Planifié » avec ses cinq phases (chef de projet de
	 * l'organisation, ou administrateur d'instance).
	 *
	 * @param Request $request Requête portant la fiche du test.
	 * @return JsonResponse
	 */
	public function store(Request $request): JsonResponse
	{
		// Forme de tous les champs d'abord, pour signaler toutes les erreurs ensemble ;
		// l'unicité de la référence attend que l'organisation soit reconnue, sans
		// quoi elle révélerait les références d'une organisation étrangère.
		$request->validate(['tenant_id' => ['required', 'string', 'max:26']] + $this->rules(null, null));

		// Lecture à travers le filtre : une organisation étrangère est introuvable,
		// avec le même message qu'une organisation inconnue (11.06).
		$tenant = Tenant::query()->find($request->string('tenant_id')->toString());
		if (! $tenant instanceof Tenant) {
			throw ValidationException::withMessages(['tenant_id' => [__('validation.custom.tenant_id.unknown')]]);
		}

		Gate::authorize('create', [PenetrationTest::class, $tenant]);

		$attributes = $this->validated($request, $tenant->id, null);
		$attributes['tenant_id'] = $tenant->id;

		$test = $this->guardReference(fn () => PenetrationTest::open($attributes, $this->user($request)));

		return $this->detailResponse($request, $test, 201);
	}

	/**
	 * Met à jour les champs fournis de la fiche ; ni l'organisation ni le statut
	 * ne changent par cette route.
	 *
	 * @param Request $request Requête portant les champs modifiés.
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function update(Request $request, PenetrationTest $test): JsonResponse
	{
		Gate::authorize('update', $test);

		$attributes = $this->validated($request, $test->tenant_id, $test);
		$this->guardReference(fn () => $test->update($attributes));

		return $this->detailResponse($request, $test);
	}

	/**
	 * Exécute une écriture et traduit une violation de l'unicité de la référence
	 * en erreur de validation : deux écritures simultanées de la même référence
	 * passent toutes deux la règle `unique`, et seule la contrainte de la base
	 * départage.
	 *
	 * @template TResult
	 *
	 * @param callable(): TResult $write Écriture à protéger.
	 * @return TResult
	 */
	private function guardReference(callable $write): mixed
	{
		try {
			return $write();
		} catch (UniqueConstraintViolationException) {
			throw ValidationException::withMessages(['reference' => [__('validation.custom.reference.unique')]]);
		}
	}

	/**
	 * Change le statut du test : toutes les transitions sont permises, sauf vers
	 * le statut courant, et chacune entre dans l'historique.
	 *
	 * @param Request $request Requête portant le statut et un commentaire facultatif.
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function changeStatus(Request $request, PenetrationTest $test): JsonResponse
	{
		Gate::authorize('update', $test);

		$valid = $request->validate([
			'status' => ['required', Rule::enum(TestStatus::class)],
			'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
		]);

		$comment = isset($valid['comment']) && trim($valid['comment']) !== '' ? $valid['comment'] : null;
		if (! $test->changeStatus(TestStatus::from($valid['status']), $this->user($request), $comment)) {
			throw ValidationException::withMessages(['status' => [__('validation.custom.status.unchanged')]]);
		}

		return $this->detailResponse($request, $test);
	}

	/**
	 * Supprime définitivement le test, ses phases et son historique
	 * (administrateur d'instance).
	 *
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function destroy(PenetrationTest $test): JsonResponse
	{
		Gate::authorize('delete', $test);

		$test->delete();

		return new JsonResponse(null, 204);
	}

	/**
	 * Valide la fiche, convertit les dates en UTC (13.07) et vérifie leur ordre,
	 * en tenant compte des valeurs déjà enregistrées lors d'une modification.
	 *
	 * @param Request $request Requête portant la fiche.
	 * @param string $tenantId Organisation du test (unicité de la référence).
	 * @param PenetrationTest|null $test Test modifié ; null à la création.
	 * @return array<string, mixed> Champs validés, prêts à enregistrer.
	 */
	private function validated(Request $request, string $tenantId, ?PenetrationTest $test): array
	{
		$valid = $request->validate($this->rules($tenantId, $test), [
			'reference.unique' => __('validation.custom.reference.unique'),
		]);

		// Une date reçue avec un décalage (+02:00) est ramenée en UTC avant
		// l'enregistrement : la base ne garde pas le fuseau.
		// Plage bornée : une année extrême passerait la règle `date` mais serait
		// refusée par certains SGBD (DATETIME de MySQL), soit une erreur 500.
		foreach (['planned_start', 'planned_end'] as $field) {
			if (array_key_exists($field, $valid) && $valid[$field] !== null) {
				$date = Date::parse($valid[$field])->utc();
				if ($date->year < 2000 || $date->year > 2099) {
					throw ValidationException::withMessages([$field => [__('validation.custom.planned.range')]]);
				}
				$valid[$field] = $date;
			}
		}

		$start = array_key_exists('planned_start', $valid) ? $valid['planned_start'] : $test?->planned_start;
		$end = array_key_exists('planned_end', $valid) ? $valid['planned_end'] : $test?->planned_end;
		if ($start instanceof CarbonInterface && $end instanceof CarbonInterface && $end->lt($start)) {
			// L'erreur porte sur le champ envoyé : seul le début peut avoir été modifié.
			$field = array_key_exists('planned_end', $valid) ? 'planned_end' : 'planned_start';
			throw ValidationException::withMessages([$field => [__('validation.custom.planned_end.before_start')]]);
		}

		return $valid;
	}

	/**
	 * Règles de validation de la fiche ; tous les champs sont obligatoires à la
	 * création, facultatifs mais non vides lors d'une modification.
	 *
	 * @param string|null $tenantId Organisation du test, pour l'unicité de la référence ; null pour ne pas la contrôler.
	 * @param PenetrationTest|null $test Test modifié ; null à la création.
	 * @return array<string, list<mixed>>
	 */
	private function rules(?string $tenantId, ?PenetrationTest $test): array
	{
		$presence = $test === null ? 'required' : 'sometimes';
		$reference = [$presence, 'required', 'string', 'max:64'];
		if ($tenantId !== null) {
			$reference[] = Rule::unique('tests', 'reference')->where('tenant_id', $tenantId)->ignore($test?->id);
		}

		return [
			'reference' => $reference,
			'title' => [$presence, 'required', 'string', 'max:255'],
			'type' => [$presence, 'required', Rule::enum(TestType::class)],
			'exposure' => [$presence, 'required', Rule::enum(TestExposure::class)],
			'planned_start' => ['sometimes', 'nullable', 'date'],
			'planned_end' => ['sometimes', 'nullable', 'date'],
		];
	}

	/**
	 * Réponse détaillée d'un test, relu pour refléter phases et historique.
	 *
	 * @param Request $request Requête courante.
	 * @param PenetrationTest $test Test à présenter.
	 * @param int $status Code HTTP de la réponse.
	 * @return JsonResponse
	 */
	private function detailResponse(Request $request, PenetrationTest $test, int $status = 200): JsonResponse
	{
		$test->load(['tenant', 'creator', 'phases', 'statusChanges.author']);
		$user = $this->user($request);

		return new JsonResponse(['data' => $this->detail($test, $user, $user->tenantRoles())], $status);
	}

	/**
	 * Utilisateur authentifié de la requête (routes protégées par Sanctum).
	 *
	 * @param Request $request Requête courante.
	 * @return User
	 */
	private function user(Request $request): User
	{
		/** @var User $user */
		$user = $request->user();

		return $user;
	}

	/**
	 * Représentation d'un test dans une liste.
	 *
	 * @param PenetrationTest $test Test à présenter (organisation et auteur chargés).
	 * @param User $user Utilisateur courant.
	 * @param array<string, TenantRole> $roles Rôles de l'utilisateur par organisation.
	 * @return array<string, mixed>
	 */
	private function summary(PenetrationTest $test, User $user, array $roles): array
	{
		/** @var Tenant $tenant */
		$tenant = $test->tenant;
		/** @var User|null $creator */
		$creator = $test->creator;

		return [
			'id' => $test->id,
			'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
			'reference' => $test->reference,
			'title' => $test->title,
			'type' => $test->type->value,
			'exposure' => $test->exposure->value,
			'status' => $test->status->value,
			'planned_start' => $this->date($test->planned_start),
			'planned_end' => $this->date($test->planned_end),
			// Nom affiché seulement : les comptes ne sont pas énumérables (14.01).
			'created_by' => $creator?->name,
			'can_manage' => PenetrationTestPolicy::manages($user, $roles[$test->tenant_id] ?? null),
			// Le périmètre est tenu par toute l'équipe, testeurs compris (engagement-4).
			'can_manage_scope' => ScopeObjectPolicy::manages($user, $roles[$test->tenant_id] ?? null),
		];
	}

	/**
	 * Représentation complète d'un test : résumé, phases et historique des
	 * statuts, du plus récent au plus ancien.
	 *
	 * @param PenetrationTest $test Test à présenter (relations chargées).
	 * @param User $user Utilisateur courant.
	 * @param array<string, TenantRole> $roles Rôles de l'utilisateur par organisation.
	 * @return array<string, mixed>
	 */
	private function detail(PenetrationTest $test, User $user, array $roles): array
	{
		return $this->summary($test, $user, $roles) + [
			'phases' => $test->phases->map(fn (Phase $phase) => [
				'id' => $phase->id,
				'code' => $phase->code->value,
				'position' => $phase->position,
			])->all(),
			'status_changes' => $test->statusChanges->map(function (TestStatusChange $change) {
				/** @var User|null $author */
				$author = $change->author;

				return [
					'from' => $change->from_status?->value,
					'to' => $change->to_status->value,
					'author' => $author?->name,
					'comment' => $change->comment,
					'changed_at' => $this->date($change->created_at),
				];
			})->all(),
		];
	}

	/**
	 * Date au format ISO 8601 en UTC (`2026-12-01T08:00:00Z`), ou null.
	 *
	 * @param CarbonInterface|null $date Date à formater.
	 * @return string|null
	 */
	private function date(?CarbonInterface $date): ?string
	{
		return $date?->toIso8601ZuluString();
	}
}
