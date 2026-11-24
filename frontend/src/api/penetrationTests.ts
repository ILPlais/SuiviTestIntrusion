/**
 * Types des tests d'intrusion renvoyés par l'API (engagement-2, 11.05, 11.08).
 */

/** Type de test (questionnaire d'engagement de 11.05). */
export type TestType = 'red_team' | 'black_box' | 'grey_box' | 'white_box'

/** Point de départ du test. */
export type TestExposure = 'internal' | 'external'

/** Statut d'un test ; les transitions sont libres et toutes tracées. */
export type TestStatus = 'planned' | 'in_progress' | 'done' | 'validated'

/** Types proposés dans les formulaires, dans l'ordre d'affichage. */
export const TEST_TYPES: readonly TestType[] = ['red_team', 'black_box', 'grey_box', 'white_box']

/** Expositions proposées dans les formulaires. */
export const TEST_EXPOSURES: readonly TestExposure[] = ['internal', 'external']

/** Statuts, dans l'ordre du cycle de vie. */
export const TEST_STATUSES: readonly TestStatus[] = ['planned', 'in_progress', 'done', 'validated']

/** Test d'intrusion dans une liste (GET /api/v1/tests). */
export interface PenetrationTestSummary {
	id: string
	tenant: { id: string, name: string }
	reference: string
	title: string
	type: TestType
	exposure: TestExposure
	status: TestStatus
	/** Début prévu, ISO 8601 en UTC, ou null. */
	planned_start: string | null
	/** Fin prévue, ISO 8601 en UTC, ou null. */
	planned_end: string | null
	/** Nom de l'auteur ; null si son compte a été purgé. */
	created_by: string | null
	/** Vrai si l'utilisateur peut modifier le test et changer son statut. */
	can_manage: boolean
	/** Vrai si l'utilisateur peut tenir le périmètre (équipe du test, testeurs compris). */
	can_manage_scope: boolean
	/** Risque résumé : score maximal et niveau des vulnérabilités ouvertes (resultats-1). */
	risk: { max_score: number | null, max_severity: 'none' | 'minor' | 'important' | 'major' | 'critical' | null }
}

/** Phase fixe d'un test. */
export interface Phase {
	id: string
	code: 'reconnaissance' | 'enumeration' | 'exploitation' | 'post_exploitation' | 'cleanup'
	position: number
}

/** Entrée de l'historique des statuts ; `from` est null pour la création. */
export interface StatusChange {
	from: TestStatus | null
	to: TestStatus
	author: string | null
	comment: string | null
	changed_at: string
}

/** Fiche complète d'un test (GET /api/v1/tests/{id}). */
export interface PenetrationTest extends PenetrationTestSummary {
	phases: Phase[]
	/** Du plus récent au plus ancien. */
	status_changes: StatusChange[]
}
