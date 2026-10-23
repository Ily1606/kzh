import { describe, it, expect, vi, beforeEach } from 'vitest';
import { router } from '@/router';
import { useAuthStore } from '@/stores/auth.store';

describe('router/index.ts navigation guards', () => {
  beforeEach(async () => {
    vi.clearAllMocks();
    const authStore = useAuthStore();
    authStore.clearAuth();
  });

  it('redirects to login with query.redirect when unauthenticated user accesses requiresAuth route', async () => {
    const authStore = useAuthStore();
    authStore.clearAuth();

    await router.push('/publish');

    expect(router.currentRoute.value.name).toBe('login');
    expect(router.currentRoute.value.query).toEqual({ redirect: '/publish' });
  });

  it('redirects to login with query.redirect for /settings when unauthenticated', async () => {
    const authStore = useAuthStore();
    authStore.clearAuth();

    await router.push('/settings');

    expect(router.currentRoute.value.name).toBe('login');
    expect(router.currentRoute.value.query).toEqual({ redirect: '/settings' });
  });

  it('redirects to home when authenticated user accesses guestOnly route', async () => {
    const authStore = useAuthStore();
    authStore.setUser({ id: 'u1', name: 'User' } as any);
    authStore.setToken('valid-token');

    await router.push('/login');

    expect(router.currentRoute.value.name).toBe('home');
  });

  it('allows access to requiresAuth route when authenticated', async () => {
    const authStore = useAuthStore();
    authStore.setUser({ id: 'u1', name: 'User' } as any);
    authStore.setToken('valid-token');

    await router.push('/publish');

    expect(router.currentRoute.value.name).toBe('publish');
  });

  it('allows access to public route when unauthenticated', async () => {
    const authStore = useAuthStore();
    authStore.clearAuth();

    await router.push('/plugins');

    expect(router.currentRoute.value.name).toBe('plugins');
  });
});

