<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\AccountLifecycle;
use App\Audit\AuditLog;
use App\Auth\AccountLockout;
use App\Auth\AuthenticationValue;
use App\Auth\TwoFactor;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Vault\VaultInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié (identite-7, parcours de 13.03).
 *
 * Un administrateur émet un lien à usage unique, valable 24 heures, qu'il
 * transmet au titulaire (aucun e-mail en V1). Le lien seul ne donne que la
 * copie 2 de la clé privée, chiffrée par la clé de récupération : inutilisable
 * sans elle. Si le compte a un second facteur, il est exigé avant cette copie.
 *
 * Le navigateur déverrouille la copie 2 avec la clé de récupération, puis
 * envoie un nouvel `auth`, une nouvelle copie 1 (nouveau mot de passe) et une
 * nouvelle copie 2 (nouvelle clé de récupération). La paire de clés ne change
 * pas : l'empreinte envoyée doit être celle du compte. Le remplacement est
 * atomique ; toutes les sessions et tous les jetons du compte sont révoqués.
 */
class PasswordResetController extends Controller
{
	/** Clé de session : copie 2 livrée pour ce compte et ce lien, second facteur vérifié. */
	private const SESSION_KEY = 'auth.password_reset';

	/** Durée laissée pour saisir la clé de récupération et le nouveau mot de passe, en secondes. */
	private const SESSION_SECONDS = 900;

	/**
	 * Reçoit les services d'authentification, le cycle de vie des comptes et le journal d'audit.
	 *
	 * @param AccountLockout $lockout Verrouillage des comptes (13.03).
	 * @param TwoFactor $twoFactor Service TOTP (identite-3).
	 * @param AccountLifecycle $accounts Révocation des accès.
	 * @param AuditLog $audit Journal d'audit (identite-6).
	 */
	public function __construct(
		private readonly AccountLockout $lockout,
		private readonly TwoFactor $twoFactor,
		private readonly AccountLifecycle $accounts,
		private readonly AuditLog $audit,
	) {
	}

	/**
	 * Adresse du compte (sel de `auth`) et besoin d'un second facteur ; 404 si le lien ne vaut pas.
	 *
	 * @param string $token Jeton du lien de réinitialisation.
	 * @return JsonResponse
	 */
	public function show(string $token): JsonResponse
	{
		$user = $this->userFor($token);

		return new JsonResponse(['email' => $user->email, 'name' => $user->name, 'two_factor_required' => $user->hasTwoFactor()]);
	}

	/**
	 * Livre la copie 2 de la clé privée, après le second facteur s'il est actif.
	 * Un code faux compte comme un échec de connexion (verrouillage de 13.03).
	 *
	 * @param Request $request Requête portant, au besoin, le code TOTP.
	 * @param string $token Jeton du lien de réinitialisation.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function open(Request $request, string $token): JsonResponse
	{
		$data = $request->validate(['code' => ['nullable', 'string', 'max:16']]);
		abort_unless($request->hasSession(), 403);
		$user = $this->userFor($token);

		if ($user->hasTwoFactor()) {
			if ($this->lockout->blocks($user) || ! $this->twoFactor->verifyCode($user, (string) ($data['code'] ?? ''))) {
				$this->lockout->recordFailure($user);
				Log::warning('Code de double authentification refusé à la réinitialisation.', ['utilisateur' => $user->id]);
				$this->audit->record(AuditAction::TwoFactorChallengeFailed, $user);

				throw ValidationException::withMessages(['code' => [__('auth.two_factor_invalid')]]);
			}
		}

		$request->session()->put(self::SESSION_KEY, [
			'user_id' => $user->id,
			'token_hash' => hash('sha256', $token),
			'expires_at' => now()->getTimestamp() + self::SESSION_SECONDS,
		]);

		return new JsonResponse([
			'recovery_copy' => $user->private_key_recovery_copy,
			'key_fingerprint' => $user->key_fingerprint,
			'version' => $user->key_version,
		]);
	}

	/**
	 * Remplace `auth` et les deux copies de la clé privée, puis invalide le lien.
	 *
	 * @param Request $request Requête portant le nouvel `auth` et les nouvelles copies.
	 * @return Response
	 *
	 * @throws ValidationException
	 */
	public function store(Request $request): Response
	{
		$data = $request->validate([
			'token' => ['required', 'string', 'size:64'],
			'auth' => ['required', 'string', AuthenticationValue::rule()],
			'key_fingerprint' => VaultInput::fingerprint(),
			'password_copy' => VaultInput::privateKeyCopy(),
			'recovery_copy' => VaultInput::privateKeyCopy(),
			'version' => ['required', 'integer', 'min:1'],
		]);
		abort_unless($request->hasSession(), 403);
		$user = $this->userFor($data['token']);

		// La copie 2 doit avoir été livrée à cette session, pour ce lien, il y a peu :
		// sans quoi le second facteur aurait été contourné.
		$opened = $request->session()->get(self::SESSION_KEY);
		if (! is_array($opened) || ($opened['user_id'] ?? null) !== $user->id
			|| ! hash_equals((string) ($opened['token_hash'] ?? ''), hash('sha256', $data['token']))
			|| ($opened['expires_at'] ?? 0) < now()->getTimestamp()) {
			throw ValidationException::withMessages(['token' => [__('auth.reset_session_expired')]]);
		}

		// Récupération, pas remplacement du coffre : la paire de clés reste la même.
		if (! hash_equals((string) $user->key_fingerprint, $data['key_fingerprint'])) {
			throw ValidationException::withMessages(['key_fingerprint' => [__('auth.reset_fingerprint_mismatch')]]);
		}

		// Mise à jour conditionnelle : lien encore valide et copie inchangée depuis sa lecture.
		$updated = $this->validQuery($data['token'])
			->where('key_version', (int) $data['version'])
			->update([
				'password' => Hash::make($data['auth']),
				'private_key_password_copy' => $data['password_copy'],
				'private_key_recovery_copy' => $data['recovery_copy'],
				'key_version' => (int) $data['version'] + 1,
				'reset_token_hash' => null,
				'reset_expires_at' => null,
				'updated_at' => now(),
			]);
		abort_if($updated === 0, 409);

		$request->session()->forget(self::SESSION_KEY);
		$user->refresh();
		$this->accounts->revokeAccess($user);
		$this->lockout->reset($user);
		$this->audit->record(AuditAction::PasswordReset, $user, after: ['key_fingerprint' => $user->key_fingerprint], actor: $user);

		return response()->noContent();
	}

	/**
	 * Compte dont le lien de réinitialisation vaut encore ; 404 sinon.
	 *
	 * @param string $token Jeton du lien, en clair.
	 * @return User
	 */
	private function userFor(string $token): User
	{
		$user = $this->validQuery($token)->first();
		abort_if($user === null, 404);

		return $user;
	}

	/**
	 * Requête du compte actif dont le lien de réinitialisation vaut encore.
	 *
	 * @param string $token Jeton du lien, en clair.
	 * @return Builder<User>
	 */
	private function validQuery(string $token): Builder
	{
		return User::query()
			->where('reset_token_hash', hash('sha256', $token))
			->where('reset_expires_at', '>', now())
			->whereNotNull('password')
			->whereNotNull('key_fingerprint')
			->whereNull('deactivated_at')
			->whereNull('pseudonymized_at');
	}
}
