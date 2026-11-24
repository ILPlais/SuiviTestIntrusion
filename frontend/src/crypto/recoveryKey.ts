/**
 * Clé de récupération (13.03, 11.06, ged-chiffree-1) : 128 bits aléatoires,
 * affichés en Base32 Crockford par groupes de quatre caractères
 * (`N1FT-KEJK-P460-…`, 26 caractères). Elle chiffre une seconde copie de la
 * clé privée et n'est jamais envoyée au serveur.
 *
 * À la saisie, casse, espaces et tirets sont ignorés, et les confusions
 * courantes corrigées comme le prévoit Crockford (O → 0, I et L → 1).
 */

/** Alphabet Base32 de Crockford : sans I, L, O ni U. */
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

/** Nombre d'octets aléatoires de la clé (128 bits). */
export const RECOVERY_KEY_BYTES = 16

/** Longueur de la clé en caractères Base32 : ⌈128 / 5⌉. */
export const RECOVERY_KEY_LENGTH = Math.ceil((RECOVERY_KEY_BYTES * 8) / 5)

/**
 * Encode des octets en Base32 Crockford, sans bourrage.
 *
 * @param bytes Octets à encoder.
 * @returns La chaîne Base32, en majuscules.
 */
export function encodeBase32(bytes: Uint8Array): string {
	let output = ''
	let buffer = 0
	let bits = 0
	for (const byte of bytes) {
		buffer = (buffer << 8) | byte
		bits += 8
		while (bits >= 5) {
			output += ALPHABET[(buffer >>> (bits - 5)) & 31]
			bits -= 5
		}
	}
	if (bits > 0) {
		output += ALPHABET[(buffer << (5 - bits)) & 31]
	}

	return output
}

/**
 * Tire une nouvelle clé de récupération, sous sa forme canonique (26 caractères,
 * sans tirets) ; `formatRecoveryKey` la découpe pour l'affichage.
 *
 * @returns La clé canonique.
 */
export function generateRecoveryKey(): string {
	return encodeBase32(crypto.getRandomValues(new Uint8Array(RECOVERY_KEY_BYTES)))
}

/**
 * Forme canonique d'une clé saisie : majuscules, sans espaces ni tirets,
 * confusions corrigées. Ne vérifie pas la longueur.
 *
 * @param input Clé saisie par l'utilisateur.
 * @returns La clé canonique, telle qu'elle protège la copie de la clé privée.
 */
export function normalizeRecoveryKey(input: string): string {
	return input
		.toUpperCase()
		.replace(/[\s-]/g, '')
		.replace(/O/g, '0')
		.replace(/[IL]/g, '1')
}

/**
 * Vrai si la saisie, une fois normalisée, a la forme d'une clé complète.
 *
 * @param input Clé saisie.
 * @returns Vrai si la longueur et l'alphabet conviennent.
 */
export function isWellFormedRecoveryKey(input: string): boolean {
	const key = normalizeRecoveryKey(input)

	return key.length === RECOVERY_KEY_LENGTH && [...key].every((character) => ALPHABET.includes(character))
}

/**
 * Découpe une clé en groupes de quatre caractères séparés par des tirets.
 *
 * @param key Clé canonique ou saisie.
 * @returns La clé affichable (`N1FT-KEJK-…`).
 */
export function formatRecoveryKey(key: string): string {
	return (normalizeRecoveryKey(key).match(/.{1,4}/g) ?? []).join('-')
}
