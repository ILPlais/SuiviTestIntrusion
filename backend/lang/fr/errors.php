<?php

/**
 * Messages des erreurs de l'API, un par code stable de 11.03.
 *
 * Le code (clé) ne change jamais ; seul le message se traduit (13.07).
 */
return [
	'unauthenticated' => 'Vous n\'êtes pas connecté, ou votre session a expiré.',
	'forbidden' => 'Vous n\'avez pas le droit d\'effectuer cette action.',
	'not_found' => 'La ressource demandée est introuvable.',
	'conflict' => 'Cette fiche a été modifiée entre-temps. Rechargez-la avant de recommencer.',
	'payload_too_large' => 'La requête ou le fichier envoyé est trop volumineux.',
	'validation_failed' => 'La requête est invalide.',
	'rate_limited' => 'Trop de requêtes. Patientez avant de recommencer.',
	'internal_error' => 'Une erreur interne est survenue. Réessayez plus tard.',
];
