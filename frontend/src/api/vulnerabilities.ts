/**
 * Fiches de vulnérabilité renvoyées par l'API (vulnerabilites-3, 11.09, 11.10)
 * et lecture des listes d'identifiants CVE et CWE saisies dans un champ texte.
 */
import type { Severity } from '../utils/cvss31'

export type { Severity } from '../utils/cvss31'

/** Niveaux de sévérité, du plus faible au plus grave (11.10). */
export const SEVERITIES: readonly Severity[] = ['none', 'minor', 'important', 'major', 'critical']

/** Axes de l'impact business (11.09), dans l'ordre d'affichage. */
export const IMPACT_AXES = ['operational', 'legal', 'financial', 'reputation'] as const

/** Axe de l'impact business. */
export type ImpactAxis = typeof IMPACT_AXES[number]

/** Priorité de correction (11.09). */
export type RemediationPriority = 'P1' | 'P2' | 'P3' | 'P4'

/** Priorités proposées. */
export const PRIORITIES: readonly RemediationPriority[] = ['P1', 'P2', 'P3', 'P4']

/** Statut de remédiation ; une vulnérabilité est ouverte tant qu'elle n'est pas vérifiée. */
export type RemediationStatus = 'todo' | 'in_progress' | 'fixed' | 'verified'

/** Statuts proposés, dans l'ordre du cycle. */
export const REMEDIATION_STATUSES: readonly RemediationStatus[] = ['todo', 'in_progress', 'fixed', 'verified']

/** Fiche de remédiation d'une vulnérabilité (vulnerabilites-5). */
export interface Remediation {
	status: RemediationStatus
	priority: RemediationPriority
	/** Priorité tirée du niveau CVSS (Critique → P1 … Mineur → P4). */
	suggested_priority: RemediationPriority
	priority_overridden: boolean
	/** Markdown. */
	recommendations: string | null
	/** Adresses web (http ou https), contrôlées par le serveur. */
	resources: string[]
	retest_result: string | null
	verified_at: string | null
	verified_by: string | null
	/** Rédiger et suivre le statut : équipe du test. */
	can_edit: boolean
	/** Surcharger la priorité : chef de projet ou administrateur. */
	can_prioritize: boolean
	/** Déclarer la correction : équipe ou client. */
	can_declare_fixed: boolean
}

/** Composant concerné : objet du périmètre du test. */
export interface AffectedComponent {
	id: string
	type: 'host' | 'service' | 'account'
	label: string
}

/** Fiche de vulnérabilité (GET /api/v1/vulnerabilities/{id}). */
export interface Vulnerability {
	id: string
	test_id: string
	/** V-001, V-002… attribuée par le serveur. */
	reference: string
	title: string
	/** Markdown, rendu et assaini par le module de rendu. */
	description: string | null
	phase: { id: string, code: string }
	cve_ids: string[]
	cwe_ids: string[]
	/** « 3.1 », ou null tant que la fiche n'est pas cotée. */
	cvss_version: string | null
	cvss_vector: string | null
	cvss_score: number | null
	severity: Severity | null
	/** Note de 0 (non applicable) à 4 par axe (vulnerabilites-4). */
	business_impact: Record<ImpactAxis, number>
	scope_objects: AffectedComponent[]
	remediation: Remediation | null
	created_by: string | null
	created_at: string
	updated_at: string
	/** Vrai si l'utilisateur peut modifier la fiche. */
	can_manage: boolean
}

/**
 * Découpe une saisie « CVE-2026-0042, CVE-2025-1234 » en identifiants, en
 * majuscules, sans doublon ni élément vide ; le serveur contrôle leur forme.
 *
 * @param text Saisie de l'utilisateur.
 * @returns Les identifiants, dans l'ordre de saisie.
 */
export function parseIdentifiers(text: string): string[] {
	const identifiers = text.split(/[\s,;]+/).map((part) => part.trim().toUpperCase()).filter((part) => part !== '')
	return [...new Set(identifiers)]
}
