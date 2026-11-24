/**
 * Durée de vie de la clé en mémoire (ged-chiffree-2, 12.01 §4.3) : verrouillage
 * après inactivité, activité qui repousse l'échéance, veille de l'onglet,
 * contrôle au retour d'un onglet en arrière-plan, arrêt sans verrouillage.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { startIdleLock } from '../../src/crypto/idleLock'

const MINUTE = 60_000

describe('startIdleLock', () => {
	beforeEach(() => {
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('verrouille une fois le délai d\'inactivité écoulé', () => {
		const onLock = vi.fn()
		startIdleLock({ timeoutMs: 2 * MINUTE, onLock })

		vi.advanceTimersByTime(MINUTE)
		expect(onLock).not.toHaveBeenCalled()
		vi.advanceTimersByTime(MINUTE)
		expect(onLock).toHaveBeenCalledExactlyOnceWith('idle')

		// Une seule fois : la surveillance s'arrête après le verrouillage.
		vi.advanceTimersByTime(10 * MINUTE)
		expect(onLock).toHaveBeenCalledOnce()
	})

	it('repousse l\'échéance à chaque activité de l\'utilisateur', () => {
		const onLock = vi.fn()
		const stop = startIdleLock({ timeoutMs: 2 * MINUTE, onLock })

		for (const event of ['keydown', 'pointerdown', 'wheel']) {
			vi.advanceTimersByTime(MINUTE + 30_000)
			window.dispatchEvent(new Event(event))
		}
		expect(onLock).not.toHaveBeenCalled()
		stop()
	})

	it('verrouille dès la mise en veille de l\'onglet', () => {
		const onLock = vi.fn()
		startIdleLock({ timeoutMs: 30 * MINUTE, onLock })

		document.dispatchEvent(new Event('freeze'))

		expect(onLock).toHaveBeenCalledExactlyOnceWith('sleep')
	})

	it('verrouille au retour de l\'onglet si l\'horloge a dépassé le délai (veille de l\'ordinateur)', () => {
		const onLock = vi.fn()
		let clock = 0
		startIdleLock({ timeoutMs: 30 * MINUTE, onLock, now: () => clock })

		// Minuteurs suspendus pendant la veille : seule l'horloge murale avance.
		clock = 31 * MINUTE
		document.dispatchEvent(new Event('visibilitychange'))

		expect(onLock).toHaveBeenCalledExactlyOnceWith('idle')
	})

	it('s\'arrête sans verrouiller', () => {
		const onLock = vi.fn()
		const stop = startIdleLock({ timeoutMs: MINUTE, onLock })

		stop()
		vi.advanceTimersByTime(10 * MINUTE)
		document.dispatchEvent(new Event('freeze'))

		expect(onLock).not.toHaveBeenCalled()
	})
})
