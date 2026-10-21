import { router } from '@/router';
import { clearSession } from '@/stores';
import axios from 'axios';

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
        clearSession();

        if (router.currentRoute?.value?.path !== '/login') {
          const currentPath = router.currentRoute?.value?.fullPath;
          router.push(
            currentPath && currentPath !== '/'
              ? { name: 'login', query: { redirect: currentPath } }
              : '/login'
          ).catch(() => {});
        }
      }
    }
    return Promise.reject(error);
  }
);

