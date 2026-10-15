import axios from 'axios';
import { getActivePinia } from 'pinia';
import { useAuthStore } from '@/stores/auth.store';
import { usePluginsStore } from '@/stores/plugins.store';
import { router } from '@/router';

export const api = axios.create({
  baseURL: (import.meta.env.VITE_API_URL || 'http://localhost:8000') + '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const isLoginRequest = error.config?.url?.includes('/v1/login');
      if (!isLoginRequest) {
        if (getActivePinia()) {
          const authStore = useAuthStore();
          authStore.clearAuth();
          const pluginsStore = usePluginsStore();
          pluginsStore.reset();
        } else {
          localStorage.removeItem('auth');
          localStorage.removeItem('auth_token');
        }

        const currentRoute = router.currentRoute?.value;
        if (currentRoute?.meta?.requiresAuth && currentRoute?.path !== '/login') {
          router.push({
            name: 'login',
            query: { redirect: currentRoute?.fullPath || currentRoute?.path },
          });
        }
      }
    }
    return Promise.reject(error);
  }
);

