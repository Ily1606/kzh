import { describe, it, expect, beforeEach } from 'vitest';
import { createApp, nextTick } from 'vue';
import { setActivePinia, createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import { useAuthStore } from '@/stores/auth.store';
import type { UserResource } from '@/api/generated/model';

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
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      avatarLink: '',
      githubName: null,
      githubLink: null,
      is_admin: false,
      created_at: null,
    } as UserResource;

    store.setUser(mockUser);
    store.setToken('mock-token');

    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
  });

  it('restores the user from localStorage', async () => {
    const firstStore = useAuthStore();
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      avatarLink: '',
      githubName: null,
      githubLink: null,
      is_admin: false,
      created_at: null,
    } as UserResource;

    firstStore.setUser(mockUser);
    firstStore.setToken('mock-token');
    await nextTick();
    expect(localStorage.getItem('auth')).toContain('"token":"mock-token"');

    const reloadedPinia = createPinia();
    reloadedPinia.use(piniaPluginPersistedstate);
    createApp({}).use(reloadedPinia);
    setActivePinia(reloadedPinia);

    expect(useAuthStore().user).toEqual(mockUser);
  });

  it('clears state correctly when clearAuth is called', () => {
    const store = useAuthStore();
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      avatarLink: '',
      githubName: null,
      githubLink: null,
      is_admin: false,
      created_at: null,
    } as UserResource;

    // Setup initial state
    store.setUser(mockUser);
    store.setToken('mock-token');
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

  it('setToken correctly manages auth_token in localStorage', () => {
    const store = useAuthStore();

    // Set a token
    store.setToken('new-fake-token');
    expect(store.token).toBe('new-fake-token');
    expect(localStorage.getItem('auth_token')).toBe('new-fake-token');

    // Remove the token
    store.setToken(null);
    expect(store.token).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  it('removes auth data from localStorage when user is set to null (custom storage logic)', async () => {
    const store = useAuthStore();
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      avatarLink: '',
      githubName: null,
      githubLink: null,
      is_admin: false,
      created_at: null,
    } as UserResource;

    // Set user and wait for pinia persistedstate to write to localStorage
    store.setUser(mockUser);
    await nextTick();
    expect(localStorage.getItem('auth')).not.toBeNull();

    // Now set user to null
    store.setUser(null);
    await nextTick();

    // The custom authStorage.setItem should have called localStorage.removeItem
    expect(localStorage.getItem('auth')).toBeNull();
  });
});

