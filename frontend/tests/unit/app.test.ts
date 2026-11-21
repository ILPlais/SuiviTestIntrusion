import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import App from '../../src/App.vue'
import { i18n } from '../../src/i18n'
import fr from '../../src/i18n/locales/fr.json'

type Messages = { [key: string]: string | Messages }

/** Remplace chaque message par sa clé : tout texte affiché hors de ces marqueurs est écrit en dur. */
function pseudo(messages: Messages, prefix = ''): Messages {
	return Object.fromEntries(Object.entries(messages).map(([key, value]) => {
		const path = prefix ? `${prefix}.${key}` : key
		return [key, typeof value === 'string' ? `⟦${path}⟧` : pseudo(value, path)]
	}))
}

function stubHealth(status: number, body: unknown) {
	vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

describe('App', () => {
	beforeEach(() => vi.unstubAllGlobals())

	it("affiche l'état de l'API en français", async () => {
		stubHealth(200, { status: 'ok', database: 'ok', time: '2026-11-21T15:30:00Z' })
		const wrapper = mount(App, { global: { plugins: [createPinia(), i18n] } })
		await flushPromises()

		expect(wrapper.text()).toContain(fr.app.title)
		expect(wrapper.text()).toContain(fr.health.ok)
	})

	it("n'affiche aucune chaîne écrite en dur", async () => {
		stubHealth(503, { status: 'degraded', database: 'error', time: '2026-11-21T15:30:00Z' })
		const keysOnly = createI18n({ legacy: false, locale: 'fr', messages: { fr: pseudo(fr) } })
		const wrapper = mount(App, { global: { plugins: [createPinia(), keysOnly] } })
		await flushPromises()

		const leftovers = wrapper.text().replace(/⟦[\w.]+⟧/g, '').trim()
		expect(leftovers).toBe('')
	})
})
