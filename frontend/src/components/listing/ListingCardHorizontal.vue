<script setup lang="ts">
import { Award, Heart, MapPin, ShieldCheck, Star } from 'lucide-vue-next'
import type { Listing } from '../../types'

defineProps<{ listing: Listing }>()
const emit = defineEmits(['toggle', 'click'])

const toggle = (e: Event, id: string) => {
  e.stopPropagation()
  emit('toggle', id)
}
</script>

<template>
  <div
    data-testid="listing-card-horizontal"
    class="flex gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60 lg:gap-5 lg:p-5"
    @click="emit('click', listing)"
  >
    <div class="relative h-24 w-24 overflow-hidden rounded-2xl lg:h-40 lg:w-40">
      <img :src="listing.coverImage" :alt="listing.title" class="h-full w-full object-cover" />
      <button
        class="absolute right-2 top-2 rounded-full bg-white/90 p-1.5 text-primary shadow-soft lg:right-3 lg:top-3 lg:p-2"
        @click="toggle($event, listing.id)"
      >
        <Heart :class="['h-4 w-4 lg:h-5 lg:w-5', listing.isFavorite ? 'fill-primary' : '']" />
      </button>
    </div>
    <div class="flex flex-1 flex-col gap-1 lg:gap-2">
      <div class="flex items-start justify-between">
        <div class="space-y-1">
          <h3 class="text-base font-semibold text-slate-900 leading-tight lg:text-lg">{{ listing.title }}</h3>
          <div class="flex items-center gap-1 text-xs text-muted lg:text-sm">
            <MapPin class="h-4 w-4 text-primary lg:h-5 lg:w-5" />
            <span>{{ listing.city }}, {{ listing.country }}</span>
          </div>
          <div class="flex flex-wrap gap-1">
            <div
              v-if="listing.landlord?.verificationStatus === 'approved'"
              class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 lg:px-3 lg:py-1 lg:text-xs"
            >
              <ShieldCheck class="h-3 w-3 lg:h-4 lg:w-4" />
              Verified landlord
            </div>
            <div
              v-if="listing.landlord?.badges?.includes('top_landlord')"
              class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 lg:px-3 lg:py-1 lg:text-xs"
            >
              <Award class="h-3 w-3 lg:h-4 lg:w-4" />
              Top landlord
            </div>
          </div>
        </div>
        <div class="flex items-center gap-1 text-sm font-semibold text-slate-900 lg:text-base">
          <Star class="h-4 w-4 fill-primary text-primary lg:h-5 lg:w-5" />
          <span>{{ listing.rating.toFixed(1) }}</span>
        </div>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm text-muted lg:text-base">${{ listing.pricePerNight }}/night</span>
        <span class="rounded-pill bg-primary/10 px-3 py-1 text-xs font-semibold capitalize text-primary lg:px-4 lg:py-1.5 lg:text-sm">
          {{ listing.category }}
        </span>
      </div>
    </div>
  </div>
</template>
