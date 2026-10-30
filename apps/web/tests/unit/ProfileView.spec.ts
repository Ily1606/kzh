import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import ProfileView from '@/views/ProfileView.vue'

const mockAuthorShow = vi.fn()
const mockAuthorPlugins = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getAuthor: () => ({
    authorShow: mockAuthorShow,
    authorPlugins: mockAuthorPlugins,
  }),
}))

const mockRoute = ref({
  params: { username: 'ada' } as Record<string, string>,
  query: {} as Record<string, string | string[]>,
})

const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ replace: mockReplace }),
  RouterLink: { template: '<a><slot /></a>' },
}))

function makeAuthor(overrides: Record<string, unknown> = {}) {
  return {
    id: 'user-1',
    name: 'Ada Lovelace',
    username: 'ada',
    avatar_url: 'https://example.com/avatar.png',
    github_link: 'https://github.com/ada',
    created_at: '2026-01-15T00:00:00Z',
    ...overrides,
  }
}

function makePlugin(overrides: Record<string, unknown> = {}) {
  return {
    id: 'plugin-1',
    name: '@dsh/dsr-webhook',
    user_id: 'user-1',
    author: { id: 'user-1', name: 'Ada Lovelace', username: 'ada', avatar_url: null },
    title: 'Webhook notifier',
    license: 'MIT',
    category: 'Integrations',
    tags: [],
    approved_at: '2026-10-01T00:00:00Z',
    status: 'approved',
    source_link: 'https://github.com/owner/repo',
    star_count: 42,
    comment_count: 7,
    view_count: 1200,
    created_at: '2026-09-30T00:00:00Z',
    updated_at: '2026-10-02T00:00:00Z',
    ...overrides,
  }
}

const meta = { current_page: 1, last_page: 3, total: 25, per_page: 9 }

function mountView() {
  return mount(ProfileView)
}

describe('ProfileView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockRoute.value.params = { username: 'ada' }
    mockRoute.value.query = {}

    mockAuthorShow.mockResolvedValue({ success: true, data: makeAuthor() })
    mockAuthorPlugins.mockResolvedValue({ data: [makePlugin()], meta })
  })

  it('loads the author and their published plugins', async () => {
    const wrapper = mountView()
    await flushPromises()

    expect(mockAuthorShow).toHaveBeenCalledWith('ada')
    expect(mockAuthorPlugins).toHaveBeenCalledWith('ada', { page: 1, per_page: 9 })
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('@ada')
    expect(wrapper.text()).toContain('https://github.com/ada')
  })

  it('renders a row per published plugin', async () => {
    mockAuthorPlugins.mockResolvedValue({
      data: [makePlugin({ id: 'p1' }), makePlugin({ id: 'p2' })],
      meta,
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.findAllComponents({ name: 'BasePluginRow' })).toHaveLength(2)
  })

  it('reads the page from the URL', async () => {
    mockRoute.value.query = { page: '3' }

    mountView()
    await flushPromises()

    expect(mockAuthorPlugins).toHaveBeenCalledWith('ada', { page: 3, per_page: 9 })
  })

  it('does not reload the profile when only the page changes', async () => {
    mountView()
    await flushPromises()

    mockAuthorShow.mockClear()
    mockRoute.value.query = { page: '2' }
    await flushPromises()

    expect(mockAuthorShow).not.toHaveBeenCalled()
    expect(mockAuthorPlugins).toHaveBeenCalledWith('ada', { page: 2, per_page: 9 })
  })

  it('replaces the URL when paging', async () => {
    const wrapper = mountView()
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Next'))!.trigger('click')

    expect(mockReplace).toHaveBeenCalledWith({
      name: 'author-profile',
      params: { username: 'ada' },
      query: { page: '2' },
    })
  })

  it('renders the pagination', async () => {
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Page 1 of 3')
    expect(wrapper.findAll('button').find((b) => b.text().includes('Previous'))!.attributes('disabled'))
      .toBeDefined()
  })

  it('shows the empty state when the author published nothing', async () => {
    mockAuthorPlugins.mockResolvedValue({
      data: [],
      meta: { ...meta, total: 0, last_page: 1 },
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('No published plugins yet')
    expect(wrapper.findAllComponents({ name: 'BasePluginRow' })).toHaveLength(0)
  })

  it('shows the not found state for an unknown author', async () => {
    mockAuthorShow.mockRejectedValue({ response: { status: 404 } })
    mockAuthorPlugins.mockRejectedValue({ response: { status: 404 } })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Profile not found')
  })

  it('shows the error state with a retry button', async () => {
    mockAuthorShow.mockRejectedValue({
      response: { status: 500, data: { message: 'Something broke.' } },
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Something went wrong')
    expect(wrapper.text()).toContain('Something broke.')

    mockAuthorShow.mockResolvedValue({ success: true, data: makeAuthor() })
    await wrapper.findAll('button').find((b) => b.text().includes('Try again'))!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Ada Lovelace')
  })
})
