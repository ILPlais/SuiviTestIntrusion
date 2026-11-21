<?php

namespace App\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Verrouillage des comptes locaux (13.03, identite-1).
 *
 * Complète la limite de débit de la classe auth (par adresse IP) par un compteur
 * d'échecs consécutifs propre au compte : à partir de `slowdown_after` échecs,
 * chaque tentative doit attendre un délai qui double (15 s, 30 s, 60 s…) ; à
 * `lockout_failures` échecs, le compte est verrouillé `lockout_minutes` minutes.
 * Une connexion réussie remet le compteur à zéro.
 */
final class AccountLockout
{
	/** Délai de la première tentative ralentie, en secondes. */
	private const FIRST_DELAY_SECONDS = 15;

	/**
	 * Vrai si le compte ne peut pas tenter de connexion maintenant (verrouillé, ou
	 * délai de ralentissement non écoulé). Une telle tentative n'est pas comptée.
	 */
	public function blocks(User $user): bool
	{
		if ($user->locked_until !== null) {
			if ($user->locked_until->isFuture()) {
				return true;
			}

			// Verrouillage échu : le compte repart de zéro.
			$this->reset($user);

			return false;
		}

		$waitUntil = $this->nextAttemptAt($user);

		return $waitUntil !== null && $waitUntil->isFuture();
	}

	/**
	 * Compte un échec et verrouille le compte au seuil.
	 */
	public function recordFailure(User $user): void
	{
		$user->failed_login_count++;
		$user->last_failed_login_at = now();

		if ($user->failed_login_count >= (int) config('suivi.authentication.lockout_failures')) {
			$user->locked_until = now()->addMinutes((int) config('suivi.authentication.lockout_minutes'));

			// Journal en attendant l'audit (identite-6) et la notification dans l'application (exploitation-1).
			Log::warning('Compte local verrouillé après des échecs de connexion répétés.', ['utilisateur' => $user->id]);
		}

		$user->save();
	}

	/**
	 * Remet le compteur à zéro (connexion réussie, verrouillage échu).
	 */
	public function reset(User $user): void
	{
		if ($user->failed_login_count === 0 && $user->locked_until === null) {
			return;
		}

		$user->forceFill([
			'failed_login_count' => 0,
			'last_failed_login_at' => null,
			'locked_until' => null,
		])->save();
	}

	/**
	 * Date à partir de laquelle une nouvelle tentative est permise pendant le
	 * ralentissement, ou null si aucun délai ne s'applique.
	 */
	private function nextAttemptAt(User $user): ?CarbonInterface
	{
		$threshold = (int) config('suivi.authentication.slowdown_after');

		if ($user->failed_login_count < $threshold || $user->last_failed_login_at === null) {
			return null;
		}

		$doublings = min($user->failed_login_count - $threshold, 16);
		$delay = min(self::FIRST_DELAY_SECONDS * (2 ** $doublings), (int) config('suivi.authentication.slowdown_max_seconds'));

		return $user->last_failed_login_at->copy()->addSeconds($delay);
	}
}
