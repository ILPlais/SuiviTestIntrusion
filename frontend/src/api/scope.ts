/**
 * Périmètre réseau d'un test (engagement-4, 11.07) : types renvoyés par l'API
 * et regroupement pour l'affichage (machines, leurs services, comptes de test).
 */

/** Type d'un objet du périmètre en V1. */
export type ScopeObjectType = 'host' | 'service' | 'account'

/** Protocole d'un service. */
export type ServiceProtocol = 'tcp' | 'udp'

/** Protocoles proposés dans les formulaires. */
export const SERVICE_PROTOCOLS: readonly ServiceProtocol[] = ['tcp', 'udp']

/** Détail d'une machine. */
export interface ScopeHostDetail {
	/** Adresse IPv4, IPv6 ou plage CIDR. */
	address: string
	hostname: string | null
	operating_system: string | null
}

/** Détail d'un service ; son parent est une machine. */
export interface ScopeServiceDetail {
	protocol: ServiceProtocol
	port: number
	name: string | null
	version: string | null
}

/** Détail d'un compte de test : jamais de secret (GED chiffrée, plus tard). */
export interface ScopeAccountDetail {
	username: string
	role_description: string | null
}

/** Objet du périmètre (GET /api/v1/tests/{id}/scope-objects). */
export interface ScopeObject {
	id: string
	test_id: string
	type: ScopeObjectType
	/** Machine hôte d'un service ou d'un compte, sinon null. */
	parent_id: string | null
	label: string
	included: boolean
	comment: string | null
	/** Plages horaires interdites et contraintes de disponibilité. */
	constraints: string | null
	/** Présent pour une machine. */
	host?: ScopeHostDetail
	/** Présent pour un service. */
	service?: ScopeServiceDetail
	/** Présent pour un compte. */
	account?: ScopeAccountDetail
	created_at: string
	updated_at: string
	/** Vrai si l'utilisateur peut modifier le périmètre. */
	can_manage: boolean
}

/** Une machine et ses services. */
export interface HostEntry {
	host: ScopeObject
	services: ScopeObject[]
}

/** Un compte et le libellé de sa machine (null s'il n'est rattaché à aucune). */
export interface AccountEntry {
	account: ScopeObject
	hostLabel: string | null
}

/** Périmètre regroupé pour l'affichage. */
export interface GroupedScope {
	/** Machines triées par libellé, chacune avec ses services triés par port. */
	hosts: HostEntry[]
	/** Comptes triés par libellé. */
	accounts: AccountEntry[]
	/** Services dont la machine manque à la liste (ne devrait pas arriver). */
	orphans: ScopeObject[]
}

/**
 * Compare deux libellés dans l'ordre alphabétique français, chiffres compris.
 *
 * @param a Premier objet.
 * @param b Second objet.
 * @returns Négatif, nul ou positif.
 */
function byLabel(a: ScopeObject, b: ScopeObject): number {
	return a.label.localeCompare(b.label, 'fr', { numeric: true })
}

/**
 * Range les objets du périmètre : machines et leurs services, puis comptes.
 *
 * @param objects Objets du périmètre d'un test, dans un ordre quelconque.
 * @returns Le périmètre regroupé et trié.
 */
export function groupScope(objects: readonly ScopeObject[]): GroupedScope {
	const hosts = new Map<string, HostEntry>()
	for (const object of objects) {
		if (object.type === 'host') {
			hosts.set(object.id, { host: object, services: [] })
		}
	}

	const orphans: ScopeObject[] = []
	const accounts: AccountEntry[] = []
	for (const object of objects) {
		if (object.type === 'service') {
			const entry = object.parent_id === null ? undefined : hosts.get(object.parent_id)
			if (entry) {
				entry.services.push(object)
			} else {
				orphans.push(object)
			}
		} else if (object.type === 'account') {
			const parent = object.parent_id === null ? undefined : hosts.get(object.parent_id)
			accounts.push({ account: object, hostLabel: parent?.host.label ?? null })
		}
	}

	for (const entry of hosts.values()) {
		entry.services.sort((a, b) => (a.service?.port ?? 0) - (b.service?.port ?? 0)
			|| (a.service?.protocol ?? '').localeCompare(b.service?.protocol ?? ''))
	}

	return {
		hosts: [...hosts.values()].sort((a, b) => byLabel(a.host, b.host)),
		accounts: accounts.sort((a, b) => byLabel(a.account, b.account)),
		orphans,
	}
}
