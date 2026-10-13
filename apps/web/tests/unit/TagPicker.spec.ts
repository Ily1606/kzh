import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import TagPicker from '@/components/pages/plugin/TagPicker.vue'

/**
 * The list is portalled to `document.body`, so the options are read from there
 * rather than from the wrapper.
 */
const options = () =>
  Array.from(document.querySelectorAll<HTMLElement>('[data-test^="tag-option-"]'))

const panelText = () => document.querySelector('[data-slot="popover-content"]')?.textContent ?? ''

async function settle() {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
}

/** What the taxonomy endpoint hands the form, which it passes straight down. */
const TAGS = [
  'Manifest checked',
  'May read files',
  'May write files',
  'May run commands',
  'May access network',
  'Version not locked',
  'Needs credentials',
]

function mountPicker(modelValue: string[] = []) {
  return mount(TagPicker, {
    props: { modelValue, tags: TAGS },
    attachTo: document.body,
  })
}

async function open(wrapper: ReturnType<typeof mountPicker>) {
  await wrapper.find('[data-test="tags-trigger"]').trigger('click')
  await settle()
}

describe('TagPicker.vue', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows the prompt and no count while nothing is picked', () => {
    const wrapper = mountPicker()

    expect(wrapper.text()).toContain('Select tags...')
    expect(wrapper.text()).toContain('0/5')
  })

  it('lists every tag the API accepts', async () => {
    const wrapper = mountPicker()
    await open(wrapper)

    expect(options().map((o) => o.dataset.test)).toEqual(
      expect.arrayContaining([
        'tag-option-Manifest checked',
        'tag-option-May read files',
        'tag-option-May write files',
        'tag-option-May run commands',
        'tag-option-May access network',
                'tag-option-Version not locked',
                'tag-option-Needs credentials',
      ]),
    )
  })

  it('adds a picked tag and shows it on the trigger', async () => {
    const wrapper = mountPicker()
    await open(wrapper)

    await options().find((o) => o.dataset.test === 'tag-option-May read files')!.click()
    await settle()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([['May read files']])
  })

  it('removes a tag that is picked again', async () => {
    const wrapper = mountPicker(['May read files'])
    await open(wrapper)

    await options().find((o) => o.dataset.test === 'tag-option-May read files')!.click()
    await settle()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([[]])
  })

  it('refuses a sixth tag instead of letting the API reject the submission', async () => {
    const wrapper = mountPicker([
      'Manifest checked',
      'May read files',
      'May write files',
      'May run commands',
      'May access network',
    ])
    await open(wrapper)

    expect(wrapper.text()).toContain('5/5')
    expect(panelText()).toContain('Limit reached')

    await options().find((o) => o.dataset.test === 'tag-option-Needs credentials')!.click()
    await settle()

    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
  })

  it('still lets a picked tag be swapped out once the limit is reached', async () => {
    // Dropping one and adding another is how the author corrects a full list.
    const wrapper = mountPicker([
      'Manifest checked',
      'May read files',
      'May write files',
      'May run commands',
      'May access network',
    ])
    await open(wrapper)

    await options().find((o) => o.dataset.test === 'tag-option-May access network')!.click()
    await settle()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
      ['Manifest checked', 'May read files', 'May write files', 'May run commands'],
    ])
  })
})