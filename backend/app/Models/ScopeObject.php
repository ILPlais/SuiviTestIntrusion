<?php

namespace App\Models;

use App\Enums\ScopeObjectType;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Objet du périmètre d'un test (engagement-4, 11.07, 14.01), table `scope_objects`.
 *
 * Champs communs à tous les types (inclusion, commentaire, contraintes
 * horaires) ; les champs propres à chaque type sont dans une table de détail
 * (ScopeHost, ScopeService, ScopeAccount). Un objet se crée par declare(), se
 * modifie par amend() et se supprime par remove(), qui gèrent ensemble l'objet
 * et son détail. Le type et le test ne changent jamais.
 *
 * Les liens vers les tâches et les vulnérabilités viendront avec les stories qui
 * créent ces objets (deroulement-1, vulnerabilites-3).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $test_id
 * @property ScopeObjectType $type
 * @property string|null $parent_id
 * @property string $label
 * @property bool $included
 * @property string|null $comment
 * @property string|null $constraints
 */
#[Fillable(['parent_id', 'label', 'included', 'comment', 'constraints'])]
class ScopeObject extends Model
{
	use BelongsToTenant;
	use HasUlids;

	/**
	 * Valeurs par défaut, identiques à celles de la migration : un objet déclaré
	 * sans précision est inclus, et la réponse le dit sans relire la ligne.
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = [
		'included' => true,
	];

	/**
	 * Champs de la table de détail de chaque type.
	 *
	 * @var array<string, list<string>>
	 */
	public const DETAIL_FIELDS = [
		'host' => ['address', 'hostname', 'operating_system'],
		'service' => ['protocol', 'port', 'name', 'version'],
		'account' => ['username', 'role_description'],
	];

	/**
	 * Champs communs modifiables.
	 *
	 * @var list<string>
	 */
	private const COMMON_FIELDS = ['parent_id', 'label', 'included', 'comment', 'constraints'];

	/**
	 * Déclare un objet dans le périmètre d'un test, avec son détail, en une seule
	 * transaction. Les données sont déjà validées ; un libellé absent est déduit
	 * du détail (defaultLabel()).
	 *
	 * @param PenetrationTest $test Test dont l'objet délimite le périmètre.
	 * @param array<string, mixed> $data Type, champs communs et champs du détail.
	 * @return self
	 */
	public static function declare(PenetrationTest $test, array $data): self
	{
		$type = ScopeObjectType::from($data['type']);

		return DB::transaction(function () use ($test, $type, $data): self {
			$object = new self(array_intersect_key($data, array_flip(self::COMMON_FIELDS)));
			$object->tenant_id = $test->tenant_id;
			$object->test_id = $test->id;
			$object->type = $type;

			$detail = $object->newDetail();
			$detail->fill(array_intersect_key($data, array_flip(self::DETAIL_FIELDS[$type->value])));
			$object->label = self::filled($data['label'] ?? null) ?? self::labelFor($type, $detail);
			$object->save();

			$detail->setAttribute('scope_object_id', $object->id);
			$detail->save();
			$object->setRelation($type->value, $detail);

			return $object;
		});
	}

	/**
	 * Modifie les champs fournis de l'objet et de son détail. Un libellé resté
	 * celui déduit du détail suit le détail ; un libellé vide redevient déduit.
	 *
	 * @param array<string, mixed> $data Champs validés à modifier (type et test exclus).
	 * @return void
	 */
	public function amend(array $data): void
	{
		DB::transaction(function () use ($data): void {
			$detail = $this->detail();
			$automatic = $this->label === self::labelFor($this->type, $detail);

			$this->fill(array_intersect_key($data, array_flip(self::COMMON_FIELDS)));
			$detail->fill(array_intersect_key($data, array_flip(self::DETAIL_FIELDS[$this->type->value])));

			if (array_key_exists('label', $data)) {
				$this->label = self::filled($data['label']) ?? self::labelFor($this->type, $detail);
			} elseif ($automatic) {
				$this->label = self::labelFor($this->type, $detail);
			}

			$detail->save();
			$this->save();
		});
	}

