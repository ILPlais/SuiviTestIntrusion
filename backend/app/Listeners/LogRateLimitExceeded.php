<?php

namespace App\Listeners;

use App\Events\RateLimitExceeded;
use Illuminate\Support\Facades\Log;

/**
 * Écrit chaque dépassement de limite de débit dans le journal de l'application,
 * pour repérer un abus (11.03), en attendant le journal d'audit d'identite-6.
 */
final class LogRateLimitExceeded
{
	public function handle(RateLimitExceeded $event): void
	{
		Log::warning('Limite de débit de l\'API atteinte.', [
			'classe' => $event->limitClass,
			'ip' => $event->ipAddress,
			'utilisateur' => $event->userId,
			'requete' => $event->method.' /'.$event->path,
		]);
	}
}
