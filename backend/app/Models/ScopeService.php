<?php

namespace App\Models;

use App\Enums\ServiceProtocol;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Détail d'un service du périmètre (engagement-4, 14.01), table `scope_services`.
 * Son objet a pour parent une machine du même test.
 *
 * Sans `tenant_id` : la ligne ne se lit qu'à travers son ScopeObject, filtré par
 * organisation. Ne jamais l'interroger directement depuis une route.
 *
 * @property string $scope_object_id
 * @property ServiceProtocol $protocol
 * @property int $port
 * @property string|null $name
 * @property string|null $version
 */
#[Fillable(['protocol', 'port', 'name', 'version'])]
class ScopeService extends Model
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

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'protocol' => ServiceProtocol::class,
			'port' => 'integer',
		];
	}
}
