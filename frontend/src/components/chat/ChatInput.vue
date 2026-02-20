<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { FileText, Paperclip, Send, X } from 'lucide-vue-next'
import { useToastStore } from '../../stores/toast'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{
  modelValue: string
  attachments?: File[]
  disabled?: boolean
  uploading?: boolean
  uploadProgress?: number | null
}>()
const emit = defineEmits(['update:modelValue', 'update:attachments', 'send', 'blur', 'metrics'])

const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const fileInput = ref<HTMLInputElement | null>(null)
const messageInput = ref<HTMLTextAreaElement | null>(null)
const previewMap = new Map<File, string>()
const keyboardOffset = ref(0)
const containerRef = ref<HTMLElement | null>(null)
const sendOnEnter = ref(true)
let sendOnEnterMedia: MediaQueryList | null = null

const attachments = computed(() => props.attachments ?? [])

const syncPreviews = (files: File[]) => {
  const set = new Set(files)
  for (const [file, url] of previewMap.entries()) {
    if (!set.has(file)) {
      URL.revokeObjectURL(url)
      previewMap.delete(file)
    }
  }
  for (const file of files) {
    if (file.type.startsWith('image/') && !previewMap.has(file)) {
      previewMap.set(file, URL.createObjectURL(file))
    }
  }
}

watch(
  () => attachments.value,
  (files) => syncPreviews(files),
  { immediate: true },
)

onBeforeUnmount(() => {
  for (const url of previewMap.values()) {
    URL.revokeObjectURL(url)
  }
  previewMap.clear()
})

const triggerPicker = () => {
  if (props.disabled || props.uploading) return
  fileInput.value?.click()
}

const onFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const files = Array.from(target.files ?? [])
  if (!files.length) return

  const next = [...attachments.value, ...files]
  if (next.length > 5) {
    toast.push({
      title: t('chat.attachmentLimitTitle'),
      message: t('chat.attachmentLimitMessage'),
      type: 'error',
    })
  }
  emit('update:attachments', next.slice(0, 5))
  target.value = ''
}

const removeAttachment = (index: number) => {
  const next = attachments.value.slice()
  next.splice(index, 1)
  emit('update:attachments', next)
}

const attachmentItems = computed(() =>
  attachments.value.map((file) => ({
    file,
    isImage: file.type.startsWith('image/'),
    preview: previewMap.get(file) ?? '',
  })),
)

const onKey = (e: KeyboardEvent) => {
  if (e.isComposing) return
  if (e.key === 'Enter' && !e.shiftKey && sendOnEnter.value) {
    e.preventDefault()
    if (props.disabled || props.uploading) return
    emitSend()
  }
}

const emitSend = () => {
  if (props.disabled || props.uploading) return
  emit('send')
  requestAnimationFrame(() => messageInput.value?.focus())
}

const emitMetrics = () => {
  const height = containerRef.value?.offsetHeight ?? 0
  emit('metrics', { height, keyboardOffset: keyboardOffset.value })
}

const updateSendOnEnter = () => {
  if (!sendOnEnterMedia) {
    sendOnEnter.value = true
    return
  }
  sendOnEnter.value = sendOnEnterMedia.matches
}

const updateKeyboardOffset = () => {
  const viewport = window.visualViewport
  if (!viewport) {
    keyboardOffset.value = 0
    emitMetrics()
    return
  }
  const offset = Math.max(0, Math.round(window.innerHeight - viewport.height - viewport.offsetTop))
  keyboardOffset.value = offset
  emitMetrics()
}

onMounted(() => {
  sendOnEnterMedia = window.matchMedia('(hover: hover) and (pointer: fine)')
  updateSendOnEnter()
  if (typeof sendOnEnterMedia.addEventListener === 'function') {
    sendOnEnterMedia.addEventListener('change', updateSendOnEnter)
  } else if (typeof sendOnEnterMedia.addListener === 'function') {
    sendOnEnterMedia.addListener(updateSendOnEnter)
  }
  updateKeyboardOffset()
  window.addEventListener('resize', emitMetrics)
  const viewport = window.visualViewport
  if (viewport) {
    viewport.addEventListener('resize', updateKeyboardOffset)
    viewport.addEventListener('scroll', updateKeyboardOffset)
  }
  requestAnimationFrame(emitMetrics)
})

