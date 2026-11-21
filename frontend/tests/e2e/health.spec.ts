/**
 * Parcours de base : la SPA joint l'API Laravel et affiche l'état de l'instance.
 */
import { expect, test } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'

test('la page d\'accueil affiche l\'état de l\'API en français', async ({ page }) => {
	await page.goto('/')

	await expect(page).toHaveTitle(fr.app.title)
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(fr.app.title)
	await expect(page.getByRole('status')).toHaveText(fr.health.ok)
})

test('la page d\'accueil ne présente aucune violation d\'accessibilité détectable', async ({ page }) => {
	await page.goto('/')
	await expect(page.getByRole('status')).toHaveText(fr.health.ok)

	await expectNoAccessibilityViolations(page)
})
