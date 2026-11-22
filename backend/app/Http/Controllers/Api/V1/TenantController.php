<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantRole;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Organisations accessibles à l'utilisateur (identite-5, 11.06) et leur fiche
 * « Client » de 11.05 (engagement-1) ; droits d'écriture dans TenantPolicy.
 *
 * Le filtre global du modèle Tenant ne laisse passer que les organisations de
 * l'utilisateur (toutes pour un administrateur d'instance) : une organisation
 * étrangère est introuvable, et la liaison de route répond 404 comme pour un
 * identifiant inconnu. Aucune énumération des autres clients n'est possible.
 */
class TenantController extends Controller
{
	/**
	 * Liste paginée des organisations accessibles, avec le rôle de l'utilisateur.
	 *
	 * @param Request $request Requête de liste (tri, pagination).
	 * @return JsonResponse
	 */
	public function index(Request $request): JsonResponse
	{
		$roles = $this->roles($request);

		return ApiListQuery::make($request, sortable: ['name', 'created_at'], filterable: [], defaultSort: 'created_at')
			->respond(Tenant::query(), fn (Tenant $tenant) => $this->present($tenant, $roles));
	}

	/**
	 * Une organisation accessible, avec le rôle de l'utilisateur.
	 *
	 * @param Request $request Requête courante.
	 * @param Tenant $tenant Organisation résolue à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function show(Request $request, Tenant $tenant): JsonResponse
	{
		return new JsonResponse(['data' => $this->present($tenant, $this->roles($request))]);
	}

	/**
	 * Crée une organisation (administrateur d'instance).
	 *
	 * @param Request $request Requête portant la fiche de l'organisation.
	 * @return JsonResponse
	 */
	public function store(Request $request): JsonResponse
	{
		Gate::authorize('create', Tenant::class);

		$tenant = Tenant::create($request->validate($this->rules(creating: true)));

		return new JsonResponse(['data' => $this->present($tenant, $this->roles($request))], 201);
	}

	/**
	 * Met à jour les champs fournis de la fiche (administrateur, ou chef de projet
	 * de l'organisation).
	 *
	 * @param Request $request Requête portant les champs modifiés.
	 * @param Tenant $tenant Organisation résolue à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function update(Request $request, Tenant $tenant): JsonResponse
	{
		Gate::authorize('update', $tenant);

		$tenant->update($request->validate($this->rules(creating: false)));

		return new JsonResponse(['data' => $this->present($tenant, $this->roles($request))]);
	}

	/**
	 * Supprime l'organisation et ses rattachements (administrateur d'instance).
	 *
	 * @param Tenant $tenant Organisation résolue à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function destroy(Tenant $tenant): JsonResponse
	{
		Gate::authorize('delete', $tenant);

		$tenant->delete();

		return new JsonResponse(null, 204);
	}

	/**
	 * Règles de validation de la fiche ; la présentation est du Markdown, rendu
	 * et assaini dans le navigateur (13.04).
	 *
	 * @param bool $creating Vrai à la création (nom obligatoire).
	 * @return array<string, list<string>>
	 */
	private function rules(bool $creating): array
	{
		return [
			'name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:255'],
			'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
			'presentation' => ['sometimes', 'nullable', 'string', 'max:20000'],
		];
	}

	/**
	 * Rôles de l'utilisateur courant, lus une fois par requête.
	 *
	 * @param Request $request Requête courante.
	 * @return array<string, TenantRole>
	 */
	private function roles(Request $request): array
	{
		/** @var User $user */
		$user = $request->user();

		return $user->tenantRoles();
	}

	/**
	 * Représentation d'une organisation dans l'API.
	 *
	 * @param Tenant $tenant Organisation à présenter.
	 * @param array<string, TenantRole> $roles Rôles de l'utilisateur par organisation.
	 * @return array{id: string, name: string, legal_name: string|null, presentation: string|null, role: string|null}
	 */
	private function present(Tenant $tenant, array $roles): array
	{
		return [
			'id' => $tenant->id,
			'name' => $tenant->name,
			'legal_name' => $tenant->legal_name,
			'presentation' => $tenant->presentation,
			'role' => ($roles[$tenant->id] ?? null)?->value,
		];
	}
}
