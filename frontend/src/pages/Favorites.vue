<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Heart, Search as SearchIcon, SlidersHorizontal } from 'lucide-vue-next'
import Chip from '../components/ui/Chip.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Input from '../components/ui/Input.vue'
import { useListingsStore } from '../stores/listings'

const listingsStore = useListingsStore()
const router = useRouter()
const category = ref<'all' | 'villa' | 'hotel' | 'apartment'>('all')
const query = ref('')

onMounted(() => {
  listingsStore.fetchFavorites()
})

const favorites = computed(() => {
  return listingsStore.favoriteListings.filter((item) => {
    const matchCategory = category.value === 'all' ? true : item.category === category.value
    const matchQuery = query.value
      ? item.title.toLowerCase().includes(query.value.toLowerCase())
      : true
    return matchCategory && matchQuery
  })
})
const loading = computed(() => listingsStore.favoritesLoading)
const error = computed(() => listingsStore.error)
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <Input
      v-model="query"
      placeholder="Search favorites"
      :left-icon="SearchIcon"
      :right-icon="SlidersHorizontal"
      @rightIconClick="category = 'all'"
    />

    <div class="flex gap-2 overflow-x-auto pb-1">
      <Chip v-for="cat in ['all', 'villa', 'hotel', 'apartment']" :key="cat" :active="category === cat" @click="category = cat as any">
        {{ cat === 'all' ? 'All' : cat.charAt(0).toUpperCase() + cat.slice(1) + 's' }}
      </Chip>
    </div>

    <ListSkeleton v-if="loading" :count="4" />
    <div v-else class="grid grid-cols-2 gap-3">
      <div
        v-for="item in favorites"
        :key="item.id"
        class="overflow-hidden rounded-2xl bg-white shadow-soft border border-white/60 cursor-pointer"
        @click="router.push(`/listing/${item.id}`)"
      >
        <div class="relative h-32 w-full overflow-hidden">
          <img :src="item.coverImage" :alt="item.title" class="h-full w-full object-cover" />
          <button
            class="absolute right-2 top-2 rounded-full bg-white/90 p-2 text-primary shadow-soft"
            @click="listingsStore.toggleFavorite(item.id)"
          >
            <Heart class="h-4 w-4 fill-primary" />
          </button>
          <div class="absolute bottom-2 left-2 rounded-pill bg-black/50 px-3 py-1 text-xs font-semibold text-white">
            {{ item.rating.toFixed(1) }} ★
          </div>
        </div>
        <div class="space-y-1 p-3">
          <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
          <p class="text-xs text-muted">${{ item.pricePerNight }}/night</p>
        </div>
      </div>
    </div>

    <EmptyState
      v-if="!favorites.length && !loading && !error"
      title="No favorites yet"
      subtitle="Tap the heart on a stay to save it"
      :icon="Heart"
    />
  </div>
</template>
