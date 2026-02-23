<script setup lang="ts">
import { computed } from 'vue'
import { Award, Heart, MapPin, ShieldCheck, Star } from 'lucide-vue-next'
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

const monthLabel = computed(() => (props.useTranslations ? t('listing.month') : 'month'))
const verifiedLabel = computed(() => (props.useTranslations ? t('listing.verifiedLandlord') : 'Verified landlord'))
const topLandlordLabel = computed(() => (props.useTranslations ? t('listing.topLandlord') : 'Top landlord'))
const categoryLabel = computed(() => {
  if (!props.useTranslations) return props.listing.category
  if (props.listing.category === 'villa') return t('listing.categoryVilla')
  if (props.listing.category === 'hotel') return t('listing.categoryHotel')
  if (props.listing.category === 'house') return t('listing.categoryHouse')
  if (props.listing.category === 'apartment') return t('listing.categoryApartment')
  return props.listing.category
})
</script>

<template>
  <div
    data-testid="listing-card-horizontal"
    class="flex h-32 gap-3 overflow-hidden rounded-[22px] bg-surface-2 p-3 shadow-card border border-border lg:h-52 lg:gap-5 lg:p-5"
    @click="emit('click', listing)"
  >
    <div class="relative h-full w-24 shrink-0 overflow-hidden rounded-2xl lg:w-40">
      <img :src="listing.coverImage" :alt="listing.title" class="h-full w-full object-cover" />
      <button
        class="absolute right-2 top-2 rounded-full bg-surface-2 p-1.5 text-primary shadow-soft border border-border transition-colors duration-150 hover:bg-primary-soft lg:right-3 lg:top-3 lg:p-2"
        @click="toggle($event, listing.id)"
      >
        <Heart :class="['h-4 w-4 lg:h-5 lg:w-5', listing.isFavorite ? 'fill-primary' : '']" />
      </button>
    </div>
    <div class="flex min-w-0 flex-1 flex-col justify-between gap-2 lg:gap-3">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
          <h3 class="line-clamp-2 text-base font-semibold text-text leading-tight lg:text-lg">
            {{ listing.title }}
          </h3>
          <div class="flex min-w-0 items-center gap-1 text-xs text-muted lg:text-sm">
            <MapPin class="h-4 w-4 text-primary lg:h-5 lg:w-5" />
            <span class="truncate">{{ listing.city }}, {{ listing.country }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-1">
            <div
              v-if="listing.landlord?.verificationStatus === 'approved'"
              class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 lg:px-3 lg:py-1 lg:text-xs"
            >
              <ShieldCheck class="h-3 w-3 lg:h-4 lg:w-4" />
              {{ verifiedLabel }}
            </div>
            <div
              v-if="listing.landlord?.badges?.includes('top_landlord')"
              class="inline-flex items-center gap-1 rounded-full bg-amber-soft px-2 py-0.5 text-[11px] font-semibold text-amber lg:px-3 lg:py-1 lg:text-xs"
            >
              <Award class="h-3 w-3 lg:h-4 lg:w-4" />
              {{ topLandlordLabel }}
            </div>
          </div>
        </div>
        <div class="flex items-center gap-1 rounded-pill bg-info-soft px-2 py-1 text-sm font-semibold text-info-text lg:text-base">
          <Star class="h-4 w-4 fill-info-text text-info-text lg:h-5 lg:w-5" />
          <span>{{ listing.rating.toFixed(1) }}</span>
        </div>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm text-muted lg:text-base">€{{ listing.pricePerMonth }}/{{ monthLabel }}</span>
        <span class="rounded-pill bg-primary-soft px-3 py-1 text-xs font-semibold capitalize text-primary lg:px-4 lg:py-1.5 lg:text-sm">
          {{ categoryLabel }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
