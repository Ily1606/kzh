import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import { emptyPluginFields, submitPluginSchema, updatePluginSchema } from '@/schemas/plugin.schema'
import type { PluginFields } from '@/schemas/plugin.schema'

const waitForValidation = async () => {
  await flushPromises()
  await new Promise((r) => setTimeout(r, 50))
  await flushPromises()
}

const valid: PluginFields = {
  name: '@dsh/guardrails',
  title: 'Opinionated safety checks',
  license: 'MIT',
  source_link: 'https://github.com/owner/repo',
}

function mountForm(overrides: Partial<{ submit: (values: PluginFields) => Promise<unknown> }> = {}) {
  return mount(PluginFieldsForm, {
    props: {
      schema: submitPluginSchema,
      initialValues: emptyPluginFields,
      submitLabel: 'Publish plugin',
      submit: overrides.submit ?? vi.fn().mockResolvedValue(undefined),
    },
  })
}

async function fillIn(wrapper: ReturnType<typeof mountForm>, values: Partial<PluginFields> = {}) {
  const merged = { ...valid, ...values }

  await wrapper.find('input#name').setValue(merged.name)
  await wrapper.find('input#title').setValue(merged.title)
  await wrapper.find('select#license').setValue(merged.license)
  await wrapper.find('input#source').setValue(merged.source_link)
}

describe('PluginFieldsForm.vue', () => {
  describe('rendering', () => {
    it('renders the four editable fields and the label from props', () => {
      const wrapper = mountForm()

      expect(wrapper.find('input#name').exists()).toBe(true)
      expect(wrapper.find('input#title').exists()).toBe(true)
      expect(wrapper.find('select#license').exists()).toBe(true)
      expect(wrapper.find('input#source').exists()).toBe(true)
      expect(wrapper.text()).toContain('Publish plugin')
    })

    it('offers every license the API accepts', () => {
      const wrapper = mountForm()

      const options = wrapper.findAll('select#license option')

      expect(options.map((o) => o.attributes('value'))).toEqual(
        expect.arrayContaining(['MIT', 'Apache-2.0', 'GPL-2.0', 'GPL-3.0', 'BSD-3-Clause', 'proprietary']),
      )
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
    it('calls submit with the four values and emits submitted', async () => {
      const submit = vi.fn().mockResolvedValue(undefined)
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(valid)
      expect(wrapper.emitted('submitted')).toBeTruthy()
    })

    it('does not emit submitted when submit rejects', async () => {
      // The page owns the failure, and the rejection is how it says so. An
      // event here would redirect away from a form whose save did not land.
      const submit = vi.fn().mockRejectedValue(new Error('boom'))
      const wrapper = mountForm({ submit })

      await fillIn(wrapper)
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalled()
      expect(wrapper.emitted('submitted')).toBeFalsy()
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
      const wrapper = mount(PluginFieldsForm, {
        props: {
          schema: updatePluginSchema,
          initialValues: valid,
          submitLabel: 'Save changes',
          submit,
        },
      })

      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(submit).toHaveBeenCalledWith(valid)
      expect(wrapper.emitted('submitted')).toBeTruthy()
    })

    it('applies the same rules as the submit schema', async () => {
      // One set of rules on purpose: two schemas would be two copies to keep in
      // step, and the update endpoint enforces what submit does.
      const submit = vi.fn()
      const wrapper = mount(PluginFieldsForm, {
        props: {
          schema: updatePluginSchema,
          initialValues: valid,
          submitLabel: 'Save changes',
          submit,
        },
      })

      await wrapper.find('input#source').setValue('not-a-url')
      await wrapper.find('form').trigger('submit')
      await waitForValidation()

      expect(wrapper.text()).toContain('Source link must be a valid URL')
      expect(submit).not.toHaveBeenCalled()
    })
  })
})