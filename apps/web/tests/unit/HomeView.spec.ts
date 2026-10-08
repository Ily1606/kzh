import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import HomeView from '@/views/HomeView.vue'

const mockV1Health = vi.fn()
const mockPluginTrending = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getHealth: () => ({
    v1Health: mockV1Health
  }),
  getPlugin: () => ({
    pluginTrending: mockPluginTrending
  })
}))

describe('HomeView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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
    const wrapper = mount(HomeView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
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

  it('falls back to mock plugins if API returns empty trending list', async () => {
    mockPluginTrending.mockResolvedValue({ data: { plugins: [] } })
    const wrapper = mount(HomeView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    const pluginCards = wrapper.findAllComponents({ name: 'PluginCard' })
    // Mock plugins array has 3 items
    expect(pluginCards.length).toBe(3)
    expect(pluginCards[0].props('fallback')).toBeDefined()
  })

  it('displays error if API health check fails', async () => {
    mockV1Health.mockRejectedValue(new Error('Network Error'))
    const wrapper = mount(HomeView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    expect(wrapper.text()).toContain('API unavailable — Network Error')
  })

  it('copies install command to clipboard', async () => {
    vi.useFakeTimers()
    const wrapper = mount(HomeView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
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
