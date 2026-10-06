import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import LoginView from '@/views/LoginView.vue';

// Mock router
const mockPush = vi.fn();
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({ query: {} })
}));

// Mock composable useAuth
const mockLogin = vi.fn();
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: mockLogin })
}));

describe('LoginView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders basic input fields correctly', () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
    expect(wrapper.find('input[type="password"]').exists()).toBe(true);
    expect(wrapper.find('button[type="submit"]').text()).toContain('Sign in');
  });

  it('displays frontend validation errors when submitting empty or invalid data', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    // Submit empty form
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Invalid email address');
    expect(wrapper.text()).toContain('Password must be at least 8 characters');
    expect(mockLogin).not.toHaveBeenCalled();

    // Submit invalid email and short password
    await wrapper.find('input[type="email"]').setValue('invalid-email');
    await wrapper.find('input[type="password"]').setValue('short');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Invalid email address');
    expect(wrapper.text()).toContain('Password must be at least 8 characters');
    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('calls login function on valid form submission and redirects', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockResolvedValueOnce(true);

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(mockLogin).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'password123'
    });
    expect(mockPush).toHaveBeenCalledWith('/');
  });

  it('displays error message when login fails', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce(new Error('Wrong Password'));

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Incorrect email or password.');
  });
});
