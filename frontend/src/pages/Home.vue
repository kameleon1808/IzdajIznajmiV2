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
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const listingsStore = useListingsStore()
const languageStore = useLanguageStore()

const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const formatWhy = (reason: string) => {
  const becausePrefix = 'Because you viewed '
  if (reason.startsWith(becausePrefix)) {
    return `${t('home.becauseViewed')} ${reason.slice(becausePrefix.length)}`
  }
  const popularPrefix = 'Popular in '
  if (reason.startsWith(popularPrefix)) {
    return `${t('home.popularIn')} ${reason.slice(popularPrefix.length)}`
  }
  if (reason === 'Fits your budget') {
    return t('home.fitsBudget')
  }
  return reason
}

const formatWhyList = (reasons: string[]) => reasons.map((reason) => formatWhy(reason)).join(' - ')

const categories = computed(() => [
  { key: 'all', label: t('home.categoryAll') },
  { key: 'villa', label: t('home.categoryVilla') },
  { key: 'hotel', label: t('home.categoryHotel') },
  { key: 'apartment', label: t('home.categoryApartment') },
])

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
    <ErrorState v-if="error" :message="error" :retry-label="t('home.retry')" @retry="retryHome" />

    <div class="card-base px-4 py-3 bg-primary-soft border-primary">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-base font-semibold text-text">{{ t('home.browseByType') }}</p>
          <p class="text-xs text-muted">{{ t('home.quickFilters') }}</p>
        </div>
        <button class="text-xs font-semibold text-primary hover:text-primary-hover hover:underline" @click="router.push('/search')">
          {{ t('home.explore') }}
        </button>
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
      <h2 class="section-title">{{ t('home.mostPopular') }}</h2>
      <button class="text-sm font-semibold text-primary hover:text-primary-hover hover:underline" @click="router.push('/search')">
        {{ t('home.seeAll') }}
      </button>
    </div>
    <div class="flex gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-3 lg:gap-6 lg:overflow-visible lg:pb-0">
      <CardSkeleton v-if="loading && !popular.length" class="w-72 shrink-0 lg:w-full" />
      <ListingCard
        v-for="item in popular"
        :key="item.id"
        class="w-72 shrink-0 lg:w-full"
        :listing="item"
        :use-translations="true"
        @click="openListing(item.id)"
        @toggle="listingsStore.toggleFavorite"
      />
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="section-title">{{ t('home.recommended') }}</h2>
      <button class="text-sm font-semibold text-primary hover:text-primary-hover hover:underline" @click="router.push('/search')">
        {{ t('home.seeAll') }}
      </button>
    </div>

    <div class="space-y-3 lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
      <ListSkeleton v-if="loading && !recommended.length" :count="3" class="lg:col-span-2" />
      <div v-for="item in recommended" :key="item.id" class="space-y-1">
        <ListingCardHorizontal
          :listing="item"
          :use-translations="true"
          @toggle="listingsStore.toggleFavorite"
          @click="openListing(item.id)"
        />
        <p v-if="item.why?.length" class="px-2 text-xs text-muted">
          {{ t('home.whyThis') }} {{ formatWhyList(item.why) }}
        </p>
      </div>
      <EmptyState
        v-if="!loading && !recommended.length && !error"
        :title="t('home.noStaysYet')"
        :subtitle="t('home.tryAdjusting')"
        class="lg:col-span-2"
      />
    </div>
  </section>
</template>
