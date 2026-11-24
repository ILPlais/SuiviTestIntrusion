/**
 * Cycle de vie des comptes de bout en bout (identite-7, écran 16 de 15.01) :
 * un administrateur invite un compte et lui transmet le lien affiché ; le
 * titulaire s'enrôle ; l'administrateur émet un lien de réinitialisation, que
 * le titulaire suit avec sa clé de récupération ; puis désactivation (connexion
 * refusée) et suppression après saisie de « SUPPRIMER ».
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'
import { activateAccount } from './activation'

const ADMIN_PASSWORD = 'girafe orange sous la pluie de mars'
const USER_PASSWORD = 'tortue verte au bord du canal'
const NEW_PASSWORD = 'mouette grise sur le toit du port'

/**
 * Connexion par l'écran de connexion.
 *
 * @param page Page Playwright.
 * @param email Adresse du compte.
 * @param password Mot de passe.
 */
async function login(page: Page, email: string, password: string): Promise<void> {
	await page.goto('/connexion')
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(password)
	await page.getByRole('button', { name: fr.auth.login }).click()
}

/**
 * Lien à usage unique affiché à l'administrateur.
 *
 * @param page Page de l'administrateur.
 * @param kind Page visée par le lien : activation ou réinitialisation.
 * @returns Le chemin du lien (sans l'origine).
 */
async function issuedPath(page: Page, kind: 'activation' | 'reinitialisation'): Promise<string> {
	const field = page.getByTestId('issued-link')
	// Le champ peut encore montrer le lien précédent : on attend celui du bon type.
	await expect(field).toHaveValue(new RegExp(`/${kind}/[A-Za-z0-9]{64}$`))

	return new URL(await field.inputValue()).pathname
}

test('un administrateur invite, réinitialise, désactive puis supprime un compte', async ({ page, browser }) => {
	test.setTimeout(120_000)
	const adminEmail = `e2e-admin-${randomUUID()}@example.org`
	const userEmail = `e2e-user-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', adminEmail, '--name=Admin E2E'], { cwd: '../backend', encoding: 'utf8' })
	const adminToken = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await activateAccount(page, adminToken, ADMIN_PASSWORD)
	await login(page, adminEmail, ADMIN_PASSWORD)
	// La connexion se termine sur l'accueil, une fois la clé privée déverrouillée.
	await expect(page.getByText('Bonjour Admin E2E.')).toBeVisible({ timeout: 30_000 })
	await page.getByRole('link', { name: fr.nav.users }).click()

	// Invitation d'un administrateur d'instance : le lien s'affiche, aucun e-mail n'est envoyé.
	await page.getByRole('tab', { name: /Invitations/ }).click()
	await page.getByLabel(fr.auth.email).fill(userEmail)
	await page.getByLabel(fr.accounts.nameOptional).fill('Camille E2E')
	await page.getByLabel(fr.accounts.inviteAdmin).check()
	await page.getByRole('button', { name: fr.accounts.sendInvitation, exact: true }).click()
	const invitationPath = await issuedPath(page, 'activation')
	await expect(page.locator(`tr[data-email="${userEmail}"]`)).toBeVisible()
	await expectNoAccessibilityViolations(page)

	// Le titulaire s'enrôle dans un autre navigateur.
	const other = await browser.newContext()
	const userPage = await other.newPage()
	const recoveryKey = await activateAccount(userPage, invitationPath.split('/').pop() ?? '', USER_PASSWORD)

	// L'administrateur émet un lien de réinitialisation du mot de passe.
	await page.getByRole('tab', { name: /Utilisateurs/ }).click()
	const row = page.locator(`tr[data-email="${userEmail}"]`)
	await expect(row).toContainText(fr.accounts.statuses.active)
	await row.getByRole('button', { name: fr.accounts.resetPassword }).click()
	const resetPath = await issuedPath(page, 'reinitialisation')

	// Le titulaire suit le lien avec sa clé de récupération et choisit un nouveau mot de passe.
	await userPage.goto(resetPath)
	await userPage.getByLabel(fr.reset.recoveryKey).fill(recoveryKey)
	await userPage.getByLabel(fr.auth.newPassword).fill(NEW_PASSWORD)
	await userPage.getByLabel(fr.auth.confirmPassword).fill(NEW_PASSWORD)
	await userPage.getByRole('button', { name: fr.enrollment.next }).click()
	const shown = userPage.getByTestId('recovery-key')
	await expect(shown).toBeVisible({ timeout: 30_000 })
	const newRecoveryKey = (await shown.textContent())?.trim() ?? ''
	expect(newRecoveryKey).not.toBe(recoveryKey)
	await userPage.getByLabel(fr.recovery.retype).fill(newRecoveryKey)
	await userPage.getByRole('button', { name: fr.reset.finish }).click()
	await expect(userPage.getByText(fr.reset.done)).toBeVisible()

	// Le nouveau mot de passe ouvre la session et la clé privée ; l'ancien est refusé.
	await login(userPage, userEmail, USER_PASSWORD)
	await expect(userPage.getByRole('alert')).toBeVisible()
	await login(userPage, userEmail, NEW_PASSWORD)
	await expect(userPage.getByText('Bonjour Camille E2E.')).toBeVisible({ timeout: 30_000 })
	await userPage.getByRole('link', { name: fr.auth.security }).click()
	await expect(userPage.getByText(fr.vault.unlocked)).toBeVisible({ timeout: 30_000 })

	// Désactivation : la session ouverte est fermée, la connexion refusée.
	await row.getByRole('button', { name: fr.accounts.deactivate }).click()
	await expect(row).toContainText(fr.accounts.statuses.deactivated)
	await login(userPage, userEmail, NEW_PASSWORD)
	await expect(userPage.getByText('Ce compte est désactivé. Adressez-vous à un administrateur.')).toBeVisible()
	await other.close()

	// Suppression après saisie du mot de confirmation : le compte quitte la liste.
	await row.getByRole('button', { name: fr.accounts.delete }).click()
	const confirm = page.getByRole('button', { name: fr.accounts.deleteForever })
	await expect(confirm).toBeDisabled()
	await page.getByLabel('Pour confirmer, saisir SUPPRIMER').fill('SUPPRIMER')
	await confirm.click()
	await expect(row).toHaveCount(0)
})
