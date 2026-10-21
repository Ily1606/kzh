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

/**
 * The reply toggle and the reply submit button both read "Reply", and the
 * submit one only comes into existence once the form is open — so it is the
 * last match, and it is enabled only when the textarea has content.
 */
function replySubmitButton(wrapper: ReturnType<typeof mount>) {
  const buttons = wrapper.findAll('button').filter((button) => button.text() === 'Reply')

  expect(buttons.length).toBeGreaterThan(0)

  return buttons[buttons.length - 1]
}

describe('CommentItem.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('alert', vi.fn())
    mockIsAuthenticated.value = true
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

  it('toggles reply box and fetches existing replies so they are not hidden', async () => {
    mockIsAuthenticated.value = true
    mockCommentStore.mockResolvedValueOnce({ data: { comment: { id: 'c2', content: 'Reply content' } } })
    mockCommentReplies.mockResolvedValueOnce({ data: { comments: [{ id: 'c3', content: 'Existing reply' }] } })

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
    await wrapper.vm.$nextTick()

    // Both the toggle and the submit button read "Reply"; the submit is the
    // second one, and it only enables once the box has content.
    const submitBtn = replySubmitButton(wrapper)
    await submitBtn.trigger('click')

    expect(mockCommentStore).toHaveBeenCalledWith('1', { content: 'My reply', parent_comment_id: 'c1' })
    await flushPromises()

    // The comment replies_count should have incremented
    expect(wrapper.props('comment').replies_count).toBe('2')
    // Fetches existing replies so old replies are not hidden
    expect(mockCommentReplies).toHaveBeenCalledWith('1', 'c1', { page: 1, per_page: 10 })
    expect(wrapper.text()).toContain('Reply content')
    expect(wrapper.text()).toContain('Existing reply')
  })

  it('submits a reply without calling commentReplies if comment has 0 replies', async () => {
    mockIsAuthenticated.value = true
    mockCommentStore.mockResolvedValueOnce({ data: { comment: { id: 'c2', content: 'Reply content' } } })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment, replies_count: '0' }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    const replyBtn = wrapper.findAll('button').find(b => b.text().includes('Reply'))
    await replyBtn!.trigger('click')

    await wrapper.find('textarea').setValue('My reply')
    await wrapper.vm.$nextTick()

    const submitBtn = replySubmitButton(wrapper)
    await submitBtn.trigger('click')
    await flushPromises()

    expect(wrapper.props('comment').replies_count).toBe('1')
    expect(mockCommentReplies).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Reply content')
  })

  it('does not re-fetch replies if replies were already loaded before submitting reply', async () => {
    mockIsAuthenticated.value = true
    mockCommentReplies.mockResolvedValueOnce({ data: { comments: [{ id: 'c3', content: 'Already loaded' }] } })
    mockCommentStore.mockResolvedValueOnce({ data: { comment: { id: 'c2', content: 'New reply' } } })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment, replies_count: '1' }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    // 1. View replies first
    const viewRepliesBtn = wrapper.findAll('button').find(b => b.text().includes('View 1 reply'))
    await viewRepliesBtn!.trigger('click')
    await flushPromises()
    expect(mockCommentReplies).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Already loaded')

    // 2. Open reply form on root comment
    const toggleReplyBtn = wrapper.findAll('button').find(b => b.text() === 'Reply')
    await toggleReplyBtn!.trigger('click')
    await wrapper.vm.$nextTick()

    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)
    await textarea.setValue('Another reply')
    await wrapper.vm.$nextTick()

    // Submit reply
    const submitBtn = wrapper.findAll('button').find(b => b.text() === 'Reply' && b.element !== toggleReplyBtn?.element)
    await submitBtn!.trigger('click')
    await flushPromises()

    // Does NOT call mockCommentReplies a second time because replies were already fetched
    expect(mockCommentReplies).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('New reply')
    expect(wrapper.text()).toContain('Already loaded')
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
    await wrapper.vm.$nextTick()
    await replySubmitButton(wrapper).trigger('click')
    await flushPromises()

    expect(window.alert).toHaveBeenCalledWith('Reply error')
    // The count is untouched by a reply that never landed.
    expect(wrapper.props('comment').replies_count).toBe('1')
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

    // Replies are paginated like top-level comments.
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
    
    expect(wrapper.text()).toContain('Please sign in to reply')
    expect(wrapper.find('textarea').exists()).toBe(false)
    // The form area is a prompt, not an input: nothing to type into.
    expect(mockCommentStore).not.toHaveBeenCalled()
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

  it('deduplicates replies when loading more replies (Show more replies)', async () => {
    mockCommentReplies.mockResolvedValueOnce({
      data: { comments: [{ id: 'r1', content: 'First reply' }, { id: 'r2', content: 'Second reply' }] },
      meta: { current_page: 1, last_page: 2 }
    })
    mockCommentReplies.mockResolvedValueOnce({
      data: { comments: [{ id: 'r2', content: 'Second reply' }, { id: 'r3', content: 'Third reply' }] },
      meta: { current_page: 2, last_page: 2 }
    })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment, replies_count: '3' }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    // Click "View 3 replies"
    const viewBtn = wrapper.findAll('button').find(b => b.text().includes('View 3 replies'))
    await viewBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('First reply')
    expect(wrapper.text()).toContain('Second reply')

    // Find and click "View more replies"
    const showMoreBtn = wrapper.findAll('button').find(b => b.text().includes('View more replies'))
    expect(showMoreBtn).toBeDefined()
    await showMoreBtn!.trigger('click')
    await flushPromises()

    expect(mockCommentReplies).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Third reply')

    // Ensure 'Second reply' is rendered only once
    const matches = wrapper.findAll('p').filter(p => p.text() === 'Second reply')
    expect(matches.length).toBe(1)
  })

  it('does not lose newly submitted reply when concurrent with toggleReplies fetch', async () => {
    let resolveFirstFetch: any
    const firstFetchPromise = new Promise(resolve => {
      resolveFirstFetch = resolve
    })

    // First fetch triggered by toggleReplies hangs until we manually resolve
    mockCommentReplies.mockImplementationOnce(() => firstFetchPromise)
    mockCommentStore.mockResolvedValueOnce({
      data: { comment: { id: 'new-reply', content: 'Brand new reply' } }
    })

    const wrapper = mount(CommentItem, {
      props: { comment: { ...mockComment, replies_count: '1' }, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    // 1. User clicks View replies (fetch is in-flight)
    const viewBtn = wrapper.findAll('button').find(b => b.text().includes('View 1 reply'))
    await viewBtn!.trigger('click')

    // 2. User quickly opens reply form and submits a reply
    const replyBtn = wrapper.findAll('button').find(b => b.text().includes('Reply'))
    await replyBtn!.trigger('click')
    await wrapper.find('textarea').setValue('Brand new reply')
    await wrapper.vm.$nextTick()
    const submitBtn = replySubmitButton(wrapper)
    await submitBtn.trigger('click')

    // 3. First fetch completes later with the old reply
    resolveFirstFetch({
      data: { comments: [{ id: 'old-reply', content: 'Old reply from server' }] },
      meta: { current_page: 1, last_page: 1 }
    })
    await flushPromises()

    // Both the newly submitted reply and the old reply must be present!
    expect(wrapper.text()).toContain('Brand new reply')
    expect(wrapper.text()).toContain('Old reply from server')
  })
})

