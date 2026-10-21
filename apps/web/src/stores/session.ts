import { getActivePinia } from 'pinia';
import { AUTH_STORAGE_KEY, useAuthStore } from './auth.store';
import { usePluginsStore } from './plugins';

/**
 * Clears the user's client session across all relevant stores.
 *
 * Removes authentication credentials and state, and empties user-scoped caches
 * (such as plugins with `is_star` state).
 */
export function clearSession(): void {
 if (getActivePinia()) {
    const authStore = useAuthStore();
    const pluginsStore = usePluginsStore();
    authStore.clearAuth();
    pluginsStore.reset();
  } else {
    // Fallback dọn dẹp localStorage khi không có Pinia active
    localStorage.removeItem(AUTH_STORAGE_KEY);
    localStorage.removeItem('auth_token');
  }
}

