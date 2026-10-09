import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import PluginHistoryView from '@/views/PluginHistoryView.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { PluginResource, UserResource } from '@/api/generated/model'

const mockPluginShow = vi.fn()
const mockPluginEventIndex = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({ pluginShow: mockPluginShow }),
  getStar: () => ({ starStore: vi.fn() }),
  getPluginEvent: () => ({ pluginEventIndex: mockPluginEventIndex }),
  getAuth: () => ({ authLogout: vi.fn() }),
  getProfile: () => ({ profileShow: vi.fn() })
}))

const mockRoute = ref({
  params: { id: 'plugin-123' } as Record<string, string>,
  fullPath: '/plugins/plugin-123/history',
  path: '/plugins/plugin-123/history'
})

const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: vi.fn(), replace: mockReplace }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="String(to.name)"><slot /></a>'
  }
}))

// The view scrolls back to the top after a page change; jsdom has no layout,
// so `scrollTo` is not implemented and would warn on every paging test.
Object.defineProperty(window, 'scrollTo', { value: vi.fn(), writable: true })

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-123',
    name: '@dsh/my-plugin',
    user_id: 'user-456',
    author: { id: 'user-456', name: 'Jane Dev', avatar_url: '' },
    title: 'My awesome plugin',
    license: 'MIT',
    approved_at: null,
    status: 'pending',
    source_link: 'https://github.com/owner/repo',
    star_count: 0,
    comment_count: 0,
    view_count: 0,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-06T00:00:00Z',
    ...overrides,
  }
}

/** The response shape the endpoint sends, envelope included. */
function timeline(events: unknown[], meta: Record<string, number> = {}) {
  return {
    data: { events },
    meta: { current_page: 1, last_page: 1, per_page: 20, total: events.length, ...meta },
  }
}

function event(overrides: Record<string, unknown> = {}) {
  return {
    id: 'event-1',
    event_type: 'created',
    message: null,
    created_at: '2026-10-01T00:00:00Z',
    ...overrides,
  }
}

function signIn(id = 'user-456') {
  const auth = useAuthStore()
  auth.setUser({ id, name: 'Jane', email: 'j@example.com' } as UserResource)
  auth.setToken('mock-token')
}

function seedStore(plugin: PluginResource = makePlugin()) {
  const store = usePluginsStore()
  store.put([plugin])
  return store
}

function mountView(): Promise<VueWrapper> {
  const wrapper = mount(PluginHistoryView)
  return flushPromises().then(() => wrapper)
}

beforeEach(() => {
  vi.clearAllMocks()

  const pinia = createPinia()
  pinia.use(piniaPluginPersistedstate)
  createApp({}).use(pinia)
  setActivePinia(pinia)
  localStorage.clear()

  mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin() } })
  mockPluginEventIndex.mockResolvedValue(timeline([]))
})

