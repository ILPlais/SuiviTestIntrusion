/**
 * Politique de mots de passe (13.03), contrôlée dans le navigateur, car le
 * serveur ne voit jamais le mot de passe : longueur de 15 à 128 caractères
 * (points de code Unicode, après NFC), refus des mots de passe courants et des
 * termes liés au service ou à l'adresse, indicateur de robustesse. Aucune règle
 * de composition n'est imposée.
 *
 * La liste de refus (environ 50 000 mots de passe courants de zxcvbn-ts) est
 * chargée à la demande, seulement sur les écrans où l'on choisit un mot de passe.
 */

/** Longueurs minimale et maximale, en caractères (13.03). */
export const MIN_LENGTH = 15
export const MAX_LENGTH = 128

/** Termes du service refusés dans un mot de passe. */
const SERVICE_TERMS = ['suivi', 'intrusion', 'pentest', 'suivitestintrusion']

/** Motif de refus, traduit par l'interface (clé `password.problems.<motif>`). */
export type PolicyProblem = 'tooShort' | 'tooLong' | 'common' | 'context'

/** Résultat du contrôle. */
export interface PolicyResult {
	valid: boolean
	problems: PolicyProblem[]
	/** Robustesse estimée par zxcvbn, de 0 (très faible) à 4 (très forte). */
	score: 0 | 1 | 2 | 3 | 4
	length: number
}

/** Estimateur zxcvbn, chargé une seule fois. */
type Checker = { check: (password: string, userInputs?: string[]) => { score: number, sequence: { pattern: string, dictionaryName?: string, matchedWord?: string, token: string }[] } }
let checkerPromise: Promise<{ checker: Checker, common: Set<string> }> | null = null

/**
 * Charge zxcvbn-ts et son dictionnaire commun dans un morceau séparé du build.
 *
 * @returns L'estimateur et la liste des mots de passe courants.
 */
function loadChecker(): Promise<{ checker: Checker, common: Set<string> }> {
	checkerPromise ??= Promise.all([import('@zxcvbn-ts/core'), import('@zxcvbn-ts/language-common')]).then(([core, language]) => ({
		checker: new core.ZxcvbnFactory({ dictionary: { ...language.dictionary }, graphs: language.adjacencyGraphs }) as Checker,
		common: new Set(language.dictionary['passwords-common'] as string[]),
	}))

	return checkerPromise
}

/**
 * Termes liés à l'adresse de l'utilisateur : partie locale et éléments du domaine.
 *
 * @param email Adresse du compte.
 * @returns Les termes à refuser, en minuscules.
 */
function contextTerms(email: string): string[] {
	const [local = '', domain = ''] = email.trim().toLowerCase().split('@')

	// Le domaine sans son extension, découpé aussi sur les tirets (« exemple-audit.fr »).
	const labels = domain.split('.').slice(0, -1)

	return [local, ...local.split(/[._+-]/), ...labels, ...labels.flatMap((label) => label.split('-'))]
		.filter((term) => term.length >= 4)
}

/**
 * Contrôle un mot de passe candidat.
 *
 * @param password Mot de passe saisi.
 * @param email Adresse du compte (ses éléments sont refusés dans le mot de passe).
 * @returns Les motifs de refus et la robustesse estimée.
 */
export async function checkPassword(password: string, email: string): Promise<PolicyResult> {
	const normalized = password.normalize('NFC')
	const length = [...normalized].length
	const lower = normalized.toLowerCase()
	const problems: PolicyProblem[] = []

	if (length < MIN_LENGTH) {
		problems.push('tooShort')
	}
	if (length > MAX_LENGTH) {
		problems.push('tooLong')
	}

	const { checker, common } = await loadChecker()

	// Comparaison du mot de passe entier, sans tenir compte de la casse (13.03).
	if (common.has(lower)) {
		problems.push('common')
	}

	const terms = [...SERVICE_TERMS, ...contextTerms(email)]
	if (terms.some((term) => lower.includes(term))) {
		problems.push('context')
	}

	// Au-delà de 128 caractères, zxcvbn n'apporte rien et serait lent.
	const score = length > MAX_LENGTH ? 4 : checker.check(normalized.slice(0, 256), terms).score

	return { valid: problems.length === 0, problems, score: score as PolicyResult['score'], length }
}
