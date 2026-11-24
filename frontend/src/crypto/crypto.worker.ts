/**
 * Worker crypto (13.04) : seul endroit de la SPA où vivent le mot de passe une
 * fois saisi, la clé privée déchiffrée et, plus tard, les clés de fichiers.
 * Le fil principal ne reçoit que des résultats publiables : `auth`, copies
 * chiffrées de la clé, empreinte, clé de récupération à afficher.
 *
 * Opérations (13.04, « Worker crypto ») :
 *
 * - `auth` : valeur d'authentification (13.03) ;
 * - `enroll` : paire de clés, clé de récupération et copies 1 et 2 (ged-chiffree-1) ;
 * - `unlock` : déverrouille la copie 1 et garde la clé dans ce Worker ;
 * - `rotate` : changement de mot de passe, seule la copie 1 est rechiffrée (13.02) ;
 * - `recover` : clé de récupération, nouveau mot de passe, nouvelles copies (13.03) ;
 * - `status` : clé déverrouillée ou non, et son empreinte.
 *
 * Toute autre opération est refusée. Verrouiller revient à arrêter ce Worker
 * (`terminate()`) : la clé disparaît avec lui (ged-chiffree-2).
 *
 * Protocole : le fil principal envoie `{ id, op, ...paramètres }` et reçoit
 * `{ id, result }` ou `{ id, error }`.
 */
import setupWasm from 'argon2id/lib/setup.js'
import simdUrl from 'argon2id/dist/simd.wasm?url'
import noSimdUrl from 'argon2id/dist/no-simd.wasm?url'
import type { PrivateKey } from 'openpgp'
import { deriveAuth, type Argon2idFunction } from './authValue'
import { generateRecoveryKey } from './recoveryKey'
import { createVault, recoverVault, rotatePassword, unlockWithPassword, VaultError } from './vault'
import type { CryptoErrorReason, CryptoOperations } from './protocol'

/** Clé privée déverrouillée par `unlock` ; ne quitte jamais ce Worker. */
let unlocked: PrivateKey | null = null

/**
 * Instancie un binaire WebAssembly servi par l'origine (CSP `'self'`,
 * `'wasm-unsafe-eval'`), sans URL `data:`.
 *
 * @param url URL du binaire, sur la même origine.
 * @returns La fonction d'instanciation attendue par argon2id.
 */
function instantiate(url: string) {
	return (imports: WebAssembly.Imports) => WebAssembly.instantiateStreaming(fetch(url), imports)
}

/**
 * Calcule `auth` ; le WebAssembly est rechargé à chaque calcul pour que ses
 * 128 Mio ne restent pas réservés dans un Worker de session.
 *
 * @param password Mot de passe.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns La valeur `auth`, en Base64.
 */
async function auth(password: string, instanceId: string, email: string): Promise<string> {
	const argon2id = (await setupWasm(instantiate(simdUrl), instantiate(noSimdUrl))) as Argon2idFunction

	return deriveAuth(argon2id, password, instanceId, email)
}

/** Traitement de chaque opération autorisée. */
const operations: { [Op in keyof CryptoOperations]: (input: CryptoOperations[Op]['input']) => Promise<CryptoOperations[Op]['output']> } = {
	auth: async ({ password, instanceId, email }) => ({ auth: await auth(password, instanceId, email) }),

	enroll: async ({ password, instanceId, email }) => {
		const recoveryKey = generateRecoveryKey()
		const vault = await createVault(password, recoveryKey)

		return { auth: await auth(password, instanceId, email), recoveryKey, vault }
	},

	unlock: async ({ passwordCopy, password }) => {
		const { key, outdated } = await unlockWithPassword(passwordCopy, password)
		unlocked = key

		return { fingerprint: key.getFingerprint(), outdated }
	},

	rotate: async ({ passwordCopy, oldPassword, newPassword, instanceId, email }) => {
		const rotated = await rotatePassword(passwordCopy, oldPassword, newPassword)

		return {
			currentAuth: await auth(oldPassword, instanceId, email),
			auth: await auth(newPassword, instanceId, email),
			passwordCopy: rotated.passwordCopy,
			fingerprint: rotated.fingerprint,
		}
	},

	recover: async ({ recoveryCopy, recoveryKey, newPassword, instanceId, email }) => {
		const newRecoveryKey = generateRecoveryKey()
		const vault = await recoverVault(recoveryCopy, recoveryKey, newPassword, newRecoveryKey)

		return { auth: await auth(newPassword, instanceId, email), recoveryKey: newRecoveryKey, vault }
	},

	status: async () => ({ unlocked: unlocked !== null, fingerprint: unlocked?.getFingerprint() ?? null }),
}

/**
 * Motif d'erreur transmis au fil principal, sans détail interne.
 *
 * @param error Erreur levée par l'opération.
 * @returns Le motif.
 */
function reasonOf(error: unknown): CryptoErrorReason {
	if (error instanceof VaultError) {
		return error.reason
	}
	// Mémoire insuffisante (surtout sur mobile) : signalée comme telle, pas comme un secret faux (13.02).
	const outOfMemory = error instanceof RangeError || String(error).includes('memory')

	return outOfMemory ? 'out_of_memory' : 'failed'
}

self.onmessage = async (event: MessageEvent<{ id: number, op: string } & Record<string, unknown>>) => {
	const { id, op, ...input } = event.data

	if (!Object.hasOwn(operations, op)) {
		self.postMessage({ id, error: 'unsupported' satisfies CryptoErrorReason })

		return
	}

	try {
		const handler = operations[op as keyof CryptoOperations] as (input: unknown) => Promise<unknown>
		self.postMessage({ id, result: await handler(input) })
	} catch (error) {
		self.postMessage({ id, error: reasonOf(error) })
	}
}
