/**
 * Regroupement du périmètre pour l'affichage (engagement-4) : chaque machine
 * avec ses services triés par port, puis les comptes avec le libellé de leur
 * machine.
 */
import { describe, expect, it } from 'vitest'
import { groupScope, type ScopeObject } from '../../src/api/scope'

/**
 * Objet du périmètre minimal pour les tests.
 *
 * @param fields Champs à remplacer.
 * @returns Un objet du périmètre complet.
 */
function object(fields: Partial<ScopeObject> & Pick<ScopeObject, 'id' | 'type' | 'label'>): ScopeObject {
	return {
		test_id: 'T',
		parent_id: null,
		included: true,
		comment: null,
		constraints: null,
		created_at: '2026-12-01T08:00:00Z',
		updated_at: '2026-12-01T08:00:00Z',
		can_manage: true,
		...fields,
	}
}

describe('groupScope', () => {
	it('range les services sous leur machine, triés par port puis protocole', () => {
		const web = object({ id: 'H1', type: 'host', label: 'srv-web01', host: { address: '203.0.113.10', hostname: 'srv-web01', operating_system: null } })
		const objects = [
			object({ id: 'S2', type: 'service', label: '443/tcp https', parent_id: 'H1', service: { protocol: 'tcp', port: 443, name: 'https', version: null } }),
			web,
			object({ id: 'S3', type: 'service', label: '22/udp', parent_id: 'H1', service: { protocol: 'udp', port: 22, name: null, version: null } }),
			object({ id: 'S1', type: 'service', label: '22/tcp ssh', parent_id: 'H1', service: { protocol: 'tcp', port: 22, name: 'ssh', version: null } }),
		]

		const grouped = groupScope(objects)

		expect(grouped.hosts).toHaveLength(1)
		expect(grouped.hosts[0]?.host).toBe(web)
		expect(grouped.hosts[0]?.services.map((service) => service.id)).toEqual(['S1', 'S3', 'S2'])
	})

	it('trie les machines par libellé et donne aux comptes le libellé de leur machine', () => {
		const objects = [
			object({ id: 'H2', type: 'host', label: 'srv-mail01', host: { address: '203.0.113.20', hostname: 'srv-mail01', operating_system: null } }),
			object({ id: 'H1', type: 'host', label: 'srv-api01', host: { address: '203.0.113.11', hostname: 'srv-api01', operating_system: null } }),
			object({ id: 'A1', type: 'account', label: 'admin', parent_id: 'H2', account: { username: 'admin', role_description: null } }),
			object({ id: 'A2', type: 'account', label: 'client.test1', account: { username: 'client.test1', role_description: 'Client' } }),
		]

		const grouped = groupScope(objects)

		expect(grouped.hosts.map((entry) => entry.host.label)).toEqual(['srv-api01', 'srv-mail01'])
		expect(grouped.accounts.map((entry) => [entry.account.id, entry.hostLabel])).toEqual([['A1', 'srv-mail01'], ['A2', null]])
	})

	it('écarte un service dont la machine n\'est pas dans la liste', () => {
		const grouped = groupScope([
			object({ id: 'S1', type: 'service', label: '22/tcp', parent_id: 'H9', service: { protocol: 'tcp', port: 22, name: null, version: null } }),
		])

		expect(grouped.hosts).toEqual([])
		expect(grouped.orphans.map((orphan) => orphan.id)).toEqual(['S1'])
	})
})
