import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuth } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth.store';
import { api } from '@/services/api';
import { API_ENDPOINTS } from '@/utils/constants';

// Mock the api service
vi.mock('@/services/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  }
}));

describe('useAuth composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.resetAllMocks();
  });

  it('fetchUser successfully fetches and sets user', async () => {
    const mockUser = { id: '1', name: 'Test User', email: 'test@example.com' };
    vi.mocked(api.get).mockResolvedValueOnce({ data: mockUser });

    const { fetchUser } = useAuth();
    const store = useAuthStore();

    await fetchUser();

    expect(api.get).toHaveBeenCalledWith(API_ENDPOINTS.USER);
    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
    expect(store.isLoading).toBe(false);
  });

  it('fetchUser handles errors and clears auth', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { fetchUser } = useAuth();
    const store = useAuthStore();

    // Set a dummy user first to ensure it gets cleared
    store.setUser({ id: '1', name: 'Old User', email: 'old@example.com' } as any);

    await fetchUser();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(store.isLoading).toBe(false);
  });

  it('login successfully logs in and fetches user', async () => {
    // Mock csrf cookie request
    vi.mocked(api.get).mockResolvedValueOnce({});
    // Mock login post request
    vi.mocked(api.post).mockResolvedValueOnce({});
    // Mock fetchUser get request (API_ENDPOINTS.USER)
    vi.mocked(api.get).mockResolvedValueOnce({ data: { id: '1', name: 'Login User' } });

    const { login } = useAuth();
    const store = useAuthStore();

    const result = await login({ email: 'test@example.com', password: 'password' });

    expect(result).toBe(true);
    expect(api.get).toHaveBeenNthCalledWith(1, '/sanctum/csrf-cookie', expect.any(Object));
    expect(api.post).toHaveBeenCalledWith(API_ENDPOINTS.LOGIN, { email: 'test@example.com', password: 'password' });
    expect(api.get).toHaveBeenNthCalledWith(2, API_ENDPOINTS.USER);

    expect(store.user).toEqual({ id: '1', name: 'Login User' });
  });

  it('login throws error when login fails', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({}); // csrf passes
    const error = new Error('Invalid credentials');
    vi.mocked(api.post).mockRejectedValueOnce(error); // login fails

    const { login } = useAuth();

    await expect(login({ email: 'test@example.com', password: 'wrong' })).rejects.toThrow('Invalid credentials');

    // fetchUser should not be called
    expect(api.get).toHaveBeenCalledTimes(1); // only csrf
  });

  it('register successfully registers and fetches user', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({});
    vi.mocked(api.post).mockResolvedValueOnce({});
    vi.mocked(api.get).mockResolvedValueOnce({ data: { id: '2', name: 'New User' } });

    const { register } = useAuth();
    const store = useAuthStore();

    const data = { name: 'New User', email: 'test@example.com', password: 'password', password_confirmation: 'password' };
    const result = await register(data);

    expect(result).toBe(true);
    expect(api.get).toHaveBeenNthCalledWith(1, '/sanctum/csrf-cookie', expect.any(Object));
    expect(api.post).toHaveBeenCalledWith(API_ENDPOINTS.REGISTER, data);
    expect(api.get).toHaveBeenNthCalledWith(2, API_ENDPOINTS.USER);

    expect(store.user).toEqual({ id: '2', name: 'New User' });
  });

  it('register throws error when register fails', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({});
    const error = new Error('Email already exists');
    vi.mocked(api.post).mockRejectedValueOnce(error);

    const { register } = useAuth();

    await expect(register({ email: 'test@example.com', password: 'password' })).rejects.toThrow('Email already exists');

    expect(api.get).toHaveBeenCalledTimes(1); // only csrf
  });

  it('logout calls api and clears store', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({});

    const { logout } = useAuth();
    const store = useAuthStore();

    // Set initial user
    store.setUser({ id: '1', name: 'User' } as any);

    await logout();

    expect(api.post).toHaveBeenCalledWith(API_ENDPOINTS.LOGOUT, {});
    expect(store.user).toBeNull();
  });
});