describe('PluginHistoryView.vue', () => {
  describe('ownership', () => {
    /*
     * The route guard only checks that someone is signed in. Ownership is the
     * check that decides, and it can only run once the plugin is in hand — so
     * a signed-in visitor who types another publisher's URL has to be turned
     * away here rather than left looking at an error state.
     */
    it('redirects a signed-in visitor who does not own the plugin', async () => {
      signIn('someone-else')
      seedStore(makePlugin({ user_id: 'user-456' }))

      await mountView()

      expect(mockReplace).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { id: 'plugin-123' },
      })
      expect(mockPluginEventIndex).not.toHaveBeenCalled()
    })

    it('redirects when the plugin cannot be loaded at all', async () => {
      signIn()
      mockPluginShow.mockRejectedValue({ response: { data: { message: 'Not found.' } } })

      await mountView()

      expect(mockReplace).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { id: 'plugin-123' },
      })
    })

    it('loads the timeline for the owner', async () => {
      signIn()
      seedStore()

      const wrapper = await mountView()

      expect(mockPluginEventIndex).toHaveBeenCalledWith('plugin-123', { page: 1, sort: 'newest' })
      expect(wrapper.text()).toContain('Review history')
      expect(mockReplace).not.toHaveBeenCalled()
    })
  })

  describe('rendering the timeline', () => {
    it('labels each event type', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([
          event({ id: 'e1', event_type: 'created', created_at: '2026-10-01T00:00:00Z' }),
          event({ id: 'e2', event_type: 'resubmitted', created_at: '2026-10-02T00:00:00Z' }),
          event({ id: 'e3', event_type: 'approved', created_at: '2026-10-03T00:00:00Z' }),
          event({ id: 'e4', event_type: 'rejected', created_at: '2026-10-04T00:00:00Z' }),
          event({ id: 'e5', event_type: 'update_requested', created_at: '2026-10-05T00:00:00Z' }),
        ]),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Submitted for review')
      expect(wrapper.text()).toContain('Changes resubmitted for review')
      expect(wrapper.text()).toContain('Approved by an admin')
      expect(wrapper.text()).toContain('Rejected by an admin')
      expect(wrapper.text()).toContain('Changes requested by an admin')
    })

    /*
     * The whole point of the feature. An admin's message is the only thing
     * that tells an owner what to change, so it has to be rendered as-is.
     */
    it('shows the message an admin wrote', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([
          event({
            event_type: 'update_requested',
            message: 'Please document the config option.',
            created_at: '2026-10-05T00:00:00Z',
          }),
        ]),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Please document the config option.')
    })

    /*
     * An approval is decided by its absence of a message. Rendering an empty
     * bubble next to it would read as a message the owner never received.
     */
    it('renders no message block for an event that carries none', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([event({ event_type: 'approved', message: null })]),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Approved by an admin')
      expect(wrapper.find('p.rounded-lg').exists()).toBe(false)
    })

    it('names the plugin the history belongs to', async () => {
      signIn()
      seedStore()

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('@dsh/my-plugin')
    })

    /*
     * A type the backend adds after this client shipped still renders. An
     * unrecognised row must read as "something happened", not as a hole in the
     * history that hides the entries around it.
     */
    it('falls back to the raw value for an unknown event type', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([event({ event_type: 'some_future_type' })]),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('some_future_type')
    })
  })

  describe('the heading', () => {
    /*
     * No counter of pending requests sits next to the title. One was tried and
     * removed: it could only be read off the loaded page, so it counted
     * historical `update_requested` rows rather than outstanding ones — an
     * owner who had already resubmitted and been approved still saw it
     * claiming someone was waiting on them.
     */
    it('carries no pending-request counter', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([
          event({ id: 'e1', event_type: 'created' }),
          event({ id: 'e2', event_type: 'update_requested', message: 'Fix the typo.' }),
          event({ id: 'e3', event_type: 'resubmitted' }),
          event({ id: 'e4', event_type: 'approved' }),
        ]),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Review history')
      expect(wrapper.text()).not.toContain('waiting on you')
    })
  })

  describe('empty and error states', () => {
    it('explains an empty timeline', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(timeline([]))

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('No activity yet')
    })

    it('shows the server message on failure', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockRejectedValue({
        response: { data: { message: 'Resource not found.' } },
      })

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Resource not found.')
    })

    it('falls back to its own message when the server sends none', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockRejectedValue({})

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Failed to load the review history.')
    })
  })

  describe('sorting and paging', () => {
    it('requests the reversed order on the second press', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([event()], { current_page: 1, last_page: 1, total: 1 }),
      )

      const wrapper = await mountView()

      await wrapper.find('button').trigger('click')
      await flushPromises()

      expect(mockPluginEventIndex).toHaveBeenLastCalledWith('plugin-123', {
        page: 1,
        sort: 'oldest',
      })
      expect(wrapper.text()).toContain('Oldest first')
    })

    it('hides the pager when everything fits on one page', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([event()], { current_page: 1, last_page: 1, total: 1 }),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).not.toContain('Page 1 of')
    })

    it('pages forward and back', async () => {
      signIn()
      seedStore()
      mockPluginEventIndex.mockResolvedValue(
        timeline([event()], { current_page: 2, last_page: 3, total: 50 }),
      )

      const wrapper = await mountView()

      expect(wrapper.text()).toContain('Page 2 of 3')

      const [previous, next] = wrapper.findAll('button').slice(-2)
      await previous!.trigger('click')
      await flushPromises()

      expect(mockPluginEventIndex).toHaveBeenLastCalledWith('plugin-123', {
        page: 1,
        sort: 'newest',
      })
    })
  })
})
