/**
 * CSP et Trusted Types sur le build de production (socle-6, 13.04), sous Chromium
 * et Firefox : la SPA compilée est servie par Laravel avec ses en-têtes ; le
 * parcours complet (activation avec paire de clés, connexion et déverrouillage
 * de la clé dans le Worker crypto) ne déclenche aucune
 * violation inattendue, et une charge XSS saisie s'affiche comme du texte.
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { activateAccount } from '../e2e/activation'

const PASSWORD = 'girafe orange sous la pluie de mars'
const XSS = '<img src=x onerror="window.__xss=1">'

/** Violation relevée par l'événement securitypolicyviolation. */
interface Violation {
	directive: string
	blocked: string
	sample: string
}

test.beforeEach(async ({ page }) => {
	// Relevé des violations dans la page, avant tout script de la SPA.
	await page.addInitScript(() => {
		const store = window as unknown as { __violations: { directive: string, blocked: string, sample: string }[] }
		store.__violations = []
		document.addEventListener('securitypolicyviolation', (event) => {
			store.__violations.push({ directive: event.violatedDirective, blocked: event.blockedURI, sample: event.sample })
		})
	})
})

/**
 * Violations relevées, sans le refus attendu de la politique Trusted Types « vue ».
 *
 * @param page Page Playwright.
 * @returns Les violations inattendues.
 */
async function unexpectedViolations(page: Page): Promise<Violation[]> {
	const violations = await page.evaluate(() => (window as unknown as { __violations: Violation[] }).__violations)

	// Chromium et Firefox ne remplissent pas les mêmes champs pour ce refus.
	return violations.filter((violation) => {
		const policyCreation = violation.directive.startsWith('trusted-types') || violation.blocked === 'trusted-types-policy'
		const sample = violation.sample.trim()

		return !(policyCreation && (sample === '' || sample === 'vue'))
	})
}

test('la SPA compilée est servie avec la CSP de 13.04', async ({ page }) => {
	const response = await page.goto('/connexion')
	const policy = response?.headers()['content-security-policy'] ?? ''

	expect(policy).toContain("require-trusted-types-for 'script'")
	expect(policy).toContain("script-src 'self' 'wasm-unsafe-eval'")
	await expect(page.getByRole('heading', { name: fr.auth.loginHeading })).toBeVisible()
	expect(await unexpectedViolations(page)).toEqual([])
})

test('activation et connexion sans violation, charge XSS affichée comme texte', async ({ page }) => {
	const email = `e2e-csp-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, `--name=${XSS}`], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await activateAccount(page, token, PASSWORD)
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()

	// Le nom, une charge XSS, apparaît tel quel ; son gestionnaire ne s'exécute pas.
	await expect(page.getByText(`Bonjour ${XSS}.`)).toBeVisible()
	expect(await page.evaluate(() => (window as unknown as { __xss?: number }).__xss)).toBeUndefined()

	// La clé privée a été générée puis déverrouillée dans le Worker crypto, qui
	// charge openpgp.js depuis son propre fichier (ged-chiffree-1, 13.07).
	await page.getByRole('link', { name: fr.auth.security }).click()
	await expect(page.getByText(fr.vault.unlocked)).toBeVisible({ timeout: 30_000 })
	expect(await unexpectedViolations(page)).toEqual([])

	// Après rechargement, la session reste ouverte : les GET de même origine portent
	// un Referer malgré « Referrer-Policy: no-referrer », sans quoi Sanctum répond 401.
	await page.reload()
	await expect(page.getByRole('link', { name: fr.auth.security })).toBeVisible()
	await expect(page.getByRole('heading', { name: fr.auth.loginHeading })).toHaveCount(0)
})
