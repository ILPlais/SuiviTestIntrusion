<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtre global des modèles rattachés à une organisation (identite-5, 14.01) :
 * seules les lignes des organisations accessibles sont lues ; une liste vide ne
 * donne aucune ligne.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
	/**
	 * Reçoit la colonne qui porte l'organisation (`tenant_id`, ou `id` pour la
	 * table des organisations elle-même).
	 *
	 * @param string $column Colonne comparée aux organisations accessibles.
	 */
	public function __construct(private readonly string $column = 'tenant_id')
	{
	}

	/**
	 * Restreint la requête aux organisations accessibles.
	 *
	 * @param Builder<covariant Model> $builder Requête à restreindre.
	 * @param Model $model Modèle interrogé.
	 * @return void
	 */
	public function apply(Builder $builder, Model $model): void
	{
		$ids = TenantAccess::visibleTenantIds();
		if ($ids !== null) {
			$builder->whereIn($model->qualifyColumn($this->column), $ids);
		}
	}
}
