/**
 * Clé de récupération (13.03, ged-chiffree-1) : 128 bits en Base32 Crockford,
 * groupes de quatre caractères, saisie tolérante (casse, tirets, espaces).
 */
import { describe, expect, it } from 'vitest'
import {
	encodeBase32,
	formatRecoveryKey,
	generateRecoveryKey,
	isWellFormedRecoveryKey,
	normalizeRecoveryKey,
	RECOVERY_KEY_LENGTH,
} from '../../src/crypto/recoveryKey'

describe('clé de récupération', () => {
	it('tire 128 bits, soit 26 caractères de l\'alphabet de Crockford', () => {
		const key = generateRecoveryKey()

		expect(RECOVERY_KEY_LENGTH).toBe(26)
		expect(key).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/)
		expect(generateRecoveryKey()).not.toBe(key)
	})

	it('encode en Base32 Crockford sans bourrage', () => {
		expect(encodeBase32(new Uint8Array([0xff]))).toBe('ZW')
		expect(encodeBase32(new Uint8Array(16))).toBe('0'.repeat(26))
	})

	it('s\'affiche par groupes de quatre caractères', () => {
		expect(formatRecoveryKey('N1FTKEJKP460ABCDEFGHJKMNPQ')).toBe('N1FT-KEJK-P460-ABCD-EFGH-JKMN-PQ')
	})

	it('accepte une saisie en minuscules, avec tirets ou espaces', () => {
		const key = generateRecoveryKey()
		const typed = formatRecoveryKey(key).toLowerCase().replace(/-/g, (dash, offset: number) => (offset % 2 ? ' ' : dash))

		expect(normalizeRecoveryKey(typed)).toBe(key)
		expect(isWellFormedRecoveryKey(typed)).toBe(true)
	})

	it('corrige les confusions de lecture (O → 0, I et L → 1)', () => {
		expect(normalizeRecoveryKey('oIl0')).toBe('0110')
	})

	it('refuse une clé incomplète ou hors alphabet', () => {
		expect(isWellFormedRecoveryKey(generateRecoveryKey().slice(1))).toBe(false)
		expect(isWellFormedRecoveryKey('U'.repeat(26))).toBe(false)
	})
})
