<?php

namespace Tests\Unit;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tests\PHPStan\RequireTypedDocBlockRule;

/**
 * Règle Larastan du projet (21.01) : chaque élément PHP porte un DocBlock décrit et
 * typé ; les classes anonymes et les fermetures en sont dispensées.
 *
 * @extends RuleTestCase<RequireTypedDocBlockRule>
 */
class RequireTypedDocBlockRuleTest extends RuleTestCase
{
	/**
	 * Règle testée, construite par le conteneur de PHPStan.
	 *
	 * @return Rule<\PhpParser\Node>
	 */
	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(RequireTypedDocBlockRule::class);
	}

	/**
	 * Configuration qui enregistre la règle comme service.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__.'/../PHPStan/rule.neon'];
	}

	/**
	 * Chaque manque de la fixture est signalé, à sa ligne ; les éléments complets,
	 * la classe anonyme et la fermeture ne le sont pas.
	 *
	 * @return void
	 */
	public function test_each_missing_or_incomplete_docblock_is_reported(): void
	{
		$this->analyse([__DIR__.'/../PHPStan/Fixtures/DocBlocks.php'], [
			['La classe Undocumented n\'a pas de DocBlock avec une description.', 8],
			['La constante LIMIT n\'a pas de DocBlock avec une description.', 10],
			['La propriété $count n\'a pas de DocBlock avec une description.', 12],
			['La méthode run() n\'a pas de DocBlock avec une description.', 14],
			['La propriété $count n\'a pas de balise @var typée.', 24],
			['La méthode run() : le paramètre $times n\'a pas de balise @param typée.', 29],
			['La méthode run() n\'a pas de balise @return typée.', 29],
			['La méthode withoutDescription() n\'a pas de DocBlock avec une description.', 38],
		]);
	}
}
