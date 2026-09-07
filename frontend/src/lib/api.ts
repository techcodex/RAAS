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

/**
 * HTTP client for the employee query app (`/api/app/*`). A separate identity
 * from the main `api` client: its own token, stored per publication slug so one
 * browser can be signed in to more than one company's app.
 */
export const appApi = axios.create({
  baseURL: '/api/app',
  headers: { Accept: 'application/json' },
})

let activeAppSlug: string | null = null

export function appTokenKey(slug: string): string {
  return `raas.app.${slug}`
}

export function getAppToken(slug: string): string | null {
  try {
    return localStorage.getItem(appTokenKey(slug))
  } catch {
    return null
  }
}

export function setAppToken(slug: string, token: string | null): void {
  try {
    if (token) localStorage.setItem(appTokenKey(slug), token)
    else localStorage.removeItem(appTokenKey(slug))
  } catch {
    /* storage unavailable */
  }
}

/** Which publication's token the `appApi` client should send. */
export function setActiveAppSlug(slug: string | null): void {
  activeAppSlug = slug
}

appApi.interceptors.request.use((config) => {
  if (activeAppSlug) {
    const token = getAppToken(activeAppSlug)
    if (token) config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let onAppUnauthorized: (() => void) | null = null
export function setAppUnauthorizedHandler(fn: () => void): void {
  onAppUnauthorized = fn
}

appApi.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    // 401 = token gone; 403 = employee deactivated (or wrong publication).
    // Either way the session is no longer valid — clear it and fall back to sign-in.
    const status = error.response?.status
    if ((status === 401 || status === 403) && activeAppSlug) {
      setAppToken(activeAppSlug, null)
      onAppUnauthorized?.()
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
