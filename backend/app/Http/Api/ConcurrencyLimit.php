<?php

namespace App\Http\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nombre de requêtes simultanées par utilisateur (11.03, socle-5) : un téléversement
 * à la fois (classe heavy) et deux exports en parallèle (classe export) par défaut.
 *
 * Usage : middleware « concurrent:<classe> ». Chaque requête prend un créneau, un
 * verrou du cache ; s'ils sont tous pris, la réponse est 429 « rate_limited ». Le
 * créneau est rendu à la fin de la requête, même en cas d'erreur.
 */
final class ConcurrencyLimit
{
	/**
	 * Durée de vie d'un créneau : il est libéré même si le processus PHP meurt en
	 * cours de requête. Doit dépasser la durée d'un envoi de 100 Mio sur une liaison lente.
	 */
	private const SLOT_SECONDS = 900;

	/** Délai conseillé au client avant de réessayer (Retry-After). */
	private const RETRY_AFTER_SECONDS = 5;

	/**
	 * @param Closure(Request): Response $next
	 */
	public function handle(Request $request, Closure $next, string $class): Response
	{
		$maximum = max(1, (int) config("api.rate_limits.{$class}.concurrent"));
		$requester = RateLimits::requesterKey($request);

		for ($slot = 0; $slot < $maximum; $slot++) {
			$lock = Cache::lock(self::slotKey($class, $requester, $slot), self::SLOT_SECONDS);

			if ($lock->get()) {
				try {
					return $next($request);
				} finally {
					$lock->release();
				}
			}
		}

		RateLimits::reject($request, $class, ['Retry-After' => self::RETRY_AFTER_SECONDS]);
	}

	/**
	 * Clé du verrou d'un créneau, pour une classe et un demandeur.
	 */
	public static function slotKey(string $class, string $requester, int $slot): string
	{
		return "api:concurrent:{$class}:{$requester}:{$slot}";
	}
}
