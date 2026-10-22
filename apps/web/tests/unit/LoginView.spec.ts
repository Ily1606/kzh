import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import LoginView from '@/views/LoginView.vue';

// Mock router
const mockPush = vi.fn();
let mockQuery: Record<string, string> = {};

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({ query: mockQuery })
}));

// Mock composable useAuth
const mockLogin = vi.fn();
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: mockLogin })
}));

describe('LoginView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockQuery = {};
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

  it('associates FormLabel with Input id via FormControl', () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    const labels = wrapper.findAll('label');
    expect(labels.length).toBeGreaterThan(0);
    labels.forEach((label) => {
      const forId = label.attributes('for');
      expect(forId).toBeDefined();
      expect(wrapper.find(`#${forId}`).exists()).toBe(true);
    });
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

    expect(wrapper.text()).toContain('Email is required');
    expect(wrapper.text()).toContain('Password is required');
    expect(mockLogin).not.toHaveBeenCalled();

    // Submit invalid email and empty password
    await wrapper.find('input[type="email"]').setValue('invalid-email');
    await wrapper.find('input[type="password"]').setValue('');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Invalid email address');
    expect(wrapper.text()).toContain('Password is required');
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

  it('redirects to route.query.redirect on valid form submission when provided', async () => {
    mockQuery = { redirect: '/plugins' };
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
    expect(mockPush).toHaveBeenCalledWith('/plugins');
  });

  it('displays incorrect credentials error message when login fails with 401 or 422', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce({
      response: { status: 401 }
    });

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Incorrect email or password.');
  });

  it('displays rate limit error message when login fails with 429', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce({
      response: { status: 429 }
    });

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Too many attempts. Please try again later.');
  });

  it('displays generic error message when login fails with 500 or network error', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce(new Error('Network error'));

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('test@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Something went wrong. Please try again.');
  });

  it('displays locked account error message when account is locked', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'Your account has been locked. Please contact support.' }
      }
    });

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('locked@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Your account has been locked. Please contact support.');
  });

  it('displays deleted account error message when account is deleted', async () => {
    const wrapper = mount(LoginView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockLogin.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'This account has been deleted.' }
      }
    });

    const emailInput = wrapper.find('input[type="email"]');
    const passwordInput = wrapper.find('input[type="password"]');

    await emailInput.setValue('deleted@example.com');
    await passwordInput.setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('This account has been deleted.');
  });
});
