<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ListingCard from '../components/listing/ListingCard.vue'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import CardSkeleton from '../components/ui/CardSkeleton.vue'
import Chip from '../components/ui/Chip.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
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
const popular = computed(() => listingsStore.popular.slice(0, 6))
const loading = computed(() => listingsStore.loading)
const error = computed(() => listingsStore.error)

const openListing = (listingId: string) => router.push(`/listing/${listingId}`)

const retryHome = async () => {
  listingsStore.error = ''
  await Promise.all([
    listingsStore.fetchFavorites(),
    listingsStore.fetchPopular(),
    listingsStore.fetchRecommended(),
  ])
}
</script>

<template>
  <section class="space-y-6 lg:space-y-8">
    <ErrorState v-if="error" :message="error" retry-label="Retry" @retry="retryHome" />

    <div class="card-base px-4 py-3">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-base font-semibold text-slate-900">Browse by type</p>
          <p class="text-xs text-muted">Quick filters to narrow your stay.</p>
        </div>
        <button class="text-xs font-semibold text-primary" @click="router.push('/search')">Explore</button>
      </div>
      <div class="mt-3 flex gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-visible">
        <Chip
          v-for="cat in categories"
          :key="cat.key"
          :active="listingsStore.filters.category === cat.key"
          @click="listingsStore.setFilters({ category: cat.key as any })"
        >
          {{ cat.label }}
        </Chip>
      </div>
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="section-title">Most Popular</h2>
      <button class="text-sm font-semibold text-primary" @click="router.push('/search')">See all</button>
    </div>
    <div class="flex gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-3 lg:gap-6 lg:overflow-visible lg:pb-0">
      <CardSkeleton v-if="loading && !popular.length" class="w-72 shrink-0 lg:w-full" />
      <ListingCard
        v-for="item in popular"
        :key="item.id"
        class="w-72 shrink-0 lg:w-full"
        :listing="item"
        @click="openListing(item.id)"
        @toggle="listingsStore.toggleFavorite"
      />
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="section-title">Recommended for you</h2>
      <button class="text-sm font-semibold text-primary" @click="router.push('/search')">See all</button>
    </div>

    <div class="space-y-3 lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
      <ListSkeleton v-if="loading && !recommended.length" :count="3" class="lg:col-span-2" />
      <div v-for="item in recommended" :key="item.id" class="space-y-1">
        <ListingCardHorizontal
          :listing="item"
          @toggle="listingsStore.toggleFavorite"
          @click="openListing(item.id)"
        />
        <p v-if="item.why?.length" class="px-2 text-xs text-muted">Why this? {{ item.why.join(' - ') }}</p>
      </div>
      <EmptyState
        v-if="!loading && !recommended.length && !error"
        title="No stays yet"
        subtitle="Try adjusting filters"
        class="lg:col-span-2"
      />
    </div>
  </section>
</template>
