/**
 * Ergonomie de la SPA (socle-8) : thème WebTUI selon la préférence du système,
 * compteur des requêtes en cours, icônes Nerd Fonts décoratives, throbber.
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { activeRequests, track } from '../../src/api/activity'
import NfIcon from '../../src/components/NfIcon.vue'
import LoadingThrobber from '../../src/components/LoadingThrobber.vue'
import { glyph, ICON_NAMES } from '../../src/icons'
import { installTheme, themeFor } from '../../src/theme'

/**
 * Faux MediaQueryList : préférence sombre donnée, changements simulables.
 *
 * @param dark Valeur initiale de la préférence sombre.
 * @returns La fausse liste et une fonction qui simule un changement.
 */
function fakeMediaQuery(dark: boolean) {
	const listeners: Array<(event: { matches: boolean }) => void> = []
	const query = {
		matches: dark,
		addEventListener: (_type: string, listener: (event: { matches: boolean }) => void) => listeners.push(listener),
	}
	return {
		query: query as unknown as MediaQueryList,
		/**
		 * Simule un changement de préférence.
		 *
		 * @param next Nouvelle valeur de la préférence sombre.
		 */
		change(next: boolean): void {
			query.matches = next
			listeners.forEach((listener) => listener({ matches: next }))
		},
	}
}

describe('thème', () => {
	it('choisit Catppuccin Mocha en sombre et Latte en clair', () => {
		expect(themeFor(true)).toBe('catppuccin-mocha')
		expect(themeFor(false)).toBe('catppuccin-latte')
	})

	it('pose le thème sur la racine et suit les changements de préférence', () => {
		const root = document.createElement('html')
		const media = fakeMediaQuery(true)

		installTheme(root, media.query)
		expect(root.getAttribute('data-webtui-theme')).toBe('catppuccin-mocha')

		media.change(false)
		expect(root.getAttribute('data-webtui-theme')).toBe('catppuccin-latte')
	})
})

describe('requêtes en cours', () => {
	it('compte les requêtes pendant leur exécution, succès ou échec', async () => {
		let succeed!: (value: string) => void
		let fail!: (reason: Error) => void
		const first = track(new Promise<string>((resolve) => { succeed = resolve }))
		const second = track(new Promise<string>((_resolve, reject) => { fail = reject }))
		expect(activeRequests.value).toBe(2)

		succeed('ok')
		await expect(first).resolves.toBe('ok')
		expect(activeRequests.value).toBe(1)

		fail(new Error('réseau'))
		await expect(second).rejects.toThrow('réseau')
		expect(activeRequests.value).toBe(0)
	})
})

describe('icônes', () => {
	it('emploie uniquement des glyphes Material Design de Nerd Fonts, sans doublon', () => {
		const codes = ICON_NAMES.map((name) => glyph(name).codePointAt(0) ?? 0)
		expect(ICON_NAMES.length).toBeGreaterThan(0)
		// Plage des glyphes nf-md-* de Nerd Fonts 3 (Material Design Icons).
		codes.forEach((code) => expect(code >= 0xF0001 && code <= 0xF1AF0).toBe(true))
		expect(new Set(codes).size).toBe(codes.length)
	})

	it('rend une icône décorative, ignorée des lecteurs d\'écran et sans texte', () => {
		const wrapper = mount(NfIcon, { props: { name: 'tests' } })
		const span = wrapper.get('span')

		expect(span.attributes('aria-hidden')).toBe('true')
		expect(span.attributes('data-glyph')).toBe(String.fromCodePoint(0xF13DA))
		expect(wrapper.text()).toBe('')
	})
})

describe('throbber', () => {
	it('affiche le spinner WebTUI et un libellé lisible par les lecteurs d\'écran', () => {
		const wrapper = mount(LoadingThrobber, { props: { label: 'Chargement…' } })

		expect(wrapper.get('[is-="spinner"]').attributes('aria-hidden')).toBe('true')
		expect(wrapper.get('.visually-hidden').text()).toBe('Chargement…')
	})

	it('peut afficher son libellé à côté du spinner', () => {
		const wrapper = mount(LoadingThrobber, { props: { label: 'Chargement…', visibleLabel: true } })

		expect(wrapper.find('.visually-hidden').exists()).toBe(false)
		expect(wrapper.text()).toBe('Chargement…')
	})
})
