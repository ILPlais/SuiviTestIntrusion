<?php

namespace App\Results;

use App\Enums\PhaseCode;
use App\Enums\RemediationPriority;
use App\Enums\RemediationStatus;
use App\Enums\Severity;
use App\Models\Remediation;
use App\Models\Vulnerability;
use Illuminate\Support\Collection;

/**
 * Risque global d'un test (resultats-1, 11.12), calculé à la lecture : aucune
 * table, conformément à 14.01.
 *
 * Les indicateurs portent sur les vulnérabilités **ouvertes** (statut de
 * remédiation autre que Vérifié) ; seul le compteur par statut de remédiation
 * les compte toutes. Les vulnérabilités non cotées entrent dans les compteurs
 * (« non cotée », priorité P4) mais ni dans le maximum ni dans la moyenne. En
 * V1, toutes les cotations sont en CVSS v3.1 : la règle du test mixte
 * (vulnerabilites-6) viendra en V2.
 */
final class TestRisk
{
	/**
	 * Calcule le risque à partir des vulnérabilités du test.
	 *
	 * @param Collection<int, Vulnerability> $vulnerabilities Vulnérabilités du test, avec phase et remédiation chargées.
	 * @return array<string, mixed>
	 */
	public static function compute(Collection $vulnerabilities): array
	{
		$open = $vulnerabilities->filter(fn (Vulnerability $vulnerability) => self::status($vulnerability) !== RemediationStatus::Verified);
		$rated = $open->filter(fn (Vulnerability $vulnerability) => $vulnerability->cvss_score !== null);

		$max = $rated->max(fn (Vulnerability $vulnerability) => $vulnerability->cvss_score);
		$maxScore = $max === null ? null : (float) $max;

		return [
			'max_score' => $maxScore,
			'max_severity' => $maxScore === null ? null : self::severityOf($rated, $maxScore),
			'cvss_version' => $maxScore === null ? null : '3.1',
			'weighted_average' => self::weightedAverage($rated),
			'open_count' => $open->count(),
			'total_count' => $vulnerabilities->count(),
			'business_profile' => self::businessProfile($open),
			'by_severity' => self::bySeverity($open),
			'by_phase' => self::byPhase($open),
			'by_remediation_status' => self::byRemediationStatus($vulnerabilities),
			'by_priority' => self::byPriority($open),
		];
	}

	/**
	 * Niveau de la vulnérabilité qui porte le score maximal.
	 *
	 * @param Collection<int, Vulnerability> $rated Vulnérabilités ouvertes cotées.
	 * @param float $maxScore Score maximal.
	 * @return string|null
	 */
	private static function severityOf(Collection $rated, float $maxScore): ?string
	{
		$top = $rated->first(fn (Vulnerability $vulnerability) => (float) $vulnerability->cvss_score === $maxScore);

		return $top?->severity?->value;
	}

	/**
	 * Moyenne des scores pondérée par la priorité (P1 = 4 … P4 = 1), au dixième ;
	 * informative, jamais à la place du maximum (11.12).
	 *
	 * @param Collection<int, Vulnerability> $rated Vulnérabilités ouvertes cotées.
	 * @return float|null
	 */
	private static function weightedAverage(Collection $rated): ?float
	{
		$weights = 0;
		$sum = 0.0;
		foreach ($rated as $vulnerability) {
			$weight = self::priority($vulnerability)->weight();
			$weights += $weight;
			$sum += $weight * (float) $vulnerability->cvss_score;
		}

		return $weights === 0 ? null : round($sum / $weights, 1);
	}

	/**
	 * Profil d'impact business : maximum de chaque axe.
	 *
	 * @param Collection<int, Vulnerability> $open Vulnérabilités ouvertes.
	 * @return array<string, int>
	 */
	private static function businessProfile(Collection $open): array
	{
		$profile = array_fill_keys(array_keys(Vulnerability::IMPACT_AXES), 0);
		foreach ($open as $vulnerability) {
			foreach ($vulnerability->businessImpact() as $axis => $level) {
				$profile[$axis] = max($profile[$axis], $level);
			}
		}

		return $profile;
	}

	/**
	 * Compteur par niveau de sévérité, plus « non cotée ».
	 *
	 * @param Collection<int, Vulnerability> $open Vulnérabilités ouvertes.
	 * @return array<string, int>
	 */
	private static function bySeverity(Collection $open): array
	{
		$counts = array_fill_keys(array_map(fn (Severity $severity) => $severity->value, Severity::cases()), 0) + ['unrated' => 0];
		foreach ($open as $vulnerability) {
			$counts[$vulnerability->severity->value ?? 'unrated']++;
		}

		return $counts;
	}

	/**
	 * Compteur par phase du test.
	 *
	 * @param Collection<int, Vulnerability> $open Vulnérabilités ouvertes.
	 * @return array<string, int>
	 */
	private static function byPhase(Collection $open): array
	{
		$counts = array_fill_keys(array_map(fn (PhaseCode $code) => $code->value, PhaseCode::cases()), 0);
		foreach ($open as $vulnerability) {
			$code = $vulnerability->phase?->code->value;
			if ($code !== null) {
				$counts[$code]++;
			}
		}

		return $counts;
	}

	/**
	 * Compteur par statut de remédiation, vulnérabilités vérifiées comprises.
	 *
	 * @param Collection<int, Vulnerability> $vulnerabilities Toutes les vulnérabilités du test.
	 * @return array<string, int>
	 */
	private static function byRemediationStatus(Collection $vulnerabilities): array
	{
		$counts = array_fill_keys(array_map(fn (RemediationStatus $status) => $status->value, RemediationStatus::cases()), 0);
		foreach ($vulnerabilities as $vulnerability) {
			$counts[self::status($vulnerability)->value]++;
		}

		return $counts;
	}

	/**
	 * Compteur par priorité de correction.
	 *
	 * @param Collection<int, Vulnerability> $open Vulnérabilités ouvertes.
	 * @return array<string, int>
	 */
	private static function byPriority(Collection $open): array
	{
		$counts = array_fill_keys(array_map(fn (RemediationPriority $priority) => $priority->value, RemediationPriority::cases()), 0);
		foreach ($open as $vulnerability) {
			$counts[self::priority($vulnerability)->value]++;
		}

		return $counts;
	}

	/**
	 * Statut de remédiation ; « à faire » si la fiche manque (ne devrait pas arriver).
	 *
	 * @param Vulnerability $vulnerability Vulnérabilité (remédiation chargée).
	 * @return RemediationStatus
	 */
	private static function status(Vulnerability $vulnerability): RemediationStatus
	{
		/** @var Remediation|null $remediation */
		$remediation = $vulnerability->remediation;

		return $remediation === null ? RemediationStatus::Todo : $remediation->status;
	}

	/**
	 * Priorité retenue ; la suggestion si la fiche manque.
	 *
	 * @param Vulnerability $vulnerability Vulnérabilité (remédiation chargée).
	 * @return RemediationPriority
	 */
	private static function priority(Vulnerability $vulnerability): RemediationPriority
	{
		/** @var Remediation|null $remediation */
		$remediation = $vulnerability->remediation;

		return $remediation === null ? RemediationPriority::suggestedFor($vulnerability->severity) : $remediation->priority;
	}
}
