<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À utiliser par chaque modèle métier qui porte une colonne `tenant_id`
 * (identite-5, 14.01) :
 *
 * - lecture : filtre global TenantScope, un objet hors des organisations de
 *   l'utilisateur est introuvable (404 par la liaison de route) ;
 * - écriture : création ou déplacement hors de ces organisations refusés
 *   (TenantMismatch).
 *
 * @mixin Model
 */
trait BelongsToTenant
{
	/**
	 * Installe le filtre de lecture et le contrôle d'écriture.
	 *
	 * @return void
	 */
	public static function bootBelongsToTenant(): void
	{
		static::addGlobalScope(new TenantScope());

		static::saving(function (Model $model): void {
			$tenantId = $model->getAttribute('tenant_id');
			if (! is_string($tenantId) || ! TenantAccess::allows($tenantId)) {
				throw new TenantMismatch('Écriture hors des organisations de l\'utilisateur ('.static::class.').');
			}

			// Ligne existante : son organisation d'origine doit aussi être accessible
			// (une instance chargée sans filtre ne se réécrit pas hors du bloc), et un
			// objet ne change jamais d'organisation, sauf restriction levée.
			if ($model->exists) {
				$original = $model->getOriginal('tenant_id');
				if (! is_string($original) || ! TenantAccess::allows($original)) {
					throw new TenantMismatch('Écriture hors des organisations de l\'utilisateur ('.static::class.').');
				}
				if ($original !== $tenantId && TenantAccess::visibleTenantIds() !== null) {
					throw new TenantMismatch('Un objet ne change pas d\'organisation ('.static::class.').');
				}
			}
		});
	}

	/**
	 * Exécute un traitement sans filtre d'organisation ; voir TenantAccess.
	 *
	 * @template TResult
	 *
	 * @param callable(): TResult $callback Traitement à exécuter.
	 * @return TResult
	 */
	public static function withoutTenantRestriction(callable $callback): mixed
	{
		return TenantAccess::withoutRestriction($callback);
	}

	/**
	 * Organisation de l'objet.
	 *
	 * @return BelongsTo<Tenant, $this>
	 */
	public function tenant(): BelongsTo
	{
		return $this->belongsTo(Tenant::class);
	}
}
