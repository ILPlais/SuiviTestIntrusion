<?php

namespace App\Enums;

/**
 * Statut d'un test d'intrusion (engagement-2, 11.08, 14.01).
 *
 * Les transitions sont libres entre les quatre statuts (décision du 2026-11-22) :
 * chacune est tracée dans l'historique `test_status_changes`, d'où se déduiront
 * aussi les dates réelles de début et de fin (11.08, Planning).
 */
enum TestStatus: string
{
	/** Planifié : statut de tout test à sa création. */
	case Planned = 'planned';

	/** En cours : les testeurs travaillent. */
	case InProgress = 'in_progress';

	/** Terminé : travaux achevés, rapport en préparation. */
	case Done = 'done';

	/** Validé : rapport accepté. */
	case Validated = 'validated';
}
