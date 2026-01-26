import axios, { AxiosHeaders, AxiosError } from "axios";
import { useSessionStore } from "./store";

const baseURL = process.env.NEXT_PUBLIC_API_BASE_URL || "";
// Ensure baseURL ends with /api/v1 for versioned API
const apiBaseURL = baseURL.endsWith('/api/v1') 
  ? baseURL 
  : `${baseURL.replace(/\/$/, '')}/api/v1`;

const api = axios.create({
  baseURL: apiBaseURL,
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  const token = useSessionStore.getState().accessToken;
  
  // Check if Authorization header is already explicitly set (from explicit headers param)
  // When headers are passed as { Authorization: "Bearer ..." }, axios converts them
  // We need to check the actual header value, not just if the key exists
  const explicitAuth = config.headers?.["Authorization"] || 
                       config.headers?.["authorization"] ||
                       (config.headers instanceof AxiosHeaders && config.headers.get("Authorization"));
  
  // Only add token if not already set explicitly
  if (token && !explicitAuth) {
    // Ensure headers exist
    if (!config.headers) {
      config.headers = {} as any;
    }
    // Set Authorization header
    config.headers["Authorization"] = `Bearer ${token}`;
    config.withCredentials = true;
  }
  return config;
});

// Add response interceptor to handle 401 errors
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Clear session and redirect to login
      const { clearSession } = useSessionStore.getState();
      clearSession();
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

export { api };

