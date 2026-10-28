import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import ReportForm from '@/components/common/ReportForm.vue'

vi.mock('@/utils/toast', () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}))

describe('ReportForm.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it('renders title and targetDescription', () => {
    const wrapper = mount(ReportForm, {
      props: {
        title: 'Report Content',
        targetDescription: 'Targeting item #42',
        submit: vi.fn(),
      },
    })

    expect(wrapper.text()).toContain('Report Content')
    expect(wrapper.text()).toContain('Targeting item #42')
    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('renders target-description via slot if provided', () => {
    const wrapper = mount(ReportForm, {
      props: {
        title: 'Custom Report',
        submit: vi.fn(),
      },
      slots: {
        'target-description': '<span class="custom-desc">Custom slotted description</span>',
      },
    })

    expect(wrapper.find('.custom-desc').text()).toBe('Custom slotted description')
  })

  it('disables submit button when character length is below minLength', async () => {
    const wrapper = mount(ReportForm, {
      props: {
        title: 'Report Content',
        submit: vi.fn(),
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Too short')

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Reason must be at least 10 characters')
  })

  it('submits form successfully and emits success event', async () => {
    const mockSubmit = vi.fn().mockResolvedValueOnce({
      message: 'Custom success message from server.',
    })

    const wrapper = mount(ReportForm, {
      props: {
        title: 'Report Content',
        submit: mockSubmit,
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Valid explanation of the violation.')

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeUndefined()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockSubmit).toHaveBeenCalledWith('Valid explanation of the violation.')
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('success')?.[0]).toEqual(['Custom success message from server.'])
  })

  it('handles 409 conflict error displaying alreadyReportedMessage', async () => {
    const mockSubmit = vi.fn().mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'Item already flagged.' },
      },
    })

    const wrapper = mount(ReportForm, {
      props: {
        title: 'Report Content',
        submit: mockSubmit,
        alreadyReportedMessage: 'Already reported fallback',
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('A valid reason for reporting this.')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Item already flagged.')
  })

  it('emits cancel event when cancel button is clicked', async () => {
    const wrapper = mount(ReportForm, {
      props: {
        title: 'Report Content',
        submit: vi.fn(),
      },
    })

    const cancelBtn = wrapper.findAll('button').find((b) => b.text().includes('Cancel'))
    await cancelBtn?.trigger('click')

    expect(wrapper.emitted('cancel')).toBeTruthy()
  })
})

