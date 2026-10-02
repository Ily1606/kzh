import { describe, it, expect, beforeEach } from 'vitest';
import { createApp, nextTick } from 'vue';
import { setActivePinia, createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import { useAuthStore } from '@/stores/auth.store';
import type { User } from '@/types';

describe('Auth Store', () => {
  beforeEach(() => {
    const pinia = createPinia();
    pinia.use(piniaPluginPersistedstate);
    createApp({}).use(pinia);
    setActivePinia(pinia);
    localStorage.clear();
  });

  it('initializes with correct default state', () => {
    const store = useAuthStore();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(store.isLoading).toBe(true); // Assuming true by default for initial check
  });

  it('updates state correctly when setUser is called', () => {
    const store = useAuthStore();
    const mockUser: User = {
      id: 'uuid',
      name: 'John Doe',
      email: 'john@example.com',
      isActive: true,
      is_admin: false
    };

    store.setUser(mockUser);

    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
  });

  it('restores the user from localStorage', async () => {
    const firstStore = useAuthStore();
    const mockUser: User = {
      id: 'uuid',
      name: 'John Doe',
      email: 'john@example.com',
      isActive: true,
      is_admin: false
    };

    firstStore.setUser(mockUser);
    await nextTick();
    expect(localStorage.getItem('auth')).toBe(JSON.stringify({ user: mockUser }));

    const reloadedPinia = createPinia();
    reloadedPinia.use(piniaPluginPersistedstate);
    createApp({}).use(reloadedPinia);
    setActivePinia(reloadedPinia);

    expect(useAuthStore().user).toEqual(mockUser);
  });

  it('clears state correctly when clearAuth is called', () => {
    const store = useAuthStore();
    const mockUser: User = {
      id: 'uuid',
      name: 'John Doe',
      email: 'john@example.com',
      isActive: true,
      is_admin: false
    };

    // Setup initial state
    store.setUser(mockUser);
    expect(store.isAuthenticated).toBe(true);

    store.clearAuth();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(localStorage.getItem('auth')).toBeNull();
  });

  it('updates loading state when setLoading is called', () => {
    const store = useAuthStore();

    store.setLoading(false);
    expect(store.isLoading).toBe(false);

    store.setLoading(true);
    expect(store.isLoading).toBe(true);
  });
});

