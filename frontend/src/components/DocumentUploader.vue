<script setup lang="ts">
import { ref } from 'vue'

import { api, errorMessage, validationErrors } from '@/lib/api'
import type { DocumentFile } from '@/lib/types'

const props = defineProps<{ projectId: number | string }>()
const emit = defineEmits<{ uploaded: [DocumentFile[]] }>()

const dragging = ref(false)
const uploading = ref(false)
const error = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

async function upload(files: FileList | File[]) {
  const list = Array.from(files)
  if (list.length === 0) return

  uploading.value = true
  error.value = ''
  const body = new FormData()
  list.forEach((file) => body.append('files[]', file))

  try {
    const { data } = await api.post<{ data: DocumentFile[] }>(
      `/projects/${props.projectId}/documents`,
      body,
    )
    emit('uploaded', data.data)
  } catch (e) {
    const fieldErrors = validationErrors(e)
    error.value = Object.values(fieldErrors)[0]?.[0] ?? errorMessage(e, 'Upload failed')
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function onDrop(event: DragEvent) {
  dragging.value = false
  if (event.dataTransfer?.files) upload(event.dataTransfer.files)
}

function onPick(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files) upload(target.files)
}
</script>

<template>
  <div>
    <div
      class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
      :class="dragging
        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950'
        : 'border-gray-300 dark:border-gray-700'"
      data-testid="dropzone"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <p class="text-sm text-gray-600 dark:text-gray-300">
        Drag &amp; drop documents here, or
        <button type="button" class="font-medium text-indigo-600 hover:underline" @click="fileInput?.click()">
          browse
        </button>
      </p>
      <p class="mt-1 text-xs text-gray-400">PDF, Word, text, Markdown, HTML, CSV, PPTX</p>
      <p v-if="uploading" class="mt-2 text-xs text-indigo-600">Uploading…</p>
      <input
        ref="fileInput"
        type="file"
        multiple
        class="hidden"
        accept=".pdf,.doc,.docx,.txt,.md,.markdown,.html,.csv,.pptx"
        @change="onPick"
      />
    </div>
    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
