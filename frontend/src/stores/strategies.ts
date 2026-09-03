import { defineStore } from 'pinia'
import { ref } from 'vue'

import { api } from '@/lib/api'
import type { StrategyCatalogue } from '@/lib/types'

/** The rag-service strategy + embedder catalogue, fetched once per session. */
export const useStrategiesStore = defineStore('strategies', () => {
  const catalogue = ref<StrategyCatalogue | null>(null)
  const loading = ref(false)

  async function ensureLoaded(): Promise<void> {
    if (catalogue.value || loading.value) return
    loading.value = true
    try {
      const { data } = await api.get<StrategyCatalogue>('/strategies')
      catalogue.value = data
    } finally {
      loading.value = false
    }
  }

  return { catalogue, loading, ensureLoaded }
})
