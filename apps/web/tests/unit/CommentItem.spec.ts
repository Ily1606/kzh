import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CommentItem from '@/components/pages/comment/CommentItem.vue'
import { ref } from 'vue'

const mockCommentReplies = vi.fn()
const mockCommentStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getComment: () => ({
    commentReplies: mockCommentReplies,
    commentStore: mockCommentStore
  })
}))

const mockIsAuthenticated = ref(true)
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    get isAuthenticated() { return mockIsAuthenticated.value }
  })
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ fullPath: '/plugins/1', path: '/plugins/1' })
}))

const mockComment = {
  id: 'c1',
  plugin_id: '1',
  parent_comment_id: '',
  content: 'Hello world',
  created_at: '2026-10-06T00:00:00Z',
  updated_at: '2026-10-06T00:00:00Z',
  replies_count: '1',
  author: {
    id: 'u1',
    name: 'Alice',
    avatar_url: ''
  }
}

describe('CommentItem.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('alert', vi.fn())
  })

  it('renders author and content correctly', () => {
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })
    expect(wrapper.text()).toContain('Alice')
    expect(wrapper.text()).toContain('Hello world')
  })

  it('does not show reply button if depth is 3', () => {
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 3 },
      global: { stubs: ['router-link'] }
    })
    expect(wrapper.text()).not.toContain('Reply')
  })

  it('toggles reply box and submits a reply successfully', async () => {
    mockIsAuthenticated.value = true
    mockCommentStore.mockResolvedValueOnce({ data: { comment: { id: 'c2', content: 'Reply content' } } })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    // Open reply form
    const replyBtn = wrapper.findAll('button').find(b => b.text().includes('Reply'))
    expect(replyBtn).toBeDefined()
    await replyBtn!.trigger('click')

    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)
    await textarea.setValue('My reply')

    // Find the Reply button inside the form
    const buttons = wrapper.findAll('button')
    const submitBtn = buttons.filter(b => b.text() === 'Reply').pop()
    await submitBtn!.trigger('click')

    expect(mockCommentStore).toHaveBeenCalledWith('1', { content: 'My reply', parent_comment_id: 'c1' })
    await flushPromises()

    // The comment replies_count should have incremented
    expect(wrapper.props('comment').replies_count).toBe('2')
  })

  it('shows alert when reply submission fails', async () => {
    mockIsAuthenticated.value = true
    mockCommentStore.mockRejectedValueOnce({ response: { data: { message: 'Reply error' } } })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    await wrapper.findAll('button').find(b => b.text().includes('Reply'))!.trigger('click')
    await wrapper.find('textarea').setValue('My reply')
    const submitBtnFail = wrapper.findAll('button').filter(b => b.text() === 'Reply').pop()
    await submitBtnFail!.trigger('click')
    await flushPromises()

    expect(window.alert).toHaveBeenCalledWith('Reply error')
  })

  it('loads replies when clicking view replies', async () => {
    mockCommentReplies.mockResolvedValueOnce({ data: { comments: [{ id: 'c3', content: 'Loaded reply' }] } })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    const viewRepliesBtn = wrapper.findAll('button').find(b => b.text().includes('View 1 reply'))
    await viewRepliesBtn!.trigger('click')

    await flushPromises()

    expect(mockCommentReplies).toHaveBeenCalledWith('1', 'c1', { page: 1, per_page: 10 })
    expect(wrapper.text()).toContain('Loaded reply')
  })

  it('submits a report and shows success alert', async () => {
    // Override setTimeout to run immediately for the mock report
    vi.useFakeTimers()

    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    // Find flag button and click
    const flagBtn = wrapper.find('button[title="Report this comment"]')
    await flagBtn.trigger('click')

    // Modal is open
    const textarea = wrapper.find('textarea[placeholder="Reason for reporting..."]')
    expect(textarea.exists()).toBe(true)
    await textarea.setValue('spamming')

    // Submit report
    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    await submitBtn!.trigger('click')

    // Advance timers
    vi.runAllTimers()
    await flushPromises()

    expect(window.alert).toHaveBeenCalledWith('Your report has been submitted successfully.')
    expect(wrapper.find('textarea[placeholder="Reason for reporting..."]').exists()).toBe(false)

    vi.useRealTimers()
  })
  it('shows sign in link when unauthenticated user tries to reply', async () => {
    mockIsAuthenticated.value = false
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })
    
    await wrapper.findAll('button').find(b => b.text().includes('Reply'))!.trigger('click')
    await flushPromises()
    
    expect(wrapper.text()).toContain('Please sign in to reply.')
    expect(wrapper.find('textarea').exists()).toBe(false)
  })

  it('hides replies when clicking view replies again', async () => {
    mockCommentReplies.mockResolvedValueOnce({ data: { comments: [{ id: 'c3', content: 'Loaded reply' }] } })
    
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })
    
    const viewBtn = wrapper.findAll('button').find(b => b.text().includes('View 1 reply'))
    await viewBtn!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Loaded reply')
    
    const hideBtn = wrapper.findAll('button').find(b => b.text().includes('Hide 1 reply'))
    await hideBtn!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain('Loaded reply')
  })
})
