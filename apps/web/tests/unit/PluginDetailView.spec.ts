import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import PluginDetailView from '@/views/PluginDetailView.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { PluginResource, UserResource } from '@/api/generated/model'

const mockPluginShow = vi.fn()
const mockStarStore = vi.fn()
const mockNotifyError = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginShow: mockPluginShow
  }),
  getStar: () => ({ starStore: mockStarStore }),
  getAuth: () => ({ authLogout: vi.fn() }),
  getProfile: () => ({ profileShow: vi.fn() })
}))

vi.mock('@/utils/toast', () => ({
  notifyError: (message: string) => mockNotifyError(message),
  notifySuccess: vi.fn()
}))

const mockRoute = ref({
  params: { id: 'plugin-123' } as Record<string, string>,
  hash: '',
  fullPath: '/plugins/plugin-123',
  query: {} as Record<string, string | string[]>
})

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: vi.fn() }),
  // Renders a real href from `to` so the sign-in target is assertable. Named
  // routes with params keep the name visible: resolving `plugin-detail` + `{ id }`
  // to a path needs the router's route table, which is not what these tests are
  // about.
  RouterLink: {
    props: ['to'],
    methods: {
      href(to: string | { name?: string; query?: Record<string, unknown>; params?: Record<string, unknown> }): string {
        if (typeof to === 'string') return to

        const params = Object.entries(to.params ?? {})
          .map(([key, value]) => `${key}=${value}`)
          .join('&')

        return `/${to.name}?redirect=${to.query?.redirect ?? ''}${params ? `&${params}` : ''}`
      },
    },
    template: '<a :href="href(to)"><slot /></a>'
  }
}))

const mockFetch = vi.fn()
global.fetch = mockFetch

window.HTMLElement.prototype.scrollIntoView = vi.fn()

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-123',
    name: '@dsh/my-plugin',
    user_id: 'user-456',
    author: { id: 'user-456', name: 'Jane Dev', avatar_url: null },
    title: 'My awesome plugin',
    license: 'MIT',
    approved_at: '2026-10-01T00:00:00Z',
    status: 'approved',
    source_link: 'https://github.com/owner/repo',
    star_count: 5,
    comment_count: 2,
    view_count: 100,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-06T00:00:00Z',
    ...overrides,
  }
}

function signIn() {
  const auth = useAuthStore()
  auth.setUser({ id: 'user-456', name: 'Jane', email: 'j@example.com' } as UserResource)
  auth.setToken('mock-token')
}

/** Seeds the store the way an earlier page would have, so no fetch is needed. */
function seedStore(plugin: PluginResource = makePlugin()) {
  const store = usePluginsStore()
  store.put([plugin])
  return store
}

function mountView() {
  // Attached to the document: the popover content is portalled to `body`, so a
  // detached mount cannot see it.
  return mount(PluginDetailView, {
    global: { stubs: { PluginComments: true } },
    attachTo: document.body
  })
}

function starButton(wrapper: ReturnType<typeof mountView>) {
  return wrapper.findAll('button').find((b) => /Star/.test(b.text()))
}

