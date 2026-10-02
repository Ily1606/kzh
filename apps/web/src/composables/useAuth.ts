import { computed, reactive } from 'vue';
import { useAuthStore } from '@/stores';
import { api } from '@/services/api';
import type { User } from '@/types';
import { API_ENDPOINTS } from '@/utils/constants';

export function useAuth() {
  const store = useAuthStore();

  async function login(credentials: Record<string, string>) {
    try {
      const response = await api.post<{ data: { user: User, token: string } }>(API_ENDPOINTS.LOGIN, credentials);
      store.setToken(response.data.data.token);
      store.setUser(response.data.data.user);
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function register(data: Record<string, string>) {
    try {
      const response = await api.post<{ data: { user: User, token: string } }>(API_ENDPOINTS.REGISTER, data);
      store.setToken(response.data.data.token);
      store.setUser(response.data.data.user);
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
      const response = await api.get<{ data: User }>(API_ENDPOINTS.USER);
      store.setUser(response.data.data);
    } catch (e) {
      store.clearAuth();
    } finally {
      store.setLoading(false);
    }
  }

  return reactive({
    user: computed(() => store.user),
    isAuthenticated: computed(() => store.isAuthenticated),
    isLoading: computed(() => store.isLoading),

    login,
    register,
    logout,
    fetchUser
  });
}
