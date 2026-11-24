/**
 * Périmètre réseau de bout en bout (engagement-4) : un administrateur crée une
 * organisation et un test, puis déclare une machine et son service, une machine
 * exclue avec son commentaire et un compte de test, sans aucun secret.
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
	const email = `e2e-scope-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Admin Périmètre'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Admin Périmètre.')).toBeVisible()
}

test('un administrateur déclare le périmètre réseau d\'un test', async ({ page }) => {
	await signedInAdmin(page)
	const organisation = `Banque ${randomUUID().slice(0, 8)}`
	const reference = `PT-${randomUUID().slice(0, 8)}`

	await page.getByRole('link', { name: fr.nav.organisations }).click()
	await page.getByLabel(fr.organisations.name).fill(organisation)
	await page.getByRole('button', { name: fr.organisations.create }).click()
	await expect(page.getByRole('heading', { name: organisation })).toBeVisible()

	await page.getByRole('link', { name: fr.nav.tests, exact: true }).click()
	await page.getByLabel(fr.tests.organisation).selectOption({ label: organisation })
	await page.getByLabel(fr.tests.reference).fill(reference)
	await page.getByLabel(fr.tests.title).fill('Test externe du site vitrine')
	await page.getByRole('button', { name: fr.tests.create }).click()
	await expect(page.getByRole('heading', { name: `${reference} — Test externe du site vitrine` })).toBeVisible()

	await page.getByRole('link', { name: fr.tests.scopeLink }).click()
	await expect(page.getByText(fr.scope.noSecret)).toBeVisible()

	// Machine incluse, avec ses plages interdites.
	const addHost = page.getByRole('group', { name: fr.scope.addHost })
	await addHost.getByLabel(fr.scope.address).fill('203.0.113.10')
	await addHost.getByLabel(fr.scope.hostname).fill('srv-web01')
	await addHost.getByLabel(fr.scope.constraints).fill('Pas de test de 12 h à 14 h')
	await addHost.getByRole('button', { name: fr.scope.addHost }).click()
	await expect(page.getByRole('rowheader', { name: /srv-web01/ })).toBeVisible()
	await expect(page.getByRole('cell', { name: 'Pas de test de 12 h à 14 h' })).toBeVisible()

	// Service de la machine.
	const addService = page.getByRole('group', { name: fr.scope.addService })
	await addService.getByLabel(fr.scope.port).fill('443')
	await addService.getByLabel(fr.scope.serviceName).fill('https')
	await addService.getByRole('button', { name: fr.scope.addService }).click()
	await expect(page.getByRole('listitem').filter({ hasText: '443/tcp https' })).toBeVisible()

	// Machine exclue, avec son commentaire.
	await addHost.getByLabel(fr.scope.address).fill('203.0.113.30')
	await addHost.getByLabel(fr.scope.hostname).fill('srv-paiement')
	await addHost.getByLabel(fr.scope.comment).fill('Prestataire tiers : hors contrat')
	await addHost.getByLabel(fr.scope.includedField).uncheck()
	await addHost.getByRole('button', { name: fr.scope.addHost }).click()
	const excluded = page.getByRole('row').filter({ hasText: 'srv-paiement' })
	await expect(excluded.getByRole('cell', { name: fr.scope.excluded, exact: true })).toBeVisible()
	await expect(excluded.getByRole('cell', { name: 'Prestataire tiers : hors contrat' })).toBeVisible()

	// Compte de test rattaché à la machine : identifiant et rôle seulement.
	const addAccount = page.getByRole('group', { name: fr.scope.addAccount })
	await addAccount.getByLabel(fr.scope.username).fill('client.test1')
	await addAccount.getByLabel(fr.scope.roleDescription).fill('Client particulier')
	await addAccount.getByLabel(fr.scope.host).selectOption({ label: 'srv-web01' })
	await addAccount.getByRole('button', { name: fr.scope.addAccount }).click()
	const account = page.getByRole('row').filter({ hasText: 'client.test1' })
	await expect(account.getByRole('cell', { name: 'Client particulier' })).toBeVisible()
	await expect(account.getByRole('cell', { name: 'srv-web01' })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	// La machine exclue revient dans le périmètre.
	await page.getByRole('button', { name: fr.scope.include.replace('{label}', 'srv-paiement') }).click()
	await expect(excluded.getByRole('cell', { name: fr.scope.included, exact: true })).toBeVisible()

	// Même écran en thème sombre (Mocha).
	await page.emulateMedia({ colorScheme: 'dark' })
	await expect(page.locator('html')).toHaveAttribute('data-webtui-theme', 'catppuccin-mocha')
	await expectNoAccessibilityViolations(page)
})
