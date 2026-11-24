/**
 * Parcours des vulnérabilités de bout en bout (vulnerabilites-3 à 5,
 * resultats-1) : un administrateur documente un constat, le cote en CVSS v3.1
 * par les boutons des métriques (aperçu du score), note son impact business,
 * rédige la remédiation, puis lit le risque global sur la fiche du test.
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
	const email = `e2e-vuln-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Admin Vulnérabilités'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Admin Vulnérabilités.')).toBeVisible()
}

/**
 * Choisit la valeur d'une métrique CVSS dans son groupe de boutons.
 *
 * @param page Page Playwright.
 * @param metric Métrique (AV, AC…).
 * @param label Libellé de la valeur, avec sa lettre : « Réseau (N) ».
 */
async function chooseMetric(page: Page, metric: keyof typeof fr.vulnerabilities.metrics, label: string): Promise<void> {
	await page.getByRole('group', { name: fr.vulnerabilities.metrics[metric] }).getByText(label).click()
}

test('un administrateur cote une vulnérabilité, rédige sa remédiation et lit le risque du test', async ({ page }) => {
	await signedInAdmin(page)
	const organisation = `Banque ${randomUUID().slice(0, 8)}`
	const reference = `PT-${randomUUID().slice(0, 8)}`

	await page.getByRole('link', { name: fr.nav.organisations }).click()
	await page.getByLabel(fr.organisations.name).fill(organisation)
	await page.getByRole('button', { name: fr.organisations.create }).click()
	await expect(page.getByRole('heading', { name: organisation })).toBeVisible()

	await page.getByRole('link', { name: fr.nav.tests, exact: true }).click()
	await page.getByRole('button', { name: fr.tests.newTest }).click()
	await page.getByLabel(fr.tests.organisation, { exact: true }).selectOption({ label: organisation })
	await page.getByLabel(fr.tests.reference, { exact: true }).fill(reference)
	await page.getByLabel(fr.tests.title, { exact: true }).fill('Test externe du site vitrine')
	await page.getByRole('button', { name: fr.tests.create }).click()
	await expect(page.getByRole('heading', { name: `${reference} — Test externe du site vitrine` })).toBeVisible()
	await expect(page.getByText(fr.risk.none).first()).toBeVisible()

	// Constat : création rapide, puis la fiche s'ouvre.
	await page.getByRole('link', { name: fr.testMenu.vulnerabilities, exact: true }).click()
	await page.getByLabel(fr.vulnerabilities.title).fill('Exécution de code à distance sur le CMS')
	await page.getByRole('button', { name: fr.vulnerabilities.create }).click()
	await expect(page.getByRole('heading', { name: 'V-001 — Exécution de code à distance sur le CMS' })).toBeVisible()

	// Constat, puis cotation : AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H = 9,8, aperçu avant l'enregistrement.
	await page.getByLabel(fr.vulnerabilities.cveIds).fill('CVE-2026-0042')
	await page.getByRole('tab', { name: fr.vulnerabilities.tabs.rating }).click()
	await chooseMetric(page, 'AV', 'Réseau (N)')
	await chooseMetric(page, 'AC', 'Faible (L)')
	await chooseMetric(page, 'PR', 'Aucun (N)')
	await chooseMetric(page, 'UI', 'Aucune (N)')
	await chooseMetric(page, 'S', 'Inchangée (U)')
	await chooseMetric(page, 'C', 'Élevé (H)')
	await chooseMetric(page, 'I', 'Élevé (H)')
	await chooseMetric(page, 'A', 'Élevé (H)')
	await expect(page.getByText('Score calculé : 9,8 (Critique)')).toBeVisible()
	await page.getByLabel(fr.vulnerabilities.axes.operational).selectOption({ index: 3 })
	await expectNoAccessibilityViolations(page)
	await page.getByRole('button', { name: fr.vulnerabilities.save }).click()
	await expect(page.getByText(fr.common.saved)).toBeVisible()
	await expect(page.getByText('Critique 9,8 (CVSS 3.1)')).toBeVisible()

	// Remédiation : recommandations et statut ; priorité suggérée P1.
	await page.getByRole('tab', { name: fr.vulnerabilities.tabs.remediation }).click()
	await page.getByLabel(fr.vulnerabilities.remediation.recommendations).fill('Mettre à jour le CMS en 4.2.3.')
	await page.getByLabel(fr.vulnerabilities.remediation.status).selectOption({ label: fr.vulnerabilities.remediation.statuses.in_progress })
	await page.getByRole('button', { name: fr.vulnerabilities.remediation.save }).click()
	await expect(page.getByText(fr.common.saved)).toBeVisible()
	await expect(page.getByRole('definition').filter({ hasText: fr.vulnerabilities.remediation.priorities.P1 })).toBeVisible()
	await expect(page.getByRole('definition').filter({ hasText: fr.vulnerabilities.remediation.statuses.in_progress })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	// Liste du test, puis synthèse du risque sur la fiche.
	await page.getByRole('link', { name: fr.testMenu.vulnerabilities, exact: true }).click()
	await expect(page.getByRole('cell', { name: 'Critique 9,8' })).toBeVisible()
	await page.getByRole('link', { name: fr.testMenu.synthesis, exact: true }).click()
	await expect(page.getByRole('heading', { name: fr.risk.maxScore })).toBeVisible()
	await expect(page.getByText('Risque : Critique 9,8', { exact: true })).toBeVisible()
	await expect(page.getByRole('definition').filter({ hasText: '3 — Dégâts majeurs sur la production' })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	await page.emulateMedia({ colorScheme: 'dark' })
	await expect(page.locator('html')).toHaveAttribute('data-webtui-theme', 'catppuccin-mocha')
	await expectNoAccessibilityViolations(page)
})
