<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Documentation de l'API (socle-6, 11.03), réservée aux administrateurs d'instance.
 *
 * - /api/openapi.json : fichier OpenAPI 3.1 généré depuis les routes par Scramble ;
 * - /api/documentation : Swagger UI, servi par l'instance elle-même (fichiers du
 *   paquet swagger-ui-dist copiés dans public/swagger-ui par le build de la SPA),
 *   avec sa propre CSP à nonce ; désactivable (API_DOCUMENTATION_UI=false).
 */
class ApiDocumentationController extends Controller
{
	/**
	 * Fichier OpenAPI de l'API v1.
	 *
	 * @param Generator $generator Générateur OpenAPI de Scramble.
	 * @return JsonResponse Document OpenAPI 3.1.
	 */
	public function specification(Generator $generator): JsonResponse
	{
		Gate::authorize('view-api-documentation');

		return new JsonResponse($generator(Scramble::getGeneratorConfig('default')));
	}

	/**
	 * Page Swagger UI : scripts du build et une initialisation en ligne autorisée par nonce.
	 *
	 * @return Response Page HTML avec sa propre CSP.
	 */
	public function ui(): Response
	{
		abort_unless((bool) config('api.documentation.ui_enabled'), 404);
		Gate::authorize('view-api-documentation');

		$nonce = base64_encode(random_bytes(18));
		$title = e(config('app.name').' — API');
		$html = <<<HTML
			<!doctype html>
			<html lang="fr">
			<head>
				<meta charset="utf-8">
				<title>{$title}</title>
				<link rel="stylesheet" href="/swagger-ui/swagger-ui.css">
			</head>
			<body>
				<div id="swagger-ui"></div>
				<script src="/swagger-ui/swagger-ui-bundle.js" nonce="{$nonce}"></script>
				<script nonce="{$nonce}">
					window.ui = SwaggerUIBundle({ url: '/api/openapi.json', dom_id: '#swagger-ui', withCredentials: true });
				</script>
			</body>
			</html>
			HTML;

		// Swagger UI utilise des images en ligne (data:) et aucun script hors du nonce.
		$policy = "default-src 'none'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";

		return response($html, 200, [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Content-Security-Policy' => $policy,
			'Cache-Control' => 'no-store',
		]);
	}
}
