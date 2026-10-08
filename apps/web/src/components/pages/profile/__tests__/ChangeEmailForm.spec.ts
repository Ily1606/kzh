import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import ChangeEmailForm from '../ChangeEmailForm.vue';
import { api } from '@/services/api';
import { notifySuccess } from '@/utils/toast';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';

// Mock dependencies
vi.mock('@/services/api', () => ({
  api: {
    post: vi.fn(),
  },
}));

vi.mock('@/utils/toast', () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock('vue-router', () => ({
  useRoute: vi.fn(),
  useRouter: vi.fn(),
}));

vi.mock('@/composables/useAuth', () => ({
  useAuth: vi.fn(),
}));

describe('ChangeEmailForm.vue', () => {
  const mockRouterReplace = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();

    // Default mocks
    (useRoute as any).mockReturnValue({ query: {} });
    (useRouter as any).mockReturnValue({ replace: mockRouterReplace });
    (useAuth as any).mockReturnValue({ user: { id: 1, email: 'old@example.com' } });
  });

  it('renders request form by default', () => {
    const wrapper = mount(ChangeEmailForm);
    expect(wrapper.find('h2').text()).toBe('Change Email');
    expect(wrapper.find('label[for="currentPassword"]').exists()).toBe(true);
    expect(wrapper.find('label[for="newEmail"]').exists()).toBe(true);
  });

  it('shows validation errors if fields are empty on submit', async () => {
    const wrapper = mount(ChangeEmailForm);

    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10)); // Wait for VeeValidate to process

    const errorMessages = wrapper.findAll('.text-destructive');
    expect(errorMessages.length).toBeGreaterThan(0);
    expect(errorMessages[0].text()).toContain('Required');
  });

  it('calls api.post and switches to verify step on successful request', async () => {
    (api.post as any).mockResolvedValueOnce({ data: { message: 'Success' } });

    const wrapper = mount(ChangeEmailForm);

    // Fill form
    await wrapper.find('input#currentPassword').setValue('password123');
    await wrapper.find('input#newEmail').setValue('new@example.com');
    await wrapper.find('form').trigger('submit');

    await new Promise(r => setTimeout(r, 10));

    expect(api.post).toHaveBeenCalledWith('/v1/user/email/request', {
      current_password: 'password123',
      new_email: 'new@example.com'
    });
    expect(notifySuccess).toHaveBeenCalledWith('Verification code sent to your new email.');

    // UI should switch to verify step
    expect(wrapper.find('label[for="token"]').exists()).toBe(true);
  });

  it('auto-switches to verify step if token is in route query', async () => {
    (useRoute as any).mockReturnValue({ query: { token: 'abc-123' } });

    const wrapper = mount(ChangeEmailForm);
    await new Promise(r => setTimeout(r, 10));

    // Should immediately show verify step
    expect(wrapper.find('label[for="token"]').exists()).toBe(true);

    // The input should be pre-filled
    const tokenInput = wrapper.find('input#token').element as HTMLInputElement;
    expect(tokenInput.value).toBe('abc-123');

    // Should have cleaned up the URL
    expect(mockRouterReplace).toHaveBeenCalledWith({ query: {} });
  });

  it('calls api.post on verify form submission and handles success', async () => {
    (useRoute as any).mockReturnValue({ query: { token: 'abc-123' } });
    (api.post as any).mockResolvedValueOnce({ data: { data: { email: 'new@example.com' } } });

    const wrapper = mount(ChangeEmailForm);
    await new Promise(r => setTimeout(r, 10));

    // Submit verify form
    await wrapper.find('form').trigger('submit');
    await new Promise(r => setTimeout(r, 10));

    expect(api.post).toHaveBeenCalledWith('/v1/user/email/verify', {
      token: 'abc-123'
    });

    expect(notifySuccess).toHaveBeenCalledWith('Email changed successfully.');

    // UI should switch back to request step
    expect(wrapper.find('label[for="currentPassword"]').exists()).toBe(true);
  });
});

