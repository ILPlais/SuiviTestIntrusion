/**
 * Références d'un test de bout en bout (engagement-1) : un administrateur crée
 * une organisation, rédige sa présentation en Markdown avec aperçu, puis remplit
 * la fiche de l'entreprise. Le HTML d'un Markdown n'est jamais exécuté (13.04).
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
	const email = `e2e-ref-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Admin Références'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Admin Références.')).toBeVisible()
}

test('un administrateur crée une organisation et rédige sa présentation en Markdown', async ({ page }) => {
	const dialogs: string[] = []
	page.on('dialog', async (dialog) => {
		dialogs.push(dialog.message())
		await dialog.dismiss()
	})
	await signedInAdmin(page)
	const name = `Banque ${randomUUID().slice(0, 8)}`

	await page.getByRole('link', { name: fr.nav.organisations }).click()
	await page.getByLabel(fr.organisations.name).fill(name)
	await page.getByRole('button', { name: fr.organisations.create }).click()
	await expect(page.getByRole('heading', { name })).toBeVisible()

	await page.getByRole('button', { name: fr.common.edit }).click()
	await page.getByLabel(fr.organisations.legalName).fill(`${name} SA`)
	await page.getByLabel(fr.organisations.presentation).fill('## Activité\n\nBanque **régionale**.\n\n<img src=x onerror="alert(1)">')
	await page.getByRole('button', { name: fr.markdown.preview }).click()
	await expect(page.locator('.markdown strong')).toHaveText('régionale')
	await expectNoAccessibilityViolations(page)
	await page.getByRole('button', { name: fr.markdown.edit }).click()
	await page.getByRole('button', { name: fr.common.save }).click()

	await expect(page.getByRole('status')).toHaveText(fr.common.saved)
	await expect(page.getByRole('heading', { name: 'Activité' })).toBeVisible()
	await expect(page.locator('.markdown img[onerror]')).toHaveCount(0)
	expect(dialogs).toEqual([])
	await expectNoAccessibilityViolations(page)
})

test('un administrateur remplit la fiche de l\'entreprise prestataire', async ({ page }) => {
	await signedInAdmin(page)

	await page.getByRole('link', { name: fr.nav.company }).click()
	await page.getByRole('button', { name: fr.common.edit }).click()
	await page.getByLabel(fr.company.name).fill('Audit & Co')
	await page.getByLabel(fr.company.presentation).fill('Tests d\'intrusion *depuis 2010*.')
	await page.getByRole('button', { name: fr.common.save }).click()

	await expect(page.getByRole('status')).toHaveText(fr.common.saved)
	await expect(page.locator('.markdown em')).toHaveText('depuis 2010')
	await expectNoAccessibilityViolations(page)
})
