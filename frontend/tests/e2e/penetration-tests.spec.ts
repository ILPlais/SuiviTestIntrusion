/**
 * Tests d'intrusion de bout en bout (engagement-2) : un administrateur crée une
 * organisation puis un test typé pour elle, retrouve ses cinq phases, change son
 * statut dans les deux sens et lit l'historique des transitions.
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'

const PASSWORD = 'girafe orange sous la pluie de mars'

/**
 * Crée un administrateur, active son compte et le connecte.
 *
 * @param page Page Playwright.
 */
async function signedInAdmin(page: Page): Promise<void> {
	const email = `e2e-test-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Admin Tests'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Admin Tests.')).toBeVisible()
}

test('un administrateur crée un test, change son statut et lit l\'historique', async ({ page }) => {
	await signedInAdmin(page)
	const organisation = `Assurance ${randomUUID().slice(0, 8)}`
	const reference = `PT-${randomUUID().slice(0, 8)}`

	await page.getByRole('link', { name: fr.nav.organisations }).click()
	await page.getByLabel(fr.organisations.name).fill(organisation)
	await page.getByRole('button', { name: fr.organisations.create }).click()
	await expect(page.getByRole('heading', { name: organisation })).toBeVisible()

	await page.getByRole('link', { name: fr.nav.tests }).click()
	await page.getByLabel(fr.tests.organisation).selectOption({ label: organisation })
	await page.getByLabel(fr.tests.reference).fill(reference)
	await page.getByLabel(fr.tests.title).fill('Test externe du site vitrine')
	await page.getByLabel(fr.tests.type).selectOption({ label: fr.tests.types.black_box })
	await page.getByLabel(fr.tests.exposure).selectOption({ label: fr.tests.exposures.external })
	await page.getByLabel(fr.tests.plannedStart).fill('2026-12-01T09:00')
	await page.getByLabel(fr.tests.plannedEnd).fill('2026-12-05T18:00')
	await expectNoAccessibilityViolations(page)
	await page.getByRole('button', { name: fr.tests.create }).click()

	await expect(page.getByRole('heading', { name: `${reference} — Test externe du site vitrine` })).toBeVisible()
	await expect(page.getByText(fr.tests.types.black_box)).toBeVisible()
	await expect(page.getByText('1 décembre 2026 à 09:00:00')).toBeVisible()
	await expect(page.getByRole('listitem').filter({ hasText: fr.tests.phaseNames.reconnaissance })).toBeVisible()
	await expect(page.getByRole('listitem').filter({ hasText: fr.tests.phaseNames.cleanup })).toBeVisible()

	await page.getByLabel(fr.tests.newStatus).selectOption({ label: fr.tests.statuses.in_progress })
	await page.getByRole('button', { name: fr.tests.applyStatus }).click()
	await expect(page.getByRole('status')).toHaveText(fr.tests.statusChanged)

	// Transitions libres : retour à « Planifié », motivé par un commentaire.
	await page.getByLabel(fr.tests.newStatus).selectOption({ label: fr.tests.statuses.planned })
	await page.getByLabel(fr.tests.comment).fill('Report demandé par le client.')
	await page.getByRole('button', { name: fr.tests.applyStatus }).click()

	const history = page.getByRole('listitem')
	await expect(history.filter({ hasText: 'de « En cours » à « Planifié » (Admin Tests)' }).filter({ hasText: 'Report demandé par le client.' })).toBeVisible()
	await expect(history.filter({ hasText: 'de « Planifié » à « En cours » (Admin Tests)' })).toBeVisible()
	await expect(history.filter({ hasText: 'créé au statut « Planifié » (Admin Tests)' })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	await page.getByRole('link', { name: fr.nav.tests }).click()
	await expect(page.getByRole('cell', { name: organisation })).toBeVisible()
	await expect(page.getByRole('link', { name: reference })).toBeVisible()
	await expectNoAccessibilityViolations(page)
})
