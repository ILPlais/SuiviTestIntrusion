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
];
