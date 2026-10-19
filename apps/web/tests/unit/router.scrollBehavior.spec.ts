import { describe, it, expect, beforeEach } from 'vitest';
import { router } from '@/router';
import { takePendingScroll } from '@/utils/pageScroll';

const behavior = router.options.scrollBehavior!;

const route = (path: string) => ({ path }) as any;

describe('router scrollBehavior', () => {
  beforeEach(() => {
    // Drop any position left over from a previous navigation.
    takePendingScroll();
  });

  it('scrolls itself when the path is unchanged, since no transition runs', () => {
    const savedPosition = { left: 0, top: 320 };

    expect(behavior(route('/resources'), route('/resources'), savedPosition)).toEqual(savedPosition);
  });

  it('defers to the page transition when the path changes', () => {
    const savedPosition = { left: 0, top: 320 };

    expect(behavior(route('/plugins'), route('/resources'), savedPosition)).toBe(false);
    expect(takePendingScroll()).toEqual(savedPosition);
  });

  it('defers to the page transition on a fresh navigation with no saved position', () => {
    expect(behavior(route('/plugins'), route('/resources'), null)).toBe(false);
    expect(takePendingScroll()).toBeNull();
  });
});