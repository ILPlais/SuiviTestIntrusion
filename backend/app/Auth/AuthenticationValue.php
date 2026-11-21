<?php

namespace App\Auth;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Normalizer;

/**
 * Valeur d'authentification `auth` (13.03) : 32 octets dérivés du mot de passe
 * par Argon2id dans le navigateur, transmis en Base64.
 */
final class AuthenticationValue
{
	/** Taille de la valeur dérivée, en octets. */
	public const BYTES = 32;

	/**
	 * Règle de validation : une chaîne Base64 qui décode exactement 32 octets.
	 * Un mot de passe envoyé par erreur à la place de `auth` est ainsi refusé.
	 */
	public static function rule(): Closure
	{
		return function (string $attribute, mixed $value, Closure $fail): void {
			$decoded = is_string($value) ? base64_decode($value, true) : false;

			// Forme canonique exigée : c'est la chaîne reçue qui est hachée.
			if ($decoded === false || strlen($decoded) !== self::BYTES || base64_encode($decoded) !== $value) {
				$fail(__('auth.invalid_value'));
			}
		};
	}

	/**
	 * Adresse e-mail telle qu'elle entre dans le sel de `auth` et dans la base :
	 * sans espaces de bord, en minuscules, normalisée NFC (13.03).
	 */
	public static function normalizeEmail(string $email): string
	{
		// Mêmes espaces de bordure que String.prototype.trim() du navigateur (espaces Unicode compris).
		$email = mb_strtolower((string) preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $email));
		$normalized = class_exists(Normalizer::class) ? Normalizer::normalize($email, Normalizer::FORM_C) : false;

		return is_string($normalized) ? $normalized : $email;
	}

	/**
	 * Hachage leurre, calculé une fois puis gardé en cache, vérifié quand le compte
	 * n'existe pas ou n'est pas activé : le temps de réponse ne trahit pas son absence.
	 */
	public static function decoyHash(): string
	{
		return Cache::rememberForever('auth:decoy-hash', fn () => Hash::make(base64_encode(random_bytes(self::BYTES))));
	}
}
