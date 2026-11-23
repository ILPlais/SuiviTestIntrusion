<?php

namespace App\Enums;

/**
 * Priorité de correction d'une vulnérabilité (vulnerabilites-5, 11.09),
 * indépendante du score CVSS ; suggérée depuis le niveau de sévérité.
 */
enum RemediationPriority: string
{
	/** Immédiat : blocage ou exposition critique. */
	case P1 = 'P1';

	/** Court terme. */
	case P2 = 'P2';

	/** Moyen terme. */
	case P3 = 'P3';

	/** Opportunité ou durcissement. */
	case P4 = 'P4';

	/**
	 * Priorité suggérée pour un niveau de sévérité (11.09) : Critique → P1,
	 * Majeur → P2, Important → P3, Mineur, Aucun ou non coté → P4.
	 *
	 * @param Severity|null $severity Niveau de la vulnérabilité, ou null si elle n'est pas cotée.
	 * @return self
	 */
	public static function suggestedFor(?Severity $severity): self
	{
		return match ($severity) {
			Severity::Critical => self::P1,
			Severity::Major => self::P2,
			Severity::Important => self::P3,
			default => self::P4,
		};
	}

	/**
	 * Poids de la priorité dans le score agrégé d'un test (11.12) : P1 = 4 … P4 = 1.
	 *
	 * @return int
	 */
	public function weight(): int
	{
		return match ($this) {
			self::P1 => 4,
			self::P2 => 3,
			self::P3 => 2,
			self::P4 => 1,
		};
	}
}
