<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import RatingStars from '../components/ui/RatingStars.vue'
import { getListingById, getListingReviews } from '../services/mockApi'
import type { Review } from '../types'

const route = useRoute()
const reviews = ref<Review[]>([])
const rating = ref(4.4)

onMounted(async () => {
  const id = route.params.id as string
  reviews.value = await getListingReviews(id)
  const listing = await getListingById(id)
  if (listing) rating.value = listing.rating
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
    <div class="flex items-center justify-between rounded-2xl bg-white p-4 shadow-soft">
      <div class="space-y-1">
        <p class="text-3xl font-bold text-slate-900">{{ rating.toFixed(1) }}</p>
        <RatingStars :rating="rating" />
        <p class="text-sm text-muted">Based on {{ reviews.length || 24 }} reviews</p>
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
          <img :src="review.avatarUrl" class="h-11 w-11 rounded-2xl object-cover" alt="avatar" />
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
      <p v-if="!reviews.length" class="text-center text-muted">No reviews yet.</p>
    </div>
  </div>
</template>
