<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditLog;
use App\Auth\AccountLockout;
use App\Auth\AuthenticationValue;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Vault\VaultInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Coffre de clés de l'utilisateur connecté (ged-chiffree-1, 13.02, 13.03).
 *
 * - Lecture : clé publique, empreinte et copie 1 (chiffrée par le mot de passe),
 *   livrées seulement après l'authentification complète, second facteur compris.
 *   La copie 2 n'est jamais servie ici : elle ne sert qu'au parcours « mot de
 *   passe oublié » (lien de réinitialisation, identite-7).
 * - Changement de mot de passe : le navigateur a rechiffré la copie 1 et calculé
 *   les valeurs d'authentification ; le serveur contrôle la valeur actuelle, puis
 *   remplace hachage et copie 1 en une écriture conditionnelle (verrou optimiste
 *   sur `key_version`). Ni la clé publique, ni la copie 2, ni aucun fichier ne
 *   changent. Les autres sessions tombent : leur empreinte de mot de passe ne
 *   correspond plus (AuthenticateSession de Sanctum) ; les jetons d'API sont révoqués.
 */
class VaultController extends Controller
{
	/**
	 * Reçoit le service de verrouillage des comptes et le journal d'audit.
	 *
	 * @param AccountLockout $lockout Verrouillage des comptes.
	 * @param AuditLog $audit Journal d'audit (identite-6).
	 */
	public function __construct(private readonly AccountLockout $lockout, private readonly AuditLog $audit)
	{
	}

	/**
	 * Coffre de l'utilisateur ; 404 pour un compte enrôlé avant le coffre.
	 *
	 * @param Request $request Requête courante.
	 * @return JsonResponse
	 */
	public function show(Request $request): JsonResponse
	{
		$user = $this->user($request);
		abort_unless($user->hasVault(), 404);

		return new JsonResponse([
			'public_key' => $user->public_key,
			'key_fingerprint' => $user->key_fingerprint,
			'password_copy' => $user->private_key_password_copy,
			'version' => $user->key_version,
		]);
	}

	/**
	 * Change le mot de passe : nouvelle valeur d'authentification et, si le
	 * compte a un coffre, nouvelle copie 1 de la clé privée.
	 *
	 * @param Request $request Requête portant `current_auth`, `auth`, `password_copy` et `version`.
	 * @return Response
	 *
	 * @throws ValidationException Si la valeur actuelle est fausse ou si rien ne change.
	 */
	public function changePassword(Request $request): Response
	{
		$user = $this->user($request);
		$data = $request->validate([
			'current_auth' => ['required', 'string', AuthenticationValue::rule()],
			'auth' => ['required', 'string', AuthenticationValue::rule()],
			// Sans coffre, il n'y a pas de copie à remplacer ; avec, elle est obligatoire.
			'password_copy' => $user->hasVault() ? VaultInput::privateKeyCopy() : ['prohibited'],
			'version' => $user->hasVault() ? ['required', 'integer', 'min:1'] : ['prohibited'],
		]);

		// Un mot de passe actuel faux compte comme un échec de connexion : sans quoi
		// cette route permettrait de deviner le mot de passe d'une session laissée ouverte.
		if ($this->lockout->blocks($user) || ! Hash::check($data['current_auth'], (string) $user->getAuthPassword())) {
			$this->lockout->recordFailure($user);

			throw ValidationException::withMessages(['current_auth' => [__('auth.current_password_invalid')]]);
		}

		if (Hash::check($data['auth'], (string) $user->getAuthPassword())) {
			throw ValidationException::withMessages(['auth' => [__('auth.password_unchanged')]]);
		}

		$changes = ['password' => Hash::make($data['auth']), 'updated_at' => now()];
		$query = User::query()->whereKey($user->id);
		if ($user->hasVault()) {
			// Verrou optimiste (13.02) : une rotation faite entre-temps sur un autre poste
			// a changé la version ; le navigateur doit relire la copie et recommencer.
			$query->where('key_version', (int) $data['version']);
			$changes['private_key_password_copy'] = $data['password_copy'];
			$changes['key_version'] = (int) $data['version'] + 1;
		}

		abort_if($query->update($changes) === 0, 409);

		$user->refresh();
		$user->tokens()->delete();
		$this->lockout->reset($user);
		$this->audit->record(AuditAction::PasswordChanged, $user, after: ['key_fingerprint' => $user->key_fingerprint], actor: $user);

		return response()->noContent();
	}

	/**
	 * Compte connecté.
	 *
	 * @param Request $request Requête courante.
	 * @return User
	 */
	private function user(Request $request): User
	{
		/** @var User $user */
		$user = $request->user();

		return $user;
	}
}
