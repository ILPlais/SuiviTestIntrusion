/**
 * Point d'entrée du module crypto pour le reste de la SPA (13.03, 13.04).
 *
 * Chaque calcul s'exécute dans un Worker dédié, détruit ensuite : la mémoire
 * d'Argon2id est rendue et le mot de passe ne reste pas dans un fil d'exécution.
 */

/** Erreur du calcul : mémoire insuffisante, ou échec inattendu. */
export class CryptoError extends Error {
	readonly reason: 'out_of_memory' | 'failed'

	/**
	 * Construit l'erreur avec son motif, traduit par l'interface.
	 *
	 * @param reason Motif de l'échec.
	 */
	constructor(reason: 'out_of_memory' | 'failed') {
		super(`Calcul cryptographique impossible : ${reason}`)
		this.reason = reason
	}
}

/**
 * Calcule la valeur d'authentification `auth` (Base64) dans le Worker crypto.
 *
 * @param password Mot de passe saisi.
 * @param instanceId Identifiant public de l'instance.
 * @param email Adresse du compte.
 * @returns La valeur `auth`, en Base64.
 */
export function computeAuth(password: string, instanceId: string, email: string): Promise<string> {
	const worker = new Worker(new URL('./crypto.worker.ts', import.meta.url), { type: 'module' })

	return new Promise<string>((resolve, reject) => {
		worker.onmessage = (event: MessageEvent<{ auth?: string, error?: 'out_of_memory' | 'failed' }>) => {
			worker.terminate()
			if (event.data.auth) {
				resolve(event.data.auth)
			} else {
				reject(new CryptoError(event.data.error ?? 'failed'))
			}
		}
		worker.onerror = () => {
			worker.terminate()
			reject(new CryptoError('failed'))
		}
		worker.postMessage({ password, instanceId, email })
	})
}
