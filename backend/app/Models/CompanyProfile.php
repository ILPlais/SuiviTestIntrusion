<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fiche de l'entreprise prestataire qui exploite l'instance (engagement-1, 11.05,
 * références « Entreprise ») : une seule ligne, d'identifiant 1. Elle n'appartient
 * à aucune organisation cliente.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $legal_name
 * @property string|null $presentation
 */
#[Fillable(['name', 'legal_name', 'presentation'])]
class CompanyProfile extends Model
{
	/**
	 * Table au singulier : elle ne contient qu'une ligne.
	 *
	 * @var string
	 */
	protected $table = 'company_profile';

	/**
	 * La fiche de l'instance, vide tant qu'aucun administrateur ne l'a remplie.
	 *
	 * @return self
	 */
	public static function current(): self
	{
		return self::query()->firstOrNew(['id' => 1]);
	}
}
