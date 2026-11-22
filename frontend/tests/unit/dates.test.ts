import { describe, expect, it } from 'vitest'
import { formatDateTime, fromLocalInput, toLocalInput } from '../../src/utils/dates'

describe('toLocalInput et fromLocalInput', () => {
	it('fait l\'aller-retour entre une date UTC de l\'API et un champ datetime-local', () => {
		const local = toLocalInput('2026-12-01T08:00:00Z')
		expect(local).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/)
		expect(fromLocalInput(local)).toBe('2026-12-01T08:00:00.000Z')
	})

	it('lit le champ dans le fuseau du navigateur', () => {
		const expected = new Date(2026, 11, 1, 9, 30).toISOString()
		expect(fromLocalInput('2026-12-01T09:30')).toBe(expected)
	})

	it('donne un champ vide pour une date absente ou invalide, et null pour un champ vide', () => {
		expect(toLocalInput(null)).toBe('')
		expect(toLocalInput('2026-12-01T08:00:00')).toBe('')
		expect(fromLocalInput('')).toBeNull()
		expect(fromLocalInput('pas une date')).toBeNull()
	})
})

describe('formatDateTime', () => {
	it('affiche une date UTC dans le fuseau demandé, selon la langue', () => {
		// 21 novembre 2026, 15:30 UTC = 16:30 à Paris (heure d'hiver).
		expect(formatDateTime('2026-11-21T15:30:00Z', 'fr', 'Europe/Paris')).toBe('21 novembre 2026 à 16:30:00')
	})

	it('utilise le fuseau du navigateur par défaut', () => {
		const expected = new Intl.DateTimeFormat('fr', { dateStyle: 'long', timeStyle: 'medium' })
			.format(new Date('2026-11-21T15:30:00Z'))
		expect(formatDateTime('2026-11-21T15:30:00Z', 'fr')).toBe(expected)
	})

	it('refuse une date sans fuseau explicite ou invalide', () => {
		expect(formatDateTime('2026-11-21T15:30:00', 'fr')).toBeNull()
		expect(formatDateTime('pas une date', 'fr')).toBeNull()
	})
})
