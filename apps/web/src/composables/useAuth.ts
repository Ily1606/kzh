import { useAuthStore } from '@/stores';
import { api } from '@/services/api';
import type { User } from '@/types';
import { API_ENDPOINTS } from '@/utils/constants';

export function useAuth() {
  const store = useAuthStore();

  async function login(credentials: Record<string, string>) {
    try {
      await api.get(API_ENDPOINTS.GET_COOKIE, {
        headers: { Accept: 'application/json' }
      });

      await api.post(API_ENDPOINTS.LOGIN, credentials);
      await fetchUser();
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function register(data: Record<string, string>) {
    try {
      await api.get(API_ENDPOINTS.GET_COOKIE, {
        headers: { Accept: 'application/json' }
      });

      await api.post(API_ENDPOINTS.REGISTER, data);
      await fetchUser();
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function logout() {
    try {
      await api.post(API_ENDPOINTS.LOGOUT, {});
    } finally {
      store.clearAuth();
    }
  }

  async function fetchUser() {
    store.setLoading(true);
    try {
      const { data } = await api.get<{ data: User }>(API_ENDPOINTS.USER);
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
    register,
    logout,
    fetchUser
  };
}

