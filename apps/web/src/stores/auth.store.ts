import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/types';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const isAuthenticated = computed(() => !!user.value);
  const isLoading = ref(true); // For initial load

  function setUser(newUser: User | null) {
    user.value = newUser;
  }

  function clearAuth() {
    user.value = null;
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
});

