import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PluginComments from '@/components/PluginComments.vue'

const mockCommentIndex = vi.fn()
const mockCommentStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getComment: () => ({
    commentIndex: mockCommentIndex,
    commentStore: mockCommentStore
  })
}))

const mockIsAuthenticated = ref(true)
vi.mock('@/composables/useAuth', () => {
  return {
    useAuth: () => ({
      get isAuthenticated() { return mockIsAuthenticated.value }
    })
  }
})

// Mocks for vue core/router
vi.mock('vue-router', () => ({
  useRoute: () => ({ fullPath: '/plugins/1', path: '/plugins/1' })
}))

import { ref } from 'vue'

describe('PluginComments.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('alert', vi.fn())
  })

  it('shows loading state initially and then empty state if no comments', async () => {
    mockCommentIndex.mockResolvedValueOnce({ data: { data: { comments: [] } } })
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'], mocks: { $route: { path: '/plugins/1', fullPath: '/plugins/1' } } }
    })
    
    expect(wrapper.text()).toContain('Loading comments...')
    await flushPromises()
    expect(wrapper.text()).toContain('No comments yet')
  })

  it('shows error state if fetching comments fails', async () => {
    mockCommentIndex.mockRejectedValueOnce(new Error('Network error'))
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'], mocks: { $route: { path: '/plugins/1', fullPath: '/plugins/1' } } }
    })
    
    await flushPromises()
    expect(wrapper.text()).toContain('Failed to load comments.')
  })

  it('shows sign in link when unauthenticated', async () => {
    mockIsAuthenticated.value = false
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [] } })
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'], mocks: { $route: { path: '/plugins/1', fullPath: '/plugins/1' } } }
    })
    
    await flushPromises()
    expect(wrapper.text()).toContain('to leave a comment')
    expect(wrapper.find('router-link-stub').exists()).toBe(true)
    expect(wrapper.find('textarea').exists()).toBe(false)
  })

  it('submits a new comment successfully', async () => {
    mockIsAuthenticated.value = true
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [] } })
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [{ id: 'c1', content: 'hello' }] } }) // For refetch
    mockCommentStore.mockResolvedValueOnce({})
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'], mocks: { $route: { path: '/plugins/1', fullPath: '/plugins/1' } } }
    })
    
    await flushPromises()
    
    const textarea = wrapper.find('textarea')
    await textarea.setValue('This is a great plugin!')
    await wrapper.find('form').trigger('submit')
    
    expect(mockCommentStore).toHaveBeenCalledWith('1', { content: 'This is a great plugin!' })
    await flushPromises()
    
    expect(wrapper.emitted('commentAdded')).toBeTruthy()
  })

  it('shows alert when submitting a comment fails', async () => {
    mockIsAuthenticated.value = true
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [] } })
    mockCommentStore.mockRejectedValueOnce({ response: { data: { message: 'Submission failed' } } })
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'], mocks: { $route: { path: '/plugins/1', fullPath: '/plugins/1' } } }
    })
    
    await flushPromises()
    
    await wrapper.find('textarea').setValue('Test error')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    
    expect(window.alert).toHaveBeenCalledWith('Submission failed')
  })
  it('uses totalCommentCount prop if provided', async () => {
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [] } })
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1', totalCommentCount: 99 },
      global: { stubs: ['router-link', 'CommentItem'] }
    })
    await flushPromises()
    // It should display '99' and '99 discussions'
    expect(wrapper.text()).toContain('99 discussions')
  })

  it('does not submit comment if text is empty', async () => {
    mockIsAuthenticated.value = true
    mockCommentIndex.mockResolvedValueOnce({ data: { comments: [] } })
    
    const wrapper = mount(PluginComments, {
      props: { pluginId: '1' },
      global: { stubs: ['router-link', 'CommentItem'] }
    })
    
    await flushPromises()
    
    const textarea = wrapper.find('textarea')
    await textarea.setValue('   ')
    
    const submitBtn = wrapper.find('button[type="submit"]')
    expect((submitBtn.element as HTMLButtonElement).disabled).toBe(true)
    
    await wrapper.find('form').trigger('submit')
    // No api call because of !trim() inside component or disabled button
    expect(mockCommentStore).not.toHaveBeenCalled()
  })
})
