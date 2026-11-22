<?php

namespace App\Http\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Taille maximale d'un message de l'API (11.03, socle-5) : 2 Mo par défaut
 * (API_MAX_JSON_SIZE). Au-delà, 413 « payload_too_large », avant tout décodage.
 *
 * Tout corps est mesuré, quel que soit le Content-Type annoncé : un client ne
 * contourne pas la limite en déclarant text/plain. Seuls les envois multipart,
 * qui portent les fichiers de la GED, suivent leur propre plafond
 * (GED_MAX_FILE_SIZE, 13.06).
 */
final class LimitJsonSize
{
	/**
	 * Refuse en 413 un corps JSON plus gros que la limite, avant de le lire en entier.
	 *
	 * @param Request $request Requête entrante.
	 * @param Closure(Request): Response $next Suite de la chaîne de middlewares.
	 * @return Response
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$maximum = (int) config('api.max_json_size');

		if (! $this->isMultipart($request)) {
			// Refus immédiat si la longueur annoncée dépasse déjà la limite, sans lire le corps.
			$announced = $request->server('CONTENT_LENGTH');
			if (is_numeric($announced) && (int) $announced > $maximum) {
				abort(413);
			}

			// Sinon, mesure du corps réel : l'en-tête peut manquer (envoi par morceaux).
			if (strlen($request->getContent()) > $maximum) {
				abort(413);
			}
		}

		return $next($request);
	}

	/**
	 * Vrai pour un envoi multipart/form-data (fichiers de la GED).
	 *
	 * @param Request $request Requête entrante.
	 * @return bool
	 */
	private function isMultipart(Request $request): bool
	{
		return str_starts_with(strtolower((string) $request->headers->get('Content-Type')), 'multipart/form-data');
	}
}
