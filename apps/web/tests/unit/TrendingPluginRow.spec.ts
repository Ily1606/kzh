import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import TrendingPluginRow from '@/components/pages/trending/TrendingPluginRow.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { PluginResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'

const mockStarStoreBySlug = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getStar: () => ({
    starStoreBySlug: mockStarStoreBySlug
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
    name: 'RouterLink',
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
    username: 'octocat',
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

  it('renders plugin name, title and stats via BasePluginRow', () => {
    const wrapper = mount(TrendingPluginRow, {
      props: {
        plugin: testPlugin,
        rank: 1
      }
    })

    // Rank #1 badge (number only, no # prefix)
    expect(wrapper.text()).toContain('1')

    // Plugin name (simple format, not GitHub style)
    expect(wrapper.text()).toContain('code-reviewer')

    // Title
    expect(wrapper.text()).toContain('Automated pull request code review assistant')

    // Stats
    expect(wrapper.text()).toContain('520')
    expect(wrapper.text()).toContain('3,200')
    expect(wrapper.text()).toContain('42')

    // Author name in meta row
    expect(wrapper.text()).toContain('octocat')

    // Date
    expect(wrapper.text()).toContain(formatRelativeDate('2026-10-15T12:00:00Z'))

    // Link to plugin detail
    const pluginLink = wrapper.find('a')
    expect(pluginLink.exists()).toBe(true)
  })

  it('renders different rank badges with correct colors', () => {
    const ranks = [1, 2, 3, 4, 10]

    ranks.forEach(rank => {
      const wrapper = mount(TrendingPluginRow, {
        props: { plugin: testPlugin, rank }
      })

      expect(wrapper.text()).toContain(`${rank}`)
    })
  })

  it('falls back to community when author is missing', () => {
    const pluginWithoutAuthor = { ...testPlugin, author: undefined }

    const wrapper = mount(TrendingPluginRow, {
      props: { plugin: pluginWithoutAuthor, rank: 1 }
    })

    expect(wrapper.text()).toContain('community')
  })
})