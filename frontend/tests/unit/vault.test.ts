// @vitest-environment node
// (openpgp.js vérifie ses tableaux d'octets avec instanceof : pas de contexte jsdom ici.)
/**
 * Coffre de clés (13.02, 13.03, ged-chiffree-1) : deux copies de la même clé
 * privée, rotation du mot de passe sans changer l'empreinte, récupération par
 * la clé de récupération, plafond de lecture du S2K.
 *
 * Les dérivations utilisent des paramètres Argon2id réduits (1 Mio) pour la
 * vitesse ; un test vérifie à part que l'application garde ceux de 13.02.
 */
import * as openpgp from 'openpgp'
import { describe, expect, it } from 'vitest'
import { formatRecoveryKey, generateRecoveryKey } from '../../src/crypto/recoveryKey'
import {
	createVault,
	recoverVault,
	rotatePassword,
	S2K_PARAMS,
	unlockWithPassword,
	VaultError,
	type S2kParams,
} from '../../src/crypto/vault'

/** Paramètres réduits pour les tests (m = 2^10 Kio). */
const FAST: S2kParams = { passes: 1, parallelism: 4, memoryExponent: 10 }

const PASSWORD = 'cheval correct pile agrafe'
const NEW_PASSWORD = 'nouveau mot de passe très long'

/**
 * Vérifie qu'une promesse échoue avec une VaultError du motif attendu.
 *
 * @param promise Opération à contrôler.
 * @param reason Motif attendu.
 */
async function expectVaultError(promise: Promise<unknown>, reason: VaultError['reason']): Promise<void> {
	await expect(promise).rejects.toBeInstanceOf(VaultError)
	await expect(promise).rejects.toMatchObject({ reason })
}

/**
 * Lit les paramètres S2K d'une copie armurée.
 *
 * @param armored Copie chiffrée.
 * @returns Le type et les paramètres du S2K de la clé primaire.
 */
async function s2kOf(armored: string): Promise<{ type: string, t: number, encodedM: number, salt: Uint8Array }> {
	const key = await openpgp.readPrivateKey({ armoredKey: armored })

	return (key.keyPacket as unknown as { s2k: { type: string, t: number, encodedM: number, salt: Uint8Array } }).s2k
}

