<?php

namespace App\Http\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Taille maximale d'un message JSON de l'API (11.03, socle-5) : 2 Mo par défaut
 * (API_MAX_JSON_SIZE). Au-delà, 413 « payload_too_large », avant tout décodage.
 *
 * Seuls les corps JSON sont concernés : les fichiers de la GED, envoyés en
 * multipart, suivent leur propre plafond (GED_MAX_FILE_SIZE, 13.06).
 */
final class LimitJsonSize
{
	/**
	 * @param Closure(Request): Response $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
		// La taille réelle du corps est mesurée : l'en-tête Content-Length peut manquer
		// (envoi par morceaux) ; le corps JSON serait de toute façon lu pour être décodé.
		if ($request->isJson() && strlen($request->getContent()) > (int) config('api.max_json_size')) {
			abort(413);
		}

		return $next($request);
	}
}
