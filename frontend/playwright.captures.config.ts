/**
 * Captures d'écran de la documentation (socle-8, 43.01) : la SPA et l'API sont
 * démarrées comme pour les tests de bout en bout, puis un parcours enregistre
 * chaque écran en thème clair (Latte) et sombre (Mocha) dans
 * docs/40-49 Publication/43 Guide d'utilisation/.
 *
 * Usage : npm run captures (ce n'est pas un test : rien ne tourne en CI).
 */
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
	testDir: './tests/captures',
	testMatch: '*.capture.ts',
	retries: 0,
	// Parcours long (dérivation Argon2id, plusieurs écrans) : il faut de la marge sur un poste lent.
	timeout: 600_000,
	expect: { timeout: 120_000 },
	reporter: 'list',
	use: {
		baseURL: 'http://localhost:5173',
		locale: 'fr-FR',
		timezoneId: 'Europe/Paris',
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } } }],
	webServer: [
		{
			command: 'php artisan serve --port=8000',
			cwd: '../backend',
			env: { ...process.env as Record<string, string>, SANCTUM_STATEFUL_DOMAINS: 'localhost:5173', API_RATE_AUTH: '1000' },
			url: 'http://localhost:8000/up',
			reuseExistingServer: true,
			timeout: 120_000,
		},
		{
			command: 'npm run dev -- --port=5173 --strictPort',
			url: 'http://localhost:5173',
			reuseExistingServer: true,
			timeout: 120_000,
		},
	],
})
