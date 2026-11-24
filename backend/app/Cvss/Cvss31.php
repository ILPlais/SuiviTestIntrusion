<?php

namespace App\Cvss;

use App\Enums\Severity;

/**
 * Moteur CVSS v3.1 (vulnerabilites-1, 11.10) : métriques de base seulement,
 * coefficients et équations de la spécification FIRST v3.1, repris par 11.10.
 *
 * Logique pure, sans base de données : le même calcul existe côté navigateur
 * (frontend/src/utils/cvss31.ts) pour l'aperçu pendant la saisie ; le serveur
 * recalcule toujours le score à l'enregistrement et seul le sien fait foi.
 */
final class Cvss31
{
	/** Préfixe obligatoire d'un vecteur v3.1. */
	public const PREFIX = 'CVSS:3.1';

	/**
	 * Valeurs admises pour chaque métrique de base, dans l'ordre de la
	 * spécification (qui est aussi celui du vecteur généré).
	 *
	 * @var array<string, list<string>>
	 */
	public const METRICS = [
		'AV' => ['N', 'A', 'L', 'P'],
		'AC' => ['L', 'H'],
		'PR' => ['N', 'L', 'H'],
		'UI' => ['N', 'R'],
		'S' => ['U', 'C'],
		'C' => ['H', 'L', 'N'],
		'I' => ['H', 'L', 'N'],
		'A' => ['H', 'L', 'N'],
	];

	/**
	 * Coefficients de la spécification (11.10).
	 *
	 * @var array<string, array<string, float>>
	 */
	private const WEIGHTS = [
		'AV' => ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2],
		'AC' => ['L' => 0.77, 'H' => 0.44],
		'UI' => ['N' => 0.85, 'R' => 0.62],
		'CIA' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
	];

	/**
	 * Privilèges requis : le coefficient dépend de la portée (inchangée, changée).
	 *
	 * @var array<string, array{U: float, C: float}>
	 */
	private const PRIVILEGES = [
		'N' => ['U' => 0.85, 'C' => 0.85],
		'L' => ['U' => 0.62, 'C' => 0.68],
		'H' => ['U' => 0.27, 'C' => 0.5],
	];

	/**
	 * Lit un vecteur `CVSS:3.1/AV:N/…` ; les métriques peuvent venir dans
	 * n'importe quel ordre, mais toutes les métriques de base sont exigées, une
	 * seule fois chacune, et aucune autre n'est admise.
	 *
	 * @param string $vector Vecteur à lire.
	 * @return array<string, string> Valeur de chaque métrique, dans l'ordre de la spécification.
	 *
	 * @throws InvalidCvssVector Si le vecteur est illisible ou incomplet.
	 */
	public static function parse(string $vector): array
	{
		$parts = explode('/', trim($vector));
		if (array_shift($parts) !== self::PREFIX) {
			throw new InvalidCvssVector('Le vecteur doit commencer par CVSS:3.1.');
		}

		$found = [];
		foreach ($parts as $part) {
			$pair = explode(':', $part);
			if (count($pair) !== 2) {
				throw new InvalidCvssVector("Élément de vecteur mal formé : {$part}.");
			}
			[$metric, $value] = $pair;
			if (! array_key_exists($metric, self::METRICS)) {
				throw new InvalidCvssVector("Métrique non admise : {$metric}.");
			}
			if (array_key_exists($metric, $found)) {
				throw new InvalidCvssVector("Métrique répétée : {$metric}.");
			}
			if (! in_array($value, self::METRICS[$metric], true)) {
				throw new InvalidCvssVector("Valeur invalide pour {$metric} : {$value}.");
			}
			$found[$metric] = $value;
		}

		$metrics = [];
		foreach (array_keys(self::METRICS) as $metric) {
			if (! array_key_exists($metric, $found)) {
				throw new InvalidCvssVector("Métrique manquante : {$metric}.");
			}
			$metrics[$metric] = $found[$metric];
		}

		return $metrics;
	}

	/**
	 * Génère le vecteur dans l'ordre de la spécification.
	 *
	 * @param array<string, string> $metrics Métriques lues par parse().
	 * @return string
	 */
	public static function vector(array $metrics): string
	{
		$parts = [self::PREFIX];
		foreach (array_keys(self::METRICS) as $metric) {
			$parts[] = $metric.':'.$metrics[$metric];
		}

		return implode('/', $parts);
	}

	/**
	 * Score de base (11.10, « Calcul ») : l'impact nul est testé en premier,
	 * puis la portée choisit la formule d'impact et le facteur final.
	 *
	 * @param array<string, string> $metrics Métriques lues par parse().
	 * @return float Score de 0,0 à 10,0, au dixième.
	 */
	public static function score(array $metrics): float
	{
		$changed = $metrics['S'] === 'C';
		$cia = self::WEIGHTS['CIA'];
		$subScore = 1 - (1 - $cia[$metrics['C']]) * (1 - $cia[$metrics['I']]) * (1 - $cia[$metrics['A']]);

		$impact = $changed
			? 7.52 * ($subScore - 0.029) - 3.25 * (($subScore - 0.02) ** 15)
			: 6.42 * $subScore;
		if ($impact <= 0) {
			return 0.0;
		}

		$exploitability = 8.22
			* self::WEIGHTS['AV'][$metrics['AV']]
			* self::WEIGHTS['AC'][$metrics['AC']]
			* self::PRIVILEGES[$metrics['PR']][$changed ? 'C' : 'U']
			* self::WEIGHTS['UI'][$metrics['UI']];

		return $changed
			? self::roundUp(min(1.08 * ($impact + $exploitability), 10))
			: self::roundUp(min($impact + $exploitability, 10));
	}

	/**
	 * Arrondi supérieur au dixième, selon l'annexe A de la spécification v3.1 :
	 * le calcul passe par des entiers pour neutraliser les erreurs de virgule
	 * flottante (4,000001 reste 4,0 ; 4,02 devient 4,1).
	 *
	 * @param float $value Valeur à arrondir.
	 * @return float
	 */
	public static function roundUp(float $value): float
	{
		$integer = (int) round($value * 100000);
		if ($integer % 10000 === 0) {
			return $integer / 100000.0;
		}

		return (floor($integer / 10000) + 1) / 10.0;
	}

	/**
	 * Niveau de sévérité d'un score (échelle de 11.10).
	 *
	 * @param float $score Score de base, de 0,0 à 10,0.
	 * @return Severity
	 */
	public static function severity(float $score): Severity
	{
		return match (true) {
			$score <= 0.0 => Severity::None,
			$score < 4.0 => Severity::Minor,
			$score < 7.0 => Severity::Important,
			$score < 9.0 => Severity::Major,
			default => Severity::Critical,
		};
	}
}
