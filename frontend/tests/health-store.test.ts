import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useHealthStore } from '../src/stores/health'

function mockFetch(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  }))
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

describe('store health', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.unstubAllGlobals()
  })

  it('appelle /api/v1/health et enregistre un état sain', async () => {
    const fetchMock = mockFetch(200, { status: 'ok', database: 'ok', time: '2026-11-21T15:30:00Z' })
    const store = useHealthStore()

    await store.check()

    expect(fetchMock).toHaveBeenCalledWith('/api/v1/health', expect.objectContaining({
      headers: expect.objectContaining({ Accept: 'application/json' }),
    }))
    expect(store.state).toBe('ok')
    expect(store.serverTime).toBe('2026-11-21T15:30:00Z')
  })

  it('signale un état dégradé quand la base est injoignable', async () => {
    mockFetch(503, { status: 'degraded', database: 'error', time: '2026-11-21T15:30:00Z' })
    const store = useHealthStore()

    await store.check()

    expect(store.state).toBe('degraded')
  })

  it("signale une API injoignable sur erreur réseau", async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    const store = useHealthStore()

    await store.check()

    expect(store.state).toBe('unreachable')
    expect(store.serverTime).toBeNull()
  })
})
