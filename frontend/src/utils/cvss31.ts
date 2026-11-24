/**
 * Moteur CVSS v3.1 côté navigateur (vulnerabilites-1, 11.10) : aperçu du score
 * pendant la saisie des métriques. Copie fidèle de backend/app/Cvss/Cvss31.php,
 * vérifiée par les mêmes vecteurs de référence ; le serveur recalcule le score
 * à l'enregistrement et seul le sien fait foi.
 */

/** Métriques de base et leurs valeurs, dans l'ordre de la spécification. */
export const CVSS31_METRICS = {
	AV: ['N', 'A', 'L', 'P'],
	AC: ['L', 'H'],
	PR: ['N', 'L', 'H'],
	UI: ['N', 'R'],
	S: ['U', 'C'],
	C: ['H', 'L', 'N'],
	I: ['H', 'L', 'N'],
	A: ['H', 'L', 'N'],
} as const

/** Nom d'une métrique de base. */
export type Cvss31Metric = keyof typeof CVSS31_METRICS

/** Valeur de chaque métrique de base. */
export type Cvss31Metrics = Record<Cvss31Metric, string>

/** Niveau de sévérité (11.10). */
export type Severity = 'none' | 'minor' | 'important' | 'major' | 'critical'

const PREFIX = 'CVSS:3.1'
const AV: Record<string, number> = { N: 0.85, A: 0.62, L: 0.55, P: 0.2 }
const AC: Record<string, number> = { L: 0.77, H: 0.44 }
const UI: Record<string, number> = { N: 0.85, R: 0.62 }
const CIA: Record<string, number> = { H: 0.56, L: 0.22, N: 0 }
/** Privilèges requis : coefficient selon la portée (U inchangée, C changée). */
const PR: Record<string, { U: number, C: number }> = { N: { U: 0.85, C: 0.85 }, L: { U: 0.62, C: 0.68 }, H: { U: 0.27, C: 0.5 } }

/**
 * Lit un vecteur `CVSS:3.1/AV:N/…` (métriques dans n'importe quel ordre,
 * toutes présentes une seule fois, aucune autre).
 *
 * @param vector Vecteur saisi.
 * @returns Les métriques, ou null si le vecteur est invalide.
 */
export function parseCvss31(vector: string): Cvss31Metrics | null {
	const [prefix, ...parts] = vector.trim().split('/')
	if (prefix !== PREFIX) {
		return null
	}
	const found: Partial<Record<string, string>> = {}
	for (const part of parts) {
		const pair = part.split(':')
		if (pair.length !== 2) {
			return null
		}
		const [metric = '', value = ''] = pair
		const allowed = (CVSS31_METRICS as Record<string, readonly string[]>)[metric]
		if (!allowed || metric in found || !allowed.includes(value)) {
			return null
		}
		found[metric] = value
	}
	const metrics: Partial<Cvss31Metrics> = {}
	for (const metric of Object.keys(CVSS31_METRICS) as Cvss31Metric[]) {
		const value = found[metric]
		if (value === undefined) {
			return null
		}
		metrics[metric] = value
	}
	return metrics as Cvss31Metrics
}

/**
 * Génère le vecteur dans l'ordre de la spécification.
 *
 * @param metrics Valeur de chaque métrique.
 * @returns Le vecteur `CVSS:3.1/…`.
 */
export function toCvss31Vector(metrics: Cvss31Metrics): string {
	return [PREFIX, ...(Object.keys(CVSS31_METRICS) as Cvss31Metric[]).map((metric) => `${metric}:${metrics[metric]}`)].join('/')
}

/**
 * Arrondi supérieur au dixième (annexe A de la spécification v3.1), par des
 * entiers pour neutraliser les erreurs de virgule flottante.
 *
 * @param value Valeur à arrondir.
 * @returns La valeur arrondie au dixième supérieur.
 */
export function roundUp(value: number): number {
	const integer = Math.round(value * 100000)
	return integer % 10000 === 0 ? integer / 100000 : (Math.floor(integer / 10000) + 1) / 10
}

/**
 * Score de base (11.10) : impact nul d'abord, puis formule selon la portée.
 *
 * @param metrics Valeur de chaque métrique.
 * @returns Score de 0 à 10, au dixième.
 */
export function cvss31Score(metrics: Cvss31Metrics): number {
	const changed = metrics.S === 'C'
	const subScore = 1 - (1 - (CIA[metrics.C] ?? 0)) * (1 - (CIA[metrics.I] ?? 0)) * (1 - (CIA[metrics.A] ?? 0))
	const impact = changed ? 7.52 * (subScore - 0.029) - 3.25 * (subScore - 0.02) ** 15 : 6.42 * subScore
	if (impact <= 0) {
		return 0
	}
	const privileges = PR[metrics.PR] ?? { U: 0, C: 0 }
	const exploitability = 8.22 * (AV[metrics.AV] ?? 0) * (AC[metrics.AC] ?? 0) * (changed ? privileges.C : privileges.U) * (UI[metrics.UI] ?? 0)
	return changed ? roundUp(Math.min(1.08 * (impact + exploitability), 10)) : roundUp(Math.min(impact + exploitability, 10))
}

/**
 * Affiche un score au dixième, avec le séparateur décimal de la langue
 * (« 9,8 » en français, comme les maquettes de 15.01).
 *
 * @param score Score de 0 à 10.
 * @param locale Langue de l'interface (« fr »).
 * @returns Le score mis en forme.
 */
export function formatScore(score: number, locale: string): string {
	return new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(score)
}

/**
 * Niveau de sévérité d'un score (échelle de 11.10).
 *
 * @param score Score de 0 à 10.
 * @returns Le niveau.
 */
export function cvss31Severity(score: number): Severity {
	if (score <= 0) {
		return 'none'
	}
	if (score < 4) {
		return 'minor'
	}
	if (score < 7) {
		return 'important'
	}
	return score < 9 ? 'major' : 'critical'
}
