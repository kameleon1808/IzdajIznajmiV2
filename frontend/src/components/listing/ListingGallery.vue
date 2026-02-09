<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'
import ImageLightbox from '../ui/ImageLightbox.vue'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{
  images: string[]
  alt?: string
}>()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const currentIndex = ref(0)
const lightboxOpen = ref(false)

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

onMounted(() => preloadAdjacent())
</script>

<template>
  <div class="space-y-2">
    <div class="relative overflow-hidden rounded-2xl bg-surface shadow-card">
      <img
        v-if="currentImage"
        :src="currentImage"
        :alt="alt || t('listing.imageAlt')"
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
        <img :src="img" :alt="alt || t('listing.thumbAlt')" loading="lazy" class="h-full w-full object-cover" />
      </button>
    </div>
  </div>

  <ImageLightbox
    :images="images"
    :open="lightboxOpen"
    :index="currentIndex"
    :alt="alt || t('listing.imageZoomAlt')"
    @update:open="lightboxOpen = $event"
    @update:index="currentIndex = $event"
  />
</template>
