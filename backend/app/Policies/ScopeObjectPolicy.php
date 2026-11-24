<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\ScopeObject;
use App\Models\User;

/**
 * Droits sur le périmètre d'un test (engagement-4, 11.06, 11.07).
 *
 * La lecture n'a pas de règle propre : le filtre global ne laisse voir que le
 * périmètre des tests des organisations de l'utilisateur, à tous leurs membres,
 * client final compris. L'équipe déclare et tient le périmètre : testeurs et
 * chef de projet de l'organisation, et administrateur d'instance.
 */
class ScopeObjectPolicy
{
	/**
	 * Vrai si l'utilisateur tient le périmètre des tests de l'organisation. Règle
	 * partagée avec l'indicateur `can_manage` renvoyé par l'API.
	 *
	 * @param User $user Utilisateur courant.
	 * @param TenantRole|null $role Rôle de l'utilisateur dans l'organisation du test.
	 * @return bool
	 */
	public static function manages(User $user, ?TenantRole $role): bool
	{
		return $user->is_instance_admin || $role === TenantRole::ProjectManager || $role === TenantRole::Tester;
	}

	/**
	 * Déclarer un objet dans le périmètre d'un test (déjà visible par l'utilisateur).
	 *
	 * @param User $user Utilisateur courant.
	 * @param PenetrationTest $test Test visé.
	 * @return bool
	 */
	public function create(User $user, PenetrationTest $test): bool
	{
		return self::manages($user, $user->roleIn($test->tenant_id));
	}

	/**
	 * Modifier un objet du périmètre.
	 *
	 * @param User $user Utilisateur courant.
	 * @param ScopeObject $object Objet visé.
	 * @return bool
	 */
	public function update(User $user, ScopeObject $object): bool
	{
		return self::manages($user, $user->roleIn($object->tenant_id));
	}

	/**
	 * Supprimer un objet du périmètre (et les services d'une machine).
	 *
	 * @param User $user Utilisateur courant.
	 * @param ScopeObject $object Objet visé.
	 * @return bool
	 */
	public function delete(User $user, ScopeObject $object): bool
	{
		return self::manages($user, $user->roleIn($object->tenant_id));
	}
}
