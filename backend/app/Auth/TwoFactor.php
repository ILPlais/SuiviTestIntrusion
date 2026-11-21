<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification TOTP (identite-3, 11.06), compatible Google
 * Authenticator : SHA-1, 6 chiffres, pas de 30 secondes (RFC 6238).
 *
 * - Un code accepté fixe le dernier pas de temps utilisé : il ne sert jamais
 *   deux fois, même dans sa fenêtre de validité (±1 pas, soit environ 90 s).
 * - Les codes de secours (10, à usage unique) ne sont gardés que par leur
 *   empreinte HMAC-SHA-256, clé de l'application : une copie de la base seule ne
 *   permet pas de les retrouver par force brute.
 * - Le pas de temps vient de l'horloge de Laravel, que les tests peuvent déplacer.
 */
final class TwoFactor
{
	/** Nombre de codes de secours émis à chaque fois. */
	public const RECOVERY_CODE_COUNT = 10;

	/** Tolérance de décalage d'horloge, en pas de 30 secondes. */
	private const WINDOW = 1;

	private readonly Google2FA $google2fa;

	public function __construct()
	{
		$this->google2fa = new Google2FA();
	}

	/**
	 * Nouveau secret TOTP (32 caractères Base32, 160 bits).
	 */
	public function generateSecret(): string
	{
		return $this->google2fa->generateSecretKey(32);
	}

	/**
	 * URL otpauth:// lue par les applications d'authentification (QR code).
	 */
	public function otpauthUrl(User $user, string $secret): string
	{
		return $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $secret);
	}

	/**
	 * Vérifie un code TOTP et le consomme : vrai une seule fois par code.
	 */
	public function verifyCode(User $user, string $code): bool
	{
		$secret = $user->two_factor_secret;
		$code = preg_replace('/\s+/', '', $code) ?? '';

		if ($secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
			return false;
		}

		$timestep = $this->google2fa->verifyKeyNewer($secret, $code, (int) $user->two_factor_last_timestep, self::WINDOW, $this->currentTimestep());

		if (! is_int($timestep)) {
			return false;
		}

		// Mise à jour conditionnelle : deux requêtes simultanées avec le même code
		// ne peuvent pas réussir toutes les deux.
		$accepted = User::query()
			->whereKey($user->id)
			->where(fn ($query) => $query->whereNull('two_factor_last_timestep')->orWhere('two_factor_last_timestep', '<', $timestep))
			->update(['two_factor_last_timestep' => $timestep]);

		$user->two_factor_last_timestep = $timestep;

		return $accepted === 1;
	}

	/**
	 * Émet de nouveaux codes de secours, remplace les anciens et les renvoie en clair
	 * (seule fois où ils sont lisibles).
	 *
	 * @return list<string>
	 */
	public function regenerateRecoveryCodes(User $user): array
	{
		$codes = [];
		for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
			$raw = Str::upper(Str::random(10));
			$codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
		}

		$user->forceFill(['two_factor_recovery_codes' => array_map(self::hashRecoveryCode(...), $codes)])->save();

		return $codes;
	}

	/**
	 * Consomme un code de secours : vrai une seule fois par code.
	 */
	public function useRecoveryCode(User $user, string $code): bool
	{
		$hash = self::hashRecoveryCode($code);

		return DB::transaction(function () use ($user, $hash): bool {
			$locked = User::query()->lockForUpdate()->findOrFail($user->id);
			$remaining = $locked->two_factor_recovery_codes ?? [];

			if (! in_array($hash, $remaining, true)) {
				return false;
			}

			$locked->forceFill(['two_factor_recovery_codes' => array_values(array_diff($remaining, [$hash]))])->save();

			return true;
		});
	}

	/**
	 * Empreinte d'un code de secours (HMAC, clé de l'application), insensible à la
	 * casse, aux tirets et aux espaces.
	 */
	private static function hashRecoveryCode(string $code): string
	{
		return hash_hmac('sha256', Str::upper((string) preg_replace('/[\s-]+/', '', $code)), (string) config('app.key'));
	}

	/**
	 * Pas de temps TOTP courant (secondes Unix / 30).
	 */
	private function currentTimestep(): int
	{
		return intdiv(now()->getTimestamp(), $this->google2fa->getKeyRegeneration());
	}
}
