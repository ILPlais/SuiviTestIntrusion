/**
 * Saisie des identifiants CVE et CWE (vulnerabilites-3) : séparateurs souples,
 * majuscules, sans doublon ni élément vide.
 */
import { describe, expect, it } from 'vitest'
import { parseIdentifiers } from '../../src/api/vulnerabilities'

describe('parseIdentifiers', () => {
	it('découpe sur les virgules, points-virgules et espaces', () => {
		expect(parseIdentifiers('CVE-2026-0042, cve-2025-1234;CVE-2024-9999  CVE-2026-0042')).toEqual([
			'CVE-2026-0042', 'CVE-2025-1234', 'CVE-2024-9999',
		])
	})

	it('renvoie une liste vide pour une saisie vide', () => {
		expect(parseIdentifiers('  ,  ')).toEqual([])
	})
})
