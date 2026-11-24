/**
 * Fiches de vulnérabilité renvoyées par l'API (vulnerabilites-3, 11.09, 11.10)
 * et lecture des listes d'identifiants CVE et CWE saisies dans un champ texte.
 */
import type { Severity } from '../utils/cvss31'

export type { Severity } from '../utils/cvss31'

/** Niveaux de sévérité, du plus faible au plus grave (11.10). */
export const SEVERITIES: readonly Severity[] = ['none', 'minor', 'important', 'major', 'critical']

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
	scope_objects: AffectedComponent[]
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
