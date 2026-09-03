import axios, { type AxiosError } from 'axios'

/**
 * Shared HTTP client for the Laravel API (through the Vite `/api` proxy).
 * Auth is a Sanctum bearer token kept in localStorage.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
})

const TOKEN_KEY = 'raas.token'

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token: string | null): void {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch {
    /* storage unavailable — session lives in memory only */
  }
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/** Callback invoked when the API returns 401, so the app can redirect to login. */
let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(fn: () => void): void {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      setToken(null)
      onUnauthorized?.()
    }
    return Promise.reject(error)
  },
)

/** Pull a flat list of messages out of a Laravel 422 validation error. */
export function validationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    return (error.response.data as { errors?: Record<string, string[]> }).errors ?? {}
  }
  return {}
}

export function errorMessage(error: unknown, fallback = 'Something went wrong'): string {
  if (axios.isAxiosError(error)) {
    return (error.response?.data as { message?: string })?.message ?? error.message ?? fallback
  }
  return error instanceof Error ? error.message : fallback
}
