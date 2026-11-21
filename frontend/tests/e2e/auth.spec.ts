/**
 * Parcours d'authentification de bout en bout (identite-1) : la commande
 * d'installation crée un administrateur, la SPA active le compte (politique de
 * mots de passe, `auth` dérivé par Argon2id dans le Worker), puis connexion et
 * déconnexion. Le mot de passe ne doit jamais partir vers le serveur.
 */
import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'

const PASSWORD = 'girafe orange sous la pluie de mars'

/** Crée un administrateur par la commande Artisan et renvoie son adresse et le jeton du lien. */
function createAdmin(): { email: string, token: string } {
	const email = `e2e-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Testeur E2E'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1]
	if (!token) {
		throw new Error(`Lien d'activation introuvable dans : ${output}`)
	}

	return { email, token }
}

/** Active le compte du lien avec le mot de passe donné. */
async function activate(page: Page, token: string, password: string): Promise<void> {
	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(password)
	await page.getByLabel(fr.auth.confirmPassword).fill(password)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await expect(page).toHaveURL(/\/connexion\?activated=1$/)
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
	await expect(page.getByRole('button', { name: fr.auth.activate })).toBeDisabled()
})

test('les écrans de connexion et d\'activation ne présentent aucune violation d\'accessibilité détectable', async ({ page }) => {
	await page.goto('/connexion')
	await expect(page.getByRole('heading', { name: fr.auth.loginHeading })).toBeVisible()
	await expectNoAccessibilityViolations(page)

	const { token } = createAdmin()
	await page.goto(`/activation/${token}`)
	await expect(page.getByLabel(fr.auth.newPassword)).toBeVisible()
	await expectNoAccessibilityViolations(page)
})
