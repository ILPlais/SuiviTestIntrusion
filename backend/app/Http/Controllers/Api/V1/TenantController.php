<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantRole;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organisations accessibles à l'utilisateur (identite-5, 11.06).
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
