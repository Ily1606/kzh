import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp, ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import PluginsView from '@/views/PluginsView.vue'

const mockPluginIndex = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginIndex: mockPluginIndex
  }),
  getStar: () => ({ starStore: vi.fn() })
}))

const mockReplace = vi.fn()
const mockRoute = ref<{ query: Record<string, unknown> }>({ query: {} })

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: vi.fn(), replace: mockReplace }),
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

function mountView() {
  return mount(PluginsView, {
    global: {
      stubs: {
        RouterLink: true,
        PluginCard: true
      }
    }
  })
}

describe('PluginsView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockRoute.value = { query: {} }

    // The category list is portalled, so each test starts from an empty body.
    document.body.innerHTML = ''

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockPluginIndex.mockResolvedValue(mockPluginsResponse)
  })

  it('renders loading state initially', async () => {
    // Return a promise that doesn't resolve immediately to check loading state
    mockPluginIndex.mockReturnValue(new Promise(() => {}))
    const wrapper = mountView()

    expect(wrapper.text()).toContain('Loading plugins catalogue...')
    expect(wrapper.find('.animate-spin').exists()).toBe(true)
  })

  it('fetches and displays plugins', async () => {
    const wrapper = mountView()

    await flushPromises()

    expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null })

    // Check if stats summary is rendered
    expect(wrapper.text()).toContain('Showing 2')
    expect(wrapper.text()).toContain('of 2 total plugins')
    expect(wrapper.text()).toContain('Page 1 of 2')

    // Check if PluginCard is rendered twice
    const pluginCards = wrapper.findAllComponents({ name: 'PluginCard' })
    expect(pluginCards.length).toBe(2)
  })

  it('reads the plugins from the store rather than holding its own copy', async () => {
    const { usePluginsStore } = await import('@/stores')
    const wrapper = mountView()

    await flushPromises()

    const store = usePluginsStore()
    expect(store.listPlugins).toHaveLength(2)
    expect(store.list.total).toBe(2)
    expect(store.list.lastPage).toBe(2)

    // A plugin added to the store from anywhere reaches this view.
    store.put([{ id: '3', name: 'plugin-three', title: 'Third', license: 'MIT' } as never])
    store.list = { ids: ['1', '2', '3'], page: 1, total: 3, lastPage: 2 }
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(3)
  })

  it('filters plugins based on search query', async () => {
    const wrapper = mountView()

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

  it('does not call the API while typing in the search box', async () => {
    const wrapper = mountView()

    await flushPromises()
    expect(mockPluginIndex).toHaveBeenCalledTimes(1)

    await wrapper.find('input[type="text"]').setValue('first')
    await flushPromises()

    // Filtering happens over the page already in hand.
    expect(mockPluginIndex).toHaveBeenCalledTimes(1)
  })

  it('displays empty state if search yields no results', async () => {
    const wrapper = mountView()

    await flushPromises()

    const searchInput = wrapper.find('input[type="text"]')
    await searchInput.setValue('non-existent-plugin')
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No plugins found')
    expect(wrapper.text()).toContain('We couldn\'t find any plugin matching "non-existent-plugin"')

    // Test clear search button
    const clear = wrapper.findAll('button').find((b) => b.text() === 'Clear search')
    await clear!.trigger('click')
    await flushPromises()
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
  })

  it('displays empty state if API returns empty list', async () => {
    mockPluginIndex.mockResolvedValue({ data: [], meta: { total: 0, last_page: 1 } })
    const wrapper = mountView()

    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No plugins found')
    expect(wrapper.text()).toContain('There are currently no published plugins available.')
  })

  it('handles pagination correctly', async () => {
    const wrapper = mountView()

    await flushPromises()

    // Next button should be present
    const buttons = wrapper.findAll('button')
    const nextBtn = buttons.find(b => b.text().includes('Next'))
    expect(nextBtn).toBeDefined()

    await nextBtn!.trigger('click')
    await flushPromises()

    expect(mockPluginIndex).toHaveBeenCalledWith({ page: 2, per_page: 9, category: null })
    expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
  })

  it('disables Previous on the first page', async () => {
    const wrapper = mountView()

    await flushPromises()

    const prevBtn = wrapper.findAll('button').find(b => b.text().includes('Previous'))
    expect(prevBtn?.attributes('disabled')).toBeDefined()
  })

  it('handles API errors gracefully', async () => {
    mockPluginIndex.mockRejectedValue(new Error('API Error'))

    const wrapper = mountView()

    await flushPromises()

    // No cards, and the loading state has been released.
    expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
    expect(wrapper.text()).toContain('No plugins found')
    expect(wrapper.text()).not.toContain('Loading plugins catalogue...')
  })

  describe('the category filter', () => {
    const option = (name: string) =>
      document.querySelector<HTMLElement>(`[data-test="${name}"]`)

    async function openFilter(wrapper: ReturnType<typeof mountView>) {
      await wrapper.find('[data-test="category-trigger"]').trigger('click')
      await flushPromises()
      await new Promise((resolve) => setTimeout(resolve, 30))
      await flushPromises()
    }

    it('asks for the whole catalogue until a category is picked', async () => {
      mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null })
    })

    it('sends the picked category and puts it in the URL', async () => {
      const wrapper = mountView()
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Vision')!.click()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: 'Vision' })
      expect(mockReplace).toHaveBeenCalledWith({ query: { category: 'Vision' } })
      expect(wrapper.text()).toContain('Vision')
    })

    it('goes back to page 1, because the filtered set is a different set', async () => {
      mockPluginsResponse.meta.last_page = 3
      const wrapper = mountView()
      await flushPromises()

      const next = wrapper.findAll('button').find((b) => b.text().includes('Next'))
      await next!.trigger('click')
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Integrations')!.click()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: 'Integrations' })
    })

    it('starts from the category in the URL', async () => {
      mockRoute.value = { query: { category: 'Integrations' } }

      const wrapper = mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: 'Integrations' })
      expect(wrapper.find('[data-test="category-trigger"]').text()).toContain('Integrations')
    })

    it('ignores a category the API does not accept', async () => {
      // The endpoint answers an unknown name with 422, so a hand-edited URL must
      // fall back to the whole catalogue rather than fail the page.
      mockRoute.value = { query: { category: 'not-a-category' } }

      const wrapper = mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null })
      expect(wrapper.find('[data-test="category-trigger"]').text()).toContain('All categories')
    })

    it('clears the category and the URL when all is picked again', async () => {
      const wrapper = mountView()
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Vision')!.click()
      await flushPromises()

      // The list stays open after a pick, so it is closed the way a reader would.
      await wrapper.find('[data-test="category-trigger"]').trigger('click')
      await flushPromises()
      await openFilter(wrapper)
      option('category-option-all')!.click()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: null })
      expect(mockReplace).toHaveBeenLastCalledWith({ query: {} })
    })

    it('offers a way out when the category has no plugins', async () => {
      mockPluginIndex.mockResolvedValue({ data: [], meta: { total: 0, last_page: 1 } })

      const wrapper = mountView()
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Integrations')!.click()
      await flushPromises()

      expect(wrapper.text()).toContain('There are no plugins in Integrations yet.')

      await wrapper.findAll('button').find((b) => b.text().includes('Show all categories'))!.trigger('click')
      await flushPromises()

      expect(mockReplace).toHaveBeenLastCalledWith({ query: {} })
    })
  })
})
