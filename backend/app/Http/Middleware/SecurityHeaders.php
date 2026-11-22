<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de toutes les réponses de Laravel (socle-6, 13.04, 13.06).
 *
 * - Toutes : X-Content-Type-Options, Referrer-Policy, et HSTS sur une connexion
 *   HTTPS (derrière le proxy de confiance, voir TRUSTED_PROXIES).
 * - API (/api/…, /sanctum/…) : CSP qui interdit toute interprétation de la réponse.
 * - Autres (SPA) : CSP de 13.04 avec Trusted Types et point de rapports.
 * Une réponse qui porte déjà sa CSP (Swagger UI, à nonce) la garde.
 */
final class SecurityHeaders
{
	/**
	 * Pose les en-têtes de sécurité sur la réponse.
	 *
	 * @param Request $request Requête entrante.
	 * @param Closure(Request): Response $next Suite du traitement.
	 * @return Response Réponse complétée de ses en-têtes.
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$response = $next($request);
		$headers = $response->headers;

		$headers->set('X-Content-Type-Options', 'nosniff');
		$headers->set('Referrer-Policy', (string) config('security.referrer_policy'));

		if ($request->isSecure() && (string) config('security.hsts') !== '') {
			$headers->set('Strict-Transport-Security', (string) config('security.hsts'));
		}

		if (! $headers->has('Content-Security-Policy')) {
			if ($request->is('api', 'api/*', 'sanctum/*')) {
				$headers->set('Content-Security-Policy', (string) config('security.api_policy'));
			} else {
				$headers->set('Content-Security-Policy', self::spaPolicy());
				$headers->set('Reporting-Endpoints', 'csp="'.$request->getSchemeAndHttpHost().config('security.spa_policy.report-uri').'"');
			}
		}

		return $response;
	}

	/**
	 * CSP de la SPA, assemblée depuis config/security.php.
	 *
	 * @return string Valeur de l'en-tête Content-Security-Policy.
	 */
	public static function spaPolicy(): string
	{
		/** @var array<string, string> $directives */
		$directives = config('security.spa_policy');

		return implode('; ', array_map(fn (string $name, string $value) => "{$name} {$value}", array_keys($directives), $directives));
	}
}
