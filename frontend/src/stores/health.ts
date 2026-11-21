/**
 * État de l'instance, tel que le rapporte `GET /api/v1/health`.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet } from '../api/client'

/**
 * - `idle` : pas encore vérifié ;
 * - `checking` : requête en cours ;
 * - `ok` : l'API répond et la base est accessible ;
 * - `degraded` : l'API répond, mais signale un problème (ex. base injoignable) ;
 * - `unreachable` : l'API ne répond pas, ou pas en JSON.
 */
export type HealthState = 'idle' | 'checking' | 'ok' | 'degraded' | 'unreachable'

/** Corps de la réponse de l'API (voir HealthController). */
interface HealthPayload {
	status: 'ok' | 'degraded'
	database: 'ok' | 'error'
	time: string
}

/** Store Pinia de l'état de l'instance. */
export const useHealthStore = defineStore('health', () => {
	const state = ref<HealthState>('idle')
	/** Heure du serveur, en UTC (ISO 8601), ou `null` si elle est inconnue. */
	const serverTime = ref<string | null>(null)

	/** Interroge l'API et met à jour l'état ; ne lève jamais d'exception. */
	async function check(): Promise<void> {
		state.value = 'checking'
		try {
			const response = await apiGet('/health')
			const payload = (await response.json()) as HealthPayload
			serverTime.value = typeof payload.time === 'string' ? payload.time : null
			state.value = response.ok && payload.status === 'ok' ? 'ok' : 'degraded'
		} catch {
			// Erreur réseau, ou réponse non JSON (ex. page d'erreur d'un proxy).
			serverTime.value = null
			state.value = 'unreachable'
		}
	}

	return { state, serverTime, check }
})
