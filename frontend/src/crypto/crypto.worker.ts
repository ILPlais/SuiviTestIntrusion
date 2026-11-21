/**
 * Worker crypto (13.04) : calcule la valeur d'authentification hors du fil
 * principal, pour ne pas figer l'interface pendant Argon2id (environ 0,5 s).
 *
 * Il recevra plus tard la clé privée et les clés de fichiers (ged-chiffree-1) ;
 * le mot de passe qu'il reçoit n'est conservé nulle part après le calcul.
 *
 * Message reçu : { password, instanceId, email } ; réponse : { auth } ou { error }.
 */
import setupWasm from 'argon2id/lib/setup.js'
import simdUrl from 'argon2id/dist/simd.wasm?url'
import noSimdUrl from 'argon2id/dist/no-simd.wasm?url'
import { deriveAuth, type Argon2idFunction } from './authValue'

/** Requête du fil principal. */
interface AuthRequest {
	password: string
	instanceId: string
	email: string
}

/**
 * Instancie un binaire WebAssembly servi par l'origine (CSP `'self'`,
 * `'wasm-unsafe-eval'`), sans URL `data:`.
 */
function instantiate(url: string) {
	return (imports: WebAssembly.Imports) => WebAssembly.instantiateStreaming(fetch(url), imports)
}

self.onmessage = async (event: MessageEvent<AuthRequest>) => {
	const { password, instanceId, email } = event.data

	try {
		// Un chargement par calcul : la mémoire WebAssembly (128 Mio) est libérée avec le Worker.
		const argon2id = (await setupWasm(instantiate(simdUrl), instantiate(noSimdUrl))) as Argon2idFunction
		const auth = await deriveAuth(argon2id, password, instanceId, email)
		self.postMessage({ auth })
	} catch (error) {
		// Mémoire insuffisante (surtout sur mobile) : signalée comme telle, pas comme un secret faux (13.02).
		const outOfMemory = error instanceof RangeError || String(error).includes('memory')
		self.postMessage({ error: outOfMemory ? 'out_of_memory' : 'failed' })
	}
}
