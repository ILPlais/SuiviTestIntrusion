/**
 * Tests de bout en bout (31.01) : l'API Laravel et la SPA sont démarrées
 * ensemble, puis pilotées par Chromium.
 */
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
	testDir: './tests/e2e',
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL: 'http://localhost:5173',
		trace: 'on-first-retry',
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
	webServer: [
		{
			command: 'php artisan serve --port=8000',
			cwd: '../backend',
			url: 'http://localhost:8000/up',
			reuseExistingServer: !process.env.CI,
			timeout: 120_000,
		},
		{
			command: 'npm run dev -- --port=5173 --strictPort',
			url: 'http://localhost:5173',
			reuseExistingServer: !process.env.CI,
			timeout: 120_000,
		},
	],
})