describe('PluginDetailView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockRoute.value.hash = ''
    mockRoute.value.query = {}

    mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin() } })
    mockStarStore.mockResolvedValue({ data: { starred: true, star_count: 6 } })
    mockNotifyError.mockImplementation(() => {})
    mockFetch.mockResolvedValue({
      ok: true,
      text: () => Promise.resolve('# Hello Markdown\nThis is README')
    })

    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) }
    })
  })

  describe('reading the plugin', () => {
    it('renders from the store without calling the detail API', async () => {
      seedStore()

      const wrapper = mountView()
      await flushPromises()
      await wrapper.vm.$nextTick()

      expect(mockPluginShow).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('@dsh/my-plugin')
      expect(wrapper.text()).toContain('My awesome plugin')
      expect(wrapper.text()).toContain('MIT license')
      expect(wrapper.text()).toContain('100 views')
    })

    it('renders the README fetched from the repository', async () => {
      seedStore()

      const wrapper = mountView()
      await flushPromises()
      await wrapper.vm.$nextTick()

      expect(mockFetch).toHaveBeenCalledWith(expect.stringContaining('raw.githubusercontent.com/owner/repo'))
      expect(wrapper.html()).toContain('Hello Markdown')
    })

    it('fetches once when the store does not have the plugin', async () => {
      mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin({ name: '@dsh/from-api' }) } })

      const wrapper = mountView()
      await flushPromises()
      await wrapper.vm.$nextTick()

      // The hard-refresh / shared-link case.
      expect(mockPluginShow).toHaveBeenCalledWith('plugin-123')
      expect(wrapper.text()).toContain('@dsh/from-api')
    })

    it('caches the fetched plugin so a second mount does not refetch', async () => {
      mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin() } })

      mountView()
      await flushPromises()

      mountView()
      await flushPromises()

      expect(mockPluginShow).toHaveBeenCalledTimes(1)
    })

    it('shows the error state when the plugin cannot be loaded', async () => {
      mockPluginShow.mockRejectedValue({ response: { data: { message: 'Failed to load plugin details.' } } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Failed to load plugin details.')
    })

    it('shows the skeleton while the first load is in flight', async () => {
      mockPluginShow.mockReturnValue(new Promise(() => {}))

      const wrapper = mountView()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    })
  })

  describe('README handling', () => {
    it('handles gracefully when the README fetch fails', async () => {
      seedStore()
      mockFetch.mockResolvedValue({ ok: false })

      const wrapper = mountView()
      await flushPromises()
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('No README available for this plugin.')
    })

    it('handles gracefully when source_link is missing', async () => {
      seedStore(makePlugin({ source_link: '' }))

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('No README available for this plugin.')
      expect(mockFetch).not.toHaveBeenCalled()
    })
  })

  describe('copy install command', () => {
    it('copies the install command to the clipboard', async () => {
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      await wrapper.find('button[title="Copy command"]').trigger('click')

      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('npx dsh add @dsh/my-plugin')
    })
  })

  describe('star button state', () => {
    it('shows "Star" and the stored count when not starred', async () => {
      seedStore(makePlugin({ is_star: false, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('5 stars')
      expect(starButton(wrapper)?.text()).toContain('Star')
      expect(starButton(wrapper)?.text()).not.toContain('Starred')
    })

    it('shows "Starred" when is_star is true in the store', async () => {
      signIn()
      seedStore(makePlugin({ is_star: true, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      expect(starButton(wrapper)?.text()).toContain('Starred')
      expect(starButton(wrapper)?.attributes('aria-pressed')).toBe('true')
    })

    it('shows "Star" when the response carried no is_star', async () => {
      seedStore(makePlugin({ is_star: undefined, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      // Guest responses omit the field entirely; an unstarred icon is the right
      // reading, and clicking still lands the correct state.
      expect(starButton(wrapper)?.text()).toContain('Star')
      expect(starButton(wrapper)?.text()).not.toContain('Starred')
    })
  })

  describe('starring as a signed-in user', () => {
    it('calls the API with starred true and updates the count', async () => {
      signIn()
      seedStore(makePlugin({ is_star: false, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockStarStore).toHaveBeenCalledWith('plugin-123', { starred: true })
      expect(wrapper.text()).toContain('6 stars')
      expect(starButton(wrapper)?.text()).toContain('Starred')
    })

    it('calls the API with starred false to unstar', async () => {
      signIn()
      seedStore(makePlugin({ is_star: true, star_count: 5 }))
      mockStarStore.mockResolvedValue({ data: { starred: false, star_count: 4 } })

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockStarStore).toHaveBeenCalledWith('plugin-123', { starred: false })
      expect(wrapper.text()).toContain('4 stars')
      expect(starButton(wrapper)?.text()).toContain('Star')
    })

    it('lets the server count win over the optimistic one', async () => {
      signIn()
      seedStore(makePlugin({ is_star: false, star_count: 5 }))
      mockStarStore.mockResolvedValue({ data: { starred: true, star_count: 17 } })

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('17 stars')
    })

    it('writes the change through to the store', async () => {
      signIn()
      const store = seedStore(makePlugin({ is_star: false, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await flushPromises()

      // One write, seen by every list holding this plugin.
      expect(store.findById('plugin-123')).toMatchObject({ is_star: true, star_count: 6 })
    })

    it('rolls back and notifies when the API fails', async () => {
      signIn()
      seedStore(makePlugin({ is_star: false, star_count: 5 }))
      mockStarStore.mockRejectedValue({ response: { data: { message: 'Too many requests.' } } })

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('5 stars')
      expect(starButton(wrapper)?.text()).not.toContain('Starred')
      expect(mockNotifyError).toHaveBeenCalledWith('Too many requests.')
    })

    it('disables the button while the request is in flight', async () => {
      signIn()
      seedStore(makePlugin({ is_star: false, star_count: 5 }))

      let release!: () => void
      mockStarStore.mockImplementation(() => new Promise((resolve) => {
        release = () => resolve({ data: { starred: true, star_count: 6 } })
      }))

      const wrapper = mountView()
      await flushPromises()

      await starButton(wrapper)!.trigger('click')
      await wrapper.vm.$nextTick()

      expect(starButton(wrapper)?.attributes('disabled')).toBeDefined()
      expect(wrapper.find('.animate-spin').exists()).toBe(true)

      release()
      await flushPromises()
    })
  })

  describe('starring as a guest', () => {
    it('disables the button and never calls the API', async () => {
      seedStore(makePlugin({ is_star: false, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      const button = starButton(wrapper)
      expect(button?.attributes('disabled')).toBeDefined()

      await button!.trigger('click')
      await flushPromises()

      expect(mockStarStore).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('5 stars')
    })

    it('offers a sign-in prompt in a popover', async () => {
      seedStore(makePlugin({ is_star: false, star_count: 5 }))

      const wrapper = mountView()
      await flushPromises()

      // The disabled button cannot be the trigger, so the wrapper span is.
      const trigger = wrapper.find('[data-slot="popover-trigger"]')
      expect(trigger.exists()).toBe(true)
      expect(trigger.attributes('aria-expanded')).toBe('false')

      await trigger.trigger('click')
      await flushPromises()
      await new Promise((resolve) => setTimeout(resolve, 30))

      expect(trigger.attributes('aria-expanded')).toBe('true')

      // The content is portalled to the document body, not into the wrapper.
      const content = document.querySelector('[data-slot="popover-content"]')
      expect(content).not.toBeNull()
      expect(content?.textContent).toContain('Sign in to star this plugin')
      // The link comes back to this page after signing in.
      expect(content?.querySelector('a')?.getAttribute('href'))
        .toBe('/login?redirect=/plugins/plugin-123')

      wrapper.unmount()
    })
  })

  describe('comments', () => {
    it('increments the comment count when comment-added fires', async () => {
      const store = seedStore(makePlugin({ comment_count: 2 }))

      const wrapper = mountView()
      await flushPromises()

      const pluginComments = wrapper.findComponent({ name: 'PluginComments' })
      expect(pluginComments.props('totalCommentCount')).toBe(2)

      await pluginComments.vm.$emit('comment-added')

      expect(pluginComments.props('totalCommentCount')).toBe(3)
      expect(store.findById('plugin-123')?.comment_count).toBe(3)
    })

    // Every status below is reached by clicking a row on Resources, where the
    // plugin is already in the store, so these mount without a fetch. The API
    // refusing comments on a plugin that is not approved is what the UI mirrors.
    describe('a published plugin', () => {
      it('shows the comments section', async () => {
        seedStore(makePlugin({ status: 'approved' }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.findComponent({ name: 'PluginComments' }).exists()).toBe(true)
      })

      it('shows no status badge, because published is the unmarked case', async () => {
        seedStore(makePlugin({ status: 'approved' }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.text()).not.toContain('Pending')
        expect(wrapper.text()).not.toContain('Rejected')
      })

      it('offers the star button and the install command', async () => {
        seedStore(makePlugin({ status: 'approved' }))

        const wrapper = mountView()
        await flushPromises()

        expect(starButton(wrapper)).toBeDefined()
        expect(wrapper.text()).toContain('npx dsh add')
      })
    })

    describe('a pending plugin seen by its owner', () => {
      it('carries a Pending badge and a notice', async () => {
        signIn()
        seedStore(makePlugin({ status: 'pending', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.text()).toContain('Pending')
        expect(wrapper.text()).toContain('Waiting for review')
      })

      it('hides the comments section', async () => {
        signIn()
        seedStore(makePlugin({ status: 'pending', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.findComponent({ name: 'PluginComments' }).exists()).toBe(false)
      })

      it('hides the star button and the install command, and keeps the source link', async () => {
        signIn()
        seedStore(makePlugin({ status: 'pending', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        expect(starButton(wrapper)).toBeUndefined()
        expect(wrapper.text()).not.toContain('npx dsh add')
        // Readable and correctable by the author, so the repository is still
        // one click away.
        expect(wrapper.find('a[href="https://github.com/owner/repo"]').exists()).toBe(true)
      })

      it('offers the edit button, pointed at the edit page for this plugin', async () => {
        signIn()
        seedStore(makePlugin({ status: 'pending', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        // The stubbed RouterLink below cannot resolve a named route, so it puts
        // the route name and the params in the href for the assertion to read.
        const edit = wrapper.findAll('a').find((a) => /Edit plugin/.test(a.text()))
        expect(edit?.attributes('href')).toContain('plugin-edit')
        expect(edit?.attributes('href')).toContain('id=plugin-123')
      })
    })

    describe('a rejected plugin seen by its owner', () => {
      it('carries a Rejected badge and a notice', async () => {
        signIn()
        seedStore(makePlugin({ status: 'rejected', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.text()).toContain('Rejected')
      })

      it('hides the comments section', async () => {
        signIn()
        seedStore(makePlugin({ status: 'rejected', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        expect(wrapper.findComponent({ name: 'PluginComments' }).exists()).toBe(false)
      })

      it('offers no edit control at all', async () => {
        signIn()
        seedStore(makePlugin({ status: 'rejected', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        // Not a greyed-out button. A disabled control still reads as "the
        // feature is here and you cannot use it", and the notice above already
        // says the submission is closed.
        expect(wrapper.findAll('a').find((a) => /Edit plugin/.test(a.text()))).toBeUndefined()
        expect(wrapper.findAll('button').find((b) => /Edit plugin/.test(b.text()))).toBeUndefined()
      })
    })

    describe('a plugin seen by someone else', () => {
      it('offers no edit link to a stranger', async () => {
        // `signIn()` in this file is the author; a different id is a stranger.
        const auth = useAuthStore()
        auth.setUser({ id: 'user-other', name: 'Someone', email: 'o@example.com' } as UserResource)
        auth.setToken('mock-token')

        seedStore(makePlugin({ status: 'pending', approved_at: null }))

        const wrapper = mountView()
        await flushPromises()

        // A pending plugin is only reachable by its author, so this state is
        // not something the API can serve. The UI must not offer editing anyway.
        expect(wrapper.findAll('a').find((a) => /Edit plugin/.test(a.text()))).toBeUndefined()
      })

      it('hides editing on a published plugin, even for its author', async () => {
        signIn()
        seedStore(makePlugin({ status: 'approved' }))

        const wrapper = mountView()
        await flushPromises()

        // An edit to a live plugin is a re-review, not a correction, and that
        // review flow is a separate piece of work.
        expect(wrapper.findAll('button').find((b) => /Edit plugin/.test(b.text()))).toBeUndefined()
        expect(wrapper.findAll('a').find((a) => /Edit plugin/.test(a.text()))).toBeUndefined()
      })
    })
  })
})
