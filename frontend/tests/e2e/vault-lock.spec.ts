/**
 * Clé en mémoire de bout en bout (ged-chiffree-2) : l'en-tête indique l'état du
 * coffre et le verrouille à la demande ; la mise en veille de l'onglet le
 * verrouille aussi ; le mot de passe le déverrouille à nouveau.
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { activateAccount } from './activation'

const PASSWORD = 'colibri jaune sur la branche du saule'

/**
 * Déverrouille le coffre depuis l'écran Sécurité.
 *
 * @param page Page Playwright.
 */
async function unlock(page: Page): Promise<void> {
	await page.locator('#vault-password').fill(PASSWORD)
	await page.getByRole('button', { name: fr.vault.unlock }).click()
	await expect(page.getByText(fr.vault.unlocked)).toBeVisible({ timeout: 30_000 })
}

test('le coffre se verrouille à la demande et à la mise en veille de l\'onglet', async ({ page }) => {
	test.setTimeout(90_000)
	const email = `e2e-lock-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Coffre E2E'], { cwd: '../backend', encoding: 'utf8' })
	await activateAccount(page, /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? '', PASSWORD)

	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText(fr.vault.stateUnlocked)).toBeVisible({ timeout: 30_000 })

	// Verrouillage à la demande : le Worker est arrêté, l'en-tête mène au déverrouillage.
	await page.getByRole('button', { name: fr.vault.lock }).click()
	await page.getByRole('link', { name: fr.vault.stateLocked }).click()
	await expect(page.getByText(fr.vault.lockReasons.manual)).toBeVisible()
	await unlock(page)

	// Mise en veille de l'onglet par le navigateur (Page Lifecycle API).
	await page.evaluate(() => document.dispatchEvent(new Event('freeze')))
	await expect(page.getByText(fr.vault.lockReasons.sleep)).toBeVisible()
	await expect(page.getByRole('link', { name: fr.vault.stateLocked })).toBeVisible()
	await unlock(page)
	await expect(page.getByText(fr.vault.stateUnlocked)).toBeVisible()
})
