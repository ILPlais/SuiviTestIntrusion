/**
 * Valeur d'authentification `auth` (13.03) : le navigateur dérive du mot de passe
 * une valeur de 32 octets, seule envoyée au serveur. Le mot de passe ne quitte
 * jamais le navigateur ; il protégera aussi la clé privée (ged-chiffree-1).
 *
 * - sel_auth = 16 premiers octets de SHA-256("sti-auth-v1" ‖ 0x00 ‖ id_instance ‖ 0x00 ‖ e-mail)
 * - auth = Argon2id(mot de passe NFC, sel_auth), t=2, p=4, 128 Mio, 32 octets (13.02)
 *
 * Ce module ne dépend pas du chargeur WebAssembly : la fonction Argon2id lui est
 * passée, ce qui permet de l'utiliser dans le Worker crypto comme dans les tests.
 */

/** Paramètres Argon2id de 13.02, communs à `auth` et au S2K de la clé privée. */
export const ARGON2_PARAMS = {
	passes: 2,
	parallelism: 4,
	memorySize: 2 ** 17, // en Kio : 128 Mio
	tagLength: 32,
} as const

/** Domaine de séparation du sel, propre à la valeur d'authentification. */
const SALT_DOMAIN = 'sti-auth-v1'

/** Fonction Argon2id fournie par le paquet `argon2id` (une fois son WebAssembly chargé). */
export type Argon2idFunction = (params: {
	password: Uint8Array
	salt: Uint8Array
	passes: number
	parallelism: number
	memorySize: number
	tagLength: number
}) => Uint8Array

/**
 * Adresse telle qu'elle entre dans le sel : sans espaces de bord, en minuscules,
 * normalisée NFC ; le serveur applique la même règle.
 */
export function normalizeEmail(email: string): string {
	return email.trim().toLowerCase().normalize('NFC')
}

/**
 * Calcule sel_auth à partir de l'identifiant public de l'instance et de l'adresse.
 */
export async function authSalt(instanceId: string, email: string): Promise<Uint8Array> {
	const encoder = new TextEncoder()
	const parts = [encoder.encode(SALT_DOMAIN), new Uint8Array([0]), encoder.encode(instanceId), new Uint8Array([0]), encoder.encode(normalizeEmail(email))]
	const input = new Uint8Array(parts.reduce((length, part) => length + part.length, 0))
	let offset = 0
	for (const part of parts) {
		input.set(part, offset)
		offset += part.length
	}

	const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', input))

	return digest.slice(0, 16)
}

/**
 * Dérive `auth` et le renvoie en Base64, forme attendue par l'API.
 */
export async function deriveAuth(argon2id: Argon2idFunction, password: string, instanceId: string, email: string): Promise<string> {
	const salt = await authSalt(instanceId, email)
	const passwordBytes = new TextEncoder().encode(password.normalize('NFC'))

	try {
		const tag = argon2id({ password: passwordBytes, salt, ...ARGON2_PARAMS })

		return toBase64(tag)
	} finally {
		// Le mot de passe encodé ne doit pas traîner en mémoire plus que nécessaire.
		passwordBytes.fill(0)
	}
}

/**
 * Encode des octets en Base64 standard (avec remplissage), sans dépendance.
 */
export function toBase64(bytes: Uint8Array): string {
	let binary = ''
	for (const byte of bytes) {
		binary += String.fromCharCode(byte)
	}

	return btoa(binary)
}
