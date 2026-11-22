<?php

/**
 * Style du code PHP (21.01, « Style du code ») : PSR-12, indentation par tabulations,
 * forme des DocBlocks.
 *
 * Vérifier : composer lint ; corriger : composer lint:fix.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
	->in([__DIR__.'/app', __DIR__.'/bootstrap', __DIR__.'/config', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
	->exclude('cache')
	->name('*.php');

return (new Config())
	->setParallelConfig(ParallelConfigFactory::detect())
	->setIndent("\t")
	->setLineEnding("\n")
	->setRiskyAllowed(false)
	->setRules([
		'@PSR12' => true,
		// PSR-12 impose des espaces : la règle du projet les remplace par des tabulations.
		'indentation_type' => true,
		'array_syntax' => ['syntax' => 'short'],
		'no_unused_imports' => true,
		'ordered_imports' => ['sort_algorithm' => 'alpha'],
		'single_quote' => true,
		'trailing_comma_in_multiline' => true,
		// Forme des DocBlocks (21.01) ; leur présence et leurs types sont vérifiés
		// par la règle Larastan Tests\PHPStan\RequireTypedDocBlockRule.
		'no_blank_lines_after_phpdoc' => true,
		'no_empty_phpdoc' => true,
		'phpdoc_indent' => true,
		'phpdoc_no_access' => true,
		'phpdoc_no_package' => true,
		'phpdoc_order' => ['order' => ['param', 'return', 'throws']],
		'phpdoc_param_order' => true,
		'phpdoc_scalar' => true,
		// Description, puis @param et @return groupés, puis les autres balises.
		'phpdoc_separation' => ['groups' => [['param', 'return'], ['deprecated', 'link', 'see', 'since'], ['property', 'property-read', 'property-write']]],
		'phpdoc_single_line_var_spacing' => true,
		'phpdoc_trim' => true,
		'phpdoc_trim_consecutive_blank_line_separation' => true,
		'phpdoc_types' => true,
		'phpdoc_var_without_name' => true,
	])
	->setFinder($finder);
