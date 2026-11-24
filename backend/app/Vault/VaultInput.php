<?php

namespace App\Vault;

/**
 * Règles de validation des éléments du coffre envoyés par le navigateur
 * (ged-chiffree-1, 13.03).
 *
 * Le serveur ne déchiffre rien : il vérifie seulement la forme (bloc OpenPGP
 * armuré du bon type, taille bornée, empreinte v6 en hexadécimal) pour ne pas
 * stocker n'importe quoi à la place d'une clé.
 */
final class VaultInput
{
	/** Taille maximale d'un bloc armuré : une clé v6 Curve25519 en fait environ 1 Kio. */
	public const MAX_ARMORED = 16384;

	/**
	 * Règles d'une clé publique armurée.
	 *
	 * @return list<string>
	 */
	public static function publicKey(): array
	{
		return ['required', 'string', 'max:'.self::MAX_ARMORED, 'starts_with:-----BEGIN PGP PUBLIC KEY BLOCK-----'];
	}

	/**
	 * Règles d'une copie chiffrée de la clé privée, armurée.
	 *
	 * @return list<string>
	 */
	public static function privateKeyCopy(): array
	{
		return ['required', 'string', 'max:'.self::MAX_ARMORED, 'starts_with:-----BEGIN PGP PRIVATE KEY BLOCK-----'];
	}

	/**
	 * Règles de l'empreinte v6 : 64 caractères hexadécimaux minuscules.
	 *
	 * @return list<string>
	 */
	public static function fingerprint(): array
	{
		return ['required', 'string', 'regex:/^[0-9a-f]{64}$/'];
	}
}
