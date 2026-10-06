import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RegisterView from '@/views/RegisterView.vue';

// Mock router
const mockPush = vi.fn();
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush })
}));

// Mock composable useAuth
const mockRegister = vi.fn();
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ register: mockRegister })
}));

describe('RegisterView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders basic input fields correctly', () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    expect(wrapper.find('input[type="text"]').exists()).toBe(true); // Full name
    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
    // There are two password fields (password and confirm password)
    expect(wrapper.findAll('input[type="password"]').length).toBe(2);
    expect(wrapper.find('button[type="submit"]').text()).toContain('Create account');
  });

  it('displays validation errors if passwords do not match', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    const nameInput = wrapper.find('input[id="fullName"]');
    const emailInput = wrapper.find('input[id="email"]');
    const passwordInput = wrapper.find('input[id="password"]');
    const confirmPasswordInput = wrapper.find('input[id="confirmPassword"]');

    await nameInput.setValue('John Doe');
    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');
    await confirmPasswordInput.setValue('differentPassword');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Passwords do not match');
    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('calls register function on valid form submission and redirects', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockResolvedValueOnce(true);

    const nameInput = wrapper.find('input[id="fullName"]');
    const emailInput = wrapper.find('input[id="email"]');
    const passwordInput = wrapper.find('input[id="password"]');
    const confirmPasswordInput = wrapper.find('input[id="confirmPassword"]');

    await nameInput.setValue('John Doe');
    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');
    await confirmPasswordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(mockRegister).toHaveBeenCalledWith({
      name: 'John Doe',
      email: 'test@example.com',
      password: 'password123',
      password_confirmation: 'password123'
    });
    expect(mockPush).toHaveBeenCalledWith('/');
  });

  it('displays error message when registration fails via API', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce(new Error('Registration failed'));

    await wrapper.find('input[id="fullName"]').setValue('John Doe');
    await wrapper.find('input[id="email"]').setValue('test@example.com');
    await wrapper.find('input[id="password"]').setValue('password123');
    await wrapper.find('input[id="confirmPassword"]').setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Couldn\'t create your account. Please try again.');
  });
});
