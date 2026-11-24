<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte désactivé ou supprimé (identite-7, 11.06) : toute session encore
 * ouverte est fermée à sa requête suivante, et la requête reçoit 401.
 *
 * La désactivation efface déjà les sessions en base et les jetons API ; ce
 * contrôle couvre les autres pilotes de session (fichier, Redis…) et les
 * requêtes déjà en vol.
 */
final class EnsureAccountActive
{
	/**
	 * Refuse en 401 la requête d'un compte désactivé ou supprimé, et ferme sa session.
	 *
	 * @param Request $request Requête entrante.
	 * @param Closure(Request): Response $next Suite de la chaîne de middlewares.
	 * @return Response
	 *
	 * @throws AuthenticationException
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$user = $request->user();

		if ($user instanceof User && ! $user->canSignIn()) {
			Auth::guard('web')->logout();
			if ($request->hasSession()) {
				$request->session()->invalidate();
			}

			throw new AuthenticationException();
		}

		return $next($request);
	}
}
