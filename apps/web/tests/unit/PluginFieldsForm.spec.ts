import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, it, expect, vi, afterEach } from 'vitest'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import { emptyPluginFields } from '@/schemas/plugin.schema'
import type { PluginFields } from '@/schemas/plugin.schema'
import { chooseOption, openSelect, selectItems } from '../helpers/select'

vi.mock('@/api/generated/endpoints', async () => {
  const { getCategory, getTag } = await import('../helpers/taxonomy')

  return { getCategory, getTag }
})

const waitForValidation = async () => {
  await flushPromises()
  await new Promise((r) => setTimeout(r, 50))
  await flushPromises()
}

const valid: PluginFields = {
  name: 'guardrails',
  title: 'Opinionated safety checks',
  license: 'MIT',
  category: 'Security & diagnostics',
  tags: [],
  source_link: 'https://github.com/owner/repo',
}

const wrappers: VueWrapper[] = []

/** The taxonomy store reaches for the API on mount; tests only need a pinia. */
function setupPinia() {
  const pinia = createPinia()

  setActivePinia(pinia)

  return pinia
}

/** Same tracking as `mountForm`, for the tests that bring their own props. */
function mountFormWith(props: {
  initialValues: PluginFields
  submitLabel: string
  submit: (values: PluginFields) => Promise<unknown>
}) {
  const wrapper = mount(PluginFieldsForm, { props, global: { plugins: [setupPinia()] } })

  wrappers.push(wrapper)

  return wrapper
}

function mountForm(overrides: Partial<{ submit: (values: PluginFields) => Promise<unknown> }> = {}) {
  const wrapper = mount(PluginFieldsForm, {
    props: {
      initialValues: emptyPluginFields,
      submitLabel: 'Publish plugin',
      submit: overrides.submit ?? vi.fn().mockResolvedValue(undefined),
    },
    global: { plugins: [setupPinia()] },
  })

  wrappers.push(wrapper)

  return wrapper
}

/** The tag list is a Popover, so it opens on click rather than reka's pointerup. */
async function pickTag(name: string) {
  const option = Array.from(document.querySelectorAll<HTMLElement>('[data-test^="tag-option-"]'))
    .find((o) => o.dataset.test === `tag-option-${name}`)

  if (!option) throw new Error(`No tag option named "${name}"`)

  option.click()

  await waitForValidation()
}

async function fillIn(wrapper: ReturnType<typeof mountForm>, values: Partial<PluginFields> = {}) {
  const merged = { ...valid, ...values }

  await wrapper.find('input#name').setValue(merged.name)
  await wrapper.find('input#title').setValue(merged.title)
  await wrapper.find('input#source').setValue(merged.source_link)

  if (merged.license !== 'MIT') {
    await openSelect(wrapper.find('button#license').element)
    await chooseOption(merged.license)
  }

  if (merged.category) {
    await openSelect(wrapper.find('button#category').element)
    await chooseOption(merged.category)
  }
}

