/**
 * Client de l'API REST versionnée (11.03).
 *
 * La SPA et l'API partagent la même origine : les requêtes emportent les
 * cookies de session Sanctum, sans CORS (13.06).
 */

/** Préfixe de toutes les routes de l'API. */
export const API_BASE = '/api/v1'

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
