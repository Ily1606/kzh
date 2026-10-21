import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RegisterView from '@/views/RegisterView.vue';

// Mock router
const mockPush = vi.fn();
let mockQuery: Record<string, string> = {};

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({ query: mockQuery }),
}));

// Mock composable useAuth
const mockRegister = vi.fn();
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ register: mockRegister })
}));

describe('RegisterView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockQuery = {};
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

  it('associates FormLabel with Input id via FormControl', () => {
    const wrapper = mount(RegisterView, {
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

  it('displays validation errors if passwords do not match', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    const nameInput = wrapper.find('input[type="text"]');
    const emailInput = wrapper.find('input[type="email"]');
    const passwordInputs = wrapper.findAll('input[type="password"]');

    await nameInput.setValue('John Doe');
    await emailInput.setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('differentPassword');

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

    const nameInput = wrapper.find('input[type="text"]');
    const emailInput = wrapper.find('input[type="email"]');
    const passwordInputs = wrapper.findAll('input[type="password"]');

    await nameInput.setValue('John Doe');
    await emailInput.setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

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

  it('redirects to route.query.redirect on successful registration when provided', async () => {
    mockQuery = { redirect: '/plugins' };
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockResolvedValueOnce(true);

    const nameInput = wrapper.find('input[type="text"]');
    const emailInput = wrapper.find('input[type="email"]');
    const passwordInputs = wrapper.findAll('input[type="password"]');

    await nameInput.setValue('John Doe');
    await emailInput.setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(mockPush).toHaveBeenCalledWith('/plugins');
  });

  it('displays error message when registration fails via API', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce(new Error('Registration failed'));

    const passwordInputs = wrapper.findAll('input[type="password"]');
    await wrapper.find('input[type="text"]').setValue('John Doe');
    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Couldn\'t create your account. Please try again.');
  });

  it('displays rate limit error message when registration fails with 429', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce({
      response: { status: 429 }
    });

    const passwordInputs = wrapper.findAll('input[type="password"]');
    await wrapper.find('input[type="text"]').setValue('John Doe');
    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Too many attempts. Please try again later.');
  });

  it('displays API validation error message when API returns 422', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'The email has already been taken.' }
      }
    });

    const passwordInputs = wrapper.findAll('input[type="password"]');
    await wrapper.find('input[type="text"]').setValue('John Doe');
    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('The email has already been taken.');
  });

  it('displays fallback validation error message when 422 has no message', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {}
      }
    });

    const passwordInputs = wrapper.findAll('input[type="password"]');
    await wrapper.find('input[type="text"]').setValue('John Doe');
    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Validation failed.');
  });

  it('maps server field errors to form fields and shows clean banner on 422', async () => {
    const wrapper = mount(RegisterView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockRegister.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'The email has already been taken. (and 1 more error)',
          errors: {
            email: ['The email has already been taken.'],
            password_confirmation: ['The password confirmation does not match.'],
          }
        }
      }
    });

    const passwordInputs = wrapper.findAll('input[type="password"]');
    await wrapper.find('input[type="text"]').setValue('John Doe');
    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await passwordInputs[0].setValue('password123');
    await passwordInputs[1].setValue('password123');

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    // Banner displays clean user-facing guidance instead of raw "(and 1 more error)"
    expect(wrapper.text()).toContain('Please check the highlighted fields below.');
    // Specific field errors are mapped to the form fields
    expect(wrapper.text()).toContain('The email has already been taken.');
    expect(wrapper.text()).toContain('The password confirmation does not match.');
  });
});
