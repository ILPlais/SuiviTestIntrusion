<?php

namespace App\Enums;

/**
 * Point de départ d'un test d'intrusion (engagement-2, 11.05) : depuis le réseau
 * interne du client ou depuis Internet.
 */
enum TestExposure: string
{
	/** Test interne : depuis le réseau du client. */
	case Internal = 'internal';

	/** Test externe : depuis Internet. */
	case External = 'external';
}