onBeforeUnmount(() => {
  if (sendOnEnterMedia) {
    if (typeof sendOnEnterMedia.removeEventListener === 'function') {
      sendOnEnterMedia.removeEventListener('change', updateSendOnEnter)
    } else if (typeof sendOnEnterMedia.removeListener === 'function') {
      sendOnEnterMedia.removeListener(updateSendOnEnter)
    }
  }
  window.removeEventListener('resize', emitMetrics)
  const viewport = window.visualViewport
  if (viewport) {
    viewport.removeEventListener('resize', updateKeyboardOffset)
    viewport.removeEventListener('scroll', updateKeyboardOffset)
  }
})

watch(
  () => attachmentItems.value.length,
  () => requestAnimationFrame(emitMetrics),
)

watch(
  () => props.uploading,
  () => requestAnimationFrame(emitMetrics),
)
</script>

<template>
  <div
    ref="containerRef"
    class="fixed bottom-0 left-0 right-0 z-40 mx-auto w-full max-w-md bg-surface/90 px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-2 backdrop-blur lg:sticky lg:left-auto lg:right-auto lg:z-auto lg:max-w-none lg:px-0 lg:pb-4"
    :style="{ bottom: `${keyboardOffset}px` }"
  >
    <div v-if="attachmentItems.length" class="mb-3 flex flex-wrap gap-2">
      <div
        v-for="(item, idx) in attachmentItems"
        :key="item.file.name + item.file.size + idx"
        class="relative overflow-hidden rounded-xl border border-line bg-white/80"
      >
        <img v-if="item.isImage" :src="item.preview" :alt="item.file.name" class="h-20 w-24 object-cover" />
        <div v-else class="flex h-20 w-36 items-center gap-2 px-3 text-xs font-semibold text-slate-700">
          <FileText class="h-4 w-4" />
          <span class="line-clamp-2">{{ item.file.name }}</span>
        </div>
        <button
          class="absolute right-1 top-1 rounded-full bg-white/80 p-1 text-slate-600 shadow"
          type="button"
          :aria-label="t('chat.removeAttachment')"
          @click="removeAttachment(idx)"
        >
          <X class="h-3 w-3" />
        </button>
      </div>
    </div>

    <div class="flex items-center gap-2 rounded-2xl bg-white px-3 py-2 shadow-soft border border-white/70">
      <button
        class="rounded-full bg-surface p-2 text-slate-600 shadow-inner disabled:opacity-50"
        type="button"
        :disabled="disabled || uploading"
        :aria-label="t('chat.addAttachment')"
        @click="triggerPicker"
      >
        <Paperclip class="h-4 w-4" />
      </button>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        multiple
        accept="image/jpeg,image/png,image/webp,application/pdf"
        @change="onFileChange"
      />
      <textarea
        ref="messageInput"
        class="min-h-[48px] flex-1 resize-none bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
        :value="modelValue"
        :placeholder="t('chat.writeMessage')"
        :disabled="disabled || uploading"
        rows="1"
        @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        @keydown="onKey"
        @blur="emit('blur')"
      ></textarea>
      <button
        type="button"
        class="rounded-full bg-primary p-3 text-white shadow-card disabled:opacity-60"
        :disabled="disabled || uploading"
        @click="emitSend"
        :aria-label="t('chat.send')"
      >
        <Send class="h-5 w-5" />
      </button>
    </div>

    <div v-if="uploading" class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-200">
      <div
        class="h-full rounded-full bg-primary transition-all"
        :style="{ width: `${uploadProgress ?? 0}%` }"
      ></div>
    </div>
  </div>
</template>
