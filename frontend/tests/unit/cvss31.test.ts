/**
 * Moteur CVSS v3.1 côté navigateur (vulnerabilites-1) : mêmes vecteurs de
 * référence que backend/tests/Unit/Cvss31Test.php, pour que l'aperçu affiché
 * pendant la saisie soit le score que le serveur enregistrera.
 */
import { describe, expect, it } from 'vitest'
import { cvss31Score, cvss31Severity, formatScore, parseCvss31, roundUp, toCvss31Vector } from '../../src/utils/cvss31'

const REFERENCES: Array<[string, number]> = [
	['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8],
	['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H', 10.0],
	['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N', 6.1],
	['CVSS:3.1/AV:L/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H', 7.8],
	['CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N', 5.9],
	['CVSS:3.1/AV:A/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N', 6.5],
	['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:C/C:L/I:L/A:N', 6.4],
	['CVSS:3.1/AV:N/AC:L/PR:H/UI:N/S:C/C:H/I:H/A:H', 9.1],
	['CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:L/I:N/A:N', 1.6],
	['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N', 0.0],
	['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:N/I:N/A:N', 0.0],
]

describe('cvss31Score', () => {
	it.each(REFERENCES)('%s vaut %d', (vector, expected) => {
		const metrics = parseCvss31(vector)
		expect(metrics).not.toBeNull()
		expect(cvss31Score(metrics!)).toBe(expected)
	})
})

describe('roundUp', () => {
	it('arrondit au dixième supérieur selon l\'annexe A', () => {
		expect(roundUp(4.000001)).toBe(4.0)
		expect(roundUp(4.02)).toBe(4.1)
		expect(roundUp(4.0)).toBe(4.0)
	})
})

describe('cvss31Severity', () => {
	it('suit l\'échelle de 11.10', () => {
		expect([0, 0.1, 3.9, 4, 6.9, 7, 8.9, 9, 10].map(cvss31Severity)).toEqual([
			'none', 'minor', 'minor', 'important', 'important', 'major', 'major', 'critical', 'critical',
		])
	})
})

describe('formatScore', () => {
	it('affiche le dixième avec le séparateur de la langue', () => {
		expect(formatScore(9.8, 'fr')).toBe('9,8')
		expect(formatScore(10, 'fr')).toBe('10,0')
		expect(formatScore(0, 'en')).toBe('0.0')
	})
})

describe('parseCvss31', () => {
	it('lit un vecteur dans n\'importe quel ordre et le régénère dans l\'ordre canonique', () => {
		const metrics = parseCvss31('CVSS:3.1/C:H/I:H/A:H/AV:N/AC:L/PR:N/UI:N/S:U')
		expect(toCvss31Vector(metrics!)).toBe('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H')
	})

	it.each([
		'AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
		'CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
		'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H',
		'CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
		'CVSS:3.1/AV:N/AV:L/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
		'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H/E:P',
		'',
	])('refuse « %s »', (vector) => {
		expect(parseCvss31(vector)).toBeNull()
	})
})
