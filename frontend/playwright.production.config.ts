/**
 * Tests de bout en bout du build de production (socle-6) : la SPA compilée est
 * copiée dans backend/public et servie par Laravel avec ses en-têtes de sécurité
 * (CSP de 13.04, Trusted Types), sous Chromium et Firefox.
 *
 * Préalable : npm run build && cp --recursive dist/. ../backend/public/
 */
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
	testDir: './tests/e2e-production',
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['github'], ['html', { open: 'never', outputFolder: 'playwright-report-production' }]] : 'list',
	use: {
		baseURL: 'http://localhost:8000',
		trace: 'on-first-retry',
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
		{ name: 'firefox', use: { ...devices['Desktop Firefox'] } },
	],
	webServer: {
		command: 'php artisan serve --port=8000',
		cwd: '../backend',
		url: 'http://localhost:8000/up',
		reuseExistingServer: !process.env.CI,
		timeout: 120_000,
		// Les parcours enchaînent plus de connexions que la limite Auth de production.
		env: { ...process.env as Record<string, string>, API_RATE_AUTH: '1000', API_RATE_STANDARD: '1000' },
	},
})
