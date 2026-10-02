import { computed, reactive } from 'vue';
import { useAuthStore } from '@/stores';
import { getAuth, getProfile } from '@/api/generated/endpoints';
import type { LoginRequest, RegisterRequest } from '@/api/generated/model';

export function useAuth() {
  const store = useAuthStore();
  const { authLogin, authRegister, authLogout } = getAuth();
  const { profileShow } = getProfile();

  async function login(credentials: LoginRequest) {
    try {
      const response = await authLogin(credentials);
      store.setToken(response.data?.token || null);
      store.setUser(response.data?.user || null);
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function register(data: RegisterRequest) {
    try {
      const response = await authRegister(data);
      store.setToken(response.data?.token || null);
      store.setUser(response.data?.user || null);
      return true;
    } catch (e) {
      throw e;
    }
  }

  async function logout() {
    try {
      await authLogout();
    } finally {
      store.clearAuth();
    }
  }

  async function fetchUser() {
    store.setLoading(true);
    try {
      const response = await profileShow();
      store.setUser(response.data || null);
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
