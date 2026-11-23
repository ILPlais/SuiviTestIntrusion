<?php

namespace App\Enums;

/**
 * Statut de remédiation d'une vulnérabilité (vulnerabilites-5, 11.09). Une
 * vulnérabilité est « ouverte » tant que son statut n'est pas Vérifié (11.12).
 */
enum RemediationStatus: string
{
	/** À faire. */
	case Todo = 'todo';

	/** En cours. */
	case InProgress = 'in_progress';

	/** Corrigé : déclaré par le client ou l'équipe. */
	case Fixed = 'fixed';

	/** Vérifié : retest par un testeur, daté, avec son résultat. */
	case Verified = 'verified';
}