describe('PluginFieldsForm.vue', () => {
  // A form left mounted keeps its portalled select open, so the next test's
  // option lookup finds the previous form's list instead of its own.
  afterEach(() => {
    while (wrappers.length) wrappers.pop()!.unmount()
    document.body.innerHTML = ''
  })

  describe('rendering', () => {
    it('renders the six editable fields and the label from props', () => {
      const wrapper = mountForm()

      expect(wrapper.find('input#name').exists()).toBe(true)
      expect(wrapper.find('input#title').exists()).toBe(true)
      expect(wrapper.find('button#license').exists()).toBe(true)
      expect(wrapper.find('button#category').exists()).toBe(true)
      expect(wrapper.findComponent({ name: 'TagPicker' }).exists()).toBe(true)
      expect(wrapper.find('input#source').exists()).toBe(true)
      expect(wrapper.text()).toContain('Publish plugin')
    })

    it('offers every license the API accepts', async () => {
      const wrapper = mountForm()

      await openSelect(wrapper.find('button#license').element)

      expect(selectItems().map((o) => o.textContent?.trim())).toEqual(
        expect.arrayContaining(['MIT', 'Apache-2.0', 'GPL-2.0', 'GPL-3.0', 'BSD-3-Clause', 'proprietary']),
      )
    })

    it('offers every category the API accepts', async () => {
      const wrapper = mountForm()

      await openSelect(wrapper.find('button#category').element)

      expect(selectItems().map((o) => o.textContent?.trim())).toEqual(
        expect.arrayContaining(['UI & workspace', 'Vision', 'Multi-agent', 'Developer tools', 'Integrations', 'Security & diagnostics']),
      )
    })

    it('starts on MIT, the license the empty form declares', async () => {
      const wrapper = mountForm()

      await flushPromises()

      // Picking the list open and closed again must not change the value.
      expect(wrapper.find('[data-slot="select-value"]').text()).toContain('MIT')
    })
  })

  describe('validation', () => {
    it('rejects an empty form without calling submit', async () => {
      const submit = vi.fn()
      const wrapper = mountForm({ submit })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(wrapper.text()).toContain('Plugin name is required')
      expect(wrapper.text()).toContain('Display title is required')
      expect(wrapper.text()).toContain('Source link is required')
      expect(submit).not.toHaveBeenCalled()
    })

    it('rejects a form whose category was never chosen', async () => {
      const submit = vi.fn()
      const wrapper = mountForm({ submit })

      await fillIn(wrapper, { category: '' })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(wrapper.text()).toContain('Please choose a category')
      expect(submit).not.toHaveBeenCalled()
    })

    it('carries the tags the form was prefilled with', async () => {
      // The edit page hands over what the plugin already has, and a save that
      // dropped them would silently clear them on the server.
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountFormWith({
        initialValues: { ...valid, tags: ['May read files', 'Version not locked'] },
        submitLabel: 'Save changes',
        submit,
      })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(
        expect.objectContaining({ tags: ['May read files', 'Version not locked'] }),
      )
    })

    it('sends the license the author picked', async () => {
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountForm({ submit })

      await fillIn(wrapper, { license: 'Apache-2.0' })
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(expect.objectContaining({ license: 'Apache-2.0' }))
    })

    it('sends the tags picked in the picker', async () => {
      // The reverse direction of the test above: a pick inside the dropdown has
      // to travel back through the form to reach the payload.
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)

      await wrapper.find('[data-test="tags-trigger"]').trigger('click')
      await waitForValidation()
      await pickTag('May run commands')

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(expect.objectContaining({ tags: ['May run commands'] }))
    })

    it('rejects a source link that is not an https URL', async () => {
      const submit = vi.fn()

      for (const [url, message] of [
        ['not-a-url', 'Source link must be a valid URL'],
        ['http://github.com/owner/repo', 'Source link must start with https://'],
      ]) {
        const wrapper = mountForm({ submit })
        await fillIn(wrapper, { source_link: url })

        await wrapper.find('form').trigger('submit')
        await waitForValidation()

        expect(wrapper.text()).toContain(message)
      }

      expect(submit).not.toHaveBeenCalled()
    })

    it('enforces the length limits the API declares', async () => {
      const submit = vi.fn()
      const wrapper = mountForm({ submit })

      await fillIn(wrapper, { title: 'x'.repeat(256) })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(wrapper.text()).toContain('Display title must not exceed 255 characters')
      expect(submit).not.toHaveBeenCalled()
    })
  })

  describe('submitting', () => {
    it('calls submit with the six values', async () => {
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(valid)
    })

    it('swallows the rejection so the form keeps the author\'s input', async () => {
      // The page owns the failure: it showed the message, and the rejection is
      // how it says so. Swallowed here so the page is not left mid-flight.
      const submit = vi.fn().mockRejectedValue(new Error('boom'))
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalled()
      expect(wrapper.find<HTMLInputElement>('input#name').element.value).toBe(valid.name)
    })

    it('shows progress on the button for the whole request', async () => {
      let release!: () => void
      const submit = vi.fn().mockImplementation(
        () => new Promise<void>((resolve) => { release = resolve }),
      )
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      const button = wrapper.find('button[type="submit"]')
      expect(button.attributes('disabled')).toBeDefined()
      expect(button.text()).toContain('Saving...')

      release()
      await flushPromises()

      expect(wrapper.find('button[type="submit"]').text()).toContain('Publish plugin')
    })
  })

  describe('server-side field errors', () => {
    it('writes a rejection onto the field it belongs to', async () => {
      const wrapper = mountForm()
      const form = wrapper.vm as unknown as { showServerErrors: (e: Record<string, string[]>) => void }

      // The one rule no client can know: this user has already taken the name.
      form.showServerErrors({ name: ['Plugin name already exists.'] })

      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('Plugin name already exists.')
    })

    it('clears a previous rejection once the server stops sending it', async () => {
      const wrapper = mountForm()
      const form = wrapper.vm as unknown as { showServerErrors: (e: Record<string, string[]>) => void }

      form.showServerErrors({ name: ['Plugin name already exists.'] })
      await wrapper.vm.$nextTick()
      form.showServerErrors({})
      await wrapper.vm.$nextTick()

      // A field the server no longer complains about must not keep the message
      // it was rejected for, or the author is correcting an invisible problem.
      expect(wrapper.text()).not.toContain('Plugin name already exists.')
    })

    it('ignores an empty message list for a field', () => {
      const wrapper = mountForm()
      const form = wrapper.vm as unknown as { showServerErrors: (e: Record<string, string[]>) => void }

      form.showServerErrors({ name: [] })

      expect(wrapper.text()).not.toContain('undefined')
    })
  })

  describe('the update schema', () => {
    it('accepts the prefilled values of a plugin already in the store', async () => {
      // The edit page loads a stored plugin into the form, so its initial values
      // have to pass the same gate a submission does.
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountFormWith({
        initialValues: valid,
        submitLabel: 'Save changes',
        submit,
      })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(valid)
    })

    it('applies the same rules as the submit schema', async () => {
      // One set of rules on purpose: two schemas would be two copies to keep in
      // step, and the update endpoint enforces what submit does.
      const submit = vi.fn()
      const wrapper = mountFormWith({
        initialValues: valid,
        submitLabel: 'Save changes',
        submit,
      })

      await wrapper.find('input#source').setValue('not-a-url')
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(wrapper.text()).toContain('Source link must be a valid URL')
      expect(submit).not.toHaveBeenCalled()
    })
  })
})