import { useAuthStore } from './auth.store';
import { usePluginsStore } from './plugins';

/**
 * Clears the user's client session across all relevant stores.
 *
 * Removes authentication credentials and state, and empties user-scoped caches
 * (such as plugins with `is_star` state).
 */
export function clearSession(): void {
  const authStore = useAuthStore();
  const pluginsStore = usePluginsStore();

  authStore.clearAuth();
  pluginsStore.reset();
}

