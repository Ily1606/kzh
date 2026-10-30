import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp, ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import TrendingView from '@/views/TrendingView.vue'

const mockPluginTrending = vi.fn()
const mockStarStoreBySlug = vi.fn()

vi.mock('@/api/generated/endpoints', async () => {
  const { getCategory, getTag } = await import('../helpers/taxonomy')

  return {
    getPlugin: () => ({
      pluginTrending: mockPluginTrending
    }),
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
    getCategory,
    getTag
  }
})

const mockRoute = ref({ query: {}, fullPath: '/trending' })
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  RouterLink: {
    template: '<a :href="to"><slot /></a>',
    props: ['to']
  }
}))

// Mock window.scrollTo
Object.defineProperty(window, 'scrollTo', { value: vi.fn(), writable: true })

const samplePlugins = [
  {
    id: '10',
    name: 'deepseek-agent',
    title: 'Autonomous multi-step coding agent plugin',
    author: { id: 'u1', name: 'octocat', username: 'octocat', avatar_url: 'https://example.com/avatar1.png' },
    category: 'Developer tools',
    tags: ['agent', 'coding'],
    star_count: 1420,
    is_star: false,
    view_count: 8530,
    comment_count: 32,
    source_link: 'https://github.com/octocat/deepseek-agent',
    approved_at: '2026-10-15T10:00:00Z',
    status: 'approved'
  },
  {
    id: '11',
    name: 'vision-analyzer',
    title: 'Image recognition and OCR tool for harness',
    author: { id: 'u2', name: 'alice', avatar_url: null },
    category: 'Vision',
    tags: ['vision', 'ocr'],
    star_count: 890,
    is_star: true,
    view_count: 4210,
    comment_count: 15,
    source_link: null,
    approved_at: '2026-10-18T10:00:00Z',
    status: 'approved'
  }
]

function mountView() {
  return mount(TrendingView, {
    attachTo: document.body,
    global: {
      stubs: {
        RouterLink: {
          template: '<a :href="to"><slot /></a>',
          props: ['to']
        }
      }
    }
  })
}

