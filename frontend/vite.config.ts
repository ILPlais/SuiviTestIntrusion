/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// En développement, /api et /sanctum sont relayés vers l'API Laravel (php artisan serve).
export default defineConfig({
	plugins: [vue()],
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
