<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import type { JsonSchemaProperty } from '@/lib/types'
import { useStrategiesStore } from '@/stores/strategies'

const strategy = defineModel<string>('strategy', { default: 'auto' })
const config = defineModel<Record<string, unknown>>('config', { default: () => ({}) })

const store = useStrategiesStore()
const showConfig = ref(false)

onMounted(() => store.ensureLoaded())

const strategies = computed(() => store.catalogue?.strategies ?? [])
const selected = computed(() => strategies.value.find((s) => s.name === strategy.value))

const fields = computed<[string, JsonSchemaProperty][]>(() =>
  Object.entries(selected.value?.config_schema.properties ?? {}),
)

watch(strategy, () => {
  config.value = { ...(selected.value?.defaults ?? {}) }
})

function update(key: string, value: string, type?: string) {
  let parsed: unknown = value
  if (type === 'integer' || type === 'number') parsed = Number(value)
  if (type === 'boolean') parsed = value === 'true'
  config.value = { ...config.value, [key]: parsed }
}
</script>

<template>
  <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
    <div class="flex flex-wrap items-center gap-2">
      <label class="text-sm font-medium">Chunking strategy</label>
      <select
        v-model="strategy"
        class="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
      >
        <option v-for="s in strategies" :key="s.name" :value="s.name">{{ s.label }}</option>
      </select>
      <button
        v-if="fields.length"
        type="button"
        class="text-xs text-indigo-600 hover:underline"
        @click="showConfig = !showConfig"
      >
        {{ showConfig ? 'Hide' : 'Options' }}
      </button>
    </div>

    <p v-if="selected" class="mt-1 text-xs text-gray-500">{{ selected.description }}</p>

    <div v-if="showConfig && fields.length" class="mt-3 grid gap-3 sm:grid-cols-2">
      <label v-for="[key, prop] in fields" :key="key" class="block text-xs">
        <span class="text-gray-600 dark:text-gray-400">{{ prop.title ?? key }}</span>
        <select
          v-if="prop.type === 'boolean'"
          class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1 dark:border-gray-700 dark:bg-gray-800"
          :value="String(config[key])"
          @change="update(key, ($event.target as HTMLSelectElement).value, 'boolean')"
        >
          <option value="true">Yes</option>
          <option value="false">No</option>
        </select>
        <input
          v-else
          type="number"
          :value="config[key] as number"
          :min="prop.minimum"
          :max="prop.maximum"
          class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1 dark:border-gray-700 dark:bg-gray-800"
          @input="update(key, ($event.target as HTMLInputElement).value, prop.type)"
        />
        <span v-if="prop.description" class="text-gray-400">{{ prop.description }}</span>
      </label>
    </div>
  </div>
</template>
