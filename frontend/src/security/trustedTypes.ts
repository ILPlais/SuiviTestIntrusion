/**
 * Politiques Trusted Types de la SPA (socle-6, 13.04).
 *
 * La CSP impose `require-trusted-types-for 'script'` : aucune chaîne brute
 * n'atteint `innerHTML`, `script.src` ou `new Worker`. Politiques autorisées par
 * la CSP : `sti-render` (rendu assaini par DOMPurify, pour PrismJS et Mermaid),
 * `sti-worker` (URL du Worker crypto), `dompurify` (créée par DOMPurify) et
 * `default` (filet pour les bibliothèques qui écrivent du HTML brut : assainit,
 * refuse tout script et toute URL de script hors du Worker crypto). La politique
 * `vue`, qui laisserait tout passer, est refusée.
 */
import DOMPurify from 'dompurify'

/** Politique Trusted Types, sous la forme qu'en donne le navigateur. */
interface TrustedTypePolicyLike {
	createHTML(input: string): unknown
	createScriptURL(input: string): unknown
}

/** Fabrique Trusted Types du navigateur (absente là où l'API n'existe pas). */
interface TrustedTypesFactory {
	createPolicy(name: string, rules: {
		createHTML?: (input: string) => string
		createScript?: (input: string) => string | null
		createScriptURL?: (input: string) => string | null
	}): TrustedTypePolicyLike
}

/** Politique de rendu, créée une seule fois. */
let renderPolicy: TrustedTypePolicyLike | null = null

/**
 * Vrai si l'URL désigne le Worker crypto du build, servi par l'origine de la page.
 *
 * @param url URL demandée, absolue ou relative.
 * @param origin Origine de la page (ex. `https://pentest.example.org`).
 * @returns Vrai pour `/assets/crypto.worker-<empreinte>.js` sur la même origine.
 */
export function isCryptoWorkerUrl(url: string, origin: string): boolean {
	try {
		const parsed = new URL(url, origin)

		return parsed.origin === origin && /^\/assets\/crypto\.worker-[\w-]+\.js$/.test(parsed.pathname)
	} catch {
		return false
	}
}

/**
 * Assainit un fragment HTML : aucun script, aucun gestionnaire d'événement.
 *
 * @param html Fragment HTML d'origine non fiable.
 * @returns Le fragment assaini, sous forme de chaîne.
 */
export function sanitizeHtml(html: string): string {
	return DOMPurify.sanitize(html)
}

/**
 * Installe les politiques Trusted Types ; sans effet si le navigateur ne connaît
 * pas l'API (la CSP et DOMPurify restent alors les barrières, 13.04).
 */
export function installTrustedTypesPolicies(): void {
	const factory = (globalThis as { trustedTypes?: TrustedTypesFactory }).trustedTypes
	if (!factory) {
		return
	}

	const origin = globalThis.location.origin
	const workerUrl = (input: string) => (isCryptoWorkerUrl(input, origin) ? input : null)

	renderPolicy = factory.createPolicy('sti-render', { createHTML: sanitizeHtml })
	factory.createPolicy('sti-worker', { createScriptURL: workerUrl })
	factory.createPolicy('default', {
		createHTML: sanitizeHtml,
		createScript: () => null,
		createScriptURL: workerUrl,
	})
}

/**
 * HTML assaini et typé pour une insertion contrôlée par le module de rendu.
 *
 * @param html Fragment HTML d'origine non fiable.
 * @returns Un TrustedHTML là où Trusted Types existe, sinon la chaîne assainie.
 */
export function trustedHtml(html: string): unknown {
	return renderPolicy ? renderPolicy.createHTML(html) : sanitizeHtml(html)
}
