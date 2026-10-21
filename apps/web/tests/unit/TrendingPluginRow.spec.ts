import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import TrendingPluginRow from '@/components/pages/trending/TrendingPluginRow.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { PluginResource } from '@/api/generated/model'

const mockStarStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getStar: () => ({
    starStore: mockStarStore
  }),
  getAuth: () => ({
    authLogin: vi.fn(),
    authRegister: vi.fn(),
    authLogout: vi.fn(),
  }),
  getProfile: () => ({
    profileShow: vi.fn(),
  }),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ fullPath: '/trending' }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  RouterLink: {
    template: '<a :href="to"><slot /></a>',
    props: ['to']
  }
}))

const testPlugin: PluginResource = {
  id: 'plug-1',
  name: 'code-reviewer',
  user_id: 'author-1',
  title: 'Automated pull request code review assistant',
  author: {
    id: 'author-1',
    name: 'octocat',
    avatar_url: 'https://example.com/octo.png'
  },
  category: 'Developer tools',
  tags: ['ai', 'review'],
  license: 'MIT',
  star_count: 520,
  is_star: false,
  view_count: 3200,
  comment_count: 42,
  source_link: 'https://github.com/octocat/code-reviewer',
  approved_at: '2026-10-15T12:00:00Z',
  created_at: '2026-10-15T12:00:00Z',
  updated_at: '2026-10-15T12:00:00Z',
  status: 'approved'
}

describe('TrendingPluginRow.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)

    usePluginsStore().put([testPlugin])
  })

  it('renders all required plugin information in GitHub style', () => {
    const wrapper = mount(TrendingPluginRow, {
      props: {
        plugin: testPlugin,
        rank: 1
      }
    })

    // Rank #1
    expect(wrapper.text()).toContain('1')

    // Author and plugin name
    expect(wrapper.text()).toContain('octocat')
    expect(wrapper.text()).toContain('code-reviewer')

    // Basic info / description
    expect(wrapper.text()).toContain('Automated pull request code review assistant')

    // Category
    expect(wrapper.text()).toContain('Developer tools')

    // Star count & View count & Comments
    expect(wrapper.text()).toContain('520')
    expect(wrapper.text()).toContain('3,200')
    expect(wrapper.text()).toContain('42')

    // Source link
    const sourceLink = wrapper.find('a[title="Source code"]')
    expect(sourceLink.exists()).toBe(true)
    expect(sourceLink.attributes('href')).toBe('https://github.com/octocat/code-reviewer')

    // Star button exists in the row
    const starBtn = wrapper.find('button[data-test="star-button"]')
    expect(starBtn.exists()).toBe(true)
  })

  it('allows authenticated user to star directly from the row', async () => {
    const auth = useAuthStore()
    auth.setUser({
      id: 'u-1',
      name: 'User 1',
      email: 'user1@example.com',
      avatarLink: '',
      githubName: null,
      githubLink: null,
      is_admin: false,
      created_at: null
    })
    auth.setToken('auth-token')

    mockStarStore.mockResolvedValue({
      data: { starred: true, star_count: 521 }
    })

    const wrapper = mount(TrendingPluginRow, {
      props: {
        plugin: testPlugin,
        rank: 2
      }
    })

    const starBtn = wrapper.find('button[data-test="star-button"]')
    await starBtn.trigger('click')
    await flushPromises()

    expect(mockStarStore).toHaveBeenCalledWith('plug-1', { starred: true })
    expect(wrapper.text()).toContain('Starred')
  })
})
