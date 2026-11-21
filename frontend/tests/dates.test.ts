import { describe, expect, it } from 'vitest'
import { formatDateTime } from '../src/utils/dates'

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
