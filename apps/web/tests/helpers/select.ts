import { flushPromises } from '@vue/test-utils'

/**
 * reka-ui selects on pointerup rather than click, and jsdom ships neither
 * `PointerEvent` nor pointer capture. So the properties reka reads are written
 * onto a plain event by hand.
 */
function pointer(target: Element, type: string) {
  const event = new Event(type, { bubbles: true }) as Event & Record<string, unknown>

  event.pointerType = 'mouse'
  event.button = 0
  event.ctrlKey = false
  event.pointerId = 1

  target.dispatchEvent(event)
}

export async function settle() {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
  await flushPromises()
}

export const selectItems = () =>
  Array.from(document.querySelectorAll<HTMLElement>('[data-slot="select-item"]'))

const selectItem = (text: string) =>
  selectItems().find((item) => item.textContent?.trim() === text)

/** Open the dropdown sitting behind `trigger`. */
export async function openSelect(trigger: Element) {
  pointer(trigger, 'pointerdown')
  await settle()
}

/** Pick `text` out of an already open dropdown. */
export async function chooseOption(text: string) {
  const item = selectItem(text)

  if (!item) {
    throw new Error(`No select option named "${text}"`)
  }

  pointer(item, 'pointerdown')
  pointer(item, 'pointerup')

  await settle()
}