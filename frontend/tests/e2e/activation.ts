/**
 * Activation d'un compte par la SPA, commune aux parcours de bout en bout
 * (identite-1, ged-chiffree-1) : mot de passe, génération de la paire de clés
 * dans le Worker crypto, puis clé de récupération lue à l'écran et ressaisie.
 */
import { expect, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }

/**
 * Active le compte du lien et renvoie la clé de récupération affichée.
 *
 * @param page Page Playwright.
 * @param token Jeton du lien d'activation.
 * @param password Mot de passe choisi.
 * @returns La clé de récupération, telle qu'affichée (groupes de quatre).
 */
export async function activateAccount(page: Page, token: string, password: string): Promise<string> {
	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(password)
	await page.getByLabel(fr.auth.confirmPassword).fill(password)
	await page.getByRole('button', { name: fr.enrollment.next }).click()

	// Génération de la paire et dérivations Argon2id : quelques secondes.
	const shown = page.getByTestId('recovery-key')
	await expect(shown).toBeVisible({ timeout: 30_000 })
	const recoveryKey = (await shown.textContent())?.trim() ?? ''
	await expect(page.getByRole('button', { name: fr.auth.activate })).toBeDisabled()

	// Ressaisie en minuscules et sans tirets : casse et séparateurs sont ignorés.
	await page.getByLabel(fr.recovery.retype).fill(recoveryKey.toLowerCase().replace(/-/g, ''))
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await expect(page).toHaveURL(/\/connexion\?activated=1$/)

	return recoveryKey
}
