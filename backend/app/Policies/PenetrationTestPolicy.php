<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use App\Models\User;

/**
 * Droits sur les tests d'intrusion (engagement-2, 11.06).
 *
 * La lecture n'a pas de règle propre : le filtre global ne laisse voir que les
 * tests des organisations de l'utilisateur, à tous leurs membres. Le chef de
 * projet de l'organisation crée le test, tient sa fiche et change son statut ;
 * l'administrateur d'instance peut tout faire, et lui seul supprime un test.
 */
class PenetrationTestPolicy
{
	/**
	 * Vrai si l'utilisateur dirige les tests de l'organisation : administrateur
	 * d'instance, ou chef de projet de l'organisation. Règle partagée avec
	 * l'indicateur `can_manage` renvoyé par l'API.
	 *
	 * @param User $user Utilisateur courant.
	 * @param TenantRole|null $role Rôle de l'utilisateur dans l'organisation du test.
	 * @return bool
	 */
	public static function manages(User $user, ?TenantRole $role): bool
	{
		return $user->is_instance_admin || $role === TenantRole::ProjectManager;
	}

	/**
	 * Créer un test dans une organisation (déjà visible par l'utilisateur).
	 *
	 * @param User $user Utilisateur courant.
	 * @param Tenant $tenant Organisation cliente du test.
	 * @return bool
	 */
	public function create(User $user, Tenant $tenant): bool
	{
		return self::manages($user, $user->roleIn($tenant));
	}

	/**
	 * Modifier la fiche du test ou changer son statut.
	 *
	 * @param User $user Utilisateur courant.
	 * @param PenetrationTest $test Test visé.
	 * @return bool
	 */
	public function update(User $user, PenetrationTest $test): bool
	{
		return self::manages($user, $user->roleIn($test->tenant_id));
	}

	/**
	 * Supprimer définitivement le test, ses phases et son historique :
	 * administrateur d'instance.
	 *
	 * @param User $user Utilisateur courant.
	 * @param PenetrationTest $test Test visé.
	 * @return bool
	 */
	public function delete(User $user, PenetrationTest $test): bool
	{
		return $user->is_instance_admin;
	}
}
