// @vitest-environment node
// (argon2id vérifie ses tableaux d'octets avec instanceof : pas de contexte jsdom ici.)
/**
 * Valeur d'authentification (13.03) : sel déterministe, normalisation de
 * l'adresse et du mot de passe, dérivation Argon2id de 32 octets.
 */
import { readFile } from 'node:fs/promises'
import { createHash } from 'node:crypto'
import { createRequire } from 'node:module'
import { describe, expect, it } from 'vitest'
import setupWasm from 'argon2id/lib/setup.js'
import { authSalt, deriveAuth, normalizeEmail, type Argon2idFunction } from '../../src/crypto/authValue'

const INSTANCE = '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90'

/**
 * Charge le WebAssembly d'argon2id depuis node_modules (hors navigateur).
 *
 * @returns La fonction Argon2id.
 */
async function loadArgon2id(): Promise<Argon2idFunction> {
	const require = createRequire(import.meta.url)
	/**
	 * Chargeur d'un binaire WebAssembly d'argon2id.
	 *
	 * @param file Nom du binaire dans `argon2id/dist`.
	 * @returns La fonction d'instanciation attendue par argon2id.
	 */
	const load = (file: string) => async (imports: WebAssembly.Imports) =>
		WebAssembly.instantiate(await readFile(require.resolve(`argon2id/dist/${file}`)), imports)

	return (await setupWasm(load('simd.wasm'), load('no-simd.wasm'))) as Argon2idFunction
}

describe('valeur d\'authentification', () => {
	it('normalise l\'adresse : espaces de bord, casse, NFC', () => {
		expect(normalizeEmail('  Élise@Example.ORG ')).toBe(normalizeEmail('élise@example.org'))
		expect(normalizeEmail(' A@B.fr ')).toBe('a@b.fr')
	})

	it('calcule sel_auth comme l\'ADR le définit', async () => {
		const expected = createHash('sha256').update(`sti-auth-v1\0${INSTANCE}\0alice@example.org`).digest().subarray(0, 16)

		expect(Buffer.from(await authSalt(INSTANCE, ' Alice@Example.org'))).toEqual(expected)
	})

	it('change de sel avec l\'instance ou l\'adresse', async () => {
		const reference = await authSalt(INSTANCE, 'alice@example.org')

		expect(await authSalt('00000000-0000-4000-8000-000000000000', 'alice@example.org')).not.toEqual(reference)
		expect(await authSalt(INSTANCE, 'bob@example.org')).not.toEqual(reference)
	})

	it('dérive 32 octets, identiques pour un « é » composé ou décomposé', async () => {
		const argon2id = await loadArgon2id()

		const composed = await deriveAuth(argon2id, 'mot de passe très long é', INSTANCE, 'alice@example.org')
		const decomposed = await deriveAuth(argon2id, 'mot de passe très long é', INSTANCE, 'alice@example.org')

		expect(atob(composed)).toHaveLength(32)
		expect(decomposed).toBe(composed)
		expect(await deriveAuth(argon2id, 'autre mot de passe très long', INSTANCE, 'alice@example.org')).not.toBe(composed)
	}, 30_000)
})
