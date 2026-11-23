<?php

namespace Tests\Unit;

use App\Cvss\Cvss31;
use App\Cvss\InvalidCvssVector;
use App\Enums\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Moteur CVSS v3.1 (vulnerabilites-1, 11.10) : score de base, niveau de
 * sévérité, lecture et génération du vecteur, sur des vecteurs de référence
 * dont le score est publié par le calculateur de FIRST.
 */
class Cvss31Test extends TestCase
{
	/**
	 * Vecteurs de référence et scores de base attendus (calculateur FIRST v3.1).
	 *
	 * @return array<string, array{string, float}>
	 */
	public static function referenceVectors(): array
	{
		return [
			'critique, portée inchangée' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8],
			'maximum, portée changée' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H', 10.0],
			'XSS réfléchie' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N', 6.1],
			'élévation locale' => ['CVSS:3.1/AV:L/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H', 7.8],
			'complexité élevée' => ['CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N', 5.9],
			'adjacent' => ['CVSS:3.1/AV:A/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N', 6.5],
			'privilèges bas, portée changée' => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:C/C:L/I:L/A:N', 6.4],
			'privilèges élevés, portée changée' => ['CVSS:3.1/AV:N/AC:L/PR:H/UI:N/S:C/C:H/I:H/A:H', 9.1],
			'physique, minimal' => ['CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:L/I:N/A:N', 1.6],
			'sans impact' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N', 0.0],
			'sans impact, portée changée' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:N/I:N/A:N', 0.0],
		];
	}

	/**
	 * Le score de base est celui du calculateur de FIRST.
	 *
	 * @param string $vector Vecteur CVSS v3.1.
	 * @param float $expected Score de base publié.
	 * @return void
	 */
	#[DataProvider('referenceVectors')]
	public function test_the_base_score_matches_first(string $vector, float $expected): void
	{
		$this->assertSame($expected, Cvss31::score(Cvss31::parse($vector)));
	}

	/**
	 * L'arrondi supérieur au dixième suit l'annexe A de la spécification :
	 * 4.000001 reste 4.0, 4.02 devient 4.1.
	 *
	 * @return void
	 */
	public function test_round_up_follows_the_specification(): void
	{
		$this->assertSame(4.0, Cvss31::roundUp(4.000001));
		$this->assertSame(4.1, Cvss31::roundUp(4.02));
		$this->assertSame(4.0, Cvss31::roundUp(4.0));
		$this->assertSame(0.0, Cvss31::roundUp(0.0));
	}

	/**
	 * Le niveau de sévérité suit l'échelle de 11.10.
	 *
	 * @return void
	 */
	public function test_the_severity_follows_the_risk_scale(): void
	{
		$this->assertSame(Severity::None, Cvss31::severity(0.0));
		$this->assertSame(Severity::Minor, Cvss31::severity(0.1));
		$this->assertSame(Severity::Minor, Cvss31::severity(3.9));
		$this->assertSame(Severity::Important, Cvss31::severity(4.0));
		$this->assertSame(Severity::Important, Cvss31::severity(6.9));
		$this->assertSame(Severity::Major, Cvss31::severity(7.0));
		$this->assertSame(Severity::Major, Cvss31::severity(8.9));
		$this->assertSame(Severity::Critical, Cvss31::severity(9.0));
		$this->assertSame(Severity::Critical, Cvss31::severity(10.0));
	}

	/**
	 * Le vecteur se relit dans n'importe quel ordre et se régénère dans l'ordre
	 * de la spécification.
	 *
	 * @return void
	 */
	public function test_the_vector_is_parsed_and_generated_in_canonical_order(): void
	{
		$metrics = Cvss31::parse('CVSS:3.1/C:H/I:H/A:H/AV:N/AC:L/PR:N/UI:N/S:U');

		$this->assertSame(['AV' => 'N', 'AC' => 'L', 'PR' => 'N', 'UI' => 'N', 'S' => 'U', 'C' => 'H', 'I' => 'H', 'A' => 'H'], $metrics);
		$this->assertSame('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', Cvss31::vector($metrics));
	}

	/**
	 * Vecteurs refusés : préfixe ou version absents, métrique manquante,
	 * inconnue, répétée ou de valeur invalide, métriques temporelles ou
	 * environnementales (hors du périmètre de 11.10).
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalidVectors(): array
	{
		return [
			'sans préfixe' => ['AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
			'version 3.0' => ['CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
			'version 4.0' => ['CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N'],
			'métrique manquante' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H'],
			'valeur invalide' => ['CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
			'métrique répétée' => ['CVSS:3.1/AV:N/AV:L/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
			'métrique temporelle' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H/E:P'],
			'élément mal formé' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A'],
			'vide' => [''],
		];
	}

	/**
	 * Un vecteur invalide lève InvalidCvssVector.
	 *
	 * @param string $vector Vecteur à refuser.
	 * @return void
	 */
	#[DataProvider('invalidVectors')]
	public function test_invalid_vectors_are_rejected(string $vector): void
	{
		$this->expectException(InvalidCvssVector::class);

		Cvss31::parse($vector);
	}
}
