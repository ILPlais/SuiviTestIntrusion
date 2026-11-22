/**
 * Rendu du texte riche (engagement-1, 13.04) : le Markdown devient du HTML
 * assaini ; aucun script, gestionnaire d'événement ni lien `javascript:`.
 */
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import MarkdownView from '../../src/render/MarkdownView.vue'
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

	it.each([
		['un style en ligne', '<div style="position:fixed;inset:0">faux écran</div>', 'style'],
		['une feuille de style', '<style>body{display:none}</style>', '<style'],
		['un formulaire', '<form action="/api/v1/auth/login"><input name="password"><button>OK</button></form>', '<form'],
		['un champ de saisie', '<input name="code">', '<input'],
		['un SVG', '<svg onload="alert(1)"><circle r="1"/></svg>', '<svg'],
		['un identifiant', '<p id="app">clobber</p>', 'id='],
		['un lien data:', '[piège](data:text/html,<script>alert(1)</script>)', 'data:'],
		['une image', '![logo](https://tiers.example/pixel.png)', '<img'],
	])('retire %s', (_name, source, forbidden) => {
		expect(markdownToHtml(source)).not.toContain(forbidden)
	})

	it('garde les liens mailto et les tableaux', () => {
		const html = markdownToHtml('[écrire](mailto:a@example.org)\n\n| a | b |\n| - | - |\n| 1 | 2 |')

		expect(html).toContain('href="mailto:a@example.org"')
		expect(html).toContain('<td>2</td>')
	})

	it('affiche dans MarkdownView le même HTML assaini', async () => {
		const wrapper = mount(MarkdownView, { props: { source: '**gras** <img src=x onerror=alert(1)><form></form>' } })
		await nextTick()

		expect(wrapper.html()).toContain('<strong>gras</strong>')
		expect(wrapper.find('img').exists()).toBe(false)
		expect(wrapper.find('form').exists()).toBe(false)
	})

	it('rend un texte vide en chaîne vide', () => {
		expect(markdownToHtml('')).toBe('')
	})
})
