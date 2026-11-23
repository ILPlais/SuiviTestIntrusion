/**
 * Captures d'écran de la documentation (socle-8) : connexion, liste des tests
 * (pendant et après le chargement), fiche d'un test, chacune en thème clair
 * (Catppuccin Latte) et sombre (Mocha), pour 43.01 Aperçu de l'interface.
 *
 * Les données sont fictives, créées pour l'occasion dans la base locale. Lancer
 * par `npm run captures` (playwright.captures.config.ts).
 */
import { execFileSync } from 'node:child_process'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }

/** Dossier des captures : catégorie 43 de la documentation. */
const OUTPUT = fileURLToPath(new URL('../../../docs/40-49 Publication/43 Guide d\'utilisation/', import.meta.url))

const PASSWORD = 'girafe orange sous la pluie de mars'

/** Thèmes capturés, avec leur nom dans le fichier. */
const SCHEMES = [['light', 'clair'], ['dark', 'sombre']] as const

/**
 * Enregistre l'écran courant dans les deux thèmes.
 *
 * @param page Page Playwright.
 * @param name Nom de l'écran, repris dans le fichier (`43.01 <nom> (clair).png`).
 */
async function capture(page: Page, name: string): Promise<void> {
	for (const [scheme, label] of SCHEMES) {
		await page.emulateMedia({ colorScheme: scheme })
		await expect(page.locator('html')).toHaveAttribute('data-webtui-theme', scheme === 'dark' ? 'catppuccin-mocha' : 'catppuccin-latte')
		await page.screenshot({ path: `${OUTPUT}43.01 ${name} (${label}).png`, fullPage: true })
	}
}

test('captures de l\'interface pour la documentation', async ({ page }) => {
	mkdirSync(OUTPUT, { recursive: true })
	const suffix = String(Date.now()).slice(-4)

	await page.goto('/connexion')
	await expect(page.getByRole('status')).toHaveText(fr.health.ok)
	await capture(page, 'Connexion')

	const email = `captures-${suffix}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Camille Martin'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Camille Martin.')).toBeVisible()

	const organisation = `Banque Exemple ${suffix}`
	await page.getByRole('link', { name: fr.nav.organisations }).click()
	await page.getByLabel(fr.organisations.name).fill(organisation)
	await page.getByRole('button', { name: fr.organisations.create }).click()
	await expect(page.getByRole('heading', { name: organisation })).toBeVisible()

	await page.getByRole('link', { name: fr.nav.tests, exact: true }).click()
	await page.getByLabel(fr.tests.organisation).selectOption({ label: organisation })
	await page.getByLabel(fr.tests.reference).fill(`PT-2026-${suffix}`)
	await page.getByLabel(fr.tests.title).fill('Test externe du site vitrine')
	await page.getByLabel(fr.tests.type).selectOption({ label: fr.tests.types.grey_box })
	await page.getByLabel(fr.tests.exposure).selectOption({ label: fr.tests.exposures.external })
	await page.getByLabel(fr.tests.plannedStart).fill('2026-12-01T09:00')
	await page.getByLabel(fr.tests.plannedEnd).fill('2026-12-05T18:00')
	await page.getByRole('button', { name: fr.tests.create }).click()
	await expect(page.getByRole('heading', { name: `PT-2026-${suffix} — Test externe du site vitrine` })).toBeVisible()
	await page.getByLabel(fr.tests.newStatus).selectOption({ label: fr.tests.statuses.in_progress })
	await page.getByLabel(fr.tests.comment).fill('Accès VPN fourni par le client.')
	await page.getByRole('button', { name: fr.tests.applyStatus }).click()
	await expect(page.getByText(fr.tests.statusChanged)).toBeVisible()
	await capture(page, 'Fiche d\'un test')

	// Chargement retardé de la liste : throbbers de l'en-tête et de la liste visibles.
	await page.route('**/api/v1/tests?**', async (route) => {
		await new Promise((resolve) => setTimeout(resolve, 15_000))
		await route.continue()
	})
	await page.getByRole('link', { name: fr.nav.tests, exact: true }).click()
	await expect(page.getByText(fr.common.working)).toBeVisible()
	await expect(page.getByText(fr.common.loading)).toBeVisible()
	await capture(page, 'Chargement en cours')

	await page.unrouteAll({ behavior: 'ignoreErrors' })
	await page.reload()
	await expect(page.getByRole('link', { name: `PT-2026-${suffix}` })).toBeVisible()
	await expect(page.getByText(fr.common.working)).toBeHidden()
	await capture(page, 'Liste des tests')
})
