import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CommentReportModal from '@/components/pages/comment/CommentReportModal.vue'
import { ref } from 'vue'

const mockCommentReportStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getCommentReport: () => ({
    commentReportStore: mockCommentReportStore
  })
}))

const mockIsAuthenticated = ref(true)
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    get isAuthenticated() { return mockIsAuthenticated.value }
  })
}))

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a :href="JSON.stringify(to)"><slot /></a>',
  },
  useRoute: () => ({ fullPath: '/plugins/1#comments' }),
}))

describe('CommentReportModal.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsAuthenticated.value = true
    document.body.innerHTML = ''
  })

  it('renders sign-in prompt when user is not authenticated', () => {
    mockIsAuthenticated.value = false
    mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'BadActor',
        commentContent: 'Some spam comment'
      }
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Sign In Required')
    expect(bodyText).toContain('You need to be signed in to submit a comment report')
    expect(document.body.querySelector('textarea')).toBeNull()
  })

  it('renders report form with author name when authenticated', () => {
    mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice'
      }
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Report Comment')
    expect(bodyText).toContain('@Alice')
    expect(document.body.querySelector('textarea')).not.toBeNull()
  })

  it('disables submit button when reason is under 10 characters', async () => {
    mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice'
      }
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Too short'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()

    const submitBtn = Array.from(document.body.querySelectorAll('button')).find(
      b => b.textContent?.includes('Submit Report')
    ) as HTMLButtonElement
    expect(submitBtn).toBeDefined()
    expect(submitBtn.disabled).toBe(true)
    expect(document.body.textContent).toContain('Reason must be at least 10 characters')
  })

  it('submits report successfully via API and shows success state', async () => {
    mockCommentReportStore.mockResolvedValueOnce({
      message: 'Comment reported successfully.'
    })

    const wrapper = mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice'
      }
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'This comment contains offensive and inappropriate content.'
    textarea.dispatchEvent(new Event('input'))
    await wrapper.vm.$nextTick()

    const submitBtn = Array.from(document.body.querySelectorAll('button')).find(
      b => b.textContent?.includes('Submit Report')
    ) as HTMLButtonElement
    expect(submitBtn.disabled).toBe(false)
    submitBtn.click()
    await flushPromises()

    expect(mockCommentReportStore).toHaveBeenCalledWith('p1', 'c1', {
      reason: 'This comment contains offensive and inappropriate content.'
    })
    expect(document.body.textContent).toContain('Report Received')
  })

  it('displays 409 conflict error when user has already reported the comment', async () => {
    mockCommentReportStore.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'You have already reported this comment.' }
      }
    })

    const wrapper = mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice'
      }
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Repeated report attempt.'
    textarea.dispatchEvent(new Event('input'))
    await wrapper.vm.$nextTick()

    const submitBtn = Array.from(document.body.querySelectorAll('button')).find(
      b => b.textContent?.includes('Submit Report')
    ) as HTMLButtonElement
    submitBtn.click()
    await flushPromises()

    expect(document.body.textContent).toContain('You have already reported this comment.')
  })

  it('emits close event when Cancel or X button is clicked', async () => {
    const wrapper = mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice'
      }
    })

    const cancelBtn = Array.from(document.body.querySelectorAll('button')).find(
      b => b.textContent?.includes('Cancel')
    )
    expect(cancelBtn).toBeDefined()
    cancelBtn?.click()
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('clears close timer when modal is closed manually before 1400ms', async () => {
    vi.useFakeTimers()
    mockCommentReportStore.mockResolvedValueOnce({
      message: 'Comment reported successfully.',
    })

    const wrapper = mount(CommentReportModal, {
      props: {
        show: true,
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'Alice',
      },
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Spam content in comment section.'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()

    const form = document.body.querySelector('form') as HTMLFormElement
    form.dispatchEvent(new Event('submit'))
    await flushPromises()

    expect(document.body.textContent).toContain('Report Received')

    // Manually close the modal before 1400ms timer completes
    const closeBtn = document.body.querySelector('button[aria-label="Close"]') as HTMLButtonElement
    closeBtn.click()
    await flushPromises()
    expect(wrapper.emitted('close')?.length).toBe(1)

    // Reopen the modal
    await wrapper.setProps({ show: true })

    // Advance time past the original 1400ms
    vi.advanceTimersByTime(1500)
    await flushPromises()

    // The modal must not have emitted another close from the old timer
    expect(wrapper.emitted('close')?.length).toBe(1)

    vi.useRealTimers()
  })
})

