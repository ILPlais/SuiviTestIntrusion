<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Détail d'une machine du périmètre (engagement-4, 14.01), table `scope_hosts`.
 *
 * Sans `tenant_id` : la ligne ne se lit qu'à travers son ScopeObject, filtré par
 * organisation. Ne jamais l'interroger directement depuis une route.
 *
 * @property string $scope_object_id
 * @property string $address
 * @property string|null $hostname
 * @property string|null $operating_system
 */
#[Fillable(['address', 'hostname', 'operating_system'])]
class ScopeHost extends Model
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
