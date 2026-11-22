<?php

namespace App\Models;

use App\Tenancy\TenantScope;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Organisation cliente, ou tenant (identite-5, 11.06, 14.01) : références
 * « Client » de 11.05.
 *
 * Le filtre global restreint toute lecture aux organisations de l'utilisateur
 * courant ; l'administrateur d'instance les voit toutes.
 *
 * @property string $id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $presentation
 */
#[Fillable(['name', 'legal_name', 'presentation'])]
class Tenant extends Model
{
	/** @use HasFactory<TenantFactory> */
	use HasFactory;
	use HasUlids;

	/**
	 * Restreint les lectures aux organisations accessibles (colonne `id`).
	 *
	 * @return void
	 */
	protected static function booted(): void
	{
		static::addGlobalScope(new TenantScope('id'));
	}

	/**
	 * Membres de l'organisation, avec leur rôle.
	 *
	 * @return BelongsToMany<User, $this>
	 */
	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
	}
}
