<?php

/**
 * Messages de l'authentification (identite-1).
 *
 * Le refus de connexion est volontairement unique : il ne dit pas si l'adresse
 * existe, si la valeur est fausse ou si le compte est verrouillé (13.03).
 */
return [
	'failed' => 'Connexion refusée : identifiants incorrects, ou trop de tentatives récentes. Réessayez dans quelques minutes.',
	'invalid_value' => 'La valeur d\'authentification est invalide.',
	'two_factor_invalid' => 'Code incorrect, expiré ou déjà utilisé.',
	// Changement de mot de passe (ged-chiffree-1).
	'current_password_invalid' => 'Mot de passe actuel incorrect, ou trop de tentatives récentes.',
	'password_unchanged' => 'Le nouveau mot de passe doit être différent de l\'actuel.',
	// Cycle de vie des comptes (identite-7).
	'account_disabled' => 'Ce compte est désactivé. Adressez-vous à un administrateur.',
	'reset_session_expired' => 'Le parcours de réinitialisation a expiré : rouvrez le lien.',
	'reset_fingerprint_mismatch' => 'Cette clé ne correspond pas à celle du compte.',
];
