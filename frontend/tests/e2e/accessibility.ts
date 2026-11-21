/**
 * Contrôle d'accessibilité automatique (13.07) : axe, règles WCAG 2.2 A et AA.
 *
 * En V1, ce contrôle signale les écarts détectables automatiquement ; la
 * conformité AA complète, avec audit, relève de exploitation-7 (V2).
 */
import AxeBuilder from '@axe-core/playwright'
import { expect, type Page } from '@playwright/test'

/**
 * Échoue si axe trouve une violation sur la page affichée.
 *
 * @param page Page Playwright déjà chargée.
 */
export async function expectNoAccessibilityViolations(page: Page): Promise<void> {
	const results = await new AxeBuilder({ page })
		.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
		.analyze()
	expect(results.violations.map((v) => `${v.id} : ${v.help}`)).toEqual([])
}
