<?php

namespace App\Enums;

/**
 * Protocole de transport d'un service du périmètre (engagement-4, 14.01).
 */
enum ServiceProtocol: string
{
	/** TCP. */
	case Tcp = 'tcp';

	/** UDP. */
	case Udp = 'udp';
}
