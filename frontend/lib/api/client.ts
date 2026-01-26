import axios, { AxiosInstance, AxiosError } from "axios";
import { useSessionStore } from "@/lib/store";

// Create axios instance
const baseURL = process.env.NEXT_PUBLIC_API_BASE_URL || 
                process.env.NEXT_PUBLIC_API_URL || 
                "http://localhost:8000";

// Ensure baseURL ends with /api/v1 for versioned API
const apiBaseURL = baseURL.endsWith('/api/v1') 
  ? baseURL 
  : `${baseURL.replace(/\/$/, '')}/api/v1`;

const apiClient: AxiosInstance = axios.create({
  baseURL: apiBaseURL,
  headers: {
    "Content-Type": "application/json",
  },
  withCredentials: true,
});

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = useSessionStore.getState().accessToken;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Handle unauthorized - redirect to login
      const { clearSession } = useSessionStore.getState();
      clearSession();
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;





