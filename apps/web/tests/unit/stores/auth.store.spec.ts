import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth.store';
import type { User } from '@/types';

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('initializes with correct default state', () => {
    const store = useAuthStore();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(store.isLoading).toBe(true); // Assuming true by default for initial check
  });

  it('updates state correctly when setUser is called', () => {
    const store = useAuthStore();
    const mockUser: User = {
      id: 'uuid',
      name: 'John Doe',
      email: 'john@example.com',
      isActive: true,
      is_admin: false
    };

    store.setUser(mockUser);

    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
  });

  it('clears state correctly when clearAuth is called', () => {
    const store = useAuthStore();
    const mockUser: User = {
      id: 'uuid',
      name: 'John Doe',
      email: 'john@example.com',
      isActive: true,
      is_admin: false
    };

    // Setup initial state
    store.setUser(mockUser);
    expect(store.isAuthenticated).toBe(true);

    // Clear auth
    store.clearAuth();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
  });

  it('updates loading state when setLoading is called', () => {
    const store = useAuthStore();

    store.setLoading(false);
    expect(store.isLoading).toBe(false);

    store.setLoading(true);
    expect(store.isLoading).toBe(true);
  });
});

