// tests/setup.ts
import { vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

// Views and forms reach for a store during setup, so every test needs an active
// pinia. Specs that care about their own store state build their own.
setActivePinia(createPinia());

// Mock matchMedia for jsdom if needed for some UI components
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(), // deprecated
    removeListener: vi.fn(), // deprecated
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// jsdom implements no pointer capture, and reka-ui calls it on every pointerdown
// to tell a drag apart from a click. Without these the trigger throws and the
// dropdown never opens.
Element.prototype.hasPointerCapture ??= () => false;
Element.prototype.setPointerCapture ??= () => {};
Element.prototype.releasePointerCapture ??= () => {};

// jsdom has no IntersectionObserver, and motion-v's whileInView constructs one
// on mount. Reporting every element as already in view keeps reveal animations
// in their finished state instead of throwing.
class ImmediateIntersectionObserver {
  constructor(private callback: IntersectionObserverCallback) {}
  observe(target: Element) {
    this.callback(
      [{ target, isIntersecting: true, intersectionRatio: 1 } as IntersectionObserverEntry],
      this as unknown as IntersectionObserver,
    );
  }
  unobserve() {}
  disconnect() {}
  takeRecords(): IntersectionObserverEntry[] {
    return [];
  }
  root = null;
  rootMargin = '';
  thresholds: number[] = [];
}
window.IntersectionObserver ??=
  ImmediateIntersectionObserver as unknown as typeof IntersectionObserver;
