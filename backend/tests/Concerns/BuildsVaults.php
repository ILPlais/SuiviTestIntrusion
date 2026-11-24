<?php

namespace Tests\Concerns;

/**
 * Coffres de clés factices pour les tests de l'API (ged-chiffree-1).
 *
 * Le serveur ne déchiffre jamais les copies : il n'en contrôle que la forme
 * (bloc armuré du bon type, taille, empreinte hexadécimale). Des blocs
 * factices suffisent donc ; la cryptographie réelle est testée côté navigateur
 * (frontend/tests/unit/vault.test.ts).
 */
trait BuildsVaults
{
	/**
	 * Bloc OpenPGP armuré factice.
	 *
	 * @param string $type Type du bloc (`PUBLIC KEY` ou `PRIVATE KEY`).
	 * @return string
	 */
	protected function armored(string $type): string
	{
		return "-----BEGIN PGP {$type} BLOCK-----\n\n".base64_encode(random_bytes(96))."\n-----END PGP {$type} BLOCK-----";
	}

	/**
	 * Champs du coffre envoyés à l'activation, avec une empreinte unique.
	 *
	 * @return array{public_key: string, key_fingerprint: string, password_copy: string, recovery_copy: string}
	 */
	protected function vaultPayload(): array
	{
		return [
			'public_key' => $this->armored('PUBLIC KEY'),
			'key_fingerprint' => bin2hex(random_bytes(32)),
			'password_copy' => $this->armored('PRIVATE KEY'),
			'recovery_copy' => $this->armored('PRIVATE KEY'),
		];
	}
}
