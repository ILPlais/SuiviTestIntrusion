/**
 * Risque global d'un test renvoyé par l'API (resultats-1, 11.12) et dessin des
 * barres en caractères du tableau de bord (maquette 02 de 15.01).
 */
import type { ImpactAxis, RemediationPriority, RemediationStatus, Severity } from './vulnerabilities'

/** Risque global (GET /api/v1/tests/{id}/risk). */
export interface TestRisk {
	/** Maximum CVSS des vulnérabilités ouvertes, ou null s'il n'y en a pas de cotée. */
	max_score: number | null
	max_severity: Severity | null
	cvss_version: string | null
	/** Moyenne pondérée P1 = 4 … P4 = 1, informative. */
	weighted_average: number | null
	open_count: number
	total_count: number
	/** Maximum de chaque axe sur les vulnérabilités ouvertes. */
	business_profile: Record<ImpactAxis, number>
	by_severity: Record<Severity | 'unrated', number>
	by_phase: Record<string, number>
	by_remediation_status: Record<RemediationStatus, number>
	by_priority: Record<RemediationPriority, number>
}

/** Barre en caractères : partie pleine et partie vide. */
export interface TextBar {
	filled: string
	empty: string
}

/**
 * Dessine une barre proportionnelle au plus grand compteur ; un compteur non
 * nul garde au moins un bloc, pour rester visible.
 *
 * @param count Compteur à représenter.
 * @param max Plus grand compteur de la série.
 * @param width Largeur de la barre, en caractères.
 * @returns Les parties pleine et vide.
 */
export function textBar(count: number, max: number, width: number): TextBar {
	const filled = max <= 0 || count <= 0 ? 0 : Math.max(1, Math.round((count / max) * width))
	return { filled: '█'.repeat(filled), empty: '░'.repeat(width - filled) }
}
