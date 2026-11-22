/**
 * Double authentification de bout en bout (identite-3) : activation depuis
 * l'écran Sécurité (QR code et clé), codes de secours, puis connexion en deux
 * temps avec un code de secours. Les codes TOTP sont calculés ici selon la
 * RFC 6238 (HMAC-SHA1, 6 chiffres, pas de 30 s), comme Google Authenticator.
 */
import { execFileSync } from 'node:child_process'
import { createHmac, randomUUID } from 'node:crypto'
import { expect, test, type Page } from '@playwright/test'
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
import { expectNoAccessibilityViolations } from './accessibility'

const PASSWORD = 'girafe orange sous la pluie de mars'

/**
 * Décode une clé Base32 (RFC 4648, sans remplissage).
 *
 * @param secret Clé en Base32.
 * @returns Les octets de la clé.
 */
function base32Decode(secret: string): Buffer {
	const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
	let bits = ''
	for (const char of secret.replace(/=+$/, '').toUpperCase()) {
		bits += alphabet.indexOf(char).toString(2).padStart(5, '0')
	}
	const bytes = bits.match(/.{8}/g) ?? []

	return Buffer.from(bytes.map((byte) => parseInt(byte, 2)))
}

/**
 * Code TOTP courant pour une clé Base32.
 *
 * @param secret Clé en Base32.
 * @returns Le code à six chiffres.
 */
function totp(secret: string): string {
	const counter = Buffer.alloc(8)
	counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30)))
	const digest = createHmac('sha1', base32Decode(secret)).update(counter).digest()
	const offset = digest[digest.length - 1] & 0x0f
	const value = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000

	return value.toString().padStart(6, '0')
}

/**
 * Crée un administrateur, active son compte et le connecte ; renvoie son adresse.
 *
 * @param page Page Playwright.
 * @returns L'adresse du compte connecté.
 */
async function signedInAdmin(page: Page): Promise<string> {
	const email = `e2e-2fa-${randomUUID()}@example.org`
	const output = execFileSync('php', ['artisan', 'suivi:create-admin', email, '--name=Testeuse 2FA'], { cwd: '../backend', encoding: 'utf8' })
	const token = /\/activation\/([A-Za-z0-9]{64})/.exec(output)?.[1] ?? ''

	await page.goto(`/activation/${token}`)
	await page.getByLabel(fr.auth.newPassword).fill(PASSWORD)
	await page.getByLabel(fr.auth.confirmPassword).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.activate }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()
	await expect(page.getByText('Bonjour Testeuse 2FA.')).toBeVisible()

	return email
}

test('activation de la double authentification, puis connexion par code de secours', async ({ page }) => {
	const email = await signedInAdmin(page)

	await page.getByRole('link', { name: fr.auth.security }).click()
	await page.getByRole('button', { name: fr.security.enable }).click()
	await expect(page.getByRole('img', { name: fr.security.qrLabel })).toBeVisible()

	const secret = (await page.getByTestId('totp-secret').textContent())?.trim() ?? ''
	expect(secret).toMatch(/^[A-Z2-7]{32}$/)
	await page.getByLabel(fr.security.confirmCode).fill(totp(secret))
	await page.getByRole('button', { name: fr.security.confirm }).click()

	const codes = page.getByTestId('recovery-codes').locator('code')
	await expect(codes).toHaveCount(10)
	const recovery = (await codes.first().textContent()) ?? ''
	await page.getByRole('button', { name: fr.security.done }).click()
	await expect(page.getByText(fr.security.enabled)).toBeVisible()

	await page.getByRole('button', { name: fr.auth.logout }).click()
	await page.getByLabel(fr.auth.email).fill(email)
	await page.getByLabel(fr.auth.password).fill(PASSWORD)
	await page.getByRole('button', { name: fr.auth.login }).click()

	// Second temps : le code TOTP de la confirmation ne peut pas resservir ; le code de secours, si.
	await expect(page.getByRole('heading', { name: fr.auth.twoFactorHeading })).toBeVisible()
	await page.getByRole('button', { name: fr.auth.useRecoveryCode }).click()
	await page.getByLabel(fr.auth.recoveryCode).fill(recovery)
	await page.getByRole('button', { name: fr.auth.verify }).click()

	await expect(page.getByText('Bonjour Testeuse 2FA.')).toBeVisible()
})

test('l\'écran Sécurité ne présente aucune violation d\'accessibilité détectable', async ({ page }) => {
	await signedInAdmin(page)

	await page.getByRole('link', { name: fr.auth.security }).click()
	await page.getByRole('button', { name: fr.security.enable }).click()
	await expect(page.getByRole('img', { name: fr.security.qrLabel })).toBeVisible()

	await expectNoAccessibilityViolations(page)
})
