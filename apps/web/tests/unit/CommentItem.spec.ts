import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CommentItem from '@/components/pages/comment/CommentItem.vue'
import { ref } from 'vue'

const mockCommentReplies = vi.fn()
const mockCommentStore = vi.fn()
const mockCommentReportStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getComment: () => ({
    commentReplies: mockCommentReplies,
    commentStore: mockCommentStore
  }),
  getCommentReport: () => ({
    commentReportStore: mockCommentReportStore
  })
}))

const mockIsAuthenticated = ref(true)
const mockCurrentUser = ref<any>(null)
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    get isAuthenticated() { return mockIsAuthenticated.value },
    get user() { return mockCurrentUser.value }
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
    mockCurrentUser.value = null
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
    mockCommentReportStore.mockResolvedValueOnce({
      message: 'Comment reported successfully. Our moderators will review it shortly.'
    })

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
    await textarea.setValue('This is an inappropriate comment')

    // Submit report
    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    await submitBtn!.trigger('click')

    await flushPromises()

    expect(mockCommentReportStore).toHaveBeenCalledWith('1', 'c1', {
      reason: 'This is an inappropriate comment'
    })
    expect(wrapper.text()).toContain('Report Submitted')
    expect(wrapper.text()).toContain('Comment reported successfully. Our moderators will review it shortly.')

    const doneBtn = wrapper.findAll('button').find(b => b.text().includes('Done'))
    await doneBtn!.trigger('click')
    expect(wrapper.find('textarea[placeholder="Reason for reporting..."]').exists()).toBe(false)
  })

  it('disables submit report button when reason has fewer than 10 characters', async () => {
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    const flagBtn = wrapper.find('button[title="Report this comment"]')
    await flagBtn.trigger('click')

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeDefined()

    const textarea = wrapper.find('textarea[placeholder="Reason for reporting..."]')
    await textarea.setValue('spam')
    expect(submitBtn?.attributes('disabled')).toBeDefined()

    await textarea.setValue('This is at least 10 characters')
    expect(submitBtn?.attributes('disabled')).toBeUndefined()
  })

  it('shows error alert when report submission fails', async () => {
    mockCommentReportStore.mockRejectedValueOnce({
      response: { data: { message: 'You have already reported this comment.' } }
    })

    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    const flagBtn = wrapper.find('button[title="Report this comment"]')
    await flagBtn.trigger('click')

    const textarea = wrapper.find('textarea[placeholder="Reason for reporting..."]')
    await textarea.setValue('This is an inappropriate comment')

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    await submitBtn!.trigger('click')

    await flushPromises()

    expect(mockCommentReportStore).toHaveBeenCalledWith('1', 'c1', {
      reason: 'This is an inappropriate comment'
    })
    expect(wrapper.text()).toContain('You have already reported this comment.')
    // Modal stays open on error
    expect(wrapper.find('textarea[placeholder="Reason for reporting..."]').exists()).toBe(true)
  })

  it('shows sign in link when unauthenticated user opens report modal', async () => {
    mockIsAuthenticated.value = false
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    const flagBtn = wrapper.find('button[title="Report this comment"]')
    await flagBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Please sign in to report this comment')
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(mockCommentReportStore).not.toHaveBeenCalled()
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

  it('hides the report button when the user is the author of the comment', () => {
    mockCurrentUser.value = { id: 'u1', name: 'Alice' }
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    expect(wrapper.find('button[title="Report this comment"]').exists()).toBe(false)
  })

  it('shows the report button when the user is not the author of the comment', () => {
    mockCurrentUser.value = { id: 'u2', name: 'Bob' }
    const wrapper = mount(CommentItem, {
      props: { comment: mockComment, pluginId: '1', depth: 1 },
      global: { stubs: ['router-link'] }
    })

    expect(wrapper.find('button[title="Report this comment"]').exists()).toBe(true)
  })
})
