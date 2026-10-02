import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { UserResource } from '@/api/generated/model';

export const AUTH_STORAGE_KEY = 'auth';

const authStorage = {
  getItem: (key: string) => localStorage.getItem(key),
  setItem: (key: string, value: string) => {
    const persistedState = JSON.parse(value) as { user?: UserResource | null };

    if (persistedState.user === null) {
      localStorage.removeItem(key);
      return;
    }

    localStorage.setItem(key, value);
  }
};

export const useAuthStore = defineStore('auth', () => {
  const user = ref<UserResource | null>(null);
  const token = ref<string | null>(null);
  const isAuthenticated = computed(() => !!user.value && !!token.value);
  const isLoading = ref(true); // For initial load

  function setUser(newUser: UserResource | null) {
    user.value = newUser;
  }

  function setToken(newToken: string | null) {
    token.value = newToken;
    if (newToken) {
      localStorage.setItem('auth_token', newToken);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  function clearAuth() {
    user.value = null;
    token.value = null;
    localStorage.removeItem(AUTH_STORAGE_KEY);
    localStorage.removeItem('auth_token');
  }

  function setLoading(status: boolean) {
    isLoading.value = status;
  }

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    setUser,
    setToken,
    clearAuth,
    setLoading
  };
}, {
  persist: {
    key: AUTH_STORAGE_KEY,
    storage: authStorage,
    pick: ['user', 'token']
  }
});

