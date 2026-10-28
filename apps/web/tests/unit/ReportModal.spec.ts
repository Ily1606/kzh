import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import ReportModal from '@/components/common/ReportModal.vue'
import { ref } from 'vue'

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
  useRoute: () => ({ fullPath: '/current/path' }),
}))

describe('ReportModal.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsAuthenticated.value = true
    document.body.innerHTML = ''
  })

  it('renders sign-in prompt when user is not authenticated', () => {
    mockIsAuthenticated.value = false
    mount(ReportModal, {
      props: {
        show: true,
        unauthTitle: 'Sign In Required',
        unauthMessage: 'Must log in to report.',
      },
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Sign In Required')
    expect(bodyText).toContain('Must log in to report.')
    expect(document.body.querySelector('textarea')).toBeNull()
  })

  it('renders direct ReportForm when title and submit props are provided', () => {
    mount(ReportModal, {
      props: {
        show: true,
        title: 'Direct Report Title',
        targetDescription: 'Targeting item',
        submit: vi.fn(),
      },
    })

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Direct Report Title')
    expect(bodyText).toContain('Targeting item')
    expect(document.body.querySelector('textarea')).not.toBeNull()
  })

  it('renders custom form via slot when provided', () => {
    mount(ReportModal, {
      props: {
        show: true,
      },
      slots: {
        default: '<div class="custom-form-slot">Custom Form Content</div>',
      },
    })

    expect(document.body.querySelector('.custom-form-slot')?.textContent).toBe('Custom Form Content')
  })

  it('emits close event when close button is clicked', async () => {
    const wrapper = mount(ReportModal, {
      props: {
        show: true,
      },
    })

    const closeBtn = document.body.querySelector('button[aria-label="Close"]') as HTMLButtonElement
    closeBtn.click()
    await flushPromises()

    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('clears close timer when modal is closed manually before 1400ms', async () => {
    vi.useFakeTimers()
    const mockSubmit = vi.fn().mockResolvedValueOnce({ message: 'Success' })

    const wrapper = mount(ReportModal, {
      props: {
        show: true,
        title: 'Timer Test',
        submit: mockSubmit,
      },
    })

    const textarea = document.body.querySelector('textarea') as HTMLTextAreaElement
    textarea.value = 'Valid reason for report.'
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

    // Reopen modal within 1.4s
    await wrapper.setProps({ show: true })

    // Advance time past 1400ms
    vi.advanceTimersByTime(1500)
    await flushPromises()

    // The modal must not have emitted another close from the old timer
    expect(wrapper.emitted('close')?.length).toBe(1)

    vi.useRealTimers()
  })
})

