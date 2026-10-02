import { api } from './api';
import type { AxiosRequestConfig } from 'axios';

export const customInstance = <T>(
  config: AxiosRequestConfig,
  options?: AxiosRequestConfig,
): Promise<T> => {
  return api({
    ...config,
    ...options,
  }).then(({ data }) => data);
};
