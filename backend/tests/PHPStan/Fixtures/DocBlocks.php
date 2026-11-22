<?php

namespace Tests\PHPStan\Fixtures;

// Fixture de RequireTypedDocBlockRuleTest : chaque manque est volontaire, et le
// fichier est exclu de l'analyse Larastan (phpstan.neon).

class Undocumented
{
	public const LIMIT = 1;

	public int $count = 0;

	public function run(int $times): int
	{
		return $times;
	}
}

/** Classe documentée, dont certains éléments sont incomplets. */
class PartlyDocumented
{
	/** Propriété décrite, sans type. */
	public int $count = 0;

	/**
	 * Méthode décrite, sans balise pour son paramètre ni son retour.
	 */
	public function run(int $times): int
	{
		return $times;
	}

	/**
	 * @param int $times Nombre de répétitions.
	 * @return int
	 */
	public function withoutDescription(int $times): int
	{
		return $times;
	}
}

/** Classe complète : aucune erreur attendue. */
class FullyDocumented
{
	/** Plafond par défaut. */
	public const LIMIT = 1;

	/**
	 * Compteur courant.
	 *
	 * @var int
	 */
	public int $count = 0;

	/**
	 * Reçoit la valeur de départ.
	 *
	 * @param int $start Valeur de départ.
	 */
	public function __construct(int $start = 0)
	{
		$this->count = $start;
	}

	/**
	 * Répète l'opération.
	 *
	 * @param int $times Nombre de répétitions.
	 * @return int
	 */
	public function run(int $times): int
	{
		return (fn (int $n): int => $n * 2)($times);
	}
}

$anonymous = new class () {};
