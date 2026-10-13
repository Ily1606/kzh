import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import PublishPluginView from '@/views/PublishPluginView.vue'
import { chooseOption, openSelect } from '../helpers/select'

const mockPluginStore = vi.fn()
vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginStore: mockPluginStore
  })
}))

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot></slot></a>' }
}))

const waitForValidation = async () => {
  await flushPromises()
  await new Promise(r => setTimeout(r, 50))
  await flushPromises()
}

describe('PublishPluginView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders correctly', () => {
    const wrapper = mount(PublishPluginView)
    expect(wrapper.find('input#name').exists()).toBe(true)
    expect(wrapper.find('input#title').exists()).toBe(true)
    expect(wrapper.find('button#license').exists()).toBe(true)
    expect(wrapper.find('input#source').exists()).toBe(true)
    expect(wrapper.text()).toContain('Publish plugin')
  })

  it('shows validation errors when submitting an empty form', async () => {
    const wrapper = mount(PublishPluginView)

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.text()).toContain('Plugin name is required')
    expect(wrapper.text()).toContain('Display title is required')
    expect(wrapper.text()).toContain('Source link is required')
    expect(mockPluginStore).not.toHaveBeenCalled()
  })

  it('shows validation errors for invalid source link', async () => {
    const wrapper = mount(PublishPluginView)

    await wrapper.find('input#name').setValue('my-plugin')
    await wrapper.find('input#title').setValue('My Plugin')
    await openSelect(wrapper.find('button#category').element)
    await chooseOption('Integrations')

    // Invalid URL (not a URL)
    await wrapper.find('input#source').setValue('not-a-url')
    await wrapper.find('form').trigger('submit')
    await waitForValidation()
    expect(wrapper.text()).toContain('Source link must be a valid URL')

    // Invalid URL (does not start with https)
    await wrapper.find('input#source').setValue('http://github.com/test/repo')
    await wrapper.find('form').trigger('submit')
    await waitForValidation()
    expect(wrapper.text()).toContain('Source link must start with https://')

    expect(mockPluginStore).not.toHaveBeenCalled()
  })

  it('submits successfully and shows the success state', async () => {
    mockPluginStore.mockResolvedValueOnce({})
    const wrapper = mount(PublishPluginView)

    await wrapper.find('input#name').setValue('my-plugin')
    await wrapper.find('input#title').setValue('My Plugin')
    await openSelect(wrapper.find('button#category').element)
    await chooseOption('Integrations')
    await wrapper.find('input#source').setValue('https://github.com/test/repo')

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(mockPluginStore).toHaveBeenCalledWith({
      name: 'my-plugin',
      title: 'My Plugin',
      license: 'MIT',
      category: 'Integrations',
      tags: [],
      source_link: 'https://github.com/test/repo'
    })

    expect(wrapper.text()).toContain('Plugin Submitted Successfully!')

    // The author decides when to leave. Waiting out the window the old redirect
    // timer used is how this proves the panel is still there and the form has
    // not come back.
    await new Promise(r => setTimeout(r, 2500))
    expect(wrapper.text()).toContain('Plugin Submitted Successfully!')
    expect(wrapper.find('form').exists()).toBe(false)
  }, 10000)

  it('maps server 422 validation errors to fields', async () => {
    mockPluginStore.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'The given data was invalid.',
          errors: {
            name: ['The name has already been taken.']
          }
        }
      }
    })

    const wrapper = mount(PublishPluginView)

    await wrapper.find('input#name').setValue('my-plugin')
    await wrapper.find('input#title').setValue('My Plugin')
    await openSelect(wrapper.find('button#category').element)
    await chooseOption('Integrations')
    await wrapper.find('input#source').setValue('https://github.com/test/repo')

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.text()).toContain('The name has already been taken.')
    expect(wrapper.text()).toContain('The given data was invalid.')
  })

  it('displays generic error if server fails with 500', async () => {
    mockPluginStore.mockRejectedValueOnce({
      response: {
        status: 500,
        data: {
          message: 'Internal Server Error'
        }
      }
    })

    const wrapper = mount(PublishPluginView)

    await wrapper.find('input#name').setValue('my-plugin')
    await wrapper.find('input#title').setValue('My Plugin')
    await openSelect(wrapper.find('button#category').element)
    await chooseOption('Integrations')
    await wrapper.find('input#source').setValue('https://github.com/test/repo')

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.text()).toContain('Internal Server Error')
  })

  it('displays default error message if error object lacks message', async () => {
    mockPluginStore.mockRejectedValueOnce(new Error('Unknown Error'))

    const wrapper = mount(PublishPluginView)

    await wrapper.find('input#name').setValue('my-plugin')
    await wrapper.find('input#title').setValue('My Plugin')
    await openSelect(wrapper.find('button#category').element)
    await chooseOption('Integrations')
    await wrapper.find('input#source').setValue('https://github.com/test/repo')

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.text()).toContain('An error occurred while submitting the plugin.')
  })

  it('toggles carousel slides', async () => {
    const wrapper = mount(PublishPluginView)

    const prevBtn = wrapper.find('button[aria-label="Previous quote"]')
    const nextBtn = wrapper.find('button[aria-label="Next quote"]')

    // Initial slide
    expect(wrapper.text()).toContain('Alex Morgan')

    // Click next -> second slide
    await nextBtn.trigger('click')
    expect(wrapper.text()).toContain('DSH Verification Engine')

    // Click next -> third slide
    await nextBtn.trigger('click')
    expect(wrapper.text()).toContain('Global Registry Edge')

    // Click next -> back to first slide
    await nextBtn.trigger('click')
    expect(wrapper.text()).toContain('Alex Morgan')

    // Click prev -> third slide
    await prevBtn.trigger('click')
    expect(wrapper.text()).toContain('Global Registry Edge')
  })
})