describe('TrendingView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockPluginTrending.mockResolvedValue({
      data: {
        plugins: JSON.parse(JSON.stringify(samplePlugins))
      }
    })
    mockStarStoreBySlug.mockResolvedValue({
      data: {
        starred: true,
        star_count: 1421
      }
    })
  })

  it('fetches and renders trending plugins in GitHub style rows', async () => {
    const wrapper = mountView()
    await flushPromises()

    expect(mockPluginTrending).toHaveBeenCalled()

    // Page title and description
    expect(wrapper.text()).toContain('Trending Plugins')
    expect(wrapper.text()).toContain('Ranked by recent engagement, stars, and views.')

    // Checks ranks - verify rank badges in each row
    const rows = wrapper.findAll('[data-plugin-id]')
    expect(rows.length).toBeGreaterThanOrEqual(2)

    const rank1Badge = rows[0].find('span.rounded-full')
    expect(rank1Badge.exists()).toBe(true)
    expect(rank1Badge.text()).toBe('1')
    expect(rank1Badge.classes()).toContain('bg-amber-100')

    const rank2Badge = rows[1].find('span.rounded-full')
    expect(rank2Badge.exists()).toBe(true)
    expect(rank2Badge.text()).toBe('2')
    expect(rank2Badge.classes()).toContain('bg-slate-200')

    // Checks author / plugin name
    expect(wrapper.text()).toContain('octocat')
    expect(wrapper.text()).toContain('deepseek-agent')
    expect(wrapper.text()).toContain('alice')
    expect(wrapper.text()).toContain('vision-analyzer')

    // Checks description
    expect(wrapper.text()).toContain('Autonomous multi-step coding agent plugin')
    expect(wrapper.text()).toContain('Image recognition and OCR tool for harness')

    // Checks stars and views
    expect(wrapper.text()).toContain('1,420')
    expect(wrapper.text()).toContain('8,530')
    expect(wrapper.text()).toContain('890')
    expect(wrapper.text()).toContain('4,210')
  })

  it('renders trending rows with rank, name, title, author, stats and date', async () => {
    const wrapper = mountView()
    await flushPromises()

    // Find first row
    const row1 = wrapper.find('[data-plugin-id="10"]')
    expect(row1.exists()).toBe(true)

    // Row contains rank badge (1), plugin name, title, author, stars, views, comments, date
    const rank1Badge = row1.find('span.rounded-full')
    expect(rank1Badge.exists()).toBe(true)
    expect(rank1Badge.text()).toBe('1')
    expect(rank1Badge.classes()).toContain('bg-amber-100')
    expect(row1.text()).toContain('deepseek-agent')
    expect(row1.text()).toContain('Autonomous multi-step coding agent plugin')
    expect(row1.text()).toContain('octocat')
    expect(row1.text()).toContain('1,420') // stars
    expect(row1.text()).toContain('8,530') // views
    expect(row1.text()).toContain('32') // comments
  })

  it('renders second row with correct rank and data', async () => {
    const wrapper = mountView()
    await flushPromises()

    const row2 = wrapper.find('[data-plugin-id="11"]')
    expect(row2.exists()).toBe(true)

    const rank2Badge = row2.find('span.rounded-full')
    expect(rank2Badge.exists()).toBe(true)
    expect(rank2Badge.text()).toBe('2')
    expect(rank2Badge.classes()).toContain('bg-slate-200')
    expect(row2.text()).toContain('vision-analyzer')
    expect(row2.text()).toContain('Image recognition and OCR tool for harness')
    expect(row2.text()).toContain('alice')
    expect(row2.text()).toContain('890')
    expect(row2.text()).toContain('4,210')
    expect(row2.text()).toContain('15')
  })

  it('shows empty state when no trending plugins exist', async () => {
    mockPluginTrending.mockResolvedValue({
      data: { plugins: [] }
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('No trending plugins available')
    expect(wrapper.text()).toContain('Explore all plugins')
  })

  it('shows error state and can retry', async () => {
    mockPluginTrending.mockRejectedValue({
      response: { data: { message: 'Network error occurred' } }
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Network error occurred')
    expect(wrapper.text()).toContain('Try again')

    // Next call succeeds
    mockPluginTrending.mockResolvedValue({
      data: { plugins: JSON.parse(JSON.stringify(samplePlugins)) }
    })

    const retryBtn = wrapper.find('[data-test="retry-button"]')
    await retryBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('deepseek-agent')
  })

  it('paginates trending plugins at 10 items per page and updates ranks accordingly', async () => {
    // Generate 25 plugins
    const manyPlugins = Array.from({ length: 25 }, (_, i) => ({
      id: `plugin-${i + 1}`,
      name: `pkg-trending-${i + 1}`,
      title: `Description for package ${i + 1}`,
      author: { id: `u-${i + 1}`, name: `dev-${i + 1}`, avatar_url: null },
      category: 'Developer tools',
      tags: ['tool'],
      star_count: 500 - i * 10,
      is_star: false,
      view_count: 2000 - i * 50,
      comment_count: 5,
      source_link: null,
      approved_at: '2026-10-15T10:00:00Z',
      status: 'approved'
    }))

    mockPluginTrending.mockResolvedValue({
      data: { plugins: manyPlugins }
    })

    const wrapper = mountView()
    await flushPromises()

    // Page 1: Should show items 1 to 10
    expect(wrapper.text()).toContain('pkg-trending-1')
    expect(wrapper.text()).toContain('pkg-trending-10')
    expect(wrapper.text()).not.toContain('pkg-trending-11')

    // Pagination info
    expect(wrapper.text()).toContain('Showing 1 to 10 of 25 plugins')

    // Previous button should be disabled on page 1
    const prevBtn = wrapper.find('button[data-test="prev-page"]')
    const nextBtn = wrapper.find('button[data-test="next-page"]')
    expect(prevBtn.attributes('disabled')).toBeDefined()
    expect(nextBtn.attributes('disabled')).toBeUndefined()

    // Click Next to go to Page 2
    await nextBtn.trigger('click')
    await flushPromises()

    // Page 2: Should show items 11 to 20
    expect(wrapper.text()).not.toContain('pkg-trending-10')
    expect(wrapper.text()).toContain('pkg-trending-11')
    expect(wrapper.text()).toContain('pkg-trending-20')
    expect(wrapper.text()).not.toContain('pkg-trending-21')
    expect(wrapper.text()).toContain('Showing 11 to 20 of 25 plugins')

    // Click Page 3 directly
    const page3Btn = wrapper.find('button[data-test="page-button-3"]')
    expect(page3Btn.exists()).toBe(true)
    await page3Btn.trigger('click')
    await flushPromises()

    // Page 3: Should show items 21 to 25
    expect(wrapper.text()).toContain('pkg-trending-21')
    expect(wrapper.text()).toContain('pkg-trending-25')
    expect(wrapper.text()).toContain('Showing 21 to 25 of 25 plugins')

    // Next button should be disabled on the last page
    expect(wrapper.find('button[data-test="next-page"]').attributes('disabled')).toBeDefined()
  })
})

