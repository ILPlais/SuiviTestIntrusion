/**
 * Durée de vie de la clé privée en mémoire (ged-chiffree-2, 12.01 §4.3, 13.04).
 *
 * La clé déchiffrée vit dans le Worker crypto de session ; la verrouiller
 * revient à arrêter ce Worker. Ce module décide quand :
 *
 * - après un délai sans activité de l'utilisateur (clic, frappe, défilement,
 *   toucher), fixé par l'instance ;
 * - dès que le navigateur met l'onglet en veille (événement `freeze` de la Page
 *   Lifecycle API) ou le retire de l'écran pour le cache de navigation
 *   (`pagehide`).
 *
 * Le délai se mesure à l'horloge murale : un ordinateur mis en veille, ou un
 * onglet en arrière-plan dont les minuteurs sont ralentis, est verrouillé dès
 * son retour si le délai est dépassé, sans attendre le prochain tour de minuteur.
 */

/** Motif d'un verrouillage automatique. */
export type LockReason = 'idle' | 'sleep'

/** Événements qui comptent comme une activité de l'utilisateur. */
const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'] as const

/** Intervalle de contrôle du délai, en millisecondes. */
const CHECK_INTERVAL_MS = 15_000

/** Paramètres du verrouillage automatique. */
export interface IdleLockOptions {
	/** Délai d'inactivité avant le verrouillage, en millisecondes. */
	timeoutMs: number
	/** Verrouille la clé ; appelé une seule fois. */
	onLock: (reason: LockReason) => void
	/** Fenêtre observée (injectable pour les tests). */
	target?: Window
	/** Document observé (injectable pour les tests). */
	doc?: Document
	/** Horloge murale en millisecondes (injectable pour les tests). */
	now?: () => number
}

/**
 * Démarre la surveillance de l'inactivité et de la mise en veille de l'onglet.
 *
 * @param options Délai, action de verrouillage et environnement.
 * @returns Une fonction qui arrête la surveillance (sans verrouiller).
 */
export function startIdleLock(options: IdleLockOptions): () => void {
	const target = options.target ?? window
	const doc = options.doc ?? document
	const now = options.now ?? Date.now
	let lastActivity = now()
	let stopped = false

	/**
	 * Verrouille une seule fois, puis arrête la surveillance.
	 *
	 * @param reason Motif du verrouillage.
	 */
	const lock = (reason: LockReason): void => {
		if (stopped) {
			return
		}
		stop()
		options.onLock(reason)
	}

	/** Note une activité de l'utilisateur. */
	const touch = (): void => {
		lastActivity = now()
	}

	/** Verrouille si le délai d'inactivité est écoulé. */
	const check = (): void => {
		if (now() - lastActivity >= options.timeoutMs) {
			lock('idle')
		}
	}

	/** Au retour de l'onglet au premier plan, contrôle aussitôt le délai. */
	const onVisibility = (): void => {
		if (doc.visibilityState === 'visible') {
			check()
		}
	}

	/** Onglet mis en veille ou retiré de l'écran : verrouillage immédiat. */
	const onSleep = (): void => {
		lock('sleep')
	}

	const timer = target.setInterval(check, Math.min(CHECK_INTERVAL_MS, options.timeoutMs))
	for (const name of ACTIVITY_EVENTS) {
		target.addEventListener(name, touch, { capture: true, passive: true })
	}
	doc.addEventListener('visibilitychange', onVisibility)
	doc.addEventListener('freeze', onSleep)
	target.addEventListener('pagehide', onSleep)

	/** Arrête la surveillance et retire les écouteurs. */
	function stop(): void {
		stopped = true
		target.clearInterval(timer)
		for (const name of ACTIVITY_EVENTS) {
			target.removeEventListener(name, touch, { capture: true })
		}
		doc.removeEventListener('visibilitychange', onVisibility)
		doc.removeEventListener('freeze', onSleep)
		target.removeEventListener('pagehide', onSleep)
	}

	return stop
}
