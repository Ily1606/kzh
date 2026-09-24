import axios, { type AxiosRequestConfig, AxiosError } from "axios";

const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000";

export const axiosInstance = axios.create({
  baseURL: API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

axiosInstance.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

export const api = {
  get: async <T>(path: string, config?: AxiosRequestConfig): Promise<T> => {
    const response = await axiosInstance.get<T>(path, config);
    return response.data;
  },

  post: async <T>(path: string, data?: any, config?: AxiosRequestConfig): Promise<T> => {
    const response = await axiosInstance.post<T>(path, data, config);
    return response.data;
  },

  put: async <T>(path: string, data?: any, config?: AxiosRequestConfig): Promise<T> => {
    const response = await axiosInstance.put<T>(path, data, config);
    return response.data;
  },

  delete: async <T>(path: string, config?: AxiosRequestConfig): Promise<T> => {
    const response = await axiosInstance.delete<T>(path, config);
    return response.data;
  },
};

export { AxiosError as ApiError };
