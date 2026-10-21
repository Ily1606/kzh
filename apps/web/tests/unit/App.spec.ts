import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref, reactive } from 'vue';
import App from '@/App.vue';

const mockPush = vi.fn();
const mockReplace = vi.fn();
const mockRoute = reactive({
  path: '/',
  fullPath: '/',
  name: 'home',
  meta: {} as Record<string, any>,
});

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  RouterView: {
    template: '<div><slot :Component="{}" /></div>'
  }
}));

const mockFetchUser = vi.fn();
const mockIsAuthenticated = ref(true);

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => reactive({
    fetchUser: mockFetchUser,
    isAuthenticated: mockIsAuthenticated,
  })
}));

describe('App.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRoute.path = '/';
    mockRoute.fullPath = '/';
    mockRoute.name = 'home';
    mockRoute.meta = {};
    mockIsAuthenticated.value = true;
  });

  it('calls auth.fetchUser on mount', () => {
    mount(App, {
      global: {
        stubs: {
          AuthLayout: true,
          DefaultLayout: true,
          Transition: false,
        }
      }
    });

    expect(mockFetchUser).toHaveBeenCalled();
  });

  it('triggers router.replace with fullPath when user becomes unauthenticated on a requiresAuth route', async () => {
    mockRoute.path = '/resources/5/edit';
    mockRoute.fullPath = '/resources/5/edit';
    mockRoute.name = 'plugin-edit';
    mockRoute.meta = { requiresAuth: true };

    mount(App, {
      global: {
        stubs: {
          AuthLayout: true,
          DefaultLayout: true,
          Transition: false,
        }
      }
    });

    mockIsAuthenticated.value = false;
    await new Promise(r => setTimeout(r, 10));

    expect(mockReplace).toHaveBeenCalledWith('/resources/5/edit');
  });

  it('does not redirect when user becomes unauthenticated on a public route', async () => {
    mockRoute.path = '/plugins';
    mockRoute.fullPath = '/plugins';
    mockRoute.name = 'plugins';
    mockRoute.meta = {};

    mount(App, {
      global: {
        stubs: {
          AuthLayout: true,
          DefaultLayout: true,
          Transition: false,
        }
      }
    });

    mockIsAuthenticated.value = false;
    await new Promise(r => setTimeout(r, 10));

    expect(mockReplace).not.toHaveBeenCalled();
    expect(mockPush).not.toHaveBeenCalled();
  });

  it('does not redirect if route does not require auth (such as login)', async () => {
    mockRoute.path = '/login';
    mockRoute.fullPath = '/login';
    mockRoute.name = 'login';
    mockRoute.meta = { guestOnly: true };

    mount(App, {
      global: {
        stubs: {
          AuthLayout: true,
          DefaultLayout: true,
          Transition: false,
        }
      }
    });

    mockIsAuthenticated.value = false;
    await new Promise(r => setTimeout(r, 10));

    expect(mockReplace).not.toHaveBeenCalled();
    expect(mockPush).not.toHaveBeenCalled();
  });
});

