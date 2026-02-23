<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { MapPin, MessageSquare, Navigation, Search as SearchIcon } from 'lucide-vue-next'
import CardSkeleton from '../components/ui/CardSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import { useListingsStore } from '../stores/listings'
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const listingsStore = useListingsStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const query = ref('')

onMounted(() => {
  if (!listingsStore.recommended.length) listingsStore.fetchRecommended()
  if (!listingsStore.popular.length) listingsStore.fetchPopular()
})

const highlighted = computed(() => listingsStore.filteredRecommended[0] ?? listingsStore.popular[0])
const loading = computed(() => listingsStore.loading)
const error = computed(() => listingsStore.error)
</script>

<template>
  <div class="relative min-h-screen bg-gradient-to-b from-surface via-white to-surface">
    <div class="absolute left-0 right-0 top-4 z-30 px-4">
      <Input
        v-model="query"
        :placeholder="t('map.searchPlaceholder')"
        :left-icon="SearchIcon"
        class="shadow-card"
        @focus="router.push('/search')"
      />
    </div>

    <div class="relative h-[70vh] w-full overflow-hidden rounded-b-[28px] bg-[radial-gradient(circle_at_20%_20%,#E8F1FF,#dbeafe_35%,#cbd5e1)]">
      <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=60')] bg-cover bg-center opacity-40" />
      <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-white/50" />
      <div class="absolute inset-6 rounded-3xl border border-white/40" />

      <div class="absolute left-1/4 top-1/3 flex flex-col items-center gap-1 rounded-2xl bg-white px-3 py-2 shadow-card">
        <div class="h-10 w-10 overflow-hidden rounded-full border border-white/80">
          <img
            src="https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=200&q=80"
            :alt="t('map.markerAlt')"
            class="h-full w-full object-cover"
          />
        </div>
        <div class="flex items-center gap-1 text-xs font-semibold text-slate-900">
          <MapPin class="h-4 w-4 text-primary" />
          <span>4.8</span>
        </div>
      </div>

      <div class="absolute right-10 top-20 flex items-center gap-2 rounded-pill bg-white px-3 py-2 shadow-soft">
        <Navigation class="h-4 w-4 text-primary" />
        <span class="text-xs font-semibold text-slate-800">{{ t('map.live') }}</span>
      </div>
    </div>

    <div class="relative -mt-12 px-4">
      <ErrorBanner v-if="error" :message="error" />
      <CardSkeleton v-else-if="loading" />
      <template v-else-if="highlighted">
        <div class="card-base flex items-center gap-3 p-3">
          <div class="h-20 w-24 overflow-hidden rounded-2xl">
            <img :src="highlighted?.coverImage" alt="listing" class="h-full w-full object-cover" />
          </div>
          <div class="flex flex-1 flex-col gap-1">
            <div class="flex items-center gap-1 text-xs text-muted">
              <MapPin class="h-4 w-4 text-primary" />
              <span>{{ highlighted?.city }}, {{ highlighted?.country }}</span>
            </div>
            <h3 class="text-base font-semibold text-slate-900">{{ highlighted?.title }}</h3>
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-primary">€{{ highlighted?.pricePerMonth }}/{{ t('listing.month') }}</span>
              <div class="flex items-center gap-1 text-xs text-muted">
                <MapPin class="h-4 w-4 text-primary" />
                <span>{{ highlighted?.rating?.toFixed(1) }} {{ t('map.ratingLabel') }}</span>
              </div>
            </div>
          </div>
          <button class="rounded-2xl bg-primary/10 p-3 text-primary" :aria-label="t('map.message')">
            <MessageSquare class="h-5 w-5" />
          </button>
        </div>

        <div class="mt-3 flex gap-2">
          <Button block size="lg" @click="router.push(`/listing/${highlighted?.id ?? '1'}`)">
            {{ t('map.sendInquiry') }}
          </Button>
        </div>
      </template>
      <EmptyState
        v-else
        :title="t('map.emptyTitle')"
        :subtitle="t('map.emptySubtitle')"
        :icon="Navigation"
      />
    </div>
  </div>
</template>
