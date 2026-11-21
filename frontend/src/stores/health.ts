import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet } from '../api/client'

export type HealthState = 'idle' | 'checking' | 'ok' | 'degraded' | 'unreachable'

interface HealthPayload {
  status: 'ok' | 'degraded'
  database: 'ok' | 'error'
  time: string
}

export const useHealthStore = defineStore('health', () => {
  const state = ref<HealthState>('idle')
  const serverTime = ref<string | null>(null)

  async function check(): Promise<void> {
    state.value = 'checking'
    try {
      const response = await apiGet('/health')
      const payload = (await response.json()) as HealthPayload
      serverTime.value = typeof payload.time === 'string' ? payload.time : null
      state.value = response.ok && payload.status === 'ok' ? 'ok' : 'degraded'
    } catch {
      serverTime.value = null
      state.value = 'unreachable'
    }
  }

  return { state, serverTime, check }
})
