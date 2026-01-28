<script setup lang="ts">
import { computed, ref } from 'vue'
import { Download, FileText } from 'lucide-vue-next'
import ImageLightbox from '../ui/ImageLightbox.vue'
import type { ChatAttachment } from '../../types'

const props = defineProps<{ attachments: ChatAttachment[] }>()

const images = computed(() => props.attachments.filter((att) => att.kind === 'image'))
const documents = computed(() => props.attachments.filter((att) => att.kind === 'document'))
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

const imageUrls = computed(() => images.value.map((att) => att.url))

const openLightbox = (index: number) => {
  lightboxIndex.value = index
  lightboxOpen.value = true
}
</script>

<template>
  <div class="space-y-2">
    <div v-if="images.length" class="grid grid-cols-2 gap-2">
      <button
        v-for="(img, idx) in images"
        :key="img.id"
        class="relative overflow-hidden rounded-xl border border-white/50"
        type="button"
        @click="openLightbox(idx)"
      >
        <img
          :src="img.thumbUrl || img.url"
          :alt="img.originalName"
          class="h-32 w-full object-cover"
          loading="lazy"
        />
        <div
          v-if="!img.thumbUrl"
          class="absolute inset-0 flex items-center justify-center bg-slate-900/60 text-xs font-semibold text-white"
        >
          Processing...
        </div>
      </button>
    </div>

    <div v-if="documents.length" class="flex flex-col gap-2">
      <a
        v-for="doc in documents"
        :key="doc.id"
        :href="doc.url"
        download
        target="_blank"
        rel="noopener"
        class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-800"
      >
        <span class="flex items-center gap-2">
          <FileText class="h-4 w-4 text-slate-500" />
          <span class="line-clamp-1">{{ doc.originalName }}</span>
        </span>
        <Download class="h-4 w-4 text-slate-500" />
      </a>
    </div>

    <ImageLightbox
      :images="imageUrls"
      :open="lightboxOpen"
      :index="lightboxIndex"
      alt="Chat attachment"
      @update:open="lightboxOpen = $event"
      @update:index="lightboxIndex = $event"
    />
  </div>
</template>
