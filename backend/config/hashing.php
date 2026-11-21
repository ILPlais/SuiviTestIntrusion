<?php

/**
 * Hachage des comptes locaux (13.02) : pilote argon2id de Laravel.
 *
 * Le serveur hache la valeur d'authentification `auth` calculée par le navigateur
 * (13.03), jamais un mot de passe. Paramètres par défaut de Laravel : 64 Mio,
 * 4 passes, 1 fil (environ 0,25 s par vérification, borné par la classe auth).
 */
return [

	'driver' => env('HASH_DRIVER', 'argon2id'),

	'bcrypt' => [
		'rounds' => env('BCRYPT_ROUNDS', 12),
		'verify' => env('HASH_VERIFY', true),
		'limit' => env('BCRYPT_LIMIT', null),
	],

	'argon' => [
		'memory' => env('ARGON_MEMORY', 65536),
		'threads' => env('ARGON_THREADS', 1),
		'time' => env('ARGON_TIME', 4),
		'verify' => env('HASH_VERIFY', true),
	],

	'rehash_on_login' => true,

];
