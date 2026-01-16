<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertCircle, ArrowRight } from 'lucide-vue-next'
import ListingCard from '../components/listing/ListingCard.vue'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import CardSkeleton from '../components/ui/CardSkeleton.vue'
import Chip from '../components/ui/Chip.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useListingsStore } from '../stores/listings'

const router = useRouter()
const listingsStore = useListingsStore()

const categories = [
  { key: 'all', label: 'All' },
  { key: 'villa', label: 'Villas' },
  { key: 'hotel', label: 'Hotels' },
  { key: 'apartment', label: 'Apartments' },
]

onMounted(() => {
  listingsStore.fetchFavorites()
  listingsStore.fetchPopular()
  listingsStore.fetchRecommended()
})

const recommended = computed(() => listingsStore.filteredRecommended)
const popular = computed(() => listingsStore.popular)
const loading = computed(() => listingsStore.loading)
const error = computed(() => listingsStore.error)

const openListing = (listingId: string) => router.push(`/listing/${listingId}`)
</script>

<template>
  <section class="space-y-6">
    <ErrorBanner v-if="error" :message="error" />

    <div class="card-base flex items-center gap-3 px-4 py-3">
      <div class="rounded-2xl bg-primary/10 p-3 text-primary">
        <AlertCircle class="h-6 w-6" />
      </div>
      <div class="flex-1">
        <p class="text-base font-semibold text-slate-900">You can change your location anytime</p>
        <p class="text-sm text-muted">Tap the pin icon to adjust your stay</p>
      </div>
      <ArrowRight class="h-5 w-5 text-primary" />
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="section-title">Most Popular</h2>
      <button class="text-sm font-semibold text-primary" @click="router.push('/search')">See all</button>
    </div>
    <div class="flex gap-4 overflow-x-auto pb-2">
      <CardSkeleton v-if="loading && !popular.length" class="w-72 shrink-0" />
      <ListingCard
        v-for="item in popular"
        :key="item.id"
        class="w-72 shrink-0"
        :listing="item"
        @click="openListing(item.id)"
        @toggle="listingsStore.toggleFavorite"
      />
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="section-title">Recommended for you</h2>
      <button class="text-sm font-semibold text-primary" @click="router.push('/search')">See all</button>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
      <Chip
        v-for="cat in categories"
        :key="cat.key"
        :active="listingsStore.filters.category === cat.key"
        @click="listingsStore.setFilters({ category: cat.key as any })"
      >
        {{ cat.label }}
      </Chip>
    </div>

    <div class="space-y-3">
      <ListSkeleton v-if="loading && !recommended.length" :count="3" />
      <ListingCardHorizontal
        v-for="item in recommended"
        :key="item.id"
        :listing="item"
        @toggle="listingsStore.toggleFavorite"
        @click="openListing(item.id)"
      />
      <EmptyState v-if="!loading && !recommended.length && !error" title="No stays yet" subtitle="Try adjusting filters" />
    </div>
  </section>
</template>
