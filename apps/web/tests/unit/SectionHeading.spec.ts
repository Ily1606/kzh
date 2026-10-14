import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import SectionHeading from '@/components/common/SectionHeading.vue';

describe('SectionHeading', () => {
  it('renders the eyebrow, title and description', () => {
    const wrapper = mount(SectionHeading, {
      props: { eyebrow: 'Trending plugins', title: 'Build on it.', description: 'Discover.' },
    });

    expect(wrapper.text()).toContain('Trending plugins');
    expect(wrapper.text()).toContain('Build on it.');
    expect(wrapper.text()).toContain('Discover.');
    expect(wrapper.find('h2').exists()).toBe(true);
  });

  it('leaves out the eyebrow when none is given', () => {
    const wrapper = mount(SectionHeading, { props: { title: 'Only a title' } });

    expect(wrapper.text()).toContain('Only a title');
    expect(wrapper.find('p').exists()).toBe(false);
  });

  it('prefers the title slot over the prop', () => {
    const wrapper = mount(SectionHeading, {
      props: { title: 'ignored' },
      slots: { title: 'slotted' },
    });

    expect(wrapper.text()).toContain('slotted');
    expect(wrapper.text()).not.toContain('ignored');
  });
});
