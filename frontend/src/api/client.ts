/**
 * Client de l'API REST versionnée (11.03).
 *
 * La SPA et l'API partagent la même origine : les requêtes emportent les
 * cookies de session Sanctum, sans CORS (13.06). Les requêtes qui modifient
 * quelque chose portent le jeton CSRF de Sanctum (en-tête X-XSRF-TOKEN, lu dans
 * le cookie XSRF-TOKEN, obtenu au besoin par /sanctum/csrf-cookie).
 */

/** Préfixe de toutes les routes de l'API. */
export const API_BASE = '/api/v1'

/** Erreur de l'API, au format {code, message, details} (11.03). */
export class ApiError extends Error {
	readonly status: number
	readonly code: string
	readonly details: Record<string, string[]>

	/**
	 * Construit l'erreur à partir de la réponse de l'API.
	 *
	 * @param status Statut HTTP.
	 * @param code Code d'erreur stable (ex. `validation_failed`).
	 * @param message Message traduit, affichable.
	 * @param details Messages par champ, pour une erreur de validation.
	 */
	constructor(status: number, code: string, message: string, details: Record<string, string[]> = {}) {
		super(message)
		this.status = status
		this.code = code
		this.details = details
	}

	/**
	 * Premier message d'erreur d'un champ, ou le message général.
	 *
	 * @returns Le premier message de champ, sinon le message général.
	 */
	firstMessage(): string {
		const first = Object.values(this.details)[0]?.[0]

		return first ?? this.message
	}
}

/**
 * Envoie une requête GET à l'API et demande une réponse JSON.
 *
 * @param path Chemin relatif à `/api/v1`, commençant par `/` (ex. `/health`).
 * @returns La réponse HTTP brute ; l'appelant interprète le code et le corps.
 */
export function apiGet(path: string): Promise<Response> {
	return fetch(`${API_BASE}${path}`, {
		method: 'GET',
		credentials: 'same-origin',
		headers: { Accept: 'application/json' },
	})
}

/**
 * GET qui renvoie le corps JSON, ou lève une ApiError.
 *
 * @param path Chemin relatif à `/api/v1`.
 * @returns Le corps JSON décodé.
 */
export async function getJson<T>(path: string): Promise<T> {
	return parse<T>(await apiGet(path))
}

/**
 * POST (JSON) avec jeton CSRF ; renvoie le corps JSON (null pour 204), ou lève une ApiError.
 *
 * @param path Chemin relatif à `/api/v1`.
 * @param body Corps envoyé en JSON.
 * @returns Le corps JSON décodé, ou null pour 204.
 */
export async function postJson<T = null>(path: string, body: unknown = {}): Promise<T> {
	return send<T>('POST', path, body)
}

/**
 * DELETE (JSON) avec jeton CSRF ; renvoie le corps JSON (null pour 204), ou lève une ApiError.
 *
 * @param path Chemin relatif à `/api/v1`.
 * @param body Corps envoyé en JSON.
 * @returns Le corps JSON décodé, ou null pour 204.
 */
export async function deleteJson<T = null>(path: string, body: unknown = {}): Promise<T> {
	return send<T>('DELETE', path, body)
}

/**
 * Requête qui modifie quelque chose : corps JSON et jeton CSRF de Sanctum.
 *
 * @param method Verbe HTTP.
 * @param path Chemin relatif à `/api/v1`.
 * @param body Corps envoyé en JSON.
 * @returns Le corps JSON décodé, ou null pour 204.
 */
async function send<T>(method: 'POST' | 'DELETE', path: string, body: unknown): Promise<T> {
	const response = await fetch(`${API_BASE}${path}`, {
		method,
		credentials: 'same-origin',
		headers: {
			'Accept': 'application/json',
			'Content-Type': 'application/json',
			'X-XSRF-TOKEN': await csrfToken(),
		},
		body: JSON.stringify(body),
	})

	return parse<T>(response)
}

/**
 * Lit le corps d'une réponse : JSON en cas de succès, ApiError sinon.
 *
 * @param response Réponse HTTP reçue.
 * @returns Le corps JSON décodé, ou null pour 204.
 */
async function parse<T>(response: Response): Promise<T> {
	if (response.status === 204) {
		return null as T
	}

	const body = await response.json().catch(() => null)
	if (!response.ok) {
		throw new ApiError(response.status, body?.code ?? 'internal_error', body?.message ?? response.statusText, body?.details ?? {})
	}

	return body as T
}

/**
 * Jeton CSRF de Sanctum, lu dans le cookie XSRF-TOKEN (obtenu au besoin). Laravel
 * le renouvelle à la déconnexion ; il est relu à chaque requête.
 *
 * @returns La valeur du jeton, à placer dans l'en-tête X-XSRF-TOKEN.
 */
async function csrfToken(): Promise<string> {
	let token = readCookie('XSRF-TOKEN')
	if (token === null) {
		await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' })
		token = readCookie('XSRF-TOKEN')
	}

	return token ?? ''
}

/**
 * Valeur décodée d'un cookie lisible par la page, ou null.
 *
 * @param name Nom du cookie.
 * @returns La valeur décodée, ou null si le cookie est absent.
 */
function readCookie(name: string): string | null {
	const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`))

	return entry ? decodeURIComponent(entry.slice(name.length + 1)) : null
}
