<script setup lang="ts">
import { computed } from 'vue'
import { Award, Heart, MapPin, ShieldCheck } from 'lucide-vue-next'
import type { Listing } from '../../types'
import { useLanguageStore } from '../../stores/language'

const props = withDefaults(defineProps<{ listing: Listing; useTranslations?: boolean }>(), {
  useTranslations: false,
})
const emit = defineEmits(['toggle', 'click'])

const toggle = (e: Event, id: string) => {
  e.stopPropagation()
  emit('toggle', id)
}

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const bedBathText = computed(() => {
  if (!props.useTranslations) {
    return `${props.listing.beds} beds · ${props.listing.baths} baths`
  }
  const bedLabel = props.listing.beds === 1 ? t('listing.bed') : t('listing.beds')
  const bathLabel = props.listing.baths === 1 ? t('listing.bath') : t('listing.baths')
  return `${props.listing.beds} ${bedLabel} · ${props.listing.baths} ${bathLabel}`
})

const nightLabel = computed(() => (props.useTranslations ? t('listing.night') : 'night'))
const verifiedLabel = computed(() => (props.useTranslations ? t('listing.verified') : 'Verified'))
const topLandlordLabel = computed(() => (props.useTranslations ? t('listing.topLandlord') : 'Top landlord'))
</script>

<template>
  <article
    data-testid="listing-card"
    class="card-base overflow-hidden transition hover:-translate-y-1 hover:shadow-card"
    @click="emit('click', listing)"
  >
    <div class="relative h-52 overflow-hidden">
      <img :src="listing.coverImage" :alt="listing.title" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10" />
      <button
        class="absolute right-3 top-3 rounded-full bg-white/90 p-2 text-primary shadow-soft backdrop-blur"
        @click="toggle($event, listing.id)"
      >
        <Heart :class="['h-5 w-5', listing.isFavorite ? 'fill-primary' : '']" />
      </button>
      <div class="absolute left-3 top-3 flex flex-col gap-2">
        <div
          v-if="listing.landlord?.verificationStatus === 'approved'"
          class="flex items-center gap-1 rounded-full bg-emerald-500/90 px-3 py-1 text-xs font-semibold text-white shadow-soft"
        >
          <ShieldCheck class="h-3.5 w-3.5" />
          {{ verifiedLabel }}
        </div>
        <div
          v-if="listing.landlord?.badges?.includes('top_landlord')"
          class="flex items-center gap-1 rounded-full bg-amber-500/90 px-3 py-1 text-xs font-semibold text-white shadow-soft"
        >
          <Award class="h-3.5 w-3.5" />
          {{ topLandlordLabel }}
        </div>
      </div>
      <div class="absolute bottom-3 left-3 right-3 flex items-end justify-between text-white">
        <div>
          <p class="text-lg font-semibold leading-tight">{{ listing.title }}</p>
          <div class="flex items-center gap-1 text-sm text-white/80">
            <MapPin class="h-4 w-4" />
            <span>{{ listing.city }}, {{ listing.country }}</span>
          </div>
        </div>
        <div class="rounded-pill bg-white/20 px-3 py-1 text-sm font-semibold">
          {{ listing.rating.toFixed(1) }} ★
        </div>
      </div>
    </div>
    <div class="space-y-1 px-4 py-3">
      <div class="flex items-center justify-between text-sm text-muted">
        <span>{{ bedBathText }}</span>
        <div class="flex items-center gap-2">
          <span v-if="listing.distanceKm !== undefined" class="rounded-full bg-surface px-3 py-1 text-xs font-semibold text-slate-700">
            {{ listing.distanceKm.toFixed(1) }} km
          </span>
          <span class="font-semibold text-primary">${{ listing.pricePerNight }}/{{ nightLabel }}</span>
        </div>
      </div>
    </div>
  </article>
</template>
