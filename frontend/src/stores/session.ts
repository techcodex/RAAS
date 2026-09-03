import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api, getToken, setToken } from '@/lib/api'
import type { User } from '@/lib/types'

interface Credentials {
  email: string
  password: string
}

interface Registration extends Credentials {
  name: string
  password_confirmation: string
  organization_name?: string
}

export const useSessionStore = defineStore('session', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const organization = computed(() => user.value?.current_organization ?? null)

  async function register(payload: Registration): Promise<void> {
    const { data } = await api.post<{ token: string; user: User }>('/auth/register', payload)
    setToken(data.token)
    user.value = data.user
  }

  async function login(payload: Credentials): Promise<void> {
    const { data } = await api.post<{ token: string; user: User }>('/auth/login', payload)
    setToken(data.token)
    user.value = data.user
  }

  async function fetchMe(): Promise<void> {
    const { data } = await api.get<{ data: User }>('/auth/me')
    user.value = data.data
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout')
    } finally {
      setToken(null)
      user.value = null
    }
  }

  /** Restore the session on app boot if a token is present. */
  async function bootstrap(): Promise<void> {
    if (getToken()) {
      try {
        await fetchMe()
      } catch {
        setToken(null)
      }
    }
    ready.value = true
  }

  function clear(): void {
    setToken(null)
    user.value = null
  }

  return {
    user,
    ready,
    isAuthenticated,
    organization,
    register,
    login,
    fetchMe,
    logout,
    bootstrap,
    clear,
  }
})
