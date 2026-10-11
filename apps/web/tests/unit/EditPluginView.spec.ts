import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import EditPluginView from '@/views/EditPluginView.vue'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import { useAuthStore, usePluginsStore } from '@/stores'
import type { PluginResource, UserResource } from '@/api/generated/model'

const mockPluginShow = vi.fn()
const mockPluginUpdate = vi.fn()
const mockNotifyError = vi.fn()
const mockNotifySuccess = vi.fn()

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginShow: mockPluginShow,
    pluginUpdate: mockPluginUpdate,
  }),
  getStar: () => ({ starStore: vi.fn() }),
  getAuth: () => ({ authLogout: vi.fn() }),
  getProfile: () => ({ profileShow: vi.fn() }),
}))

vi.mock('@/utils/toast', () => ({
  notifyError: (message: string) => mockNotifyError(message),
  notifySuccess: (message: string) => mockNotifySuccess(message),
}))

const mockRoute = ref({
  params: { id: 'plugin-123' } as Record<string, string>,
  fullPath: '/resources/plugin-123/edit',
})

const mockPush = vi.fn()
const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
}))

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-123',
    name: '@dsh/my-plugin',
    user_id: 'user-456',
    author: { id: 'user-456', name: 'Jane Dev', avatar_url: null },
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
  } as PluginResource
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

function mountView() {
  return mount(EditPluginView, {
    global: { stubs: { RouterLink: true } },
  })
}

describe('EditPluginView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    pinia.use(piniaPluginPersistedstate)
    createApp({}).use(pinia)
    setActivePinia(pinia)
    localStorage.clear()

    mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin() } })
    mockPluginUpdate.mockResolvedValue({ data: { plugin: makePlugin({ title: 'Renamed' }) } })
  })

  describe('loading the plugin', () => {
    it('prefills the form from the stored plugin without a fetch', async () => {
      signIn()
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      expect(mockPluginShow).not.toHaveBeenCalled()

      const fields = wrapper.findComponent(PluginFieldsForm)
      expect(fields.props('initialValues')).toEqual({
        name: '@dsh/my-plugin',
        title: 'My awesome plugin',
        license: 'MIT',
        source_link: 'https://github.com/owner/repo',
      })
    })

    it('fetches once when the store does not have the plugin', async () => {
      signIn()

      mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin({ title: 'From API' }) } })

      const wrapper = mountView()
      await flushPromises()

      // The hard-refresh / shared-link case.
      expect(mockPluginShow).toHaveBeenCalledWith('plugin-123')
      expect(wrapper.findComponent(PluginFieldsForm).props('initialValues')).toMatchObject({
        title: 'From API',
      })
    })

    it('shows the skeleton while the plugin is in flight', async () => {
      signIn()
      mockPluginShow.mockReturnValue(new Promise(() => {}))

      const wrapper = mountView()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    })

    it('shows the error state when the plugin cannot be loaded', async () => {
      signIn()
      mockPluginShow.mockRejectedValue({ response: { data: { message: 'Failed to load plugin details.' } } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Failed to load plugin details.')
    })
  })

  describe('who may edit', () => {
    it('redirects a plugin the caller does not own', async () => {
      signIn('someone-else')

      const wrapper = mountView()
      await flushPromises()

      // The API would refuse the write anyway; leaving the page open would only
      // reveal that at submit time.
      expect(mockReplace).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { id: 'plugin-123' },
      })
      expect(wrapper.findComponent(PluginFieldsForm).exists()).toBe(false)
    })

    it('redirects a rejected plugin, which is not editable', async () => {
      signIn()
      seedStore(makePlugin({ status: 'rejected' }))

      const wrapper = mountView()
      await flushPromises()

      expect(mockReplace).toHaveBeenCalled()
      expect(wrapper.findComponent(PluginFieldsForm).exists()).toBe(false)
    })

    it('redirects a published plugin, which cannot be edited in place', async () => {
      signIn()
      seedStore(makePlugin({ status: 'approved', approved_at: '2026-10-01T00:00:00Z' }))

      const wrapper = mountView()
      await flushPromises()

      expect(mockReplace).toHaveBeenCalled()
      expect(wrapper.findComponent(PluginFieldsForm).exists()).toBe(false)
    })

    it('redirects a guest to the detail page', async () => {
      // The route requires auth, so this is unreachable in the app; the store's
      // emptiness on a first render is what the page has to survive.
      seedStore()

      mountView()
      await flushPromises()

      expect(mockReplace).toHaveBeenCalled()
    })
  })

  describe('the pending plugin notice', () => {
    it('says the save sends the plugin back to review', async () => {
      signIn()
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      // Otherwise "Save changes" would promise a catalogue update the edit does
      // not perform. Unconditional in the template: this branch only renders
      // when `canEdit` is true, which means pending and nothing else, so a
      // status check here would be a second copy of that rule.
      expect(wrapper.text()).toContain('back into the review queue')
    })
  })

  describe('saving', () => {
    const values = {
      name: '@dsh/my-plugin',
      title: 'Renamed',
      license: 'MIT',
      source_link: 'https://github.com/owner/repo',
    }

    it('sends the four editable fields and returns to the detail page', async () => {
      signIn()
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      // Called through the form's own prop, which is the contract the form uses:
      // resolve and the form emits `submitted`, reject and the form stays.
      await wrapper.findComponent(PluginFieldsForm).props('submit')(values)
      await flushPromises()

      expect(mockPluginUpdate).toHaveBeenCalledWith('plugin-123', values)
      expect(mockNotifySuccess).toHaveBeenCalledWith('Plugin updated successfully.')
      expect(mockPush).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { id: 'plugin-123' },
      })
    })

    it('leaves the page on the plugin and shows the message when the save fails', async () => {
      signIn()
      seedStore()

      mockPluginUpdate.mockRejectedValue(new Error('boom'))

      const wrapper = mountView()
      await flushPromises()

      // The rejection is what stops the form emitting `submitted`; the page
      // state is what the author actually sees afterwards.
      await expect(wrapper.findComponent(PluginFieldsForm).props('submit')(values)).rejects.toThrow()
      await flushPromises()

      // Redirecting away from a form whose save failed would throw the author's
      // typing away and claim the edit went through.
      expect(mockPush).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Could not save the plugin.')
    })
  })
})