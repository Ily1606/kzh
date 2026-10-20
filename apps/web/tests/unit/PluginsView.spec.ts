import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp, ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import PluginsView from '@/views/PluginsView.vue'

const mockPluginIndex = vi.fn()

vi.mock('@/api/generated/endpoints', async () => {
  const { getCategory, getTag } = await import('../helpers/taxonomy')

  return {
    getPlugin: () => ({
      pluginIndex: mockPluginIndex
    }),
    getStar: () => ({ starStore: vi.fn() }),
    getCategory,
    getTag,
  }
})

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

    expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null, search: null })

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

    expect(mockPluginIndex).toHaveBeenCalledWith({ page: 2, per_page: 9, category: null, search: null })
    expect(mockReplace).toHaveBeenCalledWith({ query: { page: '2' } })
    expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
  })

  it('starts from the page in the URL', async () => {
    mockRoute.value = { query: { page: '2' } }

    mountView()
    await flushPromises()

    expect(mockPluginIndex).toHaveBeenCalledWith({
      page: 2,
      per_page: 9,
      category: null,
      search: null,
    })
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

  describe('the search box', () => {
    /** The view waits for the typing to settle before asking the API. */
    async function waitForDebounce() {
      await new Promise((resolve) => setTimeout(resolve, 350))
      await flushPromises()
    }

    /** Answers each search with the matching subset, the way the endpoint does. */
    function respondToSearch() {
      mockPluginIndex.mockImplementation((params: { search?: string | null }) => {
        if (!params.search) return Promise.resolve(mockPluginsResponse)

        const data = mockPluginsResponse.data.filter(
          (plugin) =>
            plugin.name.includes(params.search!.toLowerCase()) ||
            plugin.title.toLowerCase().includes(params.search!.toLowerCase()),
        )

        return Promise.resolve({ data, meta: { total: data.length, last_page: 1 } })
      })
    }

    async function type(wrapper: ReturnType<typeof mountView>, value: string) {
      await wrapper.find('[data-test="search-input"]').setValue(value)
      await waitForDebounce()
    }

    it('asks the API for the matches instead of filtering the page in hand', async () => {
      respondToSearch()

      const wrapper = mountView()
      await flushPromises()
      expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)

      await type(wrapper, 'first')

      expect(mockPluginIndex).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 9,
        category: null,
        search: 'first',
      })
      expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(1)
      expect(wrapper.text()).toContain('matching "first"')
    })

    it('waits for the typing to settle before asking the API', async () => {
      const wrapper = mountView()
      await flushPromises()
      expect(mockPluginIndex).toHaveBeenCalledTimes(1)

      await wrapper.find('[data-test="search-input"]').setValue('first')
      await flushPromises()

      // A request per keystroke would hammer the endpoint on a long keyword.
      expect(mockPluginIndex).toHaveBeenCalledTimes(1)

      await waitForDebounce()
      expect(mockPluginIndex).toHaveBeenCalledTimes(2)
    })

    it('collapses a burst of typing into a single request', async () => {
      const wrapper = mountView()
      await flushPromises()

      const input = wrapper.find<HTMLInputElement>('[data-test="search-input"]')

      for (const char of 'aaa') {
        await input.setValue(input.element.value + char)
      }
      await waitForDebounce()

      // Three keystrokes, three timers — and only the last one survives.
      expect(mockPluginIndex).toHaveBeenCalledTimes(2)
      expect(mockPluginIndex).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 9,
        category: null,
        search: 'aaa',
      })
      expect(mockReplace).toHaveBeenLastCalledWith({ query: { search: 'aaa' } })
    })

    it('keeps the search in the URL so the view can be shared and reloaded', async () => {
      respondToSearch()

      const wrapper = mountView()
      await flushPromises()

      await type(wrapper, 'first')

      expect(mockReplace).toHaveBeenLastCalledWith({ query: { search: 'first' } })
    })

    it('starts from the search in the URL', async () => {
      mockRoute.value = { query: { search: 'first' } }

      mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({
        page: 1,
        per_page: 9,
        category: null,
        search: 'first',
      })
    })

    it('goes back to page 1, because the matches are a different set', async () => {
      const wrapper = mountView()
      await flushPromises()

      const next = wrapper.findAll('button').find((b) => b.text().includes('Next'))
      await next!.trigger('click')
      await flushPromises()

      await type(wrapper, 'first')

      expect(mockPluginIndex).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 9,
        category: null,
        search: 'first',
      })
    })

    /** The matches are paginated too, so hiding the pager would strand them. */
    it('keeps the pager while searching', async () => {
      mockRoute.value = { query: { search: 'plugin' } }

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Page 1 of 2')

      const next = wrapper.findAll('button').find((b) => b.text().includes('Next'))
      await next!.trigger('click')
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({
        page: 2,
        per_page: 9,
        category: null,
        search: 'plugin',
      })
    })

    it('displays empty state if search yields no results', async () => {
      respondToSearch()

      const wrapper = mountView()
      await flushPromises()

      await type(wrapper, 'non-existent-plugin')

      expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(0)
      expect(wrapper.text()).toContain('No plugins found')
      expect(wrapper.text()).toContain('We couldn\'t find any plugin matching "non-existent-plugin"')

      const clear = wrapper.findAll('button').find((b) => b.text() === 'Clear search')
      await clear!.trigger('click')
      await waitForDebounce()

      expect(wrapper.findAllComponents({ name: 'PluginCard' }).length).toBe(2)
      expect(mockReplace).toHaveBeenLastCalledWith({ query: {} })
    })
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

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null, search: null })
    })

    it('sends the picked category and puts it in the URL', async () => {
      const wrapper = mountView()
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Vision')!.click()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: 'Vision', search: null })
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

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: 'Integrations', search: null })
    })

    it('starts from the category in the URL', async () => {
      mockRoute.value = { query: { category: 'Integrations' } }

      const wrapper = mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: 'Integrations', search: null })
      expect(wrapper.find('[data-test="category-trigger"]').text()).toContain('Integrations')
    })

    it('ignores a category the API does not accept', async () => {
      // The endpoint answers an unknown name with 422, so a hand-edited URL must
      // fall back to the whole catalogue rather than fail the page.
      mockRoute.value = { query: { category: 'not-a-category' } }

      const wrapper = mountView()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenCalledWith({ page: 1, per_page: 9, category: null, search: null })
      expect(wrapper.find('[data-test="category-trigger"]').text()).toContain('All categories')
    })

    it('keeps the search while the category changes', async () => {
      mockRoute.value = { query: { search: 'first' } }

      const wrapper = mountView()
      await flushPromises()

      await openFilter(wrapper)
      option('category-option-Vision')!.click()
      await flushPromises()

      expect(mockPluginIndex).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 9,
        category: 'Vision',
        search: 'first',
      })
      expect(mockReplace).toHaveBeenLastCalledWith({
        query: { category: 'Vision', search: 'first' },
      })
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

      expect(mockPluginIndex).toHaveBeenLastCalledWith({ page: 1, per_page: 9, category: null, search: null })
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
