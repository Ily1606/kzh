import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { api, axiosInstance, ApiError } from '@/services/api';

describe('api service (axios wrapper)', () => {
  // Save original window.location
  const originalLocation = window.location;

  beforeEach(() => {
    // Mock window.location for testing 401 redirect
    delete (window as any).location;
    window.location = { ...originalLocation, href: '/' } as any;
  });

  afterEach(() => {
    (window as any).location = originalLocation;
    vi.restoreAllMocks();
  });

  it('api.get calls axiosInstance.get and returns data', async () => {
    const mockData = { message: 'success' };
    vi.spyOn(axiosInstance, 'get').mockResolvedValueOnce({ data: mockData } as any);

    const result = await api.get('/test');

    expect(axiosInstance.get).toHaveBeenCalledWith('/test', undefined);
    expect(result).toEqual(mockData);
  });

  it('api.post calls axiosInstance.post and returns data', async () => {
    const mockData = { id: 1 };
    const payload = { name: 'Test' };
    vi.spyOn(axiosInstance, 'post').mockResolvedValueOnce({ data: mockData } as any);

    const result = await api.post('/test', payload);

    expect(axiosInstance.post).toHaveBeenCalledWith('/test', payload, undefined);
    expect(result).toEqual(mockData);
  });

  it('interceptor redirects to login on 401 error', async () => {
    // We need to trigger the interceptor manually to test it without making real requests
    const interceptor = (axiosInstance.interceptors.response as any).handlers[0];

    expect(interceptor).toBeDefined();

    const mockError = new ApiError('Unauthorized');
    mockError.response = { status: 401 } as any;

    try {
      await interceptor.rejected(mockError);
    } catch (e) {
      expect(e).toBe(mockError);
    }

    expect(window.location.href).toBe('/login');
  });

  it('interceptor does not redirect on other errors', async () => {
    const interceptor = (axiosInstance.interceptors.response as any).handlers[0];

    const mockError = new ApiError('Not Found');
    mockError.response = { status: 404 } as any;

    try {
      await interceptor.rejected(mockError);
    } catch (e) {
      expect(e).toBe(mockError);
    }

    expect(window.location.href).toBe('/');
  });
});

