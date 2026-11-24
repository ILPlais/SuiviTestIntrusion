/**
 * Parcours d'authentification de bout en bout (identite-1, ged-chiffree-1) : la
 * commande d'installation crée un administrateur, la SPA active le compte
 * (politique de mots de passe, `auth` dérivé par Argon2id dans le Worker, paire
 * de clés et clé de récupération ressaisie), puis connexion, déverrouillage de
 * la clé, changement de mot de passe et déconnexion. Ni le mot de passe ni la
 * clé de récupération ne doivent partir vers le serveur.
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'
import { activateAccount } from './activation'

const PASSWORD = 'girafe orange sous la pluie de mars'

/**
 * Crée un administrateur par la commande Artisan et renvoie son adresse et le jeton du lien.
 *
 * @returns L'adresse du compte et le jeton de son lien d'activation.
 */
function createAdmin(): { email: string, token: string } {
	const email = `e2e-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Testeur E2E'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1]
	if (!token) {
		throw new Error(`Lien d'activation introuvable dans : ${output}`)
	}

	return { email, token }
}

/**
 * Active le compte du lien avec le mot de passe donné.
 *
 * @param page Page Playwright.
 * @param token Jeton du lien d'activation.
 * @param password Mot de passe choisi.
 */
async function activate(page: Page, token: string, password: string): Promise<void> {
	await activateAccount(page, token, password)
}

test('un administrateur active son compte, se connecte puis se déconnecte', async ({ page }) => {
	const { email, token } = createAdmin()
	const bodies: string[] = []
	page.on('request', (request) => {
		bodies.push(request.postData() ?? '')
	})

	await activate(page, token, PASSWORD)
	await expect(page.getByText(fr.auth.activated)).toBeVisible()

	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()

	await expect(page.getByText('Bonjour Testeur E2E.')).toBeVisible()

	await page.getByRole('button', { name: fr.auth.logout }).click()
	await expect(page.getByRole('heading', { name: fr.auth.loginHeading })).toBeVisible()

	// Le mot de passe n'a jamais quitté le navigateur (13.03).
	expect(bodies.some((body) => body.includes(PASSWORD))).toBe(false)
})

test('la clé de récupération est ressaisie avant l\'activation et n\'est jamais envoyée', async ({ page }) => {
	const { email, token } = createAdmin()
	const bodies: string[] = []
	page.on('request', (request) => {
		bodies.push(request.postData() ?? '')
	})

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.enrollment.next }).click()
	const shown = page.getByTestId('recovery-key')
	await expect(shown).toBeVisible({ timeout: 30_000 })
	const recoveryKey = (await shown.textContent())?.trim() ?? ''
	expect(recoveryKey).toMatch(/^([0-9A-HJKMNP-TV-Z]{4}-){6}[0-9A-HJKMNP-TV-Z]{2}$/)
	await expectNoAccessibilityViolations(page)

	// Une clé fausse ou incomplète ne permet pas d'activer le compte.
	const activate = page.getByRole('button', { name: fr.auth.activate })
	await page.getByLabel(fr.recovery.retype).fill(recoveryKey.slice(0, -1))
	await expect(page.getByText('Il manque 1 caractère.')).toBeVisible()
	await expect(activate).toBeDisabled()
	await page.getByLabel(fr.recovery.retype).fill(recoveryKey.toLowerCase())
	await activate.click()
	await expect(page).toHaveURL(/\/connexion\?activated=1$/)

	// La clé n'a jamais été envoyée, sous aucune forme (13.03).
	const compact = recoveryKey.replace(/-/g, '')
	expect(bodies.some((body) => body.includes(compact) || body.includes(recoveryKey))).toBe(false)

	// À la connexion, la clé privée est déverrouillée dans le Worker ; l'écran Sécurité l'indique.
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Testeur E2E.')).toBeVisible()
	await page.getByRole('link', { name: fr.auth.security }).click()
	await expect(page.getByText(fr.vault.unlocked)).toBeVisible()
	await expect(page.getByTestId('key-fingerprint')).toHaveText(/^([0-9a-f]{4} ){15}[0-9a-f]{4}$/)
})

test('le mot de passe change sans changer de clé ; l\'ancien est refusé', async ({ page }) => {
	const { email, token } = createAdmin()
	const NEW_PASSWORD = 'zèbre bleu dansant sur la banquise'
	await activate(page, token, PASSWORD)
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Testeur E2E.')).toBeVisible()
	await page.getByRole('link', { name: fr.auth.security }).click()
	const fingerprint = await page.getByTestId('key-fingerprint').textContent()

	await page.getByLabel(fr.vault.currentPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.newPassword).fill(NEW_PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(NEW_PASSWORD)
	await page.getByRole('button', { name: fr.vault.change }).click()
	await expect(page.getByText(fr.vault.changed)).toBeVisible({ timeout: 30_000 })

	await page.getByRole('button', { name: fr.auth.logout }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByRole('alert')).toContainText('Connexion refusée')

	await page.getByLabel(fr.auth.password).fill(NEW_PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Testeur E2E.')).toBeVisible()
	await page.getByRole('link', { name: fr.auth.security }).click()
	await expect(page.getByText(fr.vault.unlocked)).toBeVisible()
	await expect(page.getByTestId('key-fingerprint')).toHaveText(fingerprint ?? '')
})

test('un mauvais mot de passe est refusé sans révéler la cause', async ({ page }) => {
	const { email, token } = createAdmin()
	await activate(page, token, PASSWORD)

	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill('un autre mot de passe assez long')
	await page.getByRole('button', { name: fr.auth.login }).click()

	await expect(page.getByRole('alert')).toContainText('Connexion refusée')
})

test('un mot de passe courant ne peut pas être choisi', async ({ page }) => {
	const { token } = createAdmin()
	await page.goto(`/activation/${token}`)

	await page.getByLabel(fr.auth.newPassword).fill('qazwsxedcrfvtgb')
	await page.getByLabel(fr.auth.confirmPassword).fill('qazwsxedcrfvtgb')

	await expect(page.getByText(fr.password.problems.common)).toBeVisible()
	await expect(page.getByRole('button', { name: fr.enrollment.next })).toBeDisabled()
})

test('les écrans de connexion et d\'activation ne présentent aucune violation d\'accessibilité détectable', async ({ page }) => {
	await page.goto('/connexion')
	await expect(page.getByRole('heading', { name: fr.auth.loginHeading })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	// Champs de même largeur (socle-8), quel que soit le paragraphe qui les entoure.
	const email = await page.getByLabel(fr.auth.email).boundingBox()
	const password = await page.getByLabel(fr.auth.password).boundingBox()
	expect(email?.width).toBeGreaterThan(0)
	expect(email?.width).toBe(password?.width)

	const { token } = createAdmin()
	await page.goto(`/activation/${token}`)
	await expect(page.getByLabel(fr.auth.newPassword)).toBeVisible()
	await expectNoAccessibilityViolations(page)
})
