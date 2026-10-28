import { mount } from '@vue/test-utils'
import { describe, it, expect, beforeEach } from 'vitest'
import PluginDeleteModal from '@/components/pages/plugin-detail/PluginDeleteModal.vue'

describe('PluginDeleteModal.vue', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nothing in body when show is false', () => {
    mount(PluginDeleteModal, {
      props: {
        show: false,
        pluginName: '@dsh/test-plugin',
      },
      attachTo: document.body,
    })

    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
  })

  it('renders modal dialog with plugin name and warnings when show is true', () => {
    mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
      },
      attachTo: document.body,
    })

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog?.textContent).toContain('Delete Plugin')
    expect(dialog?.textContent).toContain('@dsh/test-plugin')
    expect(dialog?.textContent).toContain('This action will permanently remove the plugin')
  })

  it('emits close event when Cancel button is clicked', async () => {
    const wrapper = mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
      },
      attachTo: document.body,
    })

    const cancelBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      /Cancel/i.test(b.textContent ?? '')
    )
    expect(cancelBtn).toBeDefined()
    cancelBtn!.click()

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('emits confirm event when Delete Plugin button is clicked', async () => {
    const wrapper = mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
      },
      attachTo: document.body,
    })

    const confirmBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      /Delete Plugin/i.test(b.textContent ?? '')
    )
    expect(confirmBtn).toBeDefined()
    confirmBtn!.click()

    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })

  it('disables buttons and shows spinner when isDeleting is true', () => {
    const wrapper = mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
        isDeleting: true,
      },
      attachTo: document.body,
    })

    const deleteBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      /Deleting.../i.test(b.textContent ?? '')
    )
    expect(deleteBtn).toBeDefined()
    expect(deleteBtn?.hasAttribute('disabled')).toBe(true)

    const cancelBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      /Cancel/i.test(b.textContent ?? '')
    )
    expect(cancelBtn?.hasAttribute('disabled')).toBe(true)

    // Clicking while isDeleting should not emit confirm or close
    deleteBtn!.click()
    cancelBtn!.click()

    expect(wrapper.emitted('confirm')).toBeUndefined()
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('emits close on Escape key when not deleting', async () => {
    const wrapper = mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
        isDeleting: false,
      },
      attachTo: document.body,
    })

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('does not emit close on Escape key when isDeleting is true', async () => {
    const wrapper = mount(PluginDeleteModal, {
      props: {
        show: true,
        pluginName: '@dsh/test-plugin',
        isDeleting: true,
      },
      attachTo: document.body,
    })

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toBeUndefined()
  })
})

