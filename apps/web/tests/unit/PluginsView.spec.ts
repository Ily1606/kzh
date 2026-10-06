import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PluginsView from '@/views/PluginsView.vue'

const mockPluginIndex = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginIndex: mockPluginIndex
  })
}))

// Mock window.scrollTo
Object.defineProperty(window, 'scrollTo', { value: vi.fn(), writable: true })

const mockPluginsResponse = {
  data: [
    { id: '1', name: 'plugin-one', title: 'First Plugin', license: 'MIT' },
    { id: '2', name: 'plugin-two', title: 'Second Plugin', license: 'Apache-2.0' },
  ],
  meta: {
    total: 2,
    last_page: 2
  }
}

describe('PluginsView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPluginIndex.mockResolvedValue(mockPluginsResponse)
  })

  it('renders loading state initially', async () => {
    // Return a promise that doesn't resolve immediately to check loading state
    mockPluginIndex.mockReturnValue(new Promise(() => {}))
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    expect(wrapper.text()).toContain('Loading plugins catalogue...')
    expect(wrapper.find('.animate-spin').exists()).toBe(true)
  })

  it('fetches and displays plugins', async () => {
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    expect(mockPluginIndex).toHaveBeenCalledWith({ params: { page: 1, per_page: 9 } })
    
    // Check if stats summary is rendered
    expect(wrapper.text()).toContain('Showing 2')
    expect(wrapper.text()).toContain('of 2 total plugins')
    expect(wrapper.text()).toContain('Page 1 of 2')
    
    // Check if PluginCard is rendered twice
    const pluginCards = wrapper.findAllComponents({ name: 'PluginCard' })
    expect(pluginCards.length).toBe(2)
  })

  it('filters plugins based on search query', async () => {
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    // Initial length is 2
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
    
    // Search for 'first'
    const searchInput = wrapper.find('input[type="text"]')
    await searchInput.setValue('first')
    await flushPromises()
    
    // Should filter down to 1
    const pluginCards = wrapper.findAllComponents({ name: 'PluginCard' })
    expect(pluginCards.length).toBe(1)
    expect(wrapper.text()).toContain('matching "first"')
  })

  it('displays empty state if search yields no results', async () => {
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    const searchInput = wrapper.find('input[type="text"]')
    await searchInput.setValue('non-existent-plugin')
    await flushPromises()
    
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No plugins found')
    expect(wrapper.text()).toContain('We couldn\'t find any plugin matching "non-existent-plugin"')
    
    // Test clear search button
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
  })

  it('displays empty state if API returns empty list', async () => {
    mockPluginIndex.mockResolvedValue({ data: [], meta: { total: 0, last_page: 1 } })
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No plugins found')
    expect(wrapper.text()).toContain('There are currently no published plugins available.')
  })

  it('handles pagination correctly', async () => {
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    // Next button should be present
    const buttons = wrapper.findAll('button')
    const nextBtn = buttons.find(b => b.text().includes('Next'))
    expect(nextBtn).toBeDefined()
    
    await nextBtn!.trigger('click')
    await flushPromises()
    
    expect(mockPluginIndex).toHaveBeenCalledWith({ params: { page: 2, per_page: 9 } })
    expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
  })

  it('handles API errors gracefully', async () => {
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
    mockPluginIndex.mockRejectedValue(new Error('API Error'))
    
    const wrapper = mount(PluginsView, {
      global: {
        stubs: {
          RouterLink: true,
          PluginCard: true
        }
      }
    })
    
    await flushPromises()
    
    expect(consoleSpy).toHaveBeenCalledWith('Failed to fetch paginated plugins', expect.any(Error))
    
    // Should stop loading and show empty list
    expect(wrapper.find('.animate-spin').exists()).toBe(false)
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    
    consoleSpy.mockRestore()
  })
})
