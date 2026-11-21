<?php

/**
 * Style du code PHP (21.01, « Style du code ») : PSR-12, indentation par tabulations.
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
	])
	->setFinder($finder);
