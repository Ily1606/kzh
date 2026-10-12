import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import {
  Popover,
  PopoverAnchor,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

/**
 * Popover content is portalled to `document.body`, so these tests attach to the
 * document and read the content from there rather than from the wrapper.
 */
const content = () => document.querySelector<HTMLElement>('[data-slot="popover-content"]')

async function settle() {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
}

function mountFixture(template: string) {
  return mount(
    {
      components: { Popover, PopoverAnchor, PopoverContent, PopoverTrigger },
      template,
    },
    { attachTo: document.body }
  )
}

let wrapper: VueWrapper | null = null

describe('ui/popover', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  describe('PopoverTrigger', () => {
    it('renders its own button by default', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger data-test="trigger">open</PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      const trigger = wrapper.find('[data-test="trigger"]')

      expect(trigger.exists()).toBe(true)
      expect(trigger.element.tagName).toBe('BUTTON')
    })

    it('renders the slot element instead when asChild is set', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger as-child><span data-test="wrap"><button disabled>star</button></span></PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      const wrap = wrapper.find('[data-test="wrap"]')

      // The span is the trigger, not a button wrapping the span.
      expect(wrap.element.tagName).toBe('SPAN')
      expect(wrap.attributes('data-slot')).toBe('popover-trigger')
      expect(wrap.find('button').attributes('disabled')).toBeDefined()
    })

    it('keeps the accessibility wiring on an asChild trigger', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger as-child><span data-test="wrap">x</span></PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      const wrap = wrapper.find('[data-test="wrap"]')

      expect(wrap.attributes('aria-haspopup')).toBe('dialog')
      expect(wrap.attributes('aria-expanded')).toBe('false')
      expect(wrap.attributes('data-state')).toBe('closed')
    })
  })

  describe('opening', () => {
    it('opens on click and reveals the portalled content', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger data-test="trigger">open</PopoverTrigger>
          <PopoverContent>panel body</PopoverContent>
        </Popover>`)

      expect(content()).toBeNull()

      await wrapper.find('[data-test="trigger"]').trigger('click')
      await settle()

      expect(content()).not.toBeNull()
      expect(content()?.textContent).toContain('panel body')
      expect(content()?.getAttribute('data-state')).toBe('open')
    })

    it('opens from an asChild trigger wrapping a disabled button', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger as-child><span data-test="wrap"><button disabled>star</button></span></PopoverTrigger>
          <PopoverContent>panel body</PopoverContent>
        </Popover>`)
      await settle()

      await wrapper.find('[data-test="wrap"]').trigger('click')
      await settle()

      // The disabled button has pointer-events: none, so the wrapper receives
      // the click. If this regresses, the guest sign-in prompt stops working.
      expect(content()).not.toBeNull()
    })

    it('flips aria-expanded on the trigger', async () => {
      wrapper = mountFixture(`
        <Popover>
          <PopoverTrigger data-test="trigger">open</PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      expect(wrapper.find('[data-test="trigger"]').attributes('aria-expanded')).toBe('false')

      await wrapper.find('[data-test="trigger"]').trigger('click')
      await settle()

      expect(wrapper.find('[data-test="trigger"]').attributes('aria-expanded')).toBe('true')
    })

    it('can start open via defaultOpen', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverTrigger data-test="trigger">open</PopoverTrigger>
          <PopoverContent>already here</PopoverContent>
        </Popover>`)
      await settle()

      expect(content()?.textContent).toContain('already here')
    })
  })

  describe('PopoverContent', () => {
    it('carries the shadcn animation and placement classes', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverTrigger>open</PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      const className = content()?.getAttribute('class') ?? ''

      // tw-animate-css is imported in style.css, so these resolve.
      expect(className).toContain('animate-in')
      expect(className).toContain('fade-in-0')
      expect(className).toContain('zoom-in-95')
      expect(className).toContain('slide-in-from-top-2')
      expect(className).toContain('origin-[var(--reka-popover-content-transform-origin)]')
      expect(className).toContain('bg-popover')
      expect(className).toContain('text-popover-foreground')
    })

    it('opens below the trigger by default', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverTrigger>open</PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      expect(content()?.getAttribute('data-side')).toBe('bottom')
    })

    it('lets the caller override side and align', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverTrigger>open</PopoverTrigger>
          <PopoverContent side="right" align="start">body</PopoverContent>
        </Popover>`)
      await settle()

      expect(content()?.getAttribute('data-side')).toBe('right')
      expect(content()?.getAttribute('data-align')).toBe('start')
    })

    it('merges a caller class instead of replacing the base', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverTrigger>open</PopoverTrigger>
          <PopoverContent class="w-96 border-destructive">body</PopoverContent>
        </Popover>`)
      await settle()

      const className = content()?.getAttribute('class') ?? ''

      expect(className).toContain('w-96')
      expect(className).toContain('border-destructive')
      // tailwind-merge drops the losing w-72 rather than shipping both.
      expect(className).not.toContain('w-72')
      expect(className).toContain('bg-popover')
    })
  })

  describe('PopoverAnchor', () => {
    it('renders as a positioned anchor inside the root', async () => {
      wrapper = mountFixture(`
        <Popover :default-open="true">
          <PopoverAnchor data-test="anchor">here</PopoverAnchor>
          <PopoverTrigger>open</PopoverTrigger>
          <PopoverContent>body</PopoverContent>
        </Popover>`)
      await settle()

      const anchor = wrapper.find('[data-test="anchor"]')

      expect(anchor.exists()).toBe(true)
      expect(anchor.attributes('data-slot')).toBe('popover-anchor')
    })
  })
})
