import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/types';

export const AUTH_STORAGE_KEY = 'auth';

const authStorage = {
  getItem: (key: string) => localStorage.getItem(key),
  setItem: (key: string, value: string) => {
    const persistedState = JSON.parse(value) as { user?: User | null };

    if (persistedState.user === null) {
      localStorage.removeItem(key);
      return;
    }

    localStorage.setItem(key, value);
  }
};

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const isAuthenticated = computed(() => !!user.value);
  const isLoading = ref(true); // For initial load

  function setUser(newUser: User | null) {
    user.value = newUser;
  }

  function clearAuth() {
    user.value = null;
    localStorage.removeItem(AUTH_STORAGE_KEY);
  }

  function setLoading(status: boolean) {
    isLoading.value = status;
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    setUser,
    clearAuth,
    setLoading
  };
}, {
  persist: {
    key: AUTH_STORAGE_KEY,
    storage: authStorage,
    pick: ['user']
  }
});

