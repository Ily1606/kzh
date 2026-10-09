import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import MyPluginRow from '@/components/pages/plugin/MyPluginRow.vue'
import { formatRelativeDate } from '@/utils/date'
import type { PluginResource } from '@/api/generated/model'

const mockPush = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({ fullPath: '/resources' }),
  useRouter: () => ({ push: mockPush }),
  RouterLink: { props: ['to'], template: '<a :href="to && to.name ? `/${to.name}/${to.params.id}` : \'\'"><slot /></a>' },
}))

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-1',
    name: '@dsh/dsr-webhook',
    user_id: 'user-1',
    author: null,
    title: 'Webhook notifier',
    license: 'MIT',
    approved_at: '2026-10-01T00:00:00Z',
    status: 'approved',
    source_link: 'https://github.com/owner/repo',
    star_count: 42,
    comment_count: 7,
    view_count: 1200,
    created_at: '2026-09-30T00:00:00Z',
    updated_at: '2026-10-02T00:00:00Z',
    ...overrides,
  } as PluginResource
}

function mountRow(plugin: PluginResource = makePlugin()) {
  return mount(MyPluginRow, { props: { plugin } })
}

describe('MyPluginRow.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    const pinia = createPinia()
    createApp({}).use(pinia)
    setActivePinia(pinia)
  })

  it('links the whole row to the plugin detail page', () => {
    const wrapper = mountRow()

    const link = wrapper.find('a')

    // The row, not just the name: this is a list of things to click, and the
    // hover and focus rings the row already had implied exactly that.
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/plugin-detail/plugin-1')
  })

  it('renders the plugin name, title and stats', () => {
    const wrapper = mountRow()

    expect(wrapper.text()).toContain('@dsh/dsr-webhook')
    expect(wrapper.text()).toContain('Webhook notifier')
    expect(wrapper.text()).toContain('1200')
    expect(wrapper.text()).toContain('42')
    expect(wrapper.text()).toContain('7')
  })

  it('shows the status badge for the plugin status', () => {
    const wrapper = mountRow(makePlugin({ status: 'pending', approved_at: null }))

    expect(wrapper.findComponent({ name: 'PluginStatusBadge' }).exists()).toBe(true)
    expect(wrapper.text()).toContain('Pending')
  })

  describe('the date column', () => {
    it('shows the approval date for a published plugin', () => {
      const wrapper = mountRow(makePlugin())

      expect(wrapper.find('time').text()).toBe(formatRelativeDate('2026-10-01T00:00:00Z'))
    })

    it('falls back to the submission date when the plugin is not approved', () => {
      // `approved_at` is null for everything in review, which is most of this
      // list. A blank cell there would read as "no date" when the one fact the
      // page always has is when it was sent.
      const wrapper = mountRow(makePlugin({ status: 'pending', approved_at: null }))

      expect(wrapper.find('time').text()).toBe(formatRelativeDate('2026-09-30T00:00:00Z'))
    })
  })

  it('contains no control that the row link would have to swallow', () => {
    const wrapper = mountRow()

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('input').exists()).toBe(false)
  })
})