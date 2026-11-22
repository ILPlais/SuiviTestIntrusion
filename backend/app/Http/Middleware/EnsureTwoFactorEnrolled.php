<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Politique d'instance de double authentification (identite-3) : quand elle est
 * obligatoire (AUTH_TWO_FACTOR_REQUIRED), un compte sans second facteur confirmé
 * n'accède qu'à son enrôlement, à son profil et à la déconnexion ; toute route
 * métier marquée « two-factor » répond 403.
 */
final class EnsureTwoFactorEnrolled
{
	/**
	 * Refuse en 403 les routes métier tant que la politique d'instance impose un second facteur non enrôlé.
	 *
	 * @param Request $request Requête entrante.
	 * @param Closure(Request): Response $next Suite de la chaîne de middlewares.
	 * @return Response
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$user = $request->user();

		if ((bool) config('suivi.authentication.two_factor_required') && $user instanceof User && ! $user->hasTwoFactor()) {
			abort(403);
		}

		return $next($request);
	}
}
