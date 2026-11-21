<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Point d'entrée de la SPA compilée (socle-2).
 *
 * En production, la SPA et l'API partagent l'origine (13.06) : le build de frontend/
 * est copié dans public/, et toute URL hors de /api reçoit index.html, pour que le
 * routeur de la SPA gère les liens profonds. Les ressources compilées (public/assets)
 * sont servies directement par le serveur web, sans passer par Laravel.
 */
class SpaController extends Controller
{
	/**
	 * Renvoie index.html, ou une erreur 404 si la SPA n'a pas été compilée
	 * (en développement, elle est servie par Vite).
	 */
	public function __invoke(): BinaryFileResponse
	{
		$index = config('app.spa_index');

		abort_unless(is_string($index) && is_file($index), 404);

		// index.html est relu à chaque visite : il désigne les ressources de la
		// dernière version, dont les noms portent une empreinte.
		return response()->file($index, [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'no-cache',
		]);
	}
}
