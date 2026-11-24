/**
 * Point d'entrée du module crypto pour le reste de la SPA (13.03, 13.04).
 *
 * Deux usages du Worker crypto :
 *
 * - calculs ponctuels (`auth`, enrôlement, changement de mot de passe,
 *   récupération) : un Worker dédié, détruit ensuite, rend la mémoire
 *   d'Argon2id et ne garde aucun secret ;
 * - session : un Worker durable garde la clé privée déverrouillée ;
 *   `lockSession()` l'arrête, ce qui efface la clé (13.04, ged-chiffree-2).
 *
 * Le fil principal n'importe ni openpgp.js ni argon2id : seuls les types du
 * protocole sont partagés.
 */
import type { CryptoErrorReason, CryptoOperations } from './protocol'

export type { CryptoErrorReason } from './protocol'
export type { VaultCopies } from './vault'

/** Erreur d'une opération du Worker crypto. */
export class CryptoError extends Error {
	readonly reason: CryptoErrorReason

	/**
	 * Construit l'erreur avec son motif, traduit par l'interface.
	 *
	 * @param reason Motif de l'échec.
	 */
	constructor(reason: CryptoErrorReason) {
		super(`Opération cryptographique impossible : ${reason}`)
		this.reason = reason
	}
}

/** Réponse du Worker à une requête. */
interface WorkerReply {
	id: number
	result?: unknown
	error?: CryptoErrorReason
}

/** Client d'un Worker crypto : requêtes numérotées, réponses typées. */
class CryptoWorkerClient {
	/** Worker sous-jacent. */
	private readonly worker: Worker

	/** Requêtes en attente de réponse, par numéro. */
	private readonly pending = new Map<number, { resolve: (value: unknown) => void, reject: (error: CryptoError) => void }>()

	/** Numéro de la prochaine requête. */
	private nextId = 1

	/** Crée le Worker (URL émise par Vite, admise par la politique Trusted Types `sti-worker`). */
	constructor() {
		this.worker = new Worker(new URL('./crypto.worker.ts', import.meta.url), { type: 'module' })
		this.worker.onmessage = (event: MessageEvent<WorkerReply>) => {
			const entry = this.pending.get(event.data.id)
			this.pending.delete(event.data.id)
			if (event.data.error) {
				entry?.reject(new CryptoError(event.data.error))
			} else {
				entry?.resolve(event.data.result)
			}
		}
		this.worker.onerror = () => this.failAll()
	}

	/**
	 * Envoie une opération au Worker.
	 *
	 * @param op Nom de l'opération.
	 * @param input Paramètres de l'opération.
	 * @returns La sortie de l'opération.
	 */
	request<Op extends keyof CryptoOperations>(op: Op, input: CryptoOperations[Op]['input']): Promise<CryptoOperations[Op]['output']> {
		const id = this.nextId++

		return new Promise((resolve, reject) => {
			this.pending.set(id, { resolve: resolve as (value: unknown) => void, reject })
			this.worker.postMessage({ ...input, id, op })
		})
	}

	/** Arrête le Worker : ce qu'il gardait en mémoire disparaît, les requêtes en cours échouent. */
	terminate(): void {
		this.worker.terminate()
		this.failAll()
	}

	/** Fait échouer toutes les requêtes en attente. */
	private failAll(): void {
		for (const entry of this.pending.values()) {
			entry.reject(new CryptoError('failed'))
		}
		this.pending.clear()
	}
}

/**
 * Exécute une opération dans un Worker dédié, arrêté aussitôt après.
 *
 * @param op Nom de l'opération.
 * @param input Paramètres de l'opération.
 * @returns La sortie de l'opération.
 */
async function once<Op extends keyof CryptoOperations>(op: Op, input: CryptoOperations[Op]['input']): Promise<CryptoOperations[Op]['output']> {
	const client = new CryptoWorkerClient()
	try {
		return await client.request(op, input)
	} finally {
		client.terminate()
	}
}

/**
 * Calcule la valeur d'authentification `auth` (Base64) dans un Worker dédié.
 *
 * @param password Mot de passe saisi.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns La valeur `auth`, en Base64.
 */
export async function computeAuth(password: string, instanceId: string, email: string): Promise<string> {
	return (await once('auth', { password, instanceId, email })).auth
}

/**
 * Enrôlement (13.03) : `auth`, paire de clés, clé de récupération et copies 1 et 2.
 *
 * @param password Mot de passe choisi.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns `auth`, la clé de récupération à afficher et les copies à publier.
 */
export function enroll(password: string, instanceId: string, email: string): Promise<CryptoOperations['enroll']['output']> {
	return once('enroll', { password, instanceId, email })
}

/**
 * Changement de mot de passe (rotation de 13.02) : nouvelle copie 1, `auth`
 * actuel (contrôlé par le serveur) et nouvel `auth`.
 *
 * @param passwordCopy Copie 1 actuelle.
 * @param oldPassword Mot de passe actuel.
 * @param newPassword Nouveau mot de passe.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns Les valeurs à envoyer au serveur.
 */
export function rotate(passwordCopy: string, oldPassword: string, newPassword: string, instanceId: string, email: string): Promise<CryptoOperations['rotate']['output']> {
	return once('rotate', { passwordCopy, oldPassword, newPassword, instanceId, email })
}

/**
 * Récupération par la clé de récupération (13.03) : nouvelles copies, nouvel
 * `auth` et nouvelle clé de récupération à afficher.
 *
 * @param recoveryCopy Copie 2 actuelle.
 * @param recoveryKey Clé de récupération saisie.
 * @param newPassword Nouveau mot de passe.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns Les valeurs à publier et la nouvelle clé de récupération.
 */
export function recover(recoveryCopy: string, recoveryKey: string, newPassword: string, instanceId: string, email: string): Promise<CryptoOperations['recover']['output']> {
	return once('recover', { recoveryCopy, recoveryKey, newPassword, instanceId, email })
}

/** Worker de session, qui garde la clé privée déverrouillée. */
let session: CryptoWorkerClient | null = null

/**
 * Déverrouille la clé privée dans le Worker de session (un nouveau Worker
 * remplace l'ancien).
 *
 * @param passwordCopy Copie 1, reçue après l'authentification complète.
 * @param password Mot de passe saisi à la connexion.
 * @returns L'empreinte de la clé et l'indication d'une copie à renouveler.
 */
export async function unlockSession(passwordCopy: string, password: string): Promise<CryptoOperations['unlock']['output']> {
	lockSession()
	const client = new CryptoWorkerClient()
	session = client
	try {
		return await client.request('unlock', { passwordCopy, password })
	} catch (error) {
		lockSession()
		throw error
	}
}

/**
 * État du Worker de session.
 *
 * @returns Clé déverrouillée ou non, et son empreinte.
 */
export async function sessionStatus(): Promise<CryptoOperations['status']['output']> {
	return session ? session.request('status', {}) : { unlocked: false, fingerprint: null }
}

/** Verrouille : arrête le Worker de session, la clé déchiffrée disparaît avec lui. */
export function lockSession(): void {
	session?.terminate()
	session = null
}
