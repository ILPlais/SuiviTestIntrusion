<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\AccountLockout;
use App\Auth\AuthenticationValue;
use App\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
	/** Clé de session de la connexion en attente du second facteur. */
	private const PENDING_KEY = 'auth.two_factor_pending';

	/** Durée de validité du premier facteur en attente du code, en secondes. */
	private const PENDING_SECONDS = 300;

	/**
	 * Reçoit le service de verrouillage des comptes et le service TOTP.
	 *
	 * @param AccountLockout $lockout Verrouillage des comptes.
	 * @param TwoFactor $twoFactor Service TOTP.
	 */
	public function __construct(private readonly AccountLockout $lockout, private readonly TwoFactor $twoFactor)
	{
	}

	/**
	 * Ouvre une session si la valeur d'authentification est correcte.
	 *
	 * @param Request $request Requête de connexion.
	 * @return JsonResponse
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

		// Second facteur actif : le premier facteur est mémorisé dans la session (renouvelée
		// contre la fixation), sans ouvrir de session authentifiée (identite-3).
		if ($user->hasTwoFactor()) {
			Auth::guard('web')->logout();
			$request->session()->regenerate();
			$request->session()->put(self::PENDING_KEY, ['user_id' => $user->id, 'expires_at' => now()->getTimestamp() + self::PENDING_SECONDS]);

			return new JsonResponse(['two_factor_required' => true]);
		}

		return $this->openSession($request, $user);
	}

	/**
	 * Second temps de la connexion : code TOTP ou code de secours (identite-3).
	 * Un code faux compte comme un échec de connexion (verrouillage de 13.03).
	 *
	 * @param Request $request Requête portant le code TOTP ou de secours.
	 * @return JsonResponse
	 */
	public function twoFactorChallenge(Request $request): JsonResponse
	{
		$data = $request->validate([
			'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:16'],
			'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
		]);
		abort_unless($request->hasSession(), 403);

		$pending = $request->session()->get(self::PENDING_KEY);
		$user = is_array($pending) && ($pending['expires_at'] ?? 0) >= now()->getTimestamp()
			? User::query()->find($pending['user_id'] ?? null)
			: null;

		if ($user === null || ! $user->hasTwoFactor() || $this->lockout->blocks($user)) {
			throw ValidationException::withMessages(['code' => [__('auth.two_factor_invalid')]]);
		}

		$valid = isset($data['code'])
			? $this->twoFactor->verifyCode($user, $data['code'])
			: $this->twoFactor->useRecoveryCode($user, (string) $data['recovery_code']);

		if (! $valid) {
			$this->lockout->recordFailure($user);
			Log::warning('Code de double authentification refusé à la connexion.', ['utilisateur' => $user->id]);

			throw ValidationException::withMessages(['code' => [__('auth.two_factor_invalid')]]);
		}

		if (! isset($data['code'])) {
			Log::notice('Connexion avec un code de secours.', ['utilisateur' => $user->id]);
		}

		$request->session()->forget(self::PENDING_KEY);
		$this->lockout->reset($user);

		return $this->openSession($request, $user);
	}

	/**
	 * Ferme la session et renouvelle le jeton CSRF.
	 *
	 * @param Request $request Requête de déconnexion.
	 * @return Response
	 */
	public function logout(Request $request): Response
	{
		Auth::guard('web')->logout();
		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return response()->noContent();
	}

	/**
	 * Ouvre la session authentifiée et renouvelle son identifiant (fixation de session).
	 *
	 * @param Request $request Requête courante.
	 * @param User $user Compte authentifié.
	 * @return JsonResponse
	 */
	private function openSession(Request $request, User $user): JsonResponse
	{
		Auth::guard('web')->login($user);
		$request->session()->regenerate();

		return new JsonResponse($user->toPublicArray());
	}

	/**
	 * Compte connecté.
	 *
	 * @param Request $request Requête courante.
	 * @return JsonResponse
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
	 *
	 * @param ?User $user Compte trouvé, null si l'adresse est inconnue.
	 * @param string $auth Valeur d'authentification reçue.
	 * @return bool
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

		// Avec un second facteur, seule sa réussite remet le compteur à zéro : sinon,
		// se reconnecter avec le mot de passe effacerait les échecs de code (force brute).
		if (! $user->hasTwoFactor()) {
			$this->lockout->reset($user);
		}

		return true;
	}
}
