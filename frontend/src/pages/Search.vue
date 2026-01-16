<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Flame, History, MapPin, Search as SearchIcon, SlidersHorizontal } from 'lucide-vue-next'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Chip from '../components/ui/Chip.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import { useListingsStore } from '../stores/listings'
import type { ListingFilters } from '../types'

const listingsStore = useListingsStore()
const router = useRouter()

const searchQuery = ref('')
const filterOpen = ref(false)
const localFilters = ref<ListingFilters>({
  ...listingsStore.filters,
  priceRange: [...listingsStore.filters.priceRange] as [number, number],
  facilities: [...listingsStore.filters.facilities],
})

onMounted(() => {
  listingsStore.fetchRecommended()
  listingsStore.fetchPopular()
})

watch(filterOpen, (open) => {
  if (open)
    localFilters.value = {
      ...listingsStore.filters,
      priceRange: [...listingsStore.filters.priceRange] as [number, number],
      facilities: [...listingsStore.filters.facilities],
    }
})

const results = computed(() => (searchQuery.value ? listingsStore.searchResults : listingsStore.filteredRecommended))
const popular = computed(() => listingsStore.popular)

const runSearch = () => listingsStore.search(searchQuery.value || '')
const applyFilters = () => {
  listingsStore.setFilters(localFilters.value)
  filterOpen.value = false
  runSearch()
}
</script>

<template>
  <div class="space-y-5">
    <Input
      v-model="searchQuery"
      class="w-full"
      placeholder="Search location or stay"
      :left-icon="SearchIcon"
      :right-icon="SlidersHorizontal"
      @rightIconClick="filterOpen = true"
      @focus="runSearch"
    />

    <div class="flex items-center justify-between px-1">
      <h3 class="section-title">Recent searches</h3>
      <button class="text-sm text-primary" @click="listingsStore.recentSearches = []">Clear</button>
    </div>
    <div class="flex flex-wrap gap-2">
      <Chip
        v-for="item in listingsStore.recentSearches"
        :key="item"
        :active="searchQuery === item"
        @click="searchQuery = item; runSearch()"
      >
        <History class="mr-2 h-4 w-4" />
        {{ item }}
      </Chip>
    </div>

    <div class="flex items-center justify-between px-1">
      <h3 class="section-title">Recently viewed</h3>
      <button class="text-sm font-semibold text-primary" @click="router.push('/favorites')">See all</button>
    </div>
    <div class="grid grid-cols-1 gap-3">
      <ListingCardHorizontal
        v-for="item in popular"
        :key="item.id"
        :listing="item"
        @toggle="listingsStore.toggleFavorite"
        @click="router.push(`/listing/${item.id}`)"
      />
    </div>

    <div class="flex items-center justify-between px-1">
      <h3 class="section-title">Results</h3>
      <div class="flex items-center gap-2 text-xs text-muted">
        <Flame class="h-4 w-4 text-primary" />
        Dynamic based on filters
      </div>
    </div>
    <div class="space-y-3">
      <ListingCardHorizontal
        v-for="item in results"
        :key="item.id"
        :listing="item"
        @toggle="listingsStore.toggleFavorite"
        @click="router.push(`/listing/${item.id}`)"
      />
      <p v-if="!results.length" class="text-center text-muted">No results yet. Try another filter.</p>
    </div>
  </div>

  <ModalSheet v-model="filterOpen" title="Filter by">
    <div class="space-y-4">
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Guests</p>
          <span class="text-sm text-muted">{{ localFilters.guests }} people</span>
        </div>
        <div class="flex gap-2">
          <Button size="md" variant="secondary" @click="localFilters.guests = Math.max(1, localFilters.guests - 1)">-</Button>
          <Button size="md" variant="secondary" class="flex-1">{{ localFilters.guests }}</Button>
          <Button size="md" variant="secondary" @click="localFilters.guests = localFilters.guests + 1">+</Button>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Price range</p>
          <span class="text-sm text-muted">${{ localFilters.priceRange[0] }} - ${{ localFilters.priceRange[1] }}</span>
        </div>
        <div class="flex items-center gap-3">
          <input
            v-model.number="localFilters.priceRange[0]"
            type="range"
            min="50"
            max="400"
            class="w-full accent-primary"
          />
          <input
            v-model.number="localFilters.priceRange[1]"
            type="range"
            min="100"
            max="500"
            class="w-full accent-primary"
          />
        </div>
      </div>

      <div class="flex items-center justify-between rounded-2xl bg-surface px-4 py-3">
        <div>
          <p class="font-semibold text-slate-900">Instant book</p>
          <p class="text-sm text-muted">Book without waiting</p>
        </div>
        <label class="relative inline-flex cursor-pointer items-center">
          <input v-model="localFilters.instantBook" type="checkbox" class="peer sr-only" />
          <div
            class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-[18px]"
          ></div>
        </label>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Location</p>
        <div class="flex flex-wrap gap-2">
          <Chip v-for="city in ['Bali', 'Lisbon', 'Tulum', 'Copenhagen']" :key="city" :active="localFilters.location === city" @click="localFilters.location = city">
            <MapPin class="mr-2 h-4 w-4" />
            {{ city }}
          </Chip>
        </div>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Facilities</p>
        <div class="grid grid-cols-2 gap-2">
          <label
            v-for="facility in ['Pool', 'Spa', 'Wi-Fi', 'Breakfast', 'Parking', 'Kitchen']"
            :key="facility"
            class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-slate-800"
          >
            <input
              v-model="localFilters.facilities"
              :value="facility"
              type="checkbox"
              class="h-4 w-4 accent-primary"
            />
            {{ facility }}
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Rating</p>
        <div class="flex gap-2">
          <Chip
            v-for="rate in [5, 4, 3, 2, 1]"
            :key="rate"
            :active="localFilters.rating === rate"
            @click="localFilters.rating = rate"
          >
            {{ rate }}+
          </Chip>
          <Chip :active="localFilters.rating === null" @click="localFilters.rating = null">Any</Chip>
        </div>
      </div>

      <Button block size="lg" @click="applyFilters">Apply Filters</Button>
    </div>
  </ModalSheet>
</template>
