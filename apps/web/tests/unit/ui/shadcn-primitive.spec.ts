import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import { h } from 'vue'
import { Alert } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'

/**
 * The primitives carry no logic of their own — the point of these assertions is
 * to pin the contract the views rely on: the `data-slot` they query on, and the
 * `cn` merging that lets a caller override a base class without shipping both.
 */

describe('ui/alert', () => {
  it('exposes data-slot and the default variant', () => {
    const wrapper = mount(Alert, { slots: { default: 'Careful' } })

    expect(wrapper.attributes('data-slot')).toBe('alert')
    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.classes()).toContain('bg-card')
    expect(wrapper.text()).toBe('Careful')
  })

  it('applies the destructive variant in place of the default one', () => {
    const wrapper = mount(Alert, { props: { variant: 'destructive' } })

    expect(wrapper.classes()).toContain('text-destructive')
    expect(wrapper.classes()).toContain('border-destructive/50')
    // Destructive recolours the card rather than replacing its background, so
    // the class the default variant contributes is gone, not the background.
    expect(wrapper.classes()).not.toContain('text-card-foreground')
  })

  it('lets a caller class override a base one', () => {
    const wrapper = mount(Alert, { props: { class: 'bg-primary' } })

    expect(wrapper.classes()).toContain('bg-primary')
    expect(wrapper.classes()).not.toContain('bg-card')
  })
})

describe('ui/button', () => {
  it('exposes data-slot and the default variant', () => {
    const wrapper = mount(Button, { slots: { default: 'Click' } })

    expect(wrapper.attributes('data-slot')).toBe('button')
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.classes()).toContain('bg-primary')
  })

  it('carries pointer-events-none when disabled, which is why a disabled button cannot be a popover trigger', () => {
    const wrapper = mount(Button, { props: { disabled: true } })

    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.classes()).toContain('disabled:pointer-events-none')
  })

  it('renders another tag through as', () => {
    const wrapper = mount(Button, { props: { as: 'a' }, slots: { default: 'Link' } })

    expect(wrapper.element.tagName).toBe('A')
  })

  it('supports the asChild slot pattern', () => {
    const wrapper = mount(Button, {
      props: { asChild: true },
      slots: { default: '<span data-test="inner">Text</span>' },
    })

    expect(wrapper.find('[data-test="inner"]').exists()).toBe(true)
  })

  it('applies the outline and size variants', () => {
    const wrapper = mount(Button, { props: { variant: 'outline', size: 'sm' } })

    expect(wrapper.classes()).toContain('border')
    expect(wrapper.classes()).toContain('h-8')
  })
})

describe('ui/card', () => {
  it('exposes data-slot on the root and on CardContent', () => {
    const wrapper = mount(Card, { slots: { default: () => h(CardContent, 'inner') } })

    expect(wrapper.attributes('data-slot')).toBe('card')
    expect(wrapper.find('[data-slot="card-content"]').exists()).toBe(true)
  })

  it('renders slots', () => {
    const wrapper = mount(Card, { slots: { default: 'Body' } })

    expect(wrapper.text()).toBe('Body')
  })
})

describe('ui/input', () => {
  it('exposes data-slot and is an input', () => {
    const wrapper = mount(Input)

    expect(wrapper.attributes('data-slot')).toBe('input')
    expect(wrapper.element.tagName).toBe('INPUT')
  })

  it('is two-way bound through defineModel', async () => {
    const wrapper = mount(Input, { props: { modelValue: '' } })

    await wrapper.setValue('dsr-webhook')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['dsr-webhook'])
  })

  it('forwards attrs to the underlying input', () => {
    const wrapper = mount(Input, { attrs: { placeholder: 'Filter plugins by name...', type: 'text' } })

    expect(wrapper.attributes('placeholder')).toBe('Filter plugins by name...')
    expect(wrapper.attributes('type')).toBe('text')
  })
})

describe('ui/label', () => {
  it('exposes data-slot and renders a label', () => {
    const wrapper = mount(Label, { slots: { default: 'Email' } })

    expect(wrapper.attributes('data-slot')).toBe('label')
    expect(wrapper.element.tagName).toBe('LABEL')
    expect(wrapper.text()).toBe('Email')
  })
})

describe('ui/spinner', () => {
  it('is an aria-hidden svg', () => {
    const wrapper = mount(Spinner)

    expect(wrapper.element.tagName.toLowerCase()).toBe('svg')
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.classes()).toContain('animate-spin')
  })
})
