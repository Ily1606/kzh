import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CommentReportForm from '@/components/pages/comment/CommentReportForm.vue'

const mockCommentReportStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getCommentReport: () => ({
    commentReportStore: mockCommentReportStore
  })
}))

describe('CommentReportForm.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it('renders report form with author name', () => {
    const wrapper = mount(CommentReportForm, {
      props: {
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'JohnDoe'
      }
    })

    expect(wrapper.text()).toContain('Report Comment')
    expect(wrapper.text()).toContain('@JohnDoe')
    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('disables submit button when character length is below 10', async () => {
    const wrapper = mount(CommentReportForm, {
      props: {
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'JohnDoe'
      }
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Short')

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Reason must be at least 10 characters')
  })

  it('submits form successfully and emits success event', async () => {
    mockCommentReportStore.mockResolvedValueOnce({
      message: 'Comment reported successfully.'
    })

    const wrapper = mount(CommentReportForm, {
      props: {
        pluginId: 'p1',
        commentId: 'c1',
        authorName: 'JohnDoe'
      }
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('This is a valid report reason for spam.')

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeUndefined()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCommentReportStore).toHaveBeenCalledWith('p1', 'c1', {
      reason: 'This is a valid report reason for spam.'
    })
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('success')?.[0]).toEqual(['Comment reported successfully.'])
  })

  it('handles 409 conflict error when already reported', async () => {
    mockCommentReportStore.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'You have already reported this comment.' }
      }
    })

    const wrapper = mount(CommentReportForm, {
      props: {
        pluginId: 'p1',
        commentId: 'c1'
      }
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Another valid report reason.')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('You have already reported this comment.')
    expect(wrapper.emitted('success')).toBeFalsy()
  })

  it('emits cancel event when Cancel button is clicked', async () => {
    const wrapper = mount(CommentReportForm, {
      props: {
        pluginId: 'p1',
        commentId: 'c1'
      }
    })

    const cancelBtn = wrapper.findAll('button').find(b => b.text().includes('Cancel'))
    await cancelBtn?.trigger('click')

    expect(wrapper.emitted('cancel')).toBeTruthy()
  })
})

