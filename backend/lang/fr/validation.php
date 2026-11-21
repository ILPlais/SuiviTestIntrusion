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
	'uuid' => 'Le champ :attribute doit être un identifiant UUID valide.',

	// Messages propres aux paramètres des listes (ApiListQuery).
	'custom' => [
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

	'attributes' => [],
];
