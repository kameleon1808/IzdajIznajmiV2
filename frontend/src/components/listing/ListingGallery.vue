<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeft, ArrowRight, X, ZoomIn, ZoomOut } from 'lucide-vue-next'

const props = defineProps<{
  images: string[]
  alt?: string
}>()

const currentIndex = ref(0)
const lightboxOpen = ref(false)
const zoom = ref(1)
const touchStartX = ref<number | null>(null)
const touchStartY = ref<number | null>(null)

const total = computed(() => props.images?.length ?? 0)
const hasMultiple = computed(() => (props.images?.length ?? 0) > 1)
const currentImage = computed(() => props.images?.[currentIndex.value] ?? '')

const setIndex = (next: number) => {
  if (!total.value) return
  const normalized = (next + total.value) % total.value
  currentIndex.value = normalized
  preloadAdjacent()
}

const next = () => setIndex(currentIndex.value + 1)
const prev = () => setIndex(currentIndex.value - 1)

const openLightbox = () => {
  if (!total.value) return
  lightboxOpen.value = true
  zoom.value = 1
}

const closeLightbox = () => {
  lightboxOpen.value = false
  zoom.value = 1
}

const onKeydown = (e: KeyboardEvent) => {
  if (!lightboxOpen.value) return
  if (e.key === 'ArrowRight') {
    next()
  } else if (e.key === 'ArrowLeft') {
    prev()
  } else if (e.key === 'Escape') {
    closeLightbox()
  }
}

const onWheel = (e: WheelEvent) => {
  if (!lightboxOpen.value) return
  e.preventDefault()
  if (e.deltaY < 0) zoom.value = Math.min(3, zoom.value + 0.1)
  else zoom.value = Math.max(1, zoom.value - 0.1)
}

const zoomIn = () => (zoom.value = Math.min(3, +(zoom.value + 0.25).toFixed(2)))
const zoomOut = () => (zoom.value = Math.max(1, +(zoom.value - 0.25).toFixed(2)))

const onTouchStart = (e: TouchEvent) => {
  if (!lightboxOpen.value) return
  const t = e.touches?.[0]
  if (!t) return
  touchStartX.value = t.clientX
  touchStartY.value = t.clientY
}

const onTouchEnd = (e: TouchEvent) => {
  if (!lightboxOpen.value || touchStartX.value === null || touchStartY.value === null) return
  const t = e.changedTouches?.[0]
  if (!t) return
  const dx = t.clientX - touchStartX.value
  const dy = t.clientY - touchStartY.value
  if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
    dx > 0 ? prev() : next()
  }
  touchStartX.value = null
  touchStartY.value = null
}

const preloadImage = (src: string) => {
  if (!src) return
  const img = new Image()
  img.src = src
}

const preloadAdjacent = () => {
  if (!hasMultiple.value) return
  const nextIdx = (currentIndex.value + 1) % total.value
  const prevIdx = (currentIndex.value - 1 + total.value) % total.value
  preloadImage(props.images[nextIdx] ?? '')
  preloadImage(props.images[prevIdx] ?? '')
}

watch(lightboxOpen, (open) => {
  if (open) {
    document.addEventListener('keydown', onKeydown)
    document.body.classList.add('overflow-hidden')
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.classList.remove('overflow-hidden')
  }
})

onMounted(() => preloadAdjacent())
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <div class="space-y-2">
    <div class="relative overflow-hidden rounded-2xl bg-surface shadow-card">
      <img
        v-if="currentImage"
        :src="currentImage"
        :alt="alt || 'Listing image'"
        loading="lazy"
        class="h-72 w-full object-cover md:h-96"
        @click="openLightbox"
      />
      <div v-if="hasMultiple" class="pointer-events-none absolute inset-0 flex items-center justify-between">
        <button
          class="pointer-events-auto m-3 flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white shadow-card backdrop-blur"
          @click.stop="prev"
        >
          <ArrowLeft class="h-5 w-5" />
        </button>
        <button
          class="pointer-events-auto m-3 flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white shadow-card backdrop-blur"
          @click.stop="next"
        >
          <ArrowRight class="h-5 w-5" />
        </button>
      </div>
      <div v-if="hasMultiple" class="absolute bottom-3 right-3 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white">
        {{ currentIndex + 1 }} / {{ total }}
      </div>
    </div>

    <div v-if="hasMultiple" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(img, idx) in images"
        :key="img + idx"
        class="h-14 w-20 flex-shrink-0 overflow-hidden rounded-xl border shadow-soft"
        :class="idx === currentIndex ? 'border-primary' : 'border-transparent'"
        @click="setIndex(idx)"
      >
        <img :src="img" :alt="alt || 'thumb'" loading="lazy" class="h-full w-full object-cover" />
      </button>
    </div>
  </div>

  <teleport to="body">
    <transition name="fade">
      <div
        v-if="lightboxOpen"
        class="fixed inset-0 z-[1200] bg-black/90 backdrop-blur-sm"
        @click.self="closeLightbox"
        @wheel.prevent="onWheel"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
      >
        <div class="absolute inset-0 flex items-center justify-center px-4">
          <img
            :src="currentImage"
            :alt="alt || 'Listing image zoomed'"
            class="max-h-[80vh] max-w-[90vw] select-none rounded-2xl shadow-2xl transition-transform duration-150 ease-out"
            :style="{ transform: `scale(${zoom})` }"
            draggable="false"
          />
        </div>

        <button
          class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/15 p-3 text-white backdrop-blur"
          @click.stop="prev"
        >
          <ArrowLeft class="h-6 w-6" />
        </button>
        <button
          class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/15 p-3 text-white backdrop-blur"
          @click.stop="next"
        >
          <ArrowRight class="h-6 w-6" />
        </button>

        <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white/10 px-4 py-2 text-sm text-white backdrop-blur">
          <button class="rounded-full bg-white/20 p-2" @click.stop="zoomOut"><ZoomOut class="h-4 w-4" /></button>
          <span class="min-w-[48px] text-center">{{ (zoom * 100).toFixed(0) }}%</span>
          <button class="rounded-full bg-white/20 p-2" @click.stop="zoomIn"><ZoomIn class="h-4 w-4" /></button>
          <span class="mx-2">{{ currentIndex + 1 }} / {{ total }}</span>
        </div>

        <button
          class="absolute right-4 top-4 rounded-full bg-white/20 p-3 text-white backdrop-blur"
          @click.stop="closeLightbox"
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
