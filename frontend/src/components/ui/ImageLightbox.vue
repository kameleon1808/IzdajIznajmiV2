<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ArrowLeft, ArrowRight, X, ZoomIn, ZoomOut } from 'lucide-vue-next'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{
  images: string[]
  open: boolean
  index: number
  alt?: string
}>()

const emit = defineEmits(['update:open', 'update:index'])
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const total = computed(() => props.images?.length ?? 0)
const hasMultiple = computed(() => (props.images?.length ?? 0) > 1)
const currentImage = computed(() => props.images?.[props.index] ?? '')

const setIndex = (next: number) => {
  if (!total.value) return
  const normalized = (next + total.value) % total.value
  emit('update:index', normalized)
}

const zoomLevel = ref(1)

const setZoom = (next: number) => {
  zoomLevel.value = Math.min(3, Math.max(1, +next.toFixed(2)))
}

const zoomIn = () => setZoom(zoomLevel.value + 0.25)
const zoomOut = () => setZoom(zoomLevel.value - 0.25)

const close = () => emit('update:open', false)

const onKeydown = (e: KeyboardEvent) => {
  if (!props.open) return
  if (e.key === 'ArrowRight') {
    setIndex(props.index + 1)
  } else if (e.key === 'ArrowLeft') {
    setIndex(props.index - 1)
  } else if (e.key === 'Escape') {
    close()
  }
}

const onWheel = (e: WheelEvent) => {
  if (!props.open) return
  e.preventDefault()
  if (e.deltaY < 0) setZoom(zoomLevel.value + 0.1)
  else setZoom(zoomLevel.value - 0.1)
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      document.addEventListener('keydown', onKeydown)
      document.body.classList.add('overflow-hidden')
      setZoom(1)
    } else {
      document.removeEventListener('keydown', onKeydown)
      document.body.classList.remove('overflow-hidden')
      setZoom(1)
    }
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[1200] bg-black/90 backdrop-blur-sm"
        @click.self="close"
        @wheel.prevent="onWheel"
      >
        <div class="absolute inset-0 flex items-center justify-center px-4">
          <img
            :src="currentImage"
            :alt="alt || t('chat.imagePreview')"
            class="max-h-[80vh] max-w-[90vw] select-none rounded-2xl shadow-2xl transition-transform duration-150 ease-out"
            :style="{ transform: `scale(${zoomLevel})` }"
            draggable="false"
          />
        </div>

        <button
          v-if="hasMultiple"
          class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/15 p-3 text-white backdrop-blur"
          @click.stop="setIndex(index - 1)"
        >
          <ArrowLeft class="h-6 w-6" />
        </button>
        <button
          v-if="hasMultiple"
          class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/15 p-3 text-white backdrop-blur"
          @click.stop="setIndex(index + 1)"
        >
          <ArrowRight class="h-6 w-6" />
        </button>

        <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white/10 px-4 py-2 text-sm text-white backdrop-blur">
          <button class="rounded-full bg-white/20 p-2" @click.stop="zoomOut"><ZoomOut class="h-4 w-4" /></button>
          <span class="min-w-[48px] text-center">{{ (zoomLevel * 100).toFixed(0) }}%</span>
          <button class="rounded-full bg-white/20 p-2" @click.stop="zoomIn"><ZoomIn class="h-4 w-4" /></button>
          <span v-if="hasMultiple" class="mx-2">{{ index + 1 }} / {{ total }}</span>
        </div>

        <button
          class="absolute right-4 top-4 rounded-full bg-white/20 p-3 text-white backdrop-blur"
          @click.stop="close"
        >
          <X class="h-5 w-5" />
        </button>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
