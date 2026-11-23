<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Détail d'un compte de test du périmètre (engagement-4, 14.01), table
 * `scope_accounts` : identifiant et rôle seulement. Le secret du compte n'est
 * jamais stocké ici : il sera chiffré dans le navigateur et rangé avec la GED
 * chiffrée (ged-chiffree, 13.01).
 *
 * Sans `tenant_id` : la ligne ne se lit qu'à travers son ScopeObject, filtré par
 * organisation. Ne jamais l'interroger directement depuis une route.
 *
 * @property string $scope_object_id
 * @property string $username
 * @property string|null $role_description
 */
#[Fillable(['username', 'role_description'])]
class ScopeAccount extends Model
{
	/**
	 * Clé primaire : l'identifiant de l'objet du périmètre.
	 *
	 * @var string
	 */
	protected $primaryKey = 'scope_object_id';

	/**
	 * Clé fournie par l'objet, pas auto-incrémentée.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * Type de la clé (ULID).
	 *
	 * @var string
	 */
	protected $keyType = 'string';
}
