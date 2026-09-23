import { useAuthStore } from '@/stores';
import { api } from '@/services/api';
import type { User } from '@/types';

export function useAuth() {
  const store = useAuthStore();

  async function login(credentials: Record<string, string>) {
    try {
      await api.get('/sanctum/csrf-cookie', {
        headers: { Accept: 'application/json' }
      });

      await api.post('/api/v1/login', credentials);
      await fetchUser();
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function logout() {
    try {
      await api.post('/api/v1/logout', {});
    } finally {
      store.clearAuth();
    }
  }

  async function fetchUser() {
    store.setLoading(true);
    try {
      const { data } = await api.get<{ data: User }>('/api/v1/user');
      store.setUser(data);
    } catch (e) {
      store.clearAuth();
    } finally {
      store.setLoading(false);
    }
  }

  return {
    user: store.user,
    isAuthenticated: store.isAuthenticated,
    isLoading: store.isLoading,

    login,
    logout,
    fetchUser
  };
}

