<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import RatingStars from '../components/ui/RatingStars.vue'
import { getListingById, getListingReviews } from '../services'
import type { Review } from '../types'
import { useLanguageStore } from '../stores/language'

const route = useRoute()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const reviews = ref<Review[]>([])
const rating = ref(4.4)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    const id = route.params.id as string
    reviews.value = await getListingReviews(id)
    const listing = await getListingById(id)
    if (listing) rating.value = listing.rating
  } catch (err) {
    error.value = (err as Error).message || t('reviews.loadFailed')
  } finally {
    loading.value = false
  }
})

const histogram = computed(() => [
  { stars: 5, count: 12 },
  { stars: 4, count: 9 },
  { stars: 3, count: 4 },
  { stars: 2, count: 1 },
  { stars: 1, count: 0 },
])

const maxCount = computed(() => Math.max(...histogram.value.map((h) => h.count), 1))
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />
    <template v-else>
      <div class="flex items-center justify-between rounded-2xl bg-white p-4 shadow-soft">
        <div class="space-y-1">
          <p class="text-3xl font-bold text-slate-900">{{ rating.toFixed(1) }}</p>
          <RatingStars :rating="rating" />
          <p class="text-sm text-muted">
            {{ t('reviews.basedOn') }} {{ reviews.length || 24 }} {{ t('reviews.countLabel') }}
          </p>
        </div>
        <div class="flex flex-col gap-2">
          <div v-for="row in histogram" :key="row.stars" class="flex items-center gap-2">
            <span class="w-4 text-xs font-semibold text-slate-800">{{ row.stars }}</span>
            <div class="h-2 flex-1 rounded-full bg-surface">
              <div
                class="h-2 rounded-full bg-primary"
                :style="{ width: `${(row.count / maxCount) * 100}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <div
          v-for="review in reviews"
          :key="review.id"
          class="rounded-2xl border border-line bg-white p-3 shadow-soft"
        >
          <div class="flex items-start gap-3">
            <img :src="review.avatarUrl" class="h-11 w-11 rounded-2xl object-cover" :alt="t('topbar.avatarAlt')" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold text-slate-900">{{ review.userName }}</p>
                  <p class="text-xs text-muted">{{ review.date }}</p>
                </div>
                <span class="rounded-pill bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{{ review.rating }} ★</span>
              </div>
              <p class="mt-2 text-sm text-slate-700 leading-relaxed">{{ review.text }}</p>
            </div>
          </div>
        </div>
        <EmptyState
          v-if="!reviews.length"
          :title="t('reviews.emptyTitle')"
          :subtitle="t('reviews.emptySubtitle')"
        />
      </div>
    </template>
  </div>
</template>
