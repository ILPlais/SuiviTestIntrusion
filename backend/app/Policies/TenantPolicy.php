<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;

/**
 * Droits sur les organisations clientes (engagement-1, 11.06).
 *
 * La lecture n'a pas de règle propre : le filtre global ne laisse voir que les
 * organisations de l'utilisateur. Créer et supprimer une organisation relèvent de
 * l'administration de l'instance ; le chef de projet tient à jour la fiche de ses
 * organisations ; testeurs et clients finaux n'ont qu'un accès en lecture.
 */
class TenantPolicy
{
	/**
	 * Créer une organisation : administrateur d'instance.
	 *
	 * @param User $user Utilisateur courant.
	 * @return bool
	 */
	public function create(User $user): bool
	{
		return $user->is_instance_admin;
	}

	/**
	 * Modifier la fiche : administrateur d'instance, ou chef de projet de
	 * l'organisation.
	 *
	 * @param User $user Utilisateur courant.
	 * @param Tenant $tenant Organisation visée.
	 * @return bool
	 */
	public function update(User $user, Tenant $tenant): bool
	{
		return $user->is_instance_admin || $user->roleIn($tenant) === TenantRole::ProjectManager;
	}

	/**
	 * Supprimer une organisation et tout ce qui lui appartient : administrateur
	 * d'instance.
	 *
	 * @param User $user Utilisateur courant.
	 * @param Tenant $tenant Organisation visée.
	 * @return bool
	 */
	public function delete(User $user, Tenant $tenant): bool
	{
		return $user->is_instance_admin;
	}
}
