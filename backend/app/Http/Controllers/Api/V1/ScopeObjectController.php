<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ScopeObjectType;
use App\Enums\ServiceProtocol;
use App\Enums\TenantRole;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\PenetrationTest;
use App\Models\ScopeObject;
use App\Models\User;
use App\Policies\ScopeObjectPolicy;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Périmètre réseau d'un test (engagement-4, 11.07) : machines, services et
 * comptes de test ; droits dans ScopeObjectPolicy.
 *
 * Routes imbriquées « shallow » : la liste et la création passent par le test
 * (`/tests/{test}/scope-objects`), la fiche, la modification et la suppression
 * par l'objet (`/scope-objects/{scope_object}`). Le filtre global des modèles
 * rend un test ou un objet d'une organisation étrangère introuvable (404).
 */
class ScopeObjectController extends Controller
{
	/**
	 * Périmètre d'un test, paginé, filtrable par type, inclusion et parent.
	 *
	 * @param Request $request Requête de liste (filtres, tri, pagination).
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function index(Request $request, PenetrationTest $test): JsonResponse
	{
		$user = $this->user($request);
		$roles = $user->tenantRoles();

		// Le filtre d'inclusion est comparé tel quel par ApiListQuery : seules les
		// valeurs booléennes sont admises, sans quoi PostgreSQL répondrait par une
		// erreur 500 et MySQL confondrait n'importe quel texte avec « exclu ».
		$request->validate(['filter.included' => ['sometimes', 'in:0,1']]);

		return ApiListQuery::make(
			$request,
			sortable: ['label', 'type', 'created_at'],
			filterable: ['type', 'included', 'parent_id'],
			defaultSort: 'created_at',
		)->respond(
			ScopeObject::query()->where('test_id', $test->id)->with(['host', 'service', 'account']),
			fn (ScopeObject $object) => $this->present($object, $user, $roles),
		);
	}

	/**
	 * Un objet du périmètre, avec son détail.
	 *
	 * @param Request $request Requête courante.
	 * @param ScopeObject $scopeObject Objet résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function show(Request $request, ScopeObject $scopeObject): JsonResponse
	{
		return $this->detailResponse($request, $scopeObject);
	}

	/**
	 * Déclare un objet dans le périmètre du test (équipe du test).
	 *
	 * @param Request $request Requête portant le type, les champs communs et le détail.
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function store(Request $request, PenetrationTest $test): JsonResponse
	{
		Gate::authorize('create', [ScopeObject::class, $test]);

		// Le type d'abord : il choisit les règles des autres champs.
		$type = ScopeObjectType::from($request->validate([
			'type' => ['required', Rule::enum(ScopeObjectType::class)],
		])['type']);

		$valid = $request->validate($this->rules($type, $test, null));
		$valid['type'] = $type->value;
		$this->ensureFreePort($type, $test, null, $valid);

		return $this->detailResponse($request, ScopeObject::declare($test, $valid), 201);
	}

	/**
	 * Modifie les champs fournis ; le type et le test ne changent pas (un `type`
	 * envoyé est ignoré).
	 *
	 * @param Request $request Requête portant les champs modifiés.
	 * @param ScopeObject $scopeObject Objet résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function update(Request $request, ScopeObject $scopeObject): JsonResponse
	{
		Gate::authorize('update', $scopeObject);

		$scopeObject->load(['test', $scopeObject->type->value]);
		/** @var PenetrationTest $test */
		$test = $scopeObject->test;

		$valid = $request->validate($this->rules($scopeObject->type, $test, $scopeObject));
		$this->ensureFreePort($scopeObject->type, $test, $scopeObject, $valid);
		$scopeObject->amend($valid);

