/**
 * Coffre de clés de l'utilisateur (13.02, 13.03, ged-chiffree-1) : paire
 * OpenPGP v6 générée dans le navigateur, et deux copies chiffrées de la clé
 * privée, seules confiées au serveur :
 *
 * - copie 1, protégée par le mot de passe ;
 * - copie 2, protégée par la clé de récupération.
 *
 * Les deux copies portent la même empreinte : changer de mot de passe ou se
 * servir de la clé de récupération ne rechiffre que la clé privée, jamais les
 * fichiers ni les trousseaux.
 *
 * Ce module n'est importé que par le Worker crypto (13.04) et par les tests :
 * la clé privée déchiffrée n'en sort pas vers le fil principal. Les paramètres
 * Argon2id sont injectables pour que les tests restent rapides ; l'application
 * utilise toujours S2K_PARAMS.
 */
import * as openpgp from 'openpgp'
import { normalizeRecoveryKey } from './recoveryKey'

/** Paramètres Argon2id du S2K (13.02) : t = 2, p = 4, m = 2^17 Kio (128 Mio). */
export interface S2kParams {
	passes: number
	parallelism: number
	memoryExponent: number
}

/** Paramètres courants de 13.02. */
export const S2K_PARAMS: S2kParams = { passes: 2, parallelism: 4, memoryExponent: 17 }

/**
 * Plafond de lecture (13.02) : une clé dont le S2K dépasse 2 Gio ou 8 passes est
 * refusée avant toute dérivation, pour qu'un serveur malveillant ne puisse pas
 * bloquer le navigateur.
 */
export const S2K_CEILING = { memoryExponent: 21, passes: 8 } as const

/** Copies publiées vers le serveur à l'enrôlement ou après une récupération. */
export interface VaultCopies {
	/** Clé publique armurée. */
	publicKey: string
	/** Empreinte v6, en hexadécimal minuscule (64 caractères). */
	fingerprint: string
	/** Copie 1 : clé privée chiffrée par le mot de passe. */
	passwordCopy: string
	/** Copie 2 : clé privée chiffrée par la clé de récupération. */
	recoveryCopy: string
}

/** Erreur du coffre, traduite par l'interface. */
export class VaultError extends Error {
	readonly reason: 'wrong_secret' | 's2k_too_costly' | 's2k_unsupported' | 'fingerprint_mismatch'

	/**
	 * Construit l'erreur avec son motif.
	 *
	 * @param reason Motif de l'échec.
	 */
	constructor(reason: VaultError['reason']) {
		super(`Coffre de clés : ${reason}`)
		this.reason = reason
	}
}

/**
 * Configuration openpgp.js de 13.02 : S2K Argon2id fixé explicitement (le défaut
 * est « itéré »), protection AEAD obligatoire avec Argon2, GCM comme 13.01.
 *
 * @param params Paramètres Argon2id.
 * @returns La configuration partielle à passer à openpgp.js.
 */
function config(params: S2kParams): Partial<openpgp.Config> {
	return {
		s2kType: openpgp.enums.s2k.argon2,
		s2kArgon2Params: params,
		aeadProtect: true,
		preferredAEADAlgorithm: openpgp.enums.aead.gcm,
		v6Keys: true,
	}
}

/**
 * Mot de passe tel qu'il protège la clé : normalisé NFC, comme pour `auth`.
 *
 * @param password Mot de passe saisi.
 * @returns Le mot de passe normalisé.
 */
function normalizePassword(password: string): string {
	return password.normalize('NFC')
}

/**
 * Chiffre une clé privée déverrouillée par un secret (nouveau sel à chaque appel).
 *
 * @param privateKey Clé privée déchiffrée.
 * @param secret Mot de passe ou clé de récupération, déjà normalisé.
 * @param params Paramètres Argon2id.
 * @returns La copie chiffrée, armurée.
 */
async function protect(privateKey: openpgp.PrivateKey, secret: string, params: S2kParams): Promise<string> {
	const encrypted = await openpgp.encryptKey({ privateKey, passphrase: secret, config: config(params) })

	return encrypted.armor()
}

/**
 * Vérifie le S2K de chaque paquet secret avant de dériver : Argon2id seulement,
 * sous le plafond de lecture.
 *
 * @param key Clé privée chiffrée, lue.
 * @returns Vrai si l'un des paquets est protégé par des paramètres inférieurs aux courants.
 * @throws {VaultError} Si un S2K n'est pas Argon2id ou dépasse le plafond.
 */
function inspectS2k(key: openpgp.PrivateKey): boolean {
	let outdated = false
	for (const packet of [key.keyPacket, ...key.getSubkeys().map((subkey) => subkey.keyPacket)]) {
		const s2k = (packet as unknown as { s2k?: { type: string, t?: number, encodedM?: number } }).s2k
		if (!s2k || s2k.type !== 'argon2' || s2k.t === undefined || s2k.encodedM === undefined) {
			throw new VaultError('s2k_unsupported')
		}
		if (s2k.encodedM > S2K_CEILING.memoryExponent || s2k.t > S2K_CEILING.passes) {
			throw new VaultError('s2k_too_costly')
		}
		outdated ||= s2k.encodedM < S2K_PARAMS.memoryExponent || s2k.t < S2K_PARAMS.passes
	}

	return outdated
}

