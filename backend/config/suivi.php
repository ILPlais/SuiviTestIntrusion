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
		// Durée de validité d'un lien d'activation ou d'invitation (heures, 72 selon 11.06).
		'activation_hours' => (int) env('AUTH_ACTIVATION_HOURS', 72),
		// Durée de validité d'un lien de réinitialisation du mot de passe (heures, identite-7).
		'reset_hours' => (int) env('AUTH_RESET_HOURS', 24),
		// Double authentification imposée à tous les comptes (identite-3, politique d'instance).
		'two_factor_required' => (bool) env('AUTH_TWO_FACTOR_REQUIRED', false),
	],

	/*
	| Journal d'audit (identite-6, 11.02). Les événements plus anciens que la
	| rétention sont purgés chaque jour par `php artisan model:prune` (service
	| scheduler) ; 0 conserve tout. Une année par défaut : assez pour retracer
	| une campagne et ses retests.
	*/
	'audit' => [
		'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
	],

];
