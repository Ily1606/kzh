import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import MyPluginsView from '@/views/MyPluginsView.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { UserResource } from '@/api/generated/model'

const mockPluginMyPlugins = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginMyPlugins: mockPluginMyPlugins
  }),
  getStar: () => ({ starStore: vi.fn() })
}))

vi.mock('@/utils/toast', () => ({
  notifyError: vi.fn(),
  notifySuccess: vi.fn()
}))

const mockRoute = ref({
  path: '/resources',
  fullPath: '/resources',
  query: {} as Record<string, string | string[]>
})

const mockPush = vi.fn()
const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  RouterLink: { template: '<a><slot /></a>' }
}))

function makePlugin(overrides: Record<string, unknown> = {}) {
  return {
    id: 'plugin-1',
    name: '@dsh/dsr-webhook',
    user_id: 'user-1',
    author: null,
    title: 'Webhook notifier',
    license: 'MIT',
    approved_at: '2026-10-01T00:00:00Z',
    status: 'approved',
    source_link: 'https://github.com/owner/repo',
    star_count: 42,
    comment_count: 7,
    view_count: 1200,
    created_at: '2026-09-30T00:00:00Z',
    updated_at: '2026-10-02T00:00:00Z',
    ...overrides
  }
}

const meta = { current_page: 1, last_page: 3, total: 25 }

function signIn() {
  const auth = useAuthStore()
  auth.setUser({ id: 'user-1', name: 'Tester', email: 't@example.com' } as UserResource)
  auth.setToken('mock-token')
}

function mountView() {
  return mount(MyPluginsView, {
    global: {
      stubs: {
        RouterLink: true,
        MyPluginRow: true
      }
    }
  })
}

describe('MyPluginsView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockRoute.value.query = {}
    mockPluginMyPlugins.mockResolvedValue({ data: [makePlugin()], meta })
    signIn()
  })

  it('fetches the caller plugins without a status filter and renders rows', async () => {
    const wrapper = mountView()

    await flushPromises()

    expect(mockPluginMyPlugins).toHaveBeenCalledWith({ page: 1, per_page: 9, status: undefined })
    expect(wrapper.findAllComponents({ name: 'MyPluginRow' }).length).toBe(1)
  })

  it('does not call the API for a guest', async () => {
    useAuthStore().clearAuth()

    const wrapper = mountView()
    await flushPromises()

    expect(mockPluginMyPlugins).not.toHaveBeenCalled()
    // Not left spinning: the flag has to be released for a guest too.
    expect(wrapper.text()).not.toContain('Loading your plugins...')
  })

  it('renders plugins of every status, not only approved ones', async () => {
    mockPluginMyPlugins.mockResolvedValue({
      data: [
        makePlugin({ id: 'p1', status: 'pending', approved_at: null, star_count: 0 }),
        makePlugin({ id: 'p2', status: 'approved' }),
        makePlugin({ id: 'p3', status: 'rejected', approved_at: null })
      ],
      meta
    })

    const wrapper = mountView()
    await flushPromises()

    // The whole point of this list: a pending plugin has to be visible here.
    expect(wrapper.findAllComponents({ name: 'MyPluginRow' }).length).toBe(3)
  })

  it('passes the status from the URL to the API', async () => {
    mockRoute.value.query = { status: 'pending' }

    const wrapper = mountView()
    await flushPromises()

    expect(mockPluginMyPlugins).toHaveBeenCalledWith({ page: 1, per_page: 9, status: 'pending' })
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toContain('Pending')
  })

  it('falls back to the All tab for an unknown status instead of sending it', async () => {
    mockRoute.value.query = { status: 'published' }

    mountView()
    await flushPromises()

    expect(mockPluginMyPlugins).toHaveBeenCalledWith({ page: 1, per_page: 9, status: undefined })
  })

  it('pushes the status onto the URL when a tab is clicked', async () => {
    const wrapper = mountView()
    await flushPromises()

    const rejectedTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes('Rejected'))
    await rejectedTab!.trigger('click')

    expect(mockPush).toHaveBeenCalledWith({ name: 'my-plugins', query: { status: 'rejected' } })
  })

  it('uses replace rather than push when paging', async () => {
    mockRoute.value.query = { status: 'pending' }

    const wrapper = mountView()
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Next'))!.trigger('click')

    expect(mockReplace).toHaveBeenCalledWith({
      name: 'my-plugins',
      query: { status: 'pending', page: '2' }
    })
  })

  it('reads the page from the URL on load', async () => {
    mockRoute.value.query = { page: '3' }

    mountView()
    await flushPromises()

    expect(mockPluginMyPlugins).toHaveBeenCalledWith({ page: 3, per_page: 9, status: undefined })
  })

  it('renders the pagination from the store', async () => {
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Page 1 of 3')
    expect(wrapper.findAll('button').find((b) => b.text().includes('Previous'))!.attributes('disabled'))
      .toBeDefined()
  })

  it('shows the onboarding empty state when the owner has nothing', async () => {
    mockPluginMyPlugins.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain("You haven't published any plugins yet")
  })

  it('shows the per-tab empty state when one tab is empty', async () => {
    mockRoute.value.query = { status: 'rejected' }
    mockPluginMyPlugins.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('No rejected plugins')
    expect(wrapper.text()).not.toContain("You haven't published any plugins yet")
  })

  it('shows the error state with a retry button', async () => {
    mockPluginMyPlugins.mockRejectedValue({
      response: { data: { message: 'Could not load your plugins.' } }
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Something went wrong')
    expect(wrapper.text()).toContain('Could not load your plugins.')

    mockPluginMyPlugins.mockResolvedValue({ data: [makePlugin()], meta })
    await wrapper.findAll('button').find((b) => b.text().includes('Try again'))!.trigger('click')
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'MyPluginRow' }).length).toBe(1)
  })

  it('reads the rows from the store', async () => {
    const store = usePluginsStore()
    const wrapper = mountView()
    await flushPromises()

    expect(store.minePlugins).toHaveLength(1)

    store.put([makePlugin({ id: 'p-new', name: '@dsh/dsr-logger' })])
    store.mine = { ids: ['p-new'], page: 1, total: 1, lastPage: 1 }
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'MyPluginRow' }).length).toBe(1)
    expect(wrapper.findComponent({ name: 'MyPluginRow' }).props('plugin')).toMatchObject({ id: 'p-new' })
  })
})