/**
 * Déverrouille une copie de la clé privée.
 *
 * @param armored Copie chiffrée, armurée.
 * @param secret Mot de passe ou clé de récupération, déjà normalisé.
 * @param params Paramètres Argon2id de référence (pour savoir si la copie est à renouveler).
 * @returns La clé déchiffrée et l'indication d'une protection inférieure aux paramètres courants.
 * @throws {VaultError} Si le secret est faux ou le S2K inacceptable.
 */
async function open(armored: string, secret: string, params: S2kParams): Promise<{ key: openpgp.PrivateKey, outdated: boolean }> {
	const encrypted = await openpgp.readPrivateKey({ armoredKey: armored })
	const outdated = inspectS2k(encrypted)

	try {
		const key = await openpgp.decryptKey({ privateKey: encrypted, passphrase: secret, config: config(params) })

		return { key, outdated }
	} catch {
		// « unable to authenticate data » : l'AEAD refuse un secret faux.
		throw new VaultError('wrong_secret')
	}
}

/**
 * Enrôlement : génère la paire v6 (Ed25519 et X25519) et ses deux copies.
 *
 * @param password Mot de passe choisi.
 * @param recoveryKey Clé de récupération tirée pour l'utilisateur.
 * @param params Paramètres Argon2id (S2K_PARAMS dans l'application).
 * @returns Clé publique, empreinte et copies chiffrées.
 */
export async function createVault(password: string, recoveryKey: string, params: S2kParams = S2K_PARAMS): Promise<VaultCopies> {
	// Aucun identifiant utilisateur dans la clé : l'adresse n'a pas à voyager avec
	// les copies, et le serveur relie la clé au compte.
	const { privateKey, publicKey } = await openpgp.generateKey({
		type: 'curve25519',
		userIDs: [{ name: 'SuiviTestIntrusion' }],
		format: 'object',
		config: config(params),
	})

	return {
		publicKey: publicKey.armor(),
		fingerprint: privateKey.getFingerprint(),
		passwordCopy: await protect(privateKey, normalizePassword(password), params),
		recoveryCopy: await protect(privateKey, normalizeRecoveryKey(recoveryKey), params),
	}
}

/**
 * Déverrouille la copie 1 avec le mot de passe.
 *
 * @param passwordCopy Copie 1, armurée.
 * @param password Mot de passe saisi.
 * @param params Paramètres Argon2id de référence.
 * @returns La clé déchiffrée et l'indication d'une copie à renouveler.
 */
export function unlockWithPassword(passwordCopy: string, password: string, params: S2kParams = S2K_PARAMS): Promise<{ key: openpgp.PrivateKey, outdated: boolean }> {
	return open(passwordCopy, normalizePassword(password), params)
}

/**
 * Changement de mot de passe (rotation de 13.02) : seule la copie 1 est
 * rechiffrée. Contrôles : empreinte inchangée, et la nouvelle copie se
 * déverrouille avec le nouveau mot de passe.
 *
 * @param passwordCopy Copie 1 actuelle.
 * @param oldPassword Mot de passe actuel.
 * @param newPassword Nouveau mot de passe.
 * @param params Paramètres Argon2id.
 * @returns La nouvelle copie 1 et l'empreinte.
 * @throws {VaultError} Si l'ancien mot de passe est faux ou si un contrôle échoue.
 */
export async function rotatePassword(passwordCopy: string, oldPassword: string, newPassword: string, params: S2kParams = S2K_PARAMS): Promise<{ passwordCopy: string, fingerprint: string }> {
	const { key } = await unlockWithPassword(passwordCopy, oldPassword, params)
	const renewed = await protect(key, normalizePassword(newPassword), params)
	const check = await unlockWithPassword(renewed, newPassword, params)
	if (check.key.getFingerprint() !== key.getFingerprint()) {
		throw new VaultError('fingerprint_mismatch')
	}

	return { passwordCopy: renewed, fingerprint: key.getFingerprint() }
}

/**
 * Récupération (13.03, mot de passe oublié) : la clé de récupération ouvre la
 * copie 2 ; un nouveau mot de passe et une nouvelle clé de récupération
 * protègent deux nouvelles copies de la même clé. L'ancienne clé de
 * récupération ne vaut plus : elle ne protège plus aucune copie publiée.
 *
 * @param recoveryCopy Copie 2 actuelle.
 * @param recoveryKey Clé de récupération saisie (casse et tirets ignorés).
 * @param newPassword Nouveau mot de passe.
 * @param newRecoveryKey Nouvelle clé de récupération.
 * @param params Paramètres Argon2id.
 * @returns Les nouvelles copies, la clé publique et l'empreinte inchangées.
 * @throws {VaultError} Si la clé de récupération est fausse.
 */
export async function recoverVault(recoveryCopy: string, recoveryKey: string, newPassword: string, newRecoveryKey: string, params: S2kParams = S2K_PARAMS): Promise<VaultCopies> {
	const { key } = await open(recoveryCopy, normalizeRecoveryKey(recoveryKey), params)

	return {
		publicKey: key.toPublic().armor(),
		fingerprint: key.getFingerprint(),
		passwordCopy: await protect(key, normalizePassword(newPassword), params),
		recoveryCopy: await protect(key, normalizeRecoveryKey(newRecoveryKey), params),
	}
}
