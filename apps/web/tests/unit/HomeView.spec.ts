import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import HomeView from '@/views/HomeView.vue'
import { usePluginsStore } from '@/stores'

const mockV1Health = vi.fn()
const mockPluginTrending = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getHealth: () => ({
    v1Health: mockV1Health
  }),
  getPlugin: () => ({
    pluginTrending: mockPluginTrending
  }),
  getStar: () => ({ starStore: vi.fn() })
}))

function mountView() {
  return mount(HomeView, {
    global: {
      stubs: {
        RouterLink: true,
        PluginCard: true
      }
    }
  })
}

describe('HomeView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockV1Health.mockResolvedValue({ data: { status: 'healthy' } })
    mockPluginTrending.mockResolvedValue({
      data: {
        plugins: [
          { id: '10', name: '@dsh/test-trending-1' },
          { id: '11', name: '@dsh/test-trending-2' }
        ]
      }
    })
    
    Object.assign(navigator, {
      clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined)
      }
    })
  })

  it('fetches and displays trending plugins and API status', async () => {
    const wrapper = mountView()
    
    // Initially shows checking state
    expect(wrapper.text()).toContain('Checking API connection…')
    
    await flushPromises()
    
    expect(mockV1Health).toHaveBeenCalled()
    expect(mockPluginTrending).toHaveBeenCalled()
    
    // Displays health status
    expect(wrapper.text()).toContain('API online — healthy')
    
    // Displays trending plugins
    const pluginCards = wrapper.findAllComponents({ name: 'PluginCard' })
    expect(pluginCards.length).toBe(2)
    expect(pluginCards[0].props('plugin')).toEqual({ id: '10', name: '@dsh/test-trending-1' })
  })

  it('shows the empty state if API returns an empty trending list', async () => {
    mockPluginTrending.mockResolvedValue({ data: { plugins: [] } })
    const wrapper = mountView()

    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No trending plugins available at the moment.')
  })

  it('does not refetch trending when the store already holds it', async () => {
    const store = usePluginsStore()
    const wrapper = mountView()

    await flushPromises()
    expect(mockPluginTrending).toHaveBeenCalledTimes(1)

    // A second visit to the home page reuses what the first fetch brought in.
    const second = mountView()
    await flushPromises()

    expect(mockPluginTrending).toHaveBeenCalledTimes(1)
    expect(second.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
    expect(store.trendingPlugins).toHaveLength(2)
    expect(wrapper.text()).toContain('Trending plugins')
  })

  it('displays error if API health check fails', async () => {
    mockV1Health.mockRejectedValue(new Error('Network Error'))
    const wrapper = mountView()

    await flushPromises()

    expect(wrapper.text()).toContain('API unavailable — Network Error')
    // Independent requests: a failing health probe must not empty the list.
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
  })

  it('copies install command to clipboard', async () => {
    vi.useFakeTimers()
    const wrapper = mountView()
    
    await flushPromises()
    
    const copyBtn = wrapper.find('button[aria-label="Copy install command"]')
    await copyBtn.trigger('click')
    
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('npx dsh add @dsh/hello-world')
    
    // Test the copied state (check icon would appear, wait 1800ms)
    // We would need to flush promises or advance timers to test internal copied ref changing,
    // but verifying writeText is called is sufficient for this logic.
    vi.useRealTimers()
  })
})
