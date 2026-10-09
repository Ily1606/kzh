import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ResetPasswordView from '@/views/ResetPasswordView.vue';

// Mock Router
const mockPush = vi.fn();
let mockQuery = { email: 'test@example.com', token: 'fake-token' };

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({ query: mockQuery })
}));

// Mock API
const mockPasswordResetResetPassword = vi.fn();
vi.mock('@/api/generated/endpoints', () => ({
  getPasswordReset: () => ({
    passwordResetResetPassword: mockPasswordResetResetPassword
  })
}));

describe('ResetPasswordView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockQuery = { email: 'test@example.com', token: 'fake-token' }; // Reset query state
  });

  it('renders correctly and populates email from URL', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    await new Promise(r => setTimeout(r, 10));

    // Email should be populated from mockQuery and read-only
    const emailInput = wrapper.find('input[type="email"]');
    expect(emailInput.exists()).toBe(true);
    expect((emailInput.element as HTMLInputElement).value).toBe('test@example.com');
    
    // Passwords fields
    expect(wrapper.findAll('input[type="password"]').length).toBe(2);
  });

  it('displays error if passwords do not match or empty', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    // Empty form test
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));
    expect(wrapper.text()).toContain('Password must be at least 8 characters');
    expect(wrapper.text()).toContain('Please confirm your password');

    await wrapper.find('input[name="password"]').setValue('password123');
    await wrapper.find('input[name="password_confirmation"]').setValue('different');
    
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Passwords do not match');
    expect(mockPasswordResetResetPassword).not.toHaveBeenCalled();
  });

  it('shows error if token is missing from URL', async () => {
    mockQuery = { email: 'test@example.com', token: '' }; // Empty token
    
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    await wrapper.find('input[name="password"]').setValue('password123');
    await wrapper.find('input[name="password_confirmation"]').setValue('password123');
    
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Invalid or missing password reset token.');
    expect(mockPasswordResetResetPassword).not.toHaveBeenCalled();
  });

  it('calls API and redirects on success', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockPasswordResetResetPassword.mockResolvedValueOnce({});

    await wrapper.find('input[name="password"]').setValue('newPassword123');
    await wrapper.find('input[name="password_confirmation"]').setValue('newPassword123');
    
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(mockPasswordResetResetPassword).toHaveBeenCalledWith({
      email: 'test@example.com',
      token: 'fake-token',
      password: 'newPassword123',
      password_confirmation: 'newPassword123'
    });

    expect(wrapper.text()).toContain('Your password has been reset.');

    // Wait for the timeout to push
    await new Promise(r => setTimeout(r, 2100));
    expect(mockPush).toHaveBeenCalledWith('/login');
  });

  it('displays API validation errors correctly', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockPasswordResetResetPassword.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'The reset token is invalid.' }
      }
    });

    await wrapper.find('input[name="password"]').setValue('newPassword123');
    await wrapper.find('input[name="password_confirmation"]').setValue('newPassword123');
    
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('The reset token is invalid.');
  });
});
