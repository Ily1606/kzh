import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import { nextTick } from 'vue';
import Reveal from '@/components/common/Reveal.vue';

const settle = () => new Promise(r => setTimeout(r, 900));

describe('Reveal', () => {
  it('renders its slot', () => {
    const wrapper = mount(Reveal, { slots: { default: '<p>hello</p>' } });

    expect(wrapper.text()).toBe('hello');
  });

  it('starts hidden and animates to full opacity once in view', async () => {
    const wrapper = mount(Reveal, { slots: { default: 'content' } });
    const start = wrapper.html();

    await nextTick();
    await settle();
    await nextTick();

    expect(start).toContain('opacity: 0');
    expect(wrapper.html()).toContain('opacity: 1');
  });

  it('renders the requested tag', () => {
    const wrapper = mount(Reveal, { props: { as: 'section' }, slots: { default: 'x' } });

    expect(wrapper.element.tagName).toBe('SECTION');
  });
});
