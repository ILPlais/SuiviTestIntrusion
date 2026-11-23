/// <reference types="vitest/config" />
import { readFileSync } from 'node:fs'
import { defineConfig, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'

/**
 * Joint au build les licences de la police d'icônes (socle-8, 13.07) : l'OFL et
 * la CC BY 4.0 de certains glyphes exigent que leur texte accompagne la police
 * redistribuée, y compris dans l'image Docker.
 */
const iconFontLicense: Plugin = {
	name: 'licence-police-icones',
	apply: 'build',
	/** Écrit assets/LICENSE-police-icones.md à côté de la police compilée. */
	generateBundle() {
		this.emitFile({
			type: 'asset',
			fileName: 'assets/LICENSE-police-icones.md',
			source: readFileSync(new URL('./src/assets/fonts/LICENSE.md', import.meta.url), 'utf8'),
		})
	},
}

// En développement, /api et /sanctum sont relayés vers l'API Laravel (php artisan serve).
export default defineConfig({
	// Pas de contenu statique sérialisé en innerHTML : Trusted Types le refuserait (13.04).
	plugins: [vue({ template: { compilerOptions: { hoistStatic: false } } }), iconFontLicense],
	// Aucun fichier intégré en URL data: (WebAssembly d'argon2id compris) : la CSP de 13.04 les refuse.
	build: { assetsInlineLimit: 0 },
	server: {
		proxy: {
			'/api': 'http://localhost:8000',
			// Jeton CSRF de Sanctum (cookie XSRF-TOKEN), demandé avant chaque connexion.
			'/sanctum': 'http://localhost:8000',
		},
	},
	test: {
		environment: 'jsdom',
		include: ['tests/unit/**/*.test.ts'],
	},
})