	/**
	 * Supprime l'objet : les services d'une machine disparaissent avec elle (clé
	 * étrangère en cascade), ses comptes restent, détachés.
	 *
	 * @return void
	 */
	public function remove(): void
	{
		DB::transaction(function (): void {
			$this->children()->where('type', ScopeObjectType::Account->value)->update(['parent_id' => null]);
			$this->delete();
		});
	}

	/**
	 * Détail de l'objet selon son type (chargé au besoin).
	 *
	 * @return ScopeHost|ScopeService|ScopeAccount
	 */
	public function detail(): ScopeHost|ScopeService|ScopeAccount
	{
		$relation = $this->type->value;
		/** @var ScopeHost|ScopeService|ScopeAccount|null $detail */
		$detail = $this->getRelationValue($relation);

		return $detail ?? $this->newDetail();
	}

	/**
	 * Nouveau détail vide, du modèle qui correspond au type.
	 *
	 * @return ScopeHost|ScopeService|ScopeAccount
	 */
	private function newDetail(): ScopeHost|ScopeService|ScopeAccount
	{
		return match ($this->type) {
			ScopeObjectType::Host => new ScopeHost(),
			ScopeObjectType::Service => new ScopeService(),
			ScopeObjectType::Account => new ScopeAccount(),
		};
	}

	/**
	 * Libellé déduit du détail : nom ou adresse d'une machine, « port/protocole
	 * nom » d'un service, identifiant d'un compte.
	 *
	 * @param ScopeObjectType $type Type de l'objet.
	 * @param ScopeHost|ScopeService|ScopeAccount $detail Détail de l'objet.
	 * @return string
	 */
	private static function labelFor(ScopeObjectType $type, ScopeHost|ScopeService|ScopeAccount $detail): string
	{
		return match ($type) {
			ScopeObjectType::Host => self::filled($detail->getAttribute('hostname')) ?? (string) $detail->getAttribute('address'),
			ScopeObjectType::Service => trim($detail->getAttribute('port').'/'.$detail->getAttribute('protocol')?->value.' '.$detail->getAttribute('name')),
			ScopeObjectType::Account => (string) $detail->getAttribute('username'),
		};
	}

	/**
	 * Chaîne non vide après suppression des espaces, ou null.
	 *
	 * @param mixed $value Valeur reçue.
	 * @return string|null
	 */
	private static function filled(mixed $value): ?string
	{
		return is_string($value) && trim($value) !== '' ? trim($value) : null;
	}

	/**
	 * Test dont l'objet délimite le périmètre.
	 *
	 * @return BelongsTo<PenetrationTest, $this>
	 */
	public function test(): BelongsTo
	{
		return $this->belongsTo(PenetrationTest::class, 'test_id');
	}

	/**
	 * Objet hôte (machine d'un service ou d'un compte).
	 *
	 * @return BelongsTo<ScopeObject, $this>
	 */
	public function parent(): BelongsTo
	{
		return $this->belongsTo(self::class, 'parent_id');
	}

	/**
	 * Objets hébergés (services et comptes d'une machine).
	 *
	 * @return HasMany<ScopeObject, $this>
	 */
	public function children(): HasMany
	{
		return $this->hasMany(self::class, 'parent_id');
	}

	/**
	 * Détail d'une machine.
	 *
	 * @return HasOne<ScopeHost, $this>
	 */
	public function host(): HasOne
	{
		return $this->hasOne(ScopeHost::class, 'scope_object_id');
	}

	/**
	 * Détail d'un service.
	 *
	 * @return HasOne<ScopeService, $this>
	 */
	public function service(): HasOne
	{
		return $this->hasOne(ScopeService::class, 'scope_object_id');
	}

	/**
	 * Détail d'un compte de test.
	 *
	 * @return HasOne<ScopeAccount, $this>
	 */
	public function account(): HasOne
	{
		return $this->hasOne(ScopeAccount::class, 'scope_object_id');
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'type' => ScopeObjectType::class,
			'included' => 'boolean',
		];
	}
}
