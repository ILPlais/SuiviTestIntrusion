/**
 * Politiques Trusted Types (socle-6, 13.04) : seule l'URL du Worker crypto de la
 * même origine est acceptée ; le HTML est assaini (aucun script, aucun gestionnaire).
 */
import { describe, expect, it } from 'vitest'
import { isCryptoWorkerUrl, sanitizeHtml } from '../../src/security/trustedTypes'

const ORIGIN = 'https://pentest.example.org'

describe('politiques Trusted Types', () => {
	it('accepte le Worker crypto du build, sur la même origine', () => {
		expect(isCryptoWorkerUrl('/assets/crypto.worker-DlD-7WN_.js', ORIGIN)).toBe(true)
		expect(isCryptoWorkerUrl(`${ORIGIN}/assets/crypto.worker-abc123.js`, ORIGIN)).toBe(true)
	})

	it('refuse toute autre URL de script', () => {
		expect(isCryptoWorkerUrl('https://tiers.example/assets/crypto.worker-abc.js', ORIGIN)).toBe(false)
		expect(isCryptoWorkerUrl('/assets/autre-abc.js', ORIGIN)).toBe(false)
		expect(isCryptoWorkerUrl('data:text/javascript,alert(1)', ORIGIN)).toBe(false)
		expect(isCryptoWorkerUrl('javascript:alert(1)', ORIGIN)).toBe(false)
	})

	it('assainit le HTML : aucun script ni gestionnaire d\'événement', () => {
		const clean = sanitizeHtml('<img src="x" onerror="alert(1)"><script>alert(2)</script><b>gras</b>')

		expect(clean).not.toContain('onerror')
		expect(clean).not.toContain('<script')
		expect(clean).toContain('<b>gras</b>')
	})
})
