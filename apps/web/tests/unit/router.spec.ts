import { describe, it, expect, vi, beforeEach } from 'vitest';
import { router } from '@/router';
import { useAuth } from '@/composables/useAuth';

vi.mock('@/composables/useAuth', () => ({
  useAuth: vi.fn(),
}));

describe('router/index.ts navigation guards', () => {
  beforeEach(async () => {
    vi.clearAllMocks();
  });

  it('redirects to login with query.redirect when unauthenticated user accesses requiresAuth route', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
    });

    await router.push('/publish');

    expect(router.currentRoute.value.name).toBe('login');
    expect(router.currentRoute.value.query).toEqual({ redirect: '/publish' });
  });

  it('redirects to login with query.redirect for /profile when unauthenticated', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
    });

    await router.push('/profile');

    expect(router.currentRoute.value.name).toBe('login');
    expect(router.currentRoute.value.query).toEqual({ redirect: '/profile' });
  });

  it('redirects to home when authenticated user accesses guestOnly route', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
    });

    await router.push('/login');

    expect(router.currentRoute.value.name).toBe('home');
  });

  it('allows access to requiresAuth route when authenticated', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: true,
    });

    await router.push('/publish');

    expect(router.currentRoute.value.name).toBe('publish');
  });

  it('allows access to public route when unauthenticated', async () => {
    ;(useAuth as any).mockReturnValue({
      isAuthenticated: false,
    });

    await router.push('/plugins');

    expect(router.currentRoute.value.name).toBe('plugins');
  });
});

