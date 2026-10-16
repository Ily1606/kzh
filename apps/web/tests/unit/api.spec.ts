import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api } from '@/services/api';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth.store';
import { usePluginsStore } from '@/stores/plugins.store';

describe('services/api interceptors', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('adds Authorization header when auth_token exists in localStorage', async () => {
    localStorage.setItem('auth_token', 'my-token');
    const requestHandler = (api.interceptors.request as any).handlers[0].fulfilled;
    const config = { headers: {} as any };
    const result = requestHandler(config);

    expect(result.headers.Authorization).toBe('Bearer my-token');
  });

  it('does not add Authorization header when auth_token is not in localStorage', async () => {
    const requestHandler = (api.interceptors.request as any).handlers[0].fulfilled;
    const config = { headers: {} as any };
    const result = requestHandler(config);

    expect(result.headers.Authorization).toBeUndefined();
  });

  it('clears auth and resets plugins store on 401 error for non-login endpoints', async () => {
    const store = useAuthStore();
    const pluginsStore = usePluginsStore();
    store.setToken('valid-token');
    store.setUser({ id: '1', name: 'User' } as any);
    pluginsStore.put([{ id: 'plugin-1', name: 'test' } as any]);

    const responseErrorHandler = (api.interceptors.response as any).handlers[0].rejected;
    const error = {
      response: { status: 401 },
      config: { url: '/v1/user/profile' },
    };

    await expect(responseErrorHandler(error)).rejects.toEqual(error);

    expect(store.token).toBeNull();
    expect(store.user).toBeNull();
    expect(pluginsStore.entities).toEqual({});
  });

  it('does not clear auth on 401 error from /v1/login', async () => {
    const store = useAuthStore();
    store.setToken('existing-token');

    const responseErrorHandler = (api.interceptors.response as any).handlers[0].rejected;
    const error = {
      response: { status: 401 },
      config: { url: '/v1/login' },
    };

    await expect(responseErrorHandler(error)).rejects.toEqual(error);

    expect(store.token).toBe('existing-token');
  });

  it('does not clear auth on non-401 errors', async () => {
    const store = useAuthStore();
    store.setToken('existing-token');

    const responseErrorHandler = (api.interceptors.response as any).handlers[0].rejected;
    const error = {
      response: { status: 500 },
      config: { url: '/v1/plugins' },
    };

    await expect(responseErrorHandler(error)).rejects.toEqual(error);

    expect(store.token).toBe('existing-token');
  });
});

