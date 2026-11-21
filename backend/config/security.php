<?php

/**
 * En-têtes de sécurité HTTP (socle-6, 13.04, 13.06).
 *
 * La CSP de la SPA est celle de 13.04 ; le serveur web (docker/apache/suivi.conf)
 * pose la même sur les fichiers statiques, car la CSP d'un Worker est celle de la
 * réponse qui sert son script. Le test de fumée Docker vérifie qu'elles concordent.
 */
return [

	// CSP de la SPA (document, scripts, Worker crypto) : directives dans l'ordre de 13.04.
	'spa_policy' => [
		'default-src' => "'none'",
		'script-src' => "'self' 'wasm-unsafe-eval'",
		'style-src' => "'self' 'unsafe-inline'",
		'img-src' => "'self' blob:",
		'font-src' => "'self'",
		'connect-src' => "'self'",
		'worker-src' => "'self'",
		'manifest-src' => "'self'",
		'object-src' => "'none'",
		'frame-src' => "'none'",
		'base-uri' => "'none'",
		'form-action' => "'self'",
		'frame-ancestors' => "'none'",
		'require-trusted-types-for' => "'script'",
		'trusted-types' => 'sti-render sti-worker dompurify default',
		'report-uri' => '/api/v1/csp-reports',
		'report-to' => 'csp',
	],

	// CSP de toute réponse de l'API : rien n'y est jamais interprété, même chargé par <script>.
	'api_policy' => "default-src 'none'; frame-ancestors 'none'; sandbox",

	// HSTS envoyé par l'application elle-même, sans « preload » par défaut (13.06).
	'hsts' => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains'),

	// Aucune adresse transmise à un autre site (protège aussi les liens d'activation).
	'referrer_policy' => 'no-referrer',

	// Taille maximale d'un rapport de violation CSP, en octets.
	'report_max_bytes' => 64 * 1024,

];
