import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PluginDetailView from '@/views/PluginDetailView.vue'

const mockPluginShow = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginShow: mockPluginShow
  })
}))

const mockRoute = {
  params: { id: 'plugin-123' },
  hash: ''
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  RouterLink: { template: '<a><slot></slot></a>' }
}))

// Mock fetch for README
const mockFetch = vi.fn()
global.fetch = mockFetch

// Mock scrollIntoView
window.HTMLElement.prototype.scrollIntoView = vi.fn()

describe('PluginDetailView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPluginShow.mockResolvedValue({
      data: {
        plugin: {
          id: 'plugin-123',
          name: '@dsh/my-plugin',
          title: 'My awesome plugin',
          license: 'MIT',
          user_id: 'user-456',
          updated_at: '2026-10-06T00:00:00Z',
          view_count: 100,
          star_count: 5,
          comment_count: 2,
          source_link: 'https://github.com/owner/repo'
        }
      }
    })
    
    mockFetch.mockResolvedValue({
      ok: true,
      text: () => Promise.resolve('# Hello Markdown\nThis is README')
    })
    
    Object.assign(navigator, {
      clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined)
      }
    })
  })

  it('renders loading state initially', async () => {
    mockPluginShow.mockReturnValue(new Promise(() => {}))
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    expect(wrapper.find('.animate-pulse').exists()).toBe(true)
  })

  it('fetches and displays plugin details and README', async () => {
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    // Markdown rendering is async and might require an extra tick
    await wrapper.vm.$nextTick()
    
    expect(mockPluginShow).toHaveBeenCalledWith('plugin-123')
    
    // Check plugin details
    expect(wrapper.text()).toContain('@dsh/my-plugin')
    expect(wrapper.text()).toContain('My awesome plugin')
    expect(wrapper.text()).toContain('MIT license')
    expect(wrapper.text()).toContain('100 views')
    
    // Check if fetch was called with a raw github URL
    expect(mockFetch).toHaveBeenCalledWith(expect.stringContaining('raw.githubusercontent.com/owner/repo'))
    
    // Check if markdown was rendered (Heading should be mapped to h1 or text)
    expect(wrapper.html()).toContain('Hello Markdown')
  })

  it('handles error when fetching plugin fails', async () => {
    mockPluginShow.mockRejectedValue(new Error('API error'))
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    
    expect(wrapper.text()).toContain('Failed to load plugin details.')
  })

  it('handles gracefully when README fetch fails', async () => {
    mockFetch.mockResolvedValue({
      ok: false
    })
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    await wrapper.vm.$nextTick()
    
    expect(wrapper.text()).toContain('No README available for this plugin.')
  })

  it('handles gracefully when source_link is missing', async () => {
    mockPluginShow.mockResolvedValue({
      data: {
        plugin: {
          id: 'plugin-123',
          name: '@dsh/no-readme',
          title: 'No readme plugin'
        }
      }
    })
    
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    
    expect(wrapper.text()).toContain('No README available for this plugin.')
    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('copies install command to clipboard', async () => {
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    
    const copyBtn = wrapper.find('button[title="Copy command"]')
    await copyBtn.trigger('click')
    
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('npx dsh add @dsh/my-plugin')
  })

  it('toggles star count', async () => {
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    
    const starBtn = wrapper.findAll('button').find(b => b.text().includes('Star'))
    expect(starBtn).toBeDefined()
    
    // Initial state: not starred, 5 stars
    expect(wrapper.text()).toContain('5 stars')
    
    // Click star
    await starBtn!.trigger('click')
    expect(wrapper.text()).toContain('Starred')
    // Top summary updates to 6
    expect(wrapper.text()).toContain('6 stars')
    
    // Click unstar
    await starBtn!.trigger('click')
    expect(wrapper.text()).toContain('Star')
    expect(wrapper.text()).toContain('5 stars')
  })

  it('increments comment count when comment-added event is emitted', async () => {
    const wrapper = mount(PluginDetailView, {
      global: { stubs: { PluginComments: true } }
    })
    
    await flushPromises()
    
    // The plugin object starts with 2 comments, but it is passed as a prop
    const pluginComments = wrapper.findComponent({ name: 'PluginComments' })
    expect(pluginComments.props('totalCommentCount')).toBe(2)
    
    // Emit event
    await pluginComments.vm.$emit('comment-added')
    
    expect(pluginComments.props('totalCommentCount')).toBe(3)
  })
})
