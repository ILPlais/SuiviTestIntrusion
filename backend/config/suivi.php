<?php

/**
 * Réglages propres à SuiviTestIntrusion, hors API REST (config/api.php).
 */
return [

	/*
	| Authentification des comptes locaux (13.03, identite-1). Les valeurs par
	| défaut sont celles de l'ADR ; elles se règlent à l'installation.
	*/
	'authentication' => [
		// Échecs consécutifs à partir desquels chaque tentative impose un délai croissant.
		'slowdown_after' => (int) env('AUTH_SLOWDOWN_AFTER', 5),
		// Délai maximal entre deux tentatives pendant le ralentissement (secondes).
		'slowdown_max_seconds' => (int) env('AUTH_SLOWDOWN_MAX_SECONDS', 240),
		// Échecs consécutifs qui verrouillent le compte, et durée du verrouillage (minutes).
		'lockout_failures' => (int) env('AUTH_LOCKOUT_FAILURES', 10),
		'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
		// Durée de validité d'un lien d'activation (heures).
		'activation_hours' => (int) env('AUTH_ACTIVATION_HOURS', 24),
	],

];
