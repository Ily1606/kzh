import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAuth } from '@/composables/useAuth';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore, usePluginsStore } from '@/stores';

// Mock api/generated/endpoints
const mockAuthLogin = vi.fn();
const mockAuthRegister = vi.fn();
const mockAuthLogout = vi.fn();
const mockProfileShow = vi.fn();

vi.mock('@/api/generated/endpoints', () => ({
  getAuth: () => ({
    authLogin: mockAuthLogin,
    authRegister: mockAuthRegister,
    authLogout: mockAuthLogout,
  }),
  getProfile: () => ({
    profileShow: mockProfileShow,
  }),
  getPlugin: () => ({
    pluginIndex: vi.fn(),
    pluginTrending: vi.fn(),
    pluginMyPlugins: vi.fn(),
    pluginShow: vi.fn(),
  }),
  getStar: () => ({ starStore: vi.fn() })
}));

describe('useAuth composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('successful login saves information to store', async () => {
    const auth = useAuth();
    const store = useAuthStore();

    mockAuthLogin.mockResolvedValueOnce({
      data: { token: 'fake-token', user: { id: '1', name: 'John Doe' } }
    });

    const result = await auth.login({ email: 'test@example.com', password: 'password123' });

    expect(result).toBe(true);
    expect(mockAuthLogin).toHaveBeenCalledWith({ email: 'test@example.com', password: 'password123' });
    expect(store.token).toBe('fake-token');
    expect(store.user?.name).toBe('John Doe');
  });

  it('failed login throws an error', async () => {
    const auth = useAuth();

    mockAuthLogin.mockRejectedValueOnce(new Error('Invalid credentials'));

    await expect(auth.login({ email: 'test@example.com', password: 'wrong' }))
      .rejects.toThrow('Invalid credentials');
  });

  it('logout calls API and clears auth store', async () => {
    const auth = useAuth();
    const store = useAuthStore();

    // Assume user is logged in
    store.setToken('old-token');
    store.setUser({ id: '1', name: 'User', email: 'test@example.com' } as any);

    mockAuthLogout.mockResolvedValueOnce({});

    await auth.logout();

    expect(mockAuthLogout).toHaveBeenCalled();
    expect(store.token).toBeNull();
    expect(store.user).toBeNull();
  });

  it('logout empties the plugin cache', async () => {
    const auth = useAuth();
    const authStore = useAuthStore();
    const pluginsStore = usePluginsStore();

    authStore.setToken('old-token');
    authStore.setUser({ id: '1', name: 'User', email: 'test@example.com' } as any);
    pluginsStore.put([{
      id: 'plugin-1', name: '@dsh/x', user_id: '1', author: null, title: 't', license: 'MIT',
      approved_at: null, status: 'approved', source_link: 'https://example.com', star_count: 3,
      is_star: true, comment_count: 0, view_count: 0, created_at: null, updated_at: null,
    }]);
    pluginsStore.list = { ids: ['plugin-1'], page: 1, total: 1, lastPage: 1 };

    mockAuthLogout.mockResolvedValueOnce({});

    await auth.logout();

    // The cache holds `is_star`, which belongs to whoever was signed in. Left
    // behind, the next user on this browser would see the previous user's stars.
    expect(pluginsStore.entities).toEqual({});
    expect(pluginsStore.list.ids).toEqual([]);
  });

  it('empties the plugin cache even when the logout request fails', async () => {
    const auth = useAuth();
    const authStore = useAuthStore();
    const pluginsStore = usePluginsStore();

    authStore.setToken('old-token');
    pluginsStore.put([{
      id: 'plugin-1', name: '@dsh/x', user_id: '1', author: null, title: 't', license: 'MIT',
      approved_at: null, status: 'approved', source_link: 'https://example.com', star_count: 0,
      comment_count: 0, view_count: 0, created_at: null, updated_at: null,
    }]);

    mockAuthLogout.mockRejectedValueOnce(new Error('network down'));

    await expect(auth.logout()).rejects.toThrow('network down');

    expect(pluginsStore.entities).toEqual({});
  });
});
