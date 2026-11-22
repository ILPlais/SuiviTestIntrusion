/**
 * Composant racine (socle-1, identite-1) : textes en français, aucune chaîne
 * écrite en dur, état de l'instance visible sans connexion, redirection vers la
 * connexion quand la session est absente.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Plugin } from 'vue'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory } from 'vue-router'
import App from '../../src/App.vue'
import { i18n } from '../../src/i18n'
import fr from '../../src/i18n/locales/fr.json'
import { createAppRouter } from '../../src/router'

type Messages = { [key: string]: string | Messages }

/**
 * Remplace chaque message par sa clé : tout texte affiché hors de ces marqueurs est écrit en dur.
 *
 * @param messages Messages d'origine.
 * @param prefix Préfixe de clé (récursion).
 * @returns Les messages remplacés par leurs clés.
 */
function pseudo(messages: Messages, prefix = ''): Messages {
	return Object.fromEntries(Object.entries(messages).map(([key, value]) => {
		const path = prefix ? `${prefix}.${key}` : key
		return [key, typeof value === 'string' ? `⟦${path}⟧` : pseudo(value, path)]
	}))
}

/**
 * Simule l'API : santé donnée, aucune session ouverte.
 *
 * @param status Statut renvoyé par /health.
 * @param health Corps renvoyé par /health.
 */
function stubApi(status: number, health: unknown) {
	vi.stubGlobal('fetch', vi.fn(async (url: string) => url.includes('/health')
		? new Response(JSON.stringify(health), { status })
		: new Response(JSON.stringify({ code: 'unauthenticated', message: '', details: {} }), { status: 401 })))
}

/**
 * Monte l'application sur la route `/`, avec le jeu de messages donné.
 *
 * @param messages Plugin vue-i18n à utiliser.
 * @returns Le composant monté.
 */
async function mountApp(messages: Plugin) {
	const pinia = createPinia()
	setActivePinia(pinia)
	const router = createAppRouter(createMemoryHistory())
	const wrapper = mount(App, { global: { plugins: [pinia, messages, router] } })
	await router.push('/')
	await router.isReady()
	await flushPromises()

	return { wrapper, router }
}

describe('App', () => {
	beforeEach(() => vi.unstubAllGlobals())

	it("affiche l'état de l'API en français, même sans session", async () => {
		stubApi(200, { status: 'ok', database: 'ok', time: '2026-11-21T15:30:00Z' })
		const { wrapper } = await mountApp(i18n)

		expect(wrapper.text()).toContain(fr.app.title)
		expect(wrapper.text()).toContain(fr.health.ok)
	})

	it('renvoie vers la connexion quand la session est absente', async () => {
		stubApi(200, { status: 'ok', database: 'ok', time: '2026-11-21T15:30:00Z' })
		const { wrapper, router } = await mountApp(i18n)

		expect(router.currentRoute.value.name).toBe('login')
		expect(router.currentRoute.value.query.redirect).toBe('/')
		expect(wrapper.text()).toContain(fr.auth.loginHeading)
	})

	it("n'affiche aucune chaîne écrite en dur", async () => {
		stubApi(503, { status: 'degraded', database: 'error', time: '2026-11-21T15:30:00Z' })
		const keysOnly = createI18n({ legacy: false, locale: 'fr', messages: { fr: pseudo(fr) } })
		const { wrapper } = await mountApp(keysOnly)

		const leftovers = wrapper.text().replace(/⟦[\w.]+⟧/g, '').trim()
		expect(leftovers).toBe('')
	})
})
