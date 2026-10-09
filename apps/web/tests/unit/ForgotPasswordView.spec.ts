import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ForgotPasswordView from '@/views/ForgotPasswordView.vue';

// Mock getPasswordReset API
const mockPasswordResetSendResetLinkEmail = vi.fn();
vi.mock('@/api/generated/endpoints', () => ({
  getPasswordReset: () => ({
    passwordResetSendResetLinkEmail: mockPasswordResetSendResetLinkEmail
  })
}));

describe('ForgotPasswordView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders correctly', () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
    expect(wrapper.find('button[type="submit"]').text()).toContain('Email Password Reset Link');
  });

  it('displays validation error if email is empty or invalid', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    // Empty email
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));
    expect(wrapper.text()).toContain('Email is required');

    // Invalid email
    await wrapper.find('input[type="email"]').setValue('invalid-email');
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Invalid email address');
    expect(mockPasswordResetSendResetLinkEmail).not.toHaveBeenCalled();
  });

  it('calls API and shows success message on valid submission', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    mockPasswordResetSendResetLinkEmail.mockResolvedValueOnce({});

    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(mockPasswordResetSendResetLinkEmail).toHaveBeenCalledWith({
      email: 'test@example.com'
    });
    
    // Check if success message is displayed
    expect(wrapper.text()).toContain('We have emailed your password reset link.');
  });

  it('displays API error message correctly', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    // Mock API returning a 422 error
    mockPasswordResetSendResetLinkEmail.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'We can\'t find a user with that email address.' }
      }
    });

    await wrapper.find('input[type="email"]').setValue('notfound@example.com');
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('We can\'t find a user with that email address.');
  });

  it('displays fallback error message for 500 errors', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: ['RouterLink', 'Spinner']
      }
    });

    // Mock generic API error
    mockPasswordResetSendResetLinkEmail.mockRejectedValueOnce(new Error('Network error'));

    await wrapper.find('input[type="email"]').setValue('test@example.com');
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(wrapper.text()).toContain('Failed to send reset link. Please try again.');
  });
});
