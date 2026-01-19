<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useRouter } from 'vue-router'
import { Flame, History, MapPin, Search as SearchIcon, SlidersHorizontal } from 'lucide-vue-next'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Chip from '../components/ui/Chip.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useListingsStore } from '../stores/listings'
import type { ListingFilters } from '../types'

const listingsStore = useListingsStore()
const router = useRouter()

const searchQuery = ref('')
const filterOpen = ref(false)
const localFilters = ref<ListingFilters & { areaRange: [number, number] }>({
  ...listingsStore.filters,
  priceRange: [...listingsStore.filters.priceRange] as [number, number],
  facilities: [...listingsStore.filters.facilities],
  amenities: [...(listingsStore.filters.amenities ?? [])],
  areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
})
const currentQuery = ref('')
const debouncedSearch = useDebounceFn(() => runSearch(), 300)
const currentQueryValue = computed(() => currentQuery.value)

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
      amenities: [...(listingsStore.filters.amenities ?? [])],
      areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
    }
})

const results = computed(() => (searchQuery.value ? listingsStore.searchResults : listingsStore.filteredRecommended))
const popular = computed(() => listingsStore.popular.slice(0, 2))
const loading = computed(() => listingsStore.loading)
const loadingMore = computed(() => listingsStore.loadingMore)
const error = computed(() => listingsStore.error)

const runSearch = () => {
  currentQuery.value = searchQuery.value || ''
  listingsStore.search(currentQuery.value)
}
const applyFilters = () => {
  localFilters.value.amenities = [...(localFilters.value.amenities ?? localFilters.value.facilities ?? [])]
  localFilters.value.facilities = [...(localFilters.value.amenities ?? [])]
  listingsStore.setFilters(localFilters.value)
  filterOpen.value = false
  runSearch()
}

const resetFilters = () => {
  listingsStore.resetFilters()
  localFilters.value = {
    ...listingsStore.filters,
    priceRange: [...listingsStore.filters.priceRange] as [number, number],
    facilities: [...listingsStore.filters.facilities],
    amenities: [...(listingsStore.filters.amenities ?? [])],
    areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
  }
  filterOpen.value = false
  runSearch()
}

watch(searchQuery, () => {
  debouncedSearch()
})
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner v-if="error" :message="error" />
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
      <ListSkeleton v-if="loading && !popular.length" :count="2" />
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
      <ListSkeleton v-if="loading && !results.length" :count="3" />
      <ListingCardHorizontal
        v-for="item in results"
        :key="item.id"
        :listing="item"
        @toggle="listingsStore.toggleFavorite"
        @click="router.push(`/listing/${item.id}`)"
      />
      <EmptyState
        v-if="!loading && !results.length && !error"
        title="No results yet"
        subtitle="Try adjusting filters or search text"
        :icon="SearchIcon"
      />
      <div class="flex justify-center">
        <Button
          v-if="listingsStore.searchMeta && listingsStore.searchMeta.current_page < listingsStore.searchMeta.last_page"
          :loading="loadingMore"
          variant="secondary"
          @click="listingsStore.loadMoreSearch(currentQueryValue)"
        >
          Load more
        </Button>
      </div>
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
        <div class="grid grid-cols-2 gap-3">
          <Input
            v-model="localFilters.priceRange[0]"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0"
            placeholder="Min"
          />
          <Input
            v-model="localFilters.priceRange[1]"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0"
            placeholder="Max"
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
        <Input v-model="localFilters.city" class="mt-2" placeholder="City contains (e.g. Zagreb)" />
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Rooms</p>
          <span class="text-sm text-muted">{{ localFilters.rooms ?? 0 }}+</span>
        </div>
        <div class="flex gap-2">
          <Button size="md" variant="secondary" @click="localFilters.rooms = Math.max(0, (localFilters.rooms ?? 0) - 1)">-</Button>
          <Button size="md" variant="secondary" class="flex-1">{{ localFilters.rooms ?? 0 }}</Button>
          <Button size="md" variant="secondary" @click="localFilters.rooms = (localFilters.rooms ?? 0) + 1">+</Button>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Area (sqm)</p>
          <span class="text-sm text-muted">{{ localFilters.areaRange[0] }} - {{ localFilters.areaRange[1] }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <Input v-model.number="localFilters.areaRange[0]" type="number" min="0" placeholder="Min" />
          <Input v-model.number="localFilters.areaRange[1]" type="number" min="0" placeholder="Max" />
        </div>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Amenities</p>
        <div class="grid grid-cols-2 gap-2">
          <label
            v-for="facility in ['Pool', 'Spa', 'Wi-Fi', 'Breakfast', 'Parking', 'Kitchen', 'Workspace']"
            :key="facility"
            class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-slate-800"
          >
            <input
              v-model="localFilters.amenities"
              :value="facility"
              type="checkbox"
              class="h-4 w-4 accent-primary"
            />
            {{ facility }}
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Status</p>
        <div class="flex flex-wrap gap-2">
          <Chip
            v-for="status in ['all', 'active', 'paused', 'rented', 'archived', 'expired']"
            :key="status"
            :active="localFilters.status === status"
            @click="localFilters.status = status as any"
          >
            {{ status === 'all' ? 'Any' : status }}
          </Chip>
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
      <Button block size="lg" variant="secondary" @click="resetFilters">Reset Filters</Button>
    </div>
  </ModalSheet>
</template>
