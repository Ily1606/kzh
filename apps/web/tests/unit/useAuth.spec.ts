import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAuth } from '@/composables/useAuth';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores';

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
  })
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
});
