<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\AccountLockout;
use App\Auth\AuthenticationValue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Connexion et déconnexion des comptes locaux par session Sanctum (identite-1, 13.03).
 *
 * Le navigateur envoie l'adresse et `auth`, la valeur d'authentification dérivée
 * du mot de passe ; le serveur ne voit jamais le mot de passe. Un refus a toujours
 * le même message, que l'adresse existe ou non, que le compte soit verrouillé ou
 * que la valeur soit fausse : rien ne permet d'énumérer les comptes.
 */
class AuthController extends Controller
{
	public function __construct(private readonly AccountLockout $lockout)
	{
	}

	/**
	 * Ouvre une session si la valeur d'authentification est correcte.
	 */
	public function login(Request $request): JsonResponse
	{
		$data = $request->validate([
			'email' => ['required', 'string', 'max:255'],
			'auth' => ['required', 'string', AuthenticationValue::rule()],
		]);

		// Connexion réservée à la SPA (session Sanctum) : une requête sans session n'en ouvre pas.
		abort_unless($request->hasSession(), 403);

		$user = User::query()->where('email', AuthenticationValue::normalizeEmail($data['email']))->first();

		if ($user === null || ! $this->verify($user, $data['auth'])) {
			throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
		}

		Auth::guard('web')->login($user);
		$request->session()->regenerate();

		return new JsonResponse($user->toPublicArray());
	}

	/**
	 * Ferme la session et renouvelle le jeton CSRF.
	 */
	public function logout(Request $request): Response
	{
		Auth::guard('web')->logout();
		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return response()->noContent();
	}

	/**
	 * Compte connecté.
	 */
	public function me(Request $request): JsonResponse
	{
		/** @var User $user */
		$user = $request->user();

		return new JsonResponse($user->toPublicArray());
	}

	/**
	 * Vérifie la valeur d'authentification en tenant compte du verrouillage.
	 *
	 * Sans compte actif, un hachage leurre est tout de même vérifié, pour qu'une
	 * adresse inconnue ne réponde pas plus vite qu'une adresse existante.
	 */
	private function verify(?User $user, string $auth): bool
	{
		$hash = $user?->getAuthPassword();

		if ($user === null || $hash === null) {
			Hash::check($auth, AuthenticationValue::decoyHash());

			return false;
		}

		if ($this->lockout->blocks($user)) {
			// Même coût qu'une vraie vérification : un compte verrouillé ne se repère pas au chronomètre.
			Hash::check($auth, AuthenticationValue::decoyHash());

			return false;
		}

		if (! Hash::check($auth, $hash)) {
			$this->lockout->recordFailure($user);

			return false;
		}

		$this->lockout->reset($user);

		return true;
	}
}
