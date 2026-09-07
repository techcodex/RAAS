import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { appApi, getAppToken, setActiveAppSlug, setAppToken } from '@/lib/api'
import type { AppConversation, EmployeeIdentity, Paginated, PublicApp } from '@/lib/types'

interface Credentials {
  email: string
  access_code: string
}

/**
 * One published employee app: its public config, and the signed-in employee (if
 * any). Re-initialised whenever the route's `:slug` changes.
 */
export const useEmployeeStore = defineStore('employee', () => {
  const slug = ref<string | null>(null)
  const publication = ref<PublicApp | null>(null)
  const user = ref<EmployeeIdentity | null>(null)
  const ready = ref(false)
  const loadError = ref('')
  const conversations = ref<AppConversation[]>([])

  const signedIn = computed(() => user.value !== null)

  async function load(nextSlug: string): Promise<void> {
    if (slug.value === nextSlug && ready.value) return

    slug.value = nextSlug
    setActiveAppSlug(nextSlug)
    ready.value = false
    loadError.value = ''
    publication.value = null
    user.value = null
    conversations.value = []

    try {
      const { data } = await appApi.get<{ data: PublicApp }>(`/${nextSlug}`)
      publication.value = data.data
    } catch {
      loadError.value = 'This app is not available.'
      ready.value = true
      return
    }

    // A stored token is trusted optimistically; the first authed call that 401s
    // clears it (see the appApi interceptor + the layout's unauthorized handler).
    if (getAppToken(nextSlug)) {
      user.value = { name: '', email: '' }
    }
    ready.value = true
  }

  async function signIn(payload: Credentials): Promise<void> {
    const s = slug.value
    if (!s) return
    const { data } = await appApi.post<{ token: string; user: EmployeeIdentity }>(
      `/${s}/session`,
      payload,
    )
    setAppToken(s, data.token)
    user.value = data.user
  }

  async function signOut(): Promise<void> {
    const s = slug.value
    if (s) {
      try {
        await appApi.delete(`/${s}/session`)
      } catch {
        /* token already gone */
      }
      setAppToken(s, null)
    }
    user.value = null
  }

  async function loadConversations(): Promise<void> {
    if (!slug.value || !signedIn.value) return
    const { data } = await appApi.get<Paginated<AppConversation>>(`/${slug.value}/conversations`)
    conversations.value = data.data
  }

  /** Move a conversation to the top of the list, inserting it if new. */
  function bumpConversation(conversation: AppConversation): void {
    conversations.value = [
      conversation,
      ...conversations.value.filter((c) => c.id !== conversation.id),
    ]
  }

  /** Local-only: called when a request 401s. */
  function clear(): void {
    if (slug.value) setAppToken(slug.value, null)
    user.value = null
    conversations.value = []
  }

  return {
    slug,
    publication,
    user,
    ready,
    loadError,
    conversations,
    signedIn,
    load,
    signIn,
    signOut,
    loadConversations,
    bumpConversation,
    clear,
  }
})
