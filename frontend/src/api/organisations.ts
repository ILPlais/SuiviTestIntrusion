/**
 * Types des références d'un test renvoyées par l'API (engagement-1, 11.05).
 */

/** Rôle de l'utilisateur dans une organisation (identite-5). */
export type OrganisationRole = 'project_manager' | 'tester' | 'client'

/** Organisation cliente, telle que la renvoie GET /api/v1/tenants. */
export interface Organisation {
	id: string
	name: string
	legal_name: string | null
	/** Présentation en Markdown. */
	presentation: string | null
	/** Rôle de l'utilisateur ; null pour un administrateur non rattaché. */
	role: OrganisationRole | null
}

/** Fiche de l'entreprise prestataire (GET /api/v1/company-profile). */
export interface CompanyProfile {
	name: string | null
	legal_name: string | null
	/** Présentation en Markdown. */
	presentation: string | null
}
