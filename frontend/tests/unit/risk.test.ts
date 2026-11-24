/**
 * Barres en caractères du risque global (resultats-1) : longueur proportionnelle
 * au plus grand compteur, au moins un bloc pour un compteur non nul.
 */
import { describe, expect, it } from 'vitest'
import { textBar } from '../../src/api/risk'

describe('textBar', () => {
	it('remplit la barre au prorata du maximum', () => {
		expect(textBar(4, 4, 8)).toEqual({ filled: '████████', empty: '' })
		expect(textBar(2, 4, 8)).toEqual({ filled: '████', empty: '░░░░' })
	})

	it('garde un bloc visible pour un petit compteur non nul', () => {
		expect(textBar(1, 100, 8).filled).toBe('█')
	})

	it('laisse la barre vide pour zéro ou sans maximum', () => {
		expect(textBar(0, 4, 4)).toEqual({ filled: '', empty: '░░░░' })
		expect(textBar(0, 0, 4)).toEqual({ filled: '', empty: '░░░░' })
	})
})
