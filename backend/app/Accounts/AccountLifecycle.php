<?php

namespace App\Accounts;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opérations partagées du cycle de vie des comptes (identite-7, 11.06, 13.03).
 *
 * Les liens d'activation et de réinitialisation sont des jetons aléatoires de
 * 64 caractères dont le serveur ne garde que l'empreinte SHA-256 : un lien
 * n'est affiché qu'une fois, à son émission, chez l'administrateur ou
 * l'invitant (aucun e-mail en V1, 13.06). En émettre un nouveau remplace le
 * précédent, qui cesse aussitôt de valoir.
 */
final class AccountLifecycle
{
	/**
	 * Émet (ou remplace) le lien d'activation d'un compte jamais activé et
	 * aligne l'échéance de ses invitations en attente.
	 *
	 * @param User $user Compte en attente d'activation.
	 * @return array{link: string, expires_at: \Illuminate\Support\Carbon}
	 */
	public function issueActivationLink(User $user): array
	{
		$token = Str::random(64);
		$expiresAt = now()->addHours((int) config('suivi.authentication.activation_hours'));

		$user->forceFill(['activation_token_hash' => hash('sha256', $token), 'activation_expires_at' => $expiresAt])->save();
		$user->invitations()->pending()->update(['expires_at' => $expiresAt]);

		return ['link' => $this->url("/activation/{$token}"), 'expires_at' => $expiresAt];
	}

	/**
	 * Émet (ou remplace) le lien de réinitialisation du mot de passe d'un compte actif.
	 *
	 * @param User $user Compte actif, qui a un coffre de clés.
	 * @return array{link: string, expires_at: \Illuminate\Support\Carbon}
	 */
	public function issueResetLink(User $user): array
	{
		$token = Str::random(64);
		$expiresAt = now()->addHours((int) config('suivi.authentication.reset_hours'));

		$user->forceFill(['reset_token_hash' => hash('sha256', $token), 'reset_expires_at' => $expiresAt])->save();

		return ['link' => $this->url("/reinitialisation/{$token}"), 'expires_at' => $expiresAt];
	}

	/**
	 * Révoque les accès ouverts du compte : jetons API et sessions enregistrées
	 * en base (pilote « database »). Les autres pilotes sont couverts par le
	 * middleware EnsureAccountActive, ou par le contrôle du hachage de Sanctum
	 * après un changement de mot de passe.
	 *
	 * @param User $user Compte dont les accès sont révoqués.
	 * @return void
	 */
	public function revokeAccess(User $user): void
	{
		$user->tokens()->delete();

		if (config('session.driver') === 'database') {
			DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
		}
	}

	/**
	 * Nombre d'administrateurs d'instance actifs, pour protéger le dernier (11.06).
	 *
	 * @return int
	 */
	public function activeAdminCount(): int
	{
		return User::query()
			->where('is_instance_admin', true)
			->whereNotNull('password')
			->whereNull('deactivated_at')
			->whereNull('pseudonymized_at')
			->count();
	}

	/**
	 * Applique les invitations en attente d'un compte qui vient d'être activé :
	 * rattachements aux organisations, rôle d'administrateur d'instance.
	 *
	 * @param User $user Compte activé.
	 * @return void
	 */
	public function acceptPendingInvitations(User $user): void
	{
		foreach ($user->invitations()->pending()->get() as $invitation) {
			$this->apply($user, $invitation);
		}
	}

	/**
	 * Applique une invitation au compte et la marque acceptée.
	 *
	 * @param User $user Compte invité.
	 * @param Invitation $invitation Invitation à appliquer.
	 * @return void
	 */
	public function apply(User $user, Invitation $invitation): void
	{
		if ($invitation->tenant_id !== null && $invitation->role !== null) {
			$user->tenants()->syncWithoutDetaching([$invitation->tenant_id => ['role' => $invitation->role->value]]);
		}
		if ($invitation->is_instance_admin && ! $user->is_instance_admin) {
			$user->forceFill(['is_instance_admin' => true])->save();
		}

		$invitation->forceFill(['accepted_at' => now()])->save();
	}

	/**
	 * Adresse absolue d'une page de la SPA.
	 *
	 * @param string $path Chemin de la page, commençant par « / ».
	 * @return string
	 */
	private function url(string $path): string
	{
		return rtrim((string) config('app.url'), '/').$path;
	}
}
