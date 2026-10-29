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
const mockPluginUpdateBySlug = vi.fn()
const mockNotifyError = vi.fn()
const mockNotifySuccess = vi.fn()

vi.mock('@/api/generated/endpoints', async () => {
  const { getCategory, getTag } = await import('../helpers/taxonomy')

  return {
    getPlugin: () => ({
      pluginShow: mockPluginShow,
      pluginUpdateBySlug: mockPluginUpdateBySlug,
    }),
    getStar: () => ({ starStore: vi.fn() }),
    getAuth: () => ({ authLogout: vi.fn() }),
    getProfile: () => ({ profileShow: vi.fn() }),
    getCategory,
    getTag,
  }
})

vi.mock('@/utils/toast', () => ({
  notifyError: (message: string) => mockNotifyError(message),
  notifySuccess: (message: string) => mockNotifySuccess(message),
}))

const mockRoute = ref({
  params: { username: 'janedev', pluginName: '@dsh/my-plugin' } as Record<string, string>,
  fullPath: '/resources/janedev/@dsh/my-plugin/edit',
})

const mockPush = vi.fn()
const mockReplace = vi.fn()
const mockBack = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => ({ push: mockPush, replace: mockReplace, back: mockBack }),
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
}))

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-123',
    name: '@dsh/my-plugin',
    user_id: 'user-456',
    author: { id: 'user-456', name: 'Jane Dev', username: 'janedev', avatar_url: '' },
    title: 'My awesome plugin',
    license: 'MIT',
    category: 'Integrations',
    tags: ['May read files'],
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

    // No earlier entry by default: a bookmark or a new tab has no history to
    // pop. Tests that do have one set their own `back` below.
    window.history.replaceState(null, '')

    mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin() } })
    mockPluginUpdateBySlug.mockResolvedValue({ data: { plugin: makePlugin({ title: 'Renamed' }) } })
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
        category: 'Integrations',
        tags: ['May read files'],
        source_link: 'https://github.com/owner/repo',
      })
    })

    it('leaves the taxonomy empty for a plugin published before it existed', async () => {
      signIn()
      seedStore(makePlugin({ category: null, tags: [] }))

      const wrapper = mountView()
      await flushPromises()

      // Nothing to carry over: the form must not claim a category the plugin
      // never chose, and the author has to pick one before saving.
      expect(wrapper.findComponent(PluginFieldsForm).props('initialValues')).toMatchObject({
        category: '',
        tags: [],
      })
    })

    it('fetches once when the store does not have the plugin', async () => {
      signIn()

      mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin({ title: 'From API' }) } })

      const wrapper = mountView()
      await flushPromises()

      // The hard-refresh / shared-link case.
      expect(mockPluginShow).toHaveBeenCalledWith('janedev', '@dsh/my-plugin')
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
        params: { username: 'janedev', pluginName: '@dsh/my-plugin' },
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

  describe('the back button', () => {
    it('pops the history rather than pushing the detail route', async () => {
      signIn()
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      const back = wrapper.findAll('button').find((button) => button.text() === 'Back to plugin')
      expect(back).toBeDefined()

      await back!.trigger('click')

      expect(mockBack).toHaveBeenCalled()
      // Pushing the detail route again would leave the edit page in the
      // history, so the browser Back button would land on it a second time
      // instead of returning to the list.
      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  describe('saving', () => {
    const baseValues = {
      name: '@dsh/my-plugin',
      title: 'Renamed',
      license: 'MIT',
      category: 'Vision',
      tags: ['May read files'],
      source_link: 'https://github.com/owner/repo',
    }

    it('sends the six editable fields and returns to the detail page', async () => {
      signIn()
      seedStore()
      // The view only pops history when there is an earlier entry to go back to.
      window.history.replaceState({ back: '/plugins' }, '')

      const wrapper = mountView()
      await flushPromises()

      // Called through the form's own prop, which is the contract the form uses:
      // resolve and the form emits `submitted`, reject and the form stays.
      await wrapper.findComponent(PluginFieldsForm).props('submit')(baseValues)
      await flushPromises()

      expect(mockPluginUpdateBySlug).toHaveBeenCalledWith('janedev', '@dsh/my-plugin', baseValues)
      expect(mockNotifySuccess).toHaveBeenCalledWith('Plugin updated successfully.')
      // Back, not a fresh push: pushing the detail route again would leave this
      // page in the history and send the browser Back button straight back into it.
      expect(mockBack).toHaveBeenCalled()
    })

    it('navigates to the new slug when the save renames the plugin', async () => {
      signIn()
      seedStore()
      mockPluginUpdateBySlug.mockResolvedValue({
        data: { plugin: makePlugin({ name: 'renamed-plugin' }) },
      })

      const wrapper = mountView()
      await flushPromises()

      // Submit with the NEW name in the payload to exercise the rename logic.
      // The form's submit prop is the contract between the page and the form.
      const renamedValues = { ...baseValues, name: 'renamed-plugin' }
      await wrapper.findComponent(PluginFieldsForm).props('submit')(renamedValues)
      await flushPromises()

      // The update request should use the OLD slug (janedev/@dsh/my-plugin) but
      // send the NEW name (renamed-plugin) in the payload.
      expect(mockPluginUpdateBySlug).toHaveBeenCalledWith(
        'janedev',
        '@dsh/my-plugin',
        expect.objectContaining({ name: 'renamed-plugin' })
      )

      // Back would land on the URL built from the old name, which now 404s.
      expect(mockBack).not.toHaveBeenCalled()
      expect(mockReplace).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { username: 'janedev', pluginName: 'renamed-plugin' },
      })
    })

    it('returns to the detail page when there is no earlier history entry', async () => {
      signIn()
      seedStore()

      const wrapper = mountView()
      await flushPromises()

      await wrapper.findComponent(PluginFieldsForm).props('submit')(baseValues)
      await flushPromises()

      // A bookmark or a new tab: `back()` would drop the reader out of the app.
      expect(mockBack).not.toHaveBeenCalled()
      expect(mockReplace).toHaveBeenCalledWith({
        name: 'plugin-detail',
        params: { username: 'janedev', pluginName: '@dsh/my-plugin' },
      })
    })

    it('leaves the page on the plugin and shows the message when the save fails', async () => {
      signIn()
      seedStore()

      mockPluginUpdateBySlug.mockRejectedValue(new Error('boom'))

      const wrapper = mountView()
      await flushPromises()

      // The rejection is what stops the form emitting `submitted`; the page
      // state is what the author actually sees afterwards.
      await expect(wrapper.findComponent(PluginFieldsForm).props('submit')(baseValues)).rejects.toThrow()
      await flushPromises()

      // Redirecting away from a form whose save failed would throw the author's
      // typing away and claim the edit went through.
      expect(mockBack).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Could not save the plugin.')
    })
  })
})