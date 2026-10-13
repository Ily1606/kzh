import { mount, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, afterEach, beforeEach } from 'vitest'
import { h, ref } from 'vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { chooseOption, openSelect, selectItems, settle } from '../../helpers/select'

let mounted: VueWrapper | null = null

function mountSelect(initial?: string) {
  const selected = ref<string | undefined>(initial)

  const wrapper = mount(
    {
      setup: () => () =>
        h(
          Select,
          {
            modelValue: selected.value,
            'onUpdate:modelValue': (value: unknown) => (selected.value = value as string),
          },
          {
            default: () => [
              h(SelectTrigger, { id: 'pick', 'data-test': 'trigger' }, {
                default: () => h(SelectValue, { placeholder: 'Pick one' }),
              }),
              h(SelectContent, null, {
                default: () =>
                  ['Vision', 'Fun', 'Integrations'].map((name) =>
                    h(SelectItem, { value: name, key: name }, { default: () => name }),
                  ),
              }),
            ],
          },
        ),
    },
    { attachTo: document.body },
  )

  mounted = wrapper

  return { wrapper, selected }
}

describe('ui/select', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  // A select left mounted from the previous test keeps answering to the same
  // context, so the next one renders the placeholder instead of its value.
  afterEach(() => {
    mounted?.unmount()
    mounted = null
  })

  describe('the trigger', () => {
    it('shows the placeholder until something is picked', async () => {
      const { wrapper } = mountSelect()
      await settle()

      const trigger = wrapper.find('#pick')

      expect(trigger.text()).toContain('Pick one')
      expect(wrapper.find('[data-slot="select-value"]').attributes('data-placeholder')).toBe('Pick one')
    })

    it('shows the current value instead of the placeholder', async () => {
      // The label is read off the mounted options, so it settles a tick after
      // the first render rather than during it.
      const { wrapper } = mountSelect('Fun')
      await settle()

      const trigger = wrapper.find('#pick')

      expect(trigger.text()).toContain('Fun')
      expect(wrapper.find('[data-slot="select-value"]').attributes('data-placeholder')).toBeUndefined()
    })

    it('carries the design-system classes alongside the ones the caller passes', () => {
      const { wrapper } = mountSelect()
      const className = wrapper.find('#pick').attributes('class') ?? ''

      expect(className).toContain('border-input')
      expect(className).toContain('data-[placeholder]:text-muted-foreground')
      expect(className).toContain('focus-visible:border-ring')
      expect(className).toContain('w-full')
    })
  })

  describe('opening', () => {
    it('keeps the options out of the page until it is opened', () => {
      mountSelect()

      expect(selectItems()).toHaveLength(0)
    })

    it('lists every item on open', async () => {
      const { wrapper } = mountSelect()

      await openSelect(wrapper.find('#pick').element)

      expect(selectItems().map((i) => i.textContent?.trim())).toEqual(['Vision', 'Fun', 'Integrations'])
    })

    it('portals the list and marks the open state', async () => {
      const { wrapper } = mountSelect()

      await openSelect(wrapper.find('#pick').element)
      await settle()

      const content = document.querySelector('[data-slot="select-content"]')

      expect(content?.getAttribute('data-state')).toBe('open')
      // Tailwind rewrites the arbitrary value to the spacing scale, so what
      // ships is min-w-32 rather than min-w-[8rem].
      expect(content?.className).toContain('bg-popover')
      expect(content?.className).toContain('min-w-32')
    })
  })

  describe('picking', () => {
    it('writes the choice back through v-model', async () => {
      const { wrapper, selected } = mountSelect()

      await openSelect(wrapper.find('#pick').element)
      await chooseOption('Fun')

      expect(selected.value).toBe('Fun')
    })

    it('puts the choice on the trigger', async () => {
      const { wrapper } = mountSelect()

      await openSelect(wrapper.find('#pick').element)
      await chooseOption('Integrations')

      expect(wrapper.find('#pick').text()).toContain('Integrations')
    })

    it('replaces the choice when another is picked', async () => {
      const { wrapper } = mountSelect()

      await openSelect(wrapper.find('#pick').element)
      await chooseOption('Fun')
      await openSelect(wrapper.find('#pick').element)
      await chooseOption('Vision')

      expect(wrapper.find('#pick').text()).toContain('Vision')
      expect(wrapper.find('#pick').text()).not.toContain('Fun')
    })

    it('marks the chosen item as the selected one', async () => {
      const { wrapper } = mountSelect('Fun')

      await openSelect(wrapper.find('#pick').element)

      expect(
        selectItems().find((i) => i.textContent?.trim() === 'Fun')?.getAttribute('data-state'),
      ).toBe('checked')
    })
  })
})