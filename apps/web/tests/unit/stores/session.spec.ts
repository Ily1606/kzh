import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore, usePluginsStore, clearSession, AUTH_STORAGE_KEY } from '@/stores';

describe('clearSession', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  it('clears auth store state, localStorage auth items, and resets plugins store', () => {
    const authStore = useAuthStore();
    const pluginsStore = usePluginsStore();

    authStore.setToken('test-token');
    authStore.setUser({ id: '1', name: 'User' } as any);
    pluginsStore.put([{ id: 'plugin-1', name: 'test' } as any]);

    expect(authStore.token).toBe('test-token');
    expect(authStore.user).not.toBeNull();
    expect(pluginsStore.entities['plugin-1']).toBeDefined();

    clearSession();

    expect(authStore.token).toBeNull();
    expect(authStore.user).toBeNull();
    expect(pluginsStore.entities).toEqual({});
    expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
  });
});

