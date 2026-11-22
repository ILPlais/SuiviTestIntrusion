<?php

/**
 * Messages de validation en français (13.07).
 *
 * Seules les règles utilisées par l'API figurent ici ; chaque story qui
 * emploie une nouvelle règle ajoute son message. Une règle sans message
 * afficherait sa clé (« validation.xxx ») : les tests le détectent.
 */
return [
	'array' => 'Le champ :attribute doit être une liste.',
	'boolean' => 'Le champ :attribute doit être vrai ou faux.',
	'date' => 'Le champ :attribute n\'est pas une date valide.',
	'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
	'enum' => 'La valeur du champ :attribute n\'est pas autorisée.',
	'in' => 'La valeur du champ :attribute n\'est pas autorisée.',
	'integer' => 'Le champ :attribute doit être un nombre entier.',
	'max' => [
		'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
		'file' => 'Le fichier :attribute ne peut pas dépasser :max kilo-octets.',
		'numeric' => 'Le champ :attribute ne peut pas être supérieur à :max.',
		'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
	],
	'min' => [
		'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
		'file' => 'Le fichier :attribute doit faire au moins :min kilo-octets.',
		'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
		'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
	],
	'numeric' => 'Le champ :attribute doit être un nombre.',
	'required' => 'Le champ :attribute est obligatoire.',
	'string' => 'Le champ :attribute doit être une chaîne de caractères.',
	'unique' => 'La valeur du champ :attribute est déjà utilisée.',
	'uuid' => 'Le champ :attribute doit être un identifiant UUID valide.',

	// Messages propres aux paramètres des listes (ApiListQuery) et aux tests
	// d'intrusion (engagement-2).
	'custom' => [
		'tenant_id' => [
			// Même message pour une organisation inconnue et une organisation
			// étrangère : aucune énumération des autres clients (11.06).
			'unknown' => 'L\'organisation choisie est introuvable.',
		],
		'reference' => [
			'unique' => 'Cette référence est déjà employée par un autre test de l\'organisation.',
		],
		'planned_end' => [
			'before_start' => 'La fin prévue ne peut pas précéder le début prévu.',
		],
		'status' => [
			'unchanged' => 'Le test a déjà ce statut.',
		],
		'sort' => [
			'in' => 'Le tri n\'est pas autorisé sur ce champ.',
		],
		'filter' => [
			'array' => 'Un des filtres demandés n\'est pas autorisé.',
		],
		'cursor' => [
			'invalid' => 'Le curseur est invalide ou ne correspond pas au tri demandé.',
		],
	],

	// Noms des champs dans les messages.
	'attributes' => [
		'tenant_id' => 'organisation',
		'reference' => 'référence',
		'title' => 'titre',
		'exposure' => 'exposition',
		'status' => 'statut',
		'planned_start' => 'début prévu',
		'planned_end' => 'fin prévue',
		'comment' => 'commentaire',
	],
];
