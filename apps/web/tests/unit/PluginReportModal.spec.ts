import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PluginReportModal from '@/components/pages/plugin-detail/PluginReportModal.vue'
import { ref } from 'vue'

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

const mockIsAuthenticated = ref(true)
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    get isAuthenticated() {
      return mockIsAuthenticated.value
    },
  }),
}))

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a :href="JSON.stringify(to)"><slot /></a>',
  },
  useRoute: () => ({ fullPath: '/plugins/plugin-1' }),
}))

describe('PluginReportModal.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsAuthenticated.value = true
    document.body.innerHTML = ''
  })

  it('renders sign-in prompt when user is not authenticated', () => {
    mockIsAuthenticated.value = false
    mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
        pluginName: 'Test Plugin',
      },
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Sign In Required')
    expect(bodyText).toContain('You need to be signed in to submit a report for this plugin')
    expect(document.body.querySelector('textarea')).toBeNull()
  })

  it('renders report form with plugin name when authenticated', () => {
    mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
        pluginName: 'Test Plugin',
      },
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Report Plugin')
    expect(bodyText).toContain('Test Plugin')
    expect(document.body.querySelector('textarea')).not.toBeNull()
  })

  it('disables submit button when reason is under 10 characters', async () => {
    mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
        pluginName: 'Test Plugin',
      },
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Too short'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()

    const submitBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      b.textContent?.includes('Submit Report')
    ) as HTMLButtonElement
    expect(submitBtn).toBeDefined()
    expect(submitBtn.disabled).toBe(true)
    expect(document.body.textContent).toContain('Reason must be at least 10 characters')
  })

  it('submits report successfully and shows success state', async () => {
    mockPluginReportStore.mockResolvedValueOnce({
      message: 'Plugin reported successfully.',
    })

    mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
        pluginName: 'Test Plugin',
      },
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Malicious code detected in plugin repository.'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()

    const form = document.body.querySelector('form') as HTMLFormElement
    form.dispatchEvent(new Event('submit'))
    await flushPromises()

    expect(mockPluginReportStore).toHaveBeenCalledWith('p-1', {
      reason: 'Malicious code detected in plugin repository.',
    })

    expect(document.body.textContent).toContain('Report Received')
    expect(document.body.textContent).toContain('Thank you for helping keep our community safe')
  })

  it('emits close event when close button is clicked', async () => {
    const wrapper = mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
      },
    })

    const closeBtn = document.body.querySelector('button[aria-label="Close"]') as HTMLButtonElement
    closeBtn.click()
    await flushPromises()

    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('clears close timer when modal is closed manually before 1400ms', async () => {
    vi.useFakeTimers()
    mockPluginReportStore.mockResolvedValueOnce({
      message: 'Plugin reported successfully.',
    })

    const wrapper = mount(PluginReportModal, {
      props: {
        show: true,
        pluginId: 'p-1',
        pluginName: 'Test Plugin',
      },
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Malicious code detected in plugin repository.'
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

