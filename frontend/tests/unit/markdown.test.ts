/**
 * Rendu du texte riche (engagement-1, 13.04) : le Markdown devient du HTML
 * assaini ; aucun script, gestionnaire d'événement ni lien `javascript:`.
 */
import { describe, expect, it } from 'vitest'
import { markdownToHtml } from '../../src/render/markdown'

describe('rendu Markdown', () => {
	it('rend titres, emphase, listes et liens', () => {
		const html = markdownToHtml('## Activité\n\nBanque **régionale**.\n\n- un\n- deux\n\n[site](https://example.org)')

		expect(html).toContain('<h2>Activité</h2>')
		expect(html).toContain('<strong>régionale</strong>')
		expect(html).toContain('<li>deux</li>')
		expect(html).toContain('href="https://example.org"')
	})

	it('neutralise le HTML et les liens dangereux', () => {
		const html = markdownToHtml('<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n[piège](javascript:alert(3))')

		expect(html).not.toContain('<script')
		expect(html).not.toContain('onerror')
		expect(html).not.toContain('javascript:')
	})

	it('rend un texte vide en chaîne vide', () => {
		expect(markdownToHtml('')).toBe('')
	})
})
