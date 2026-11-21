<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\AuthenticationValue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Activation d'un compte par son lien à usage unique (identite-1).
 *
 * Le lien est créé par la commande d'installation (suivi:create-admin) ; sans
 * e-mail en V1, il est transmis par un autre canal (13.06). La SPA lit l'adresse
 * du compte, nécessaire au sel de `auth`, puis envoie `auth` calculé à partir du
 * mot de passe choisi, après contrôle de la politique dans le navigateur (13.03).
 */
class ActivationController extends Controller
{
	/**
	 * Adresse et nom du compte à activer ; 404 si le lien est inconnu, utilisé ou échu.
	 */
	public function show(string $token): JsonResponse
	{
		$user = $this->pendingUser($token);

		return new JsonResponse(['email' => $user->email, 'name' => $user->name]);
	}

	/**
	 * Enregistre la valeur d'authentification et invalide le lien.
	 */
	public function store(Request $request): Response
	{
		$data = $request->validate([
			'token' => ['required', 'string', 'size:64'],
			'auth' => ['required', 'string', AuthenticationValue::rule()],
		]);

		// Mise à jour conditionnelle en une requête : deux envois simultanés du même
		// lien ne peuvent pas réussir tous les deux (usage unique).
		$activated = $this->pendingQuery($data['token'])->update([
			'password' => Hash::make($data['auth']),
			'activation_token_hash' => null,
			'activation_expires_at' => null,
			'updated_at' => now(),
		]);

		abort_if($activated === 0, 404);

		return response()->noContent();
	}

	/**
	 * Compte en attente d'activation pour ce jeton, comparé par son empreinte.
	 */
	private function pendingUser(string $token): User
	{
		$user = $this->pendingQuery($token)->first();

		abort_if($user === null, 404);

		return $user;
	}

	/**
	 * Requête des comptes en attente d'activation pour ce jeton (lien valide).
	 *
	 * @return Builder<User>
	 */
	private function pendingQuery(string $token): Builder
	{
		return User::query()
			->where('activation_token_hash', hash('sha256', $token))
			->whereNull('password')
			->where('activation_expires_at', '>', now());
	}
}