describe('coffre de clés', () => {
	it('garde les paramètres Argon2id de 13.02 dans l\'application', () => {
		expect(S2K_PARAMS).toEqual({ passes: 2, parallelism: 4, memoryExponent: 17 })
	})

	it('génère une paire v6 et deux copies de même empreinte, protégées par Argon2id et AEAD', async () => {
		const recoveryKey = generateRecoveryKey()
		const vault = await createVault(PASSWORD, recoveryKey, FAST)

		expect(vault.fingerprint).toMatch(/^[0-9a-f]{64}$/)
		const publicKey = await openpgp.readKey({ armoredKey: vault.publicKey })
		expect(publicKey.isPrivate()).toBe(false)
		expect(publicKey.getFingerprint()).toBe(vault.fingerprint)
		expect(publicKey.keyPacket.version).toBe(6)

		const s2k = await s2kOf(vault.passwordCopy)
		expect(s2k.type).toBe('argon2')
		expect(s2k.salt).toHaveLength(16)
		const recoveryCopy = await openpgp.readPrivateKey({ armoredKey: vault.recoveryCopy })
		expect(recoveryCopy.getFingerprint()).toBe(vault.fingerprint)
		expect(recoveryCopy.isDecrypted()).toBe(false)

		const { key } = await unlockWithPassword(vault.passwordCopy, PASSWORD, FAST)
		expect(key.getFingerprint()).toBe(vault.fingerprint)
	})

	it('normalise le mot de passe en NFC avant de protéger la clé', async () => {
		const vault = await createVault('mot de passe é composé'.normalize('NFD'), generateRecoveryKey(), FAST)

		const { key } = await unlockWithPassword(vault.passwordCopy, 'mot de passe é composé'.normalize('NFC'), FAST)
		expect(key.getFingerprint()).toBe(vault.fingerprint)
	})

	it('refuse un mot de passe faux', async () => {
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)

		await expectVaultError(unlockWithPassword(vault.passwordCopy, 'faux', FAST), 'wrong_secret')
	})

	it('change de mot de passe sans changer d\'empreinte ; l\'ancien est refusé', async () => {
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)
		// Un message chiffré avant la rotation reste lisible après : rien d'autre n'est rechiffré.
		const message = await openpgp.encrypt({
			message: await openpgp.createMessage({ text: 'preuve' }),
			encryptionKeys: await openpgp.readKey({ armoredKey: vault.publicKey }),
		})

		const rotated = await rotatePassword(vault.passwordCopy, PASSWORD, NEW_PASSWORD, FAST)

		expect(rotated.fingerprint).toBe(vault.fingerprint)
		expect((await s2kOf(rotated.passwordCopy)).salt).not.toEqual((await s2kOf(vault.passwordCopy)).salt)
		await expectVaultError(unlockWithPassword(rotated.passwordCopy, PASSWORD, FAST), 'wrong_secret')
		const { key } = await unlockWithPassword(rotated.passwordCopy, NEW_PASSWORD, FAST)
		const { data } = await openpgp.decrypt({ message: await openpgp.readMessage({ armoredMessage: message }), decryptionKeys: key })
		expect(data).toBe('preuve')
	})

	it('refuse la rotation avec un mauvais mot de passe actuel', async () => {
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)

		await expectVaultError(rotatePassword(vault.passwordCopy, 'faux', NEW_PASSWORD, FAST), 'wrong_secret')
	})

	it('récupère la clé par la clé de récupération saisie en minuscules avec tirets, puis la renouvelle', async () => {
		const recoveryKey = generateRecoveryKey()
		const vault = await createVault(PASSWORD, recoveryKey, FAST)
		const newRecoveryKey = generateRecoveryKey()

		const recovered = await recoverVault(vault.recoveryCopy, formatRecoveryKey(recoveryKey).toLowerCase(), NEW_PASSWORD, newRecoveryKey, FAST)

		expect(recovered.fingerprint).toBe(vault.fingerprint)
		expect((await openpgp.readKey({ armoredKey: recovered.publicKey })).getFingerprint()).toBe(vault.fingerprint)
		// Ancien mot de passe et ancienne clé de récupération refusés, nouveaux acceptés.
		await expectVaultError(unlockWithPassword(recovered.passwordCopy, PASSWORD, FAST), 'wrong_secret')
		await expectVaultError(recoverVault(recovered.recoveryCopy, recoveryKey, NEW_PASSWORD, generateRecoveryKey(), FAST), 'wrong_secret')
		expect((await unlockWithPassword(recovered.passwordCopy, NEW_PASSWORD, FAST)).key.getFingerprint()).toBe(vault.fingerprint)
		expect((await recoverVault(recovered.recoveryCopy, newRecoveryKey, NEW_PASSWORD, generateRecoveryKey(), FAST)).fingerprint).toBe(vault.fingerprint)
	})

	it('refuse une fausse clé de récupération', async () => {
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)

		await expectVaultError(recoverVault(vault.recoveryCopy, generateRecoveryKey(), NEW_PASSWORD, generateRecoveryKey(), FAST), 'wrong_secret')
	})

	it('signale une copie protégée par des paramètres inférieurs à ceux de 13.02', async () => {
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)

		expect((await unlockWithPassword(vault.passwordCopy, PASSWORD, FAST)).outdated).toBe(true)
	})

	it('refuse, avant toute dérivation, un S2K au-dessus du plafond de lecture', async () => {
		// Clé protégée à 2^22 Kio (4 Gio) : forgée en réécrivant l'exposant du S2K, sans dériver.
		const vault = await createVault(PASSWORD, generateRecoveryKey(), FAST)
		const key = await openpgp.readPrivateKey({ armoredKey: vault.passwordCopy })
		;(key.keyPacket as unknown as { s2k: { encodedM: number } }).s2k.encodedM = 22

		await expectVaultError(unlockWithPassword(key.armor(), PASSWORD, FAST), 's2k_too_costly')
	})

	it('refuse une copie qui n\'est pas protégée par Argon2id', async () => {
		const { privateKey } = await openpgp.generateKey({ type: 'curve25519', userIDs: [{ name: 'x' }], format: 'object' })
		const iterated = await openpgp.encryptKey({ privateKey, passphrase: PASSWORD })

		await expectVaultError(unlockWithPassword(iterated.armor(), PASSWORD, FAST), 's2k_unsupported')
	})
})
