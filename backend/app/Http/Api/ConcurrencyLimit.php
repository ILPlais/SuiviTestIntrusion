<?php

namespace App\Http\Api;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nombre de requêtes simultanées par utilisateur (11.03, socle-5) : un téléversement
 * à la fois (classe heavy) et deux exports en parallèle (classe export) par défaut.
 *
 * Usage : middleware « concurrent:<classe> ». Chaque requête prend un créneau, un
 * verrou du cache ; s'ils sont tous pris, la réponse est 429 « rate_limited ». Le
 * créneau est rendu quand la réponse est entièrement produite, même en cas d'erreur :
 * au retour du contrôleur, ou après l'envoi pour une réponse en flux (export).
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
	 * Réserve une place de la classe de concurrence pour la durée de la requête, ou refuse en 429.
	 *
	 * @param Request $request Requête entrante.
	 * @param Closure(Request): Response $next Suite de la chaîne de middlewares.
	 * @param string $class Classe de concurrence (heavy, export…).
	 * @return Response
	 */
	public function handle(Request $request, Closure $next, string $class): Response
	{
		$maximum = max(1, (int) config("api.rate_limits.{$class}.concurrent"));
		$requester = RateLimits::requesterKey($request);

		for ($slot = 0; $slot < $maximum; $slot++) {
			$lock = Cache::lock(self::slotKey($class, $requester, $slot), self::SLOT_SECONDS);

			if ($lock->get()) {
				return $this->runHoldingSlot($request, $next, $lock);
			}
		}

		RateLimits::reject($request, $class, [
			'X-RateLimit-Limit' => $maximum,
			'X-RateLimit-Remaining' => 0,
			'Retry-After' => self::RETRY_AFTER_SECONDS,
		]);
	}

	/**
	 * Clé du verrou d'un créneau, pour une classe et un demandeur.
	 *
	 * @param string $class Classe de concurrence.
	 * @param string $requester Identifiant du demandeur (utilisateur ou adresse).
	 * @param int $slot Numéro de la place.
	 * @return string
	 */
	public static function slotKey(string $class, string $requester, int $slot): string
	{
		return "api:concurrent:{$class}:{$requester}:{$slot}";
	}

	/**
	 * Exécute la requête en gardant le créneau tant que la réponse n'est pas produite.
	 *
	 * @param Request $request Requête qui occupe la place.
	 * @param Closure(Request): Response $next Suite de la chaîne de middlewares.
	 * @param Lock $lock Verrou de la place à libérer.
	 * @return Response
	 */
	private function runHoldingSlot(Request $request, Closure $next, Lock $lock): Response
	{
		try {
			$response = $next($request);
		} catch (\Throwable $e) {
			$lock->release();

			throw $e;
		}

		// Réponse en flux : le corps est produit après le retour du middleware ; le
		// créneau est rendu à la fin de la fonction qui l'écrit. Si elle ne s'exécute
		// jamais (client parti), la durée de vie du verrou sert de filet.
		if ($response instanceof StreamedResponse && ($callback = $response->getCallback()) !== null) {
			$response->setCallback(function () use ($callback, $lock) {
				try {
					return $callback();
				} finally {
					$lock->release();
				}
			});

			return $response;
		}

		// Fichier envoyé par le serveur web après la requête : créneau rendu à la fin.
		if ($response instanceof BinaryFileResponse) {
			app()->terminating(fn () => $lock->release());

			return $response;
		}

		$lock->release();

		return $response;
	}
}
