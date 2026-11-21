// Client de l'API REST versionnée (11.03) : même origine, cookies de session Sanctum.
export const API_BASE = '/api/v1'

export function apiGet(path: string): Promise<Response> {
  return fetch(`${API_BASE}${path}`, {
    method: 'GET',
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
}
