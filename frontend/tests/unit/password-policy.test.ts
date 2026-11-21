/**
 * Politique de mots de passe (13.03) : longueur en caractères Unicode, liste de
 * refus, termes du service et de l'adresse, robustesse.
 */
import { describe, expect, it } from 'vitest'
import { checkPassword, MAX_LENGTH, MIN_LENGTH } from '../../src/crypto/passwordPolicy'

const EMAIL = 'marie.durand@exemple-audit.fr'

describe('politique de mots de passe', () => {
	it('fixe les longueurs de l\'ADR', () => {
		expect(MIN_LENGTH).toBe(15)
		expect(MAX_LENGTH).toBe(128)
	})

	it('accepte une phrase longue sans règle de composition', async () => {
		const result = await checkPassword('girafe orange sous la pluie de mars', EMAIL)

		expect(result.problems).toEqual([])
		expect(result.valid).toBe(true)
		expect(result.score).toBeGreaterThanOrEqual(3)
	})

	it('compte les caractères en points de code, pas en unités UTF-16', async () => {
		// 14 émojis = 14 caractères (28 unités UTF-16) : trop court.
		const result = await checkPassword('🦒'.repeat(14), EMAIL)

		expect(result.length).toBe(14)
		expect(result.problems).toContain('tooShort')
	})

	it('refuse un mot de passe trop long sans le tronquer', async () => {
		expect((await checkPassword('a'.repeat(129), EMAIL)).problems).toContain('tooLong')
	})

	it('refuse un mot de passe courant, quelle que soit la casse', async () => {
		const result = await checkPassword('QAZWSXEDCRFVTGB', EMAIL)

		expect(result.problems).toContain('common')
	})

	it('refuse les termes du service et de l\'adresse', async () => {
		expect((await checkPassword('mon accès pentest du lundi', EMAIL)).problems).toContain('context')
		expect((await checkPassword('le chat de marie dort encore', EMAIL)).problems).toContain('context')
		expect((await checkPassword('chez exemple on teste bien', EMAIL)).problems).toContain('context')
	})
})
