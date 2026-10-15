import { computed, reactive } from 'vue';
import { useAuthStore, usePluginsStore } from '@/stores';
import { getAuth, getProfile } from '@/api/generated/endpoints';
import type { LoginRequest, RegisterRequest } from '@/api/generated/model';

export function useAuth() {
  const store = useAuthStore();
  const pluginsStore = usePluginsStore();
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
    } catch {
      // Swallowed: client-side session is cleared in finally,
      // so notifying the user or breaking the flow is unnecessary.
    } finally {
      store.clearAuth();
      // The plugin cache holds `is_star`, which belongs to whoever is signed in.
      // Clearing the session has to clear it too, or the next user on this
      // browser inherits the previous user's stars.
      pluginsStore.reset();
    }
  }

  async function fetchUser() {
    if (!store.token) {
      store.setLoading(false);
      return;
    }
    store.setLoading(true);
    try {
      const response = await profileShow();
      store.setUser(response.data || null);
    } catch (e: any) {
      if (e?.response?.status === 401 || e?.status === 401) {
        store.clearAuth();
        pluginsStore.reset();
      }
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