		return $this->detailResponse($request, $scopeObject);
	}

	/**
	 * Supprime l'objet ; une machine emporte ses services, ses comptes restent.
	 *
	 * @param ScopeObject $scopeObject Objet résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function destroy(ScopeObject $scopeObject): JsonResponse
	{
		Gate::authorize('delete', $scopeObject);

		$scopeObject->remove();

		return new JsonResponse(null, 204);
	}

	/**
	 * Règles de validation pour un type ; à la création, les champs obligatoires
	 * du type le sont, lors d'une modification ils deviennent facultatifs mais
	 * non vides. Les champs inconnus (un mot de passe, par exemple) sont ignorés :
	 * seuls les champs validés sont enregistrés.
	 *
	 * @param ScopeObjectType $type Type de l'objet.
	 * @param PenetrationTest $test Test de l'objet (parent et unicité des ports).
	 * @param ScopeObject|null $object Objet modifié ; null à la création.
	 * @return array<string, list<mixed>>
	 */
	private function rules(ScopeObjectType $type, PenetrationTest $test, ?ScopeObject $object): array
	{
		$presence = $object === null ? 'required' : 'sometimes';
		$common = [
			'label' => ['sometimes', 'nullable', 'string', 'max:255'],
			'included' => ['sometimes', 'boolean'],
			'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
			'constraints' => ['sometimes', 'nullable', 'string', 'max:2000'],
		];

		return $common + match ($type) {
			ScopeObjectType::Host => [
				'parent_id' => ['prohibited'],
				'address' => [$presence, 'required', 'string', 'max:64', $this->ipOrCidr()],
				'hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
				'operating_system' => ['sometimes', 'nullable', 'string', 'max:255'],
			],
			ScopeObjectType::Service => [
				'parent_id' => [$presence, 'required', 'string', 'max:26', $this->hostOf($test)],
				'protocol' => [$presence, 'required', Rule::enum(ServiceProtocol::class)],
				'port' => [$presence, 'required', 'integer', 'between:1,65535'],
				'name' => ['sometimes', 'nullable', 'string', 'max:64'],
				'version' => ['sometimes', 'nullable', 'string', 'max:255'],
			],
			ScopeObjectType::Account => [
				'parent_id' => ['sometimes', 'nullable', 'string', 'max:26', $this->hostOf($test)],
				'username' => [$presence, 'required', 'string', 'max:255'],
				'role_description' => ['sometimes', 'nullable', 'string', 'max:255'],
			],
		};
	}

	/**
	 * Règle : adresse IPv4 ou IPv6, ou plage CIDR au préfixe valide pour sa famille.
	 *
	 * @return Closure(string, mixed, Closure): void
	 */
	private function ipOrCidr(): Closure
	{
		return function (string $attribute, mixed $value, Closure $fail): void {
			$parts = is_string($value) ? explode('/', $value, 2) : [''];
			$ip = $parts[0];
			$valid = filter_var($ip, FILTER_VALIDATE_IP) !== false;

			if ($valid && isset($parts[1])) {
				$max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
				$valid = $parts[1] !== '' && strlen($parts[1]) <= 3 && ctype_digit($parts[1]) && (int) $parts[1] <= $max;
			}

			if (! $valid) {
				$fail(__('validation.custom.address.ip_or_cidr'));
			}
		};
	}

	/**
	 * Règle : l'objet parent est une machine du même test. La lecture passe par le
	 * filtre d'organisation : un objet étranger est introuvable, avec le même
	 * message qu'un objet inconnu.
	 *
	 * @param PenetrationTest $test Test de l'objet.
	 * @return Closure(string, mixed, Closure): void
	 */
	private function hostOf(PenetrationTest $test): Closure
	{
		return function (string $attribute, mixed $value, Closure $fail) use ($test): void {
			if ($value === null) {
				return;
			}

			$exists = is_string($value) && ScopeObject::query()
				->whereKey($value)
				->where('test_id', $test->id)
				->where('type', ScopeObjectType::Host->value)
				->exists();

			if (! $exists) {
				$fail(__('validation.custom.parent_id.host'));
			}
		};
	}

	/**
	 * Vérifie, après validation, que le couple protocole et port d'un service
	 * n'est pas déjà déclaré sur sa machine. Le contrôle porte sur les valeurs
	 * résultantes (reçues, complétées par celles du service modifié) : déplacer
	 * un service vers une autre machine ou changer seulement son protocole est
	 * vérifié comme un changement de port.
	 *
	 * Deux écritures simultanées du même couple pourraient encore passer : la
	 * table de détail ne connaît pas la machine (14.01), aucun index unique ne
	 * peut donc départager ; risque accepté en V1.
	 *
	 * @param ScopeObjectType $type Type de l'objet.
	 * @param PenetrationTest $test Test de l'objet.
	 * @param ScopeObject|null $object Service modifié ; null à la création.
	 * @param array<string, mixed> $valid Champs validés de la requête.
	 * @return void
	 *
	 * @throws ValidationException Si le couple est déjà pris.
	 */
	private function ensureFreePort(ScopeObjectType $type, PenetrationTest $test, ?ScopeObject $object, array $valid): void
	{
		$fields = ['parent_id', 'protocol', 'port'];
		if ($type !== ScopeObjectType::Service || ($object !== null && array_intersect_key($valid, array_flip($fields)) === [])) {
			return;
		}

		$current = $object?->detail();
		$parent = $valid['parent_id'] ?? $object?->parent_id;
		$protocol = $valid['protocol'] ?? $current?->getAttribute('protocol')?->value;
		$port = (int) ($valid['port'] ?? $current?->getAttribute('port'));

		$taken = ScopeObject::query()
			->where('test_id', $test->id)
			->where('parent_id', $parent)
			->where('type', ScopeObjectType::Service->value)
			->when($object !== null, fn ($query) => $query->whereKeyNot($object?->id))
			->whereHas('service', fn ($query) => $query->where('protocol', $protocol)->where('port', $port))
			->exists();

		if ($taken) {
			throw ValidationException::withMessages(['port' => [__('validation.custom.port.taken')]]);
		}
	}

	/**
	 * Réponse détaillée d'un objet, relu avec son détail.
	 *
	 * @param Request $request Requête courante.
	 * @param ScopeObject $object Objet à présenter.
	 * @param int $status Code HTTP de la réponse.
	 * @return JsonResponse
	 */
	private function detailResponse(Request $request, ScopeObject $object, int $status = 200): JsonResponse
	{
		$object->load($object->type->value);
		$user = $this->user($request);

		return new JsonResponse(['data' => $this->present($object, $user, $user->tenantRoles())], $status);
	}

	/**
	 * Représentation d'un objet : champs communs, détail sous la clé de son type
	 * (`host`, `service` ou `account`) et droit de modification.
	 *
	 * @param ScopeObject $object Objet à présenter (détail chargé).
	 * @param User $user Utilisateur courant.
	 * @param array<string, TenantRole> $roles Rôles de l'utilisateur par organisation.
	 * @return array<string, mixed>
	 */
	private function present(ScopeObject $object, User $user, array $roles): array
	{
		$detail = $object->detail();
		$fields = [];
		foreach (ScopeObject::DETAIL_FIELDS[$object->type->value] as $field) {
			$value = $detail->getAttribute($field);
			$fields[$field] = $value instanceof BackedEnum ? $value->value : $value;
		}

		return [
			'id' => $object->id,
			'test_id' => $object->test_id,
			'type' => $object->type->value,
			'parent_id' => $object->parent_id,
			'label' => $object->label,
			'included' => $object->included,
			'comment' => $object->comment,
			'constraints' => $object->constraints,
			$object->type->value => $fields,
			'created_at' => $this->date($object->created_at),
			'updated_at' => $this->date($object->updated_at),
			'can_manage' => ScopeObjectPolicy::manages($user, $roles[$object->tenant_id] ?? null),
		];
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
	 * Date au format ISO 8601 en UTC, ou null.
	 *
	 * @param CarbonInterface|null $date Date à formater.
	 * @return string|null
	 */
	private function date(?CarbonInterface $date): ?string
	{
		return $date?->toIso8601ZuluString();
	}
}
