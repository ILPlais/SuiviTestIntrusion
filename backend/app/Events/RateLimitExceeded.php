<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une limite de débit de l'API a été atteinte (11.03, socle-5).
 *
 * Événement destiné au journal d'audit (identite-6) ; en attendant, il est écrit
 * dans le journal de l'application (LogRateLimitExceeded). Il ne porte jamais le
 * corps de la requête : pas de mot de passe, de jeton ni de secret.
 */
final class RateLimitExceeded
{
	use Dispatchable;

	/**
	 * @param string $limitClass Classe dépassée : standard, heavy, export ou auth.
	 * @param string $ipAddress Adresse du client (après les proxys de confiance).
	 * @param int|string|null $userId Identifiant de l'utilisateur, null si anonyme.
	 * @param string $method Verbe HTTP de la requête refusée.
	 * @param string $path Chemin de la requête, sans la chaîne de requête.
	 */
	public function __construct(
		public readonly string $limitClass,
		public readonly string $ipAddress,
		public readonly int|string|null $userId,
		public readonly string $method,
		public readonly string $path,
	) {
	}
}
