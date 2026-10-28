import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PluginReportForm from '@/components/pages/plugin-detail/PluginReportForm.vue'

const mockPluginReportStore = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPluginReport: () => ({
    pluginReportStore: mockPluginReportStore,
  }),
}))

vi.mock('@/utils/toast', () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}))

describe('PluginReportForm.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it('renders report form with plugin name', () => {
    const wrapper = mount(PluginReportForm, {
      props: {
        pluginId: 'p-123',
        pluginName: 'My Awesome Plugin',
      },
    })

    expect(wrapper.text()).toContain('Report Plugin')
    expect(wrapper.text()).toContain('My Awesome Plugin')
    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('disables submit button when character length is below 10', async () => {
    const wrapper = mount(PluginReportForm, {
      props: {
        pluginId: 'p-123',
        pluginName: 'My Awesome Plugin',
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Short')

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Reason must be at least 10 characters')
  })

  it('submits form successfully and emits success event', async () => {
    mockPluginReportStore.mockResolvedValueOnce({
      message: 'Plugin reported successfully. Our moderators will review it shortly.',
    })

    const wrapper = mount(PluginReportForm, {
      props: {
        pluginId: 'p-123',
        pluginName: 'My Awesome Plugin',
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('This plugin contains malicious unauthorized code.')

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Submit Report'))
    expect(submitBtn?.attributes('disabled')).toBeUndefined()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockPluginReportStore).toHaveBeenCalledWith('p-123', {
      reason: 'This plugin contains malicious unauthorized code.',
    })
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('success')?.[0]).toEqual([
      'Plugin reported successfully. Our moderators will review it shortly.',
    ])
  })

  it('handles 409 conflict error when already reported', async () => {
    mockPluginReportStore.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'You have already reported this plugin.' },
      },
    })

    const wrapper = mount(PluginReportForm, {
      props: {
        pluginId: 'p-123',
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Another valid report reason text.')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('You have already reported this plugin.')
  })

  it('emits cancel event when Cancel button clicked', async () => {
    const wrapper = mount(PluginReportForm, {
      props: {
        pluginId: 'p-123',
      },
    })

    const cancelBtn = wrapper.findAll('button').find((b) => b.text().includes('Cancel'))
    await cancelBtn?.trigger('click')

    expect(wrapper.emitted('cancel')).toBeTruthy()
  })
})

