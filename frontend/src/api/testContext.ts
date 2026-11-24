/**
 * Contexte partagé des écrans d'un test (socle-9, maquettes de 15.01) : le
 * gabarit TestLayout charge le test une fois et le fournit à ses écrans, qui
 * demandent son rechargement après une écriture (le bandeau reflète alors le
 * nouveau risque) ; plus les couleurs des badges.
 */
import type { InjectionKey, Ref } from 'vue'
import type { PenetrationTest, TestStatus } from './penetrationTests'
import type { Severity } from './vulnerabilities'

/** Contexte fourni par TestLayout à ses écrans. */
export interface TestContext {
	/** Test courant, ou null pendant le chargement. */
	test: Ref<PenetrationTest | null>
	/** Recharge le test (statut, risque) après une écriture. */
	refresh: () => Promise<void>
}

/** Clé d'injection du contexte du test. */
export const TEST_CONTEXT: InjectionKey<TestContext> = Symbol('test-context')

/**
 * Classe de badge d'un niveau de sévérité (échelle de 11.10).
 *
 * @param severity Niveau, ou null si non coté.
 * @returns Classe CSS du badge.
 */
export function severityTag(severity: Severity | null): string {
	switch (severity) {
		case 'critical':
			return 'tag-red'
		case 'major':
			return 'tag-peach'
		case 'important':
			return 'tag-yellow'
		case 'minor':
			return 'tag-green'
		default:
			return 'tag-grey'
	}
}

/**
 * Classe de badge du statut d'un test.
 *
 * @param status Statut du test.
 * @returns Classe CSS du badge.
 */
export function statusTag(status: TestStatus): string {
	switch (status) {
		case 'in_progress':
			return 'tag-blue'
		case 'done':
		case 'validated':
			return 'tag-green'
		default:
			return 'tag-grey'
	}
}
