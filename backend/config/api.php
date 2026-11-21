<?php

/**
 * Réglages de l'API REST (11.03).
 */
return [
	/*
	| Mode de pagination par défaut des listes : « cursor » (recommandé, stable
	| quand des lignes sont ajoutées pendant le parcours) ou « page » (affiche
	| le total). Le client peut toujours demander le mode page avec ?page=.
	*/
	'pagination' => env('API_PAGINATION', 'cursor'),

	/** Taille de tranche par défaut et maximale (paramètre limit). */
	'limit' => [
		'default' => 50,
		'max' => 200,
	],

	/*
	| Limites de débit par classe d'appel (socle-5) : requêtes par minute et, pour
	| l'écriture lourde et l'export, requêtes simultanées par utilisateur. La classe
	| auth compte par adresse IP ; chaque nouveau blocage double l'attente (60 s,
	| 120 s, 240 s…) sans dépasser max_lockout_seconds.
	*/
	'rate_limits' => [
		'standard' => [
			'per_minute' => (int) env('API_RATE_STANDARD', 60),
		],
		'heavy' => [
			'per_minute' => (int) env('API_RATE_HEAVY', 20),
			'concurrent' => (int) env('API_CONCURRENT_HEAVY', 1),
		],
		'export' => [
			'per_minute' => (int) env('API_RATE_EXPORT', 5),
			'concurrent' => (int) env('API_CONCURRENT_EXPORT', 2),
		],
		'auth' => [
			'per_minute' => (int) env('API_RATE_AUTH', 10),
			'max_lockout_seconds' => (int) env('API_AUTH_MAX_LOCKOUT', 3600),
		],
	],

	/*
	| Taille maximale d'un corps JSON, en octets (2 Mo par défaut) ; au-delà, 413.
	| Les fichiers de la GED (multipart) suivent GED_MAX_FILE_SIZE.
	*/
	'max_json_size' => (int) env('API_MAX_JSON_SIZE', 2 * 1024 * 1024),

	/*
	| Documentation (socle-6) : Swagger UI désactivable en production ; le fichier
	| OpenAPI reste disponible pour les administrateurs.
	*/
	'documentation' => [
		'ui_enabled' => (bool) env('API_DOCUMENTATION_UI', true),
	],
];
