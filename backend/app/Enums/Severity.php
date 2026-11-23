<?php

namespace App\Enums;

/**
 * Niveau de sévérité d'une vulnérabilité, d'après son score CVSS (11.10,
 * « Échelle de risque », 14.01). Les seuils sont les mêmes en CVSS v3.1 et v4.0.
 */
enum Severity: string
{
	/** Aucun : 0,0. */
	case None = 'none';

	/** Mineur : 0,1 à 3,9. */
	case Minor = 'minor';

	/** Important : 4,0 à 6,9. */
	case Important = 'important';

	/** Majeur : 7,0 à 8,9. */
	case Major = 'major';

	/** Critique : 9,0 à 10,0. */
	case Critical = 'critical';

	/**
	 * Rang du niveau, de 0 (aucun) à 4 (critique), pour comparer des niveaux
	 * (risque global d'un test, 11.12).
	 *
	 * @return int
	 */
	public function rank(): int
	{
		return match ($this) {
			self::None => 0,
			self::Minor => 1,
			self::Important => 2,
			self::Major => 3,
			self::Critical => 4,
		};
	}
}
