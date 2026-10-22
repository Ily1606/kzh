import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AppNavbar from '@/components/common/AppNavbar.vue'
import { useAuth } from '@/composables/useAuth'

vi.mock('@/composables/useAuth', () => ({
  useAuth: vi.fn(),
}))

const mockPush = vi.fn()
let mockCurrentRoute: { path: string; fullPath: string; meta?: Record<string, any> } = { path: '/', fullPath: '/' }

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    computed: {
      href(): string {
        const to = (this as any).to
        if (typeof to === 'string') return to
        if (to && typeof to === 'object') {
          const path = to.path || ''
          const query = to.query
          if (query && Object.keys(query).length > 0) {
            const params = new URLSearchParams(query).toString()
            return `${path}?${params}`
          }
          return path
        }
        return ''
      }
    },
    template: '<a :href="href"><slot /></a>',
  },
  useRouter: () => ({
    push: mockPush,
  }),
  useRoute: () => mockCurrentRoute,
}))

describe('AppNavbar.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCurrentRoute = { path: '/', fullPath: '/' }
    document.body.innerHTML = ''
  })

  it('renders desktop profile pill, mobile avatar link, and hamburger button on mobile', () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
      user: {
        name: 'Giap Dev',
        avatarLink: 'https://example.com/avatar.png',
      },
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar)

    // Desktop profile link exists
    const desktopProfileLink = wrapper.find('a[aria-label="Profile"]')
    expect(desktopProfileLink.exists()).toBe(true)
    expect(desktopProfileLink.text()).toContain('Hello, Giap Dev')

    // Mobile avatar link exists on the right side
    const mobileProfileLink = wrapper.find('a[aria-label="Mobile Profile"]')
    expect(mobileProfileLink.exists()).toBe(true)
    expect(mobileProfileLink.find('img').attributes('src')).toBe('https://example.com/avatar.png')

    // Mobile menu toggle button exists
    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    expect(menuBtn.exists()).toBe(true)
  })

  it('opens mobile drawer when clicking hamburger button and displays full navigation', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
      user: {
        name: 'Giap Dev',
        avatarLink: 'https://example.com/avatar.png',
      },
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar, {
      attachTo: document.body,
    })

    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    await menuBtn.trigger('click')

    // Teleported drawer content inside document.body
    const drawerPanel = document.body.querySelector('.fixed.inset-y-0.left-0')
    expect(drawerPanel).not.toBeNull()
    expect(drawerPanel?.classList.contains('border-r')).toBe(true)

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Plugins')
    expect(bodyText).toContain('Resources')
    expect(bodyText).toContain('Publish Plugin')
    expect(bodyText).toContain('My Profile')
    expect(bodyText).toContain('Log out')
    expect(bodyText).toContain('Giap Dev')

    wrapper.unmount()
  })

  it('handles logout from mobile drawer', async () => {
    const mockLogout = vi.fn().mockResolvedValue(undefined)
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
      user: { name: 'User' },
      logout: mockLogout,
    })

    const wrapper = mount(AppNavbar, {
      attachTo: document.body,
    })

    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    await menuBtn.trigger('click')

    const logoutBtn = Array.from(document.body.querySelectorAll('button')).find(
      (b) => b.textContent?.includes('Log out')
    )
    expect(logoutBtn).toBeDefined()
    logoutBtn?.click()
    await flushPromises()

    expect(mockLogout).toHaveBeenCalledTimes(1)
    expect(mockPush).toHaveBeenCalledWith('/login')

    wrapper.unmount()
  })

  it('closes mobile drawer on Escape key press', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
      user: { name: 'User' },
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar, {
      attachTo: document.body,
    })

    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    await menuBtn.trigger('click')

    expect(document.body.textContent).toContain('My Profile')

    // Press Escape
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button[aria-expanded="true"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('closes mobile drawer and restores body overflow when viewport expands to >= 768px', async () => {
    let mediaChangeHandler: ((e: any) => void) | null = null
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn((event, handler) => {
        if (event === 'change') {
          mediaChangeHandler = handler
        }
      }),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))

    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
      user: { name: 'User' },
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar, {
      attachTo: document.body,
    })

    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    await menuBtn.trigger('click')

    expect(document.body.style.overflow).toBe('hidden')
    expect(wrapper.find('button[aria-expanded="true"]').exists()).toBe(true)

    // Trigger media query change event to desktop
    expect(mediaChangeHandler).toBeDefined()
    mediaChangeHandler!({ matches: true })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button[aria-expanded="true"]').exists()).toBe(false)
    expect(document.body.style.overflow).toBe('')

    wrapper.unmount()
  })

  it('renders guest drawer and navbar login button when unauthenticated', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
      user: null,
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar, {
      attachTo: document.body,
    })

    // Mobile navbar shows "Log in" button
    const loginLink = wrapper.find('a[href="/login"]')
    expect(loginLink.exists()).toBe(true)
    expect(loginLink.text()).toContain('Log in')

    const menuBtn = wrapper.find('button[aria-label="Toggle navigation menu"]')
    await menuBtn.trigger('click')

    const bodyText = document.body.textContent || ''
    expect(bodyText).toContain('Plugins')
    expect(bodyText).toContain('Log in')
    expect(bodyText).toContain('Get started')
    expect(bodyText).not.toContain('Log out')

    wrapper.unmount()
  })

  it('appends redirect query parameter when unauthenticated user is on a non-home page like /plugins', () => {
    mockCurrentRoute = { path: '/plugins', fullPath: '/plugins' }
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
      user: null,
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar)

    const loginLinks = wrapper.findAll('a').filter((a) => a.text().includes('Log in'))
    expect(loginLinks.length).toBeGreaterThan(0)
    expect(loginLinks[0].attributes('href')).toBe('/login?redirect=%2Fplugins')

    const registerLinks = wrapper.findAll('a').filter((a) => a.text().includes('Get started'))
    expect(registerLinks.length).toBeGreaterThan(0)
    expect(registerLinks[0].attributes('href')).toBe('/register?redirect=%2Fplugins')
  })

  it('does not append redirect query parameter when unauthenticated user is on a guestOnly route', () => {
    mockCurrentRoute = { path: '/login', fullPath: '/login', meta: { guestOnly: true } }
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
      user: null,
      logout: vi.fn(),
    })

    const wrapper = mount(AppNavbar)

    const loginLinks = wrapper.findAll('a').filter((a) => a.text().includes('Log in'))
    expect(loginLinks.length).toBeGreaterThan(0)
    expect(loginLinks[0].attributes('href')).toBe('/login')

    const registerLinks = wrapper.findAll('a').filter((a) => a.text().includes('Get started'))
    expect(registerLinks.length).toBeGreaterThan(0)
    expect(registerLinks[0].attributes('href')).toBe('/register')
  })
})
