<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'
import { Flame, History, Map as MapIcon, MapPin, List as ListIcon, Search as SearchIcon, SlidersHorizontal } from 'lucide-vue-next'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import ListingCard from '../components/listing/ListingCard.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Chip from '../components/ui/Chip.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { geocodeLocation } from '../services'
import { defaultFilters, useListingsStore } from '../stores/listings'
import type { ListingFilters } from '../types'

const MapExplorer = defineAsyncComponent(() => import('../components/search/MapExplorer.vue'))

const listingsStore = useListingsStore()
const router = useRouter()
const route = useRoute()

const viewMode = ref<'list' | 'map'>((route.query.view as string) === 'map' ? 'map' : 'list')
const searchQuery = ref<string>((route.query.q as string) ?? '')
const filterOpen = ref(false)
const geocoding = ref(false)
const geocodeError = ref('')
const defaultCenter = { lat: 44.8125, lng: 20.4612 } // Belgrade
const localFilters = ref<ListingFilters & { areaRange: [number, number] }>({
  ...listingsStore.filters,
  priceRange: [...listingsStore.filters.priceRange] as [number, number],
  facilities: [...listingsStore.filters.facilities],
  amenities: [...(listingsStore.filters.amenities ?? [])],
  areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
  radiusKm: listingsStore.filters.radiusKm ?? defaultFilters.radiusKm,
})
const currentQuery = ref('')
const debouncedSearch = useDebounceFn(() => runSearch(), 300)
const currentQueryValue = computed(() => currentQuery.value)

const results = computed(() =>
  viewMode.value === 'map' ? listingsStore.searchResults : searchQuery.value ? listingsStore.searchResults : listingsStore.filteredRecommended,
)
const popular = computed(() => listingsStore.popular.slice(0, 2))
const loading = computed(() => listingsStore.loading)
const loadingMore = computed(() => listingsStore.loadingMore)
const error = computed(() => listingsStore.error)
const mapCenter = computed(() =>
  listingsStore.filters.centerLat != null && listingsStore.filters.centerLng != null
    ? { lat: listingsStore.filters.centerLat, lng: listingsStore.filters.centerLng }
    : null,
)
const radiusValue = computed<number>(() => listingsStore.filters.radiusKm ?? defaultFilters.radiusKm ?? 0)
const inlineRadius = ref(radiusValue.value)
const missingGeoCount = computed(() => results.value.filter((item) => item.lat == null || item.lng == null).length)

watch(radiusValue, (val) => {
  inlineRadius.value = val
})

const hydrateFromRoute = () => {
  const query = route.query
  searchQuery.value = typeof query.q === 'string' ? query.q : ''
  viewMode.value = query.view === 'map' ? 'map' : 'list'

  const parsed: Partial<ListingFilters> = {}
  if (query.category) parsed.category = query.category as ListingFilters['category']
  if (query.city) parsed.city = String(query.city)
  if (query.location) parsed.location = String(query.location)
  if (query.guests) parsed.guests = Number(query.guests)
  if (query.priceMin) parsed.priceRange = [Number(query.priceMin), listingsStore.filters.priceRange[1]] as [number, number]
  if (query.priceMax) parsed.priceRange = [listingsStore.filters.priceRange[0], Number(query.priceMax)] as [number, number]
  if (query.rooms) parsed.rooms = Number(query.rooms)
  if (query.areaMin) parsed.areaRange = [Number(query.areaMin), listingsStore.filters.areaRange?.[1] ?? 100000] as [number, number]
  if (query.areaMax) parsed.areaRange = [listingsStore.filters.areaRange?.[0] ?? 0, Number(query.areaMax)] as [number, number]
  if (query.status) parsed.status = query.status as any
  if (query.instantBook) parsed.instantBook = query.instantBook === '1' || query.instantBook === 'true'
  if (query.rating) parsed.rating = Number(query.rating)
  const facilities = query.facilities ? ([] as string[]).concat(query.facilities as any) : []
  if (facilities.length) parsed.facilities = facilities
  const amenities = query.amenities ? ([] as string[]).concat(query.amenities as any) : []
  if (amenities.length) parsed.amenities = amenities
  if (query.centerLat && query.centerLng) {
    parsed.centerLat = Number(query.centerLat)
    parsed.centerLng = Number(query.centerLng)
  }
  if (query.radiusKm) parsed.radiusKm = Number(query.radiusKm)

  listingsStore.setFilters({ ...listingsStore.filters, ...parsed }, { fetch: false })

  if (!parsed.centerLat && !parsed.centerLng && (listingsStore.filters.centerLat == null || listingsStore.filters.centerLng == null)) {
    listingsStore.updateGeoFilters(defaultCenter.lat, defaultCenter.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
  }

  localFilters.value = {
    ...listingsStore.filters,
    priceRange: [...listingsStore.filters.priceRange] as [number, number],
    facilities: [...listingsStore.filters.facilities],
    amenities: [...(listingsStore.filters.amenities ?? [])],
    areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
    radiusKm: listingsStore.filters.radiusKm ?? defaultFilters.radiusKm,
  }
}

const buildQueryFromState = () => {
  const f = listingsStore.filters
  const nextQuery: Record<string, any> = {}
  if (searchQuery.value) nextQuery.q = searchQuery.value
  if (viewMode.value === 'map') nextQuery.view = 'map'
  if (f.category !== defaultFilters.category) nextQuery.category = f.category
  if (f.guests !== defaultFilters.guests) nextQuery.guests = f.guests
  if (f.priceRange?.[0] !== defaultFilters.priceRange[0]) nextQuery.priceMin = f.priceRange?.[0]
  if (f.priceRange?.[1] !== defaultFilters.priceRange[1]) nextQuery.priceMax = f.priceRange?.[1]
  if (f.instantBook) nextQuery.instantBook = '1'
  if (f.location) nextQuery.location = f.location
  if (f.city) nextQuery.city = f.city
  if (f.rooms) nextQuery.rooms = f.rooms
  if (f.areaRange?.[0] !== defaultFilters.areaRange?.[0]) nextQuery.areaMin = f.areaRange?.[0]
  if (f.areaRange?.[1] !== defaultFilters.areaRange?.[1]) nextQuery.areaMax = f.areaRange?.[1]
  if (f.facilities?.length) nextQuery.facilities = f.facilities
  if (f.amenities?.length) nextQuery.amenities = f.amenities
  if (f.rating) nextQuery.rating = f.rating
  if (f.status && f.status !== 'all') nextQuery.status = f.status
  if (f.centerLat != null && f.centerLng != null) {
    nextQuery.centerLat = f.centerLat
    nextQuery.centerLng = f.centerLng
  }
  if (f.radiusKm && f.radiusKm !== defaultFilters.radiusKm) nextQuery.radiusKm = f.radiusKm
  if (route.query.savedSearchId) nextQuery.savedSearchId = route.query.savedSearchId
  return nextQuery
}

const syncQueryParams = () => {
  const desired = buildQueryFromState()
  const current = { ...route.query }
  if (JSON.stringify(desired) === JSON.stringify(current)) return
  router.replace({ query: desired })
}

const ensureMapCenter = async () => {
  if (listingsStore.filters.centerLat != null && listingsStore.filters.centerLng != null) return
  const queryText = listingsStore.filters.city || listingsStore.filters.location || searchQuery.value
  if (!queryText) {
    listingsStore.updateGeoFilters(defaultCenter.lat, defaultCenter.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
    syncQueryParams()
    return
  }
  geocodeError.value = ''
  geocoding.value = true
  try {
    const { lat, lng } = await geocodeLocation(queryText)
    listingsStore.updateGeoFilters(lat, lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
    syncQueryParams()
  } catch (err) {
    geocodeError.value = (err as Error).message || 'Unable to locate that place.'
  } finally {
    geocoding.value = false
  }
}

const snapCurrentLocation = async () => {
  await ensureMapCenter()
  const lat = listingsStore.filters.centerLat ?? defaultCenter.lat
  const lng = listingsStore.filters.centerLng ?? defaultCenter.lng
  listingsStore.updateGeoFilters(lat, lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
  syncQueryParams()
  await runSearch()
}

const runSearch = async () => {
  currentQuery.value = searchQuery.value || ''
  await listingsStore.search(currentQuery.value)
  syncQueryParams()
}

const applyFilters = async () => {
  localFilters.value.amenities = [...(localFilters.value.amenities ?? localFilters.value.facilities ?? [])]
  localFilters.value.facilities = [...(localFilters.value.amenities ?? [])]
  listingsStore.setFilters(localFilters.value)
  filterOpen.value = false
  await runSearch()
}

const resetFilters = async () => {
  viewMode.value = 'list'
  listingsStore.resetFilters()
  searchQuery.value = ''
  localFilters.value = {
    ...listingsStore.filters,
    priceRange: [...listingsStore.filters.priceRange] as [number, number],
    facilities: [...listingsStore.filters.facilities],
    amenities: [...(listingsStore.filters.amenities ?? [])],
    areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
    radiusKm: listingsStore.filters.radiusKm ?? defaultFilters.radiusKm,
  }
  filterOpen.value = false
  await runSearch()
}

const handleSearchArea = async (coords: { lat: number; lng: number }) => {
  listingsStore.updateGeoFilters(coords.lat, coords.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
  syncQueryParams()
  await runSearch()
}

const handleCenterChange = (coords: { lat: number; lng: number }) => {
  listingsStore.updateGeoFilters(coords.lat, coords.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
}

const handleRadiusChange = async (radius: number) => {
  listingsStore.updateGeoFilters(listingsStore.filters.centerLat ?? null, listingsStore.filters.centerLng ?? null, radius)
}

const setViewMode = async (mode: 'list' | 'map') => {
  viewMode.value = mode
  if (mode === 'list') {
    geocodeError.value = ''
  }
  if (mode === 'map') {
    await ensureMapCenter()
    if (!listingsStore.searchResults.length) {
      await runSearch()
    }
  }
  syncQueryParams()
}

onMounted(async () => {
  hydrateFromRoute()
  await listingsStore.fetchRecommended()
  await listingsStore.fetchPopular()
  await runSearch()
  if (viewMode.value === 'map' && (!listingsStore.filters.centerLat || !listingsStore.filters.centerLng)) {
    listingsStore.updateGeoFilters(defaultCenter.lat, defaultCenter.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
    syncQueryParams()
  }
})

watch(filterOpen, (open) => {
  if (open)
    localFilters.value = {
      ...listingsStore.filters,
      priceRange: [...listingsStore.filters.priceRange] as [number, number],
      facilities: [...listingsStore.filters.facilities],
      amenities: [...(listingsStore.filters.amenities ?? [])],
      areaRange: [...(listingsStore.filters.areaRange ?? [0, 100000])] as [number, number],
      radiusKm: listingsStore.filters.radiusKm ?? defaultFilters.radiusKm,
    }
})

watch(searchQuery, () => {
  debouncedSearch()
})

watch(
  () => route.query,
  (newQuery) => {
    if (JSON.stringify(buildQueryFromState()) === JSON.stringify(newQuery)) return
    hydrateFromRoute()
    runSearch()
  },
  { deep: true },
)

watch(
  () => listingsStore.filters.radiusKm,
  () => {
    if (viewMode.value === 'map' && listingsStore.filters.centerLat != null && listingsStore.filters.centerLng != null) {
      syncQueryParams()
    }
  },
)
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner v-if="error" :message="error" />
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <Input
        v-model="searchQuery"
        class="w-full"
        placeholder="Search location or stay"
        :left-icon="SearchIcon"
        :right-icon="SlidersHorizontal"
        @rightIconClick="filterOpen = true"
        @focus="runSearch"
      />
      <div class="inline-flex overflow-hidden rounded-full border border-line bg-surface text-sm font-semibold shadow-soft">
        <button
          :class="[
            'flex items-center gap-2 px-4 py-2 transition',
            viewMode === 'list' ? 'bg-white text-primary shadow-soft' : 'text-muted hover:text-slate-800',
          ]"
          @click="setViewMode('list')"
        >
          <ListIcon class="h-4 w-4" />
          List
        </button>
        <button
          :class="[
            'flex items-center gap-2 px-4 py-2 transition',
            viewMode === 'map' ? 'bg-primary text-white shadow-soft' : 'text-muted hover:text-slate-800',
          ]"
          @click="setViewMode('map')"
        >
          <MapIcon class="h-4 w-4" />
          Map
        </button>
      </div>
    </div>

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

    <div v-if="viewMode === 'list'" class="space-y-5">
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

    <div v-else class="space-y-4">
      <div class="flex flex-wrap items-center gap-3 px-1 text-sm text-muted">
        <div class="flex items-center gap-2 font-semibold text-slate-700">
          <MapPin class="h-4 w-4 text-primary" />
          <span>Within {{ radiusValue }} km radius</span>
        </div>
        <Button size="sm" variant="secondary" :loading="geocoding" @click="snapCurrentLocation">
          Snap this location
        </Button>
      </div>

      <div class="rounded-3xl bg-white px-4 py-3 shadow-soft border border-line/60">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Radius</p>
        <div class="mt-1 flex items-center gap-3">
          <span class="text-sm font-semibold text-slate-900 w-12">{{ inlineRadius }} km</span>
          <input
            v-model.number="inlineRadius"
            type="range"
            min="1"
            max="50"
            step="1"
            class="flex-1 accent-primary"
            @input="handleRadiusChange(Number(inlineRadius))"
          />
        </div>
      </div>

      <MapExplorer
        :listings="results"
        :center="mapCenter"
        :radius-km="radiusValue"
        :loading="loading"
        :missing-geo-count="missingGeoCount"
        @search-area="handleSearchArea"
        @center-change="handleCenterChange"
        @radius-change="handleRadiusChange"
        @select-listing="(id) => router.push(`/listing/${id}`)"
      />

      <p v-if="geocodeError" class="px-1 text-sm font-semibold text-amber-700">{{ geocodeError }}</p>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <ListSkeleton v-if="loading && !results.length" :count="2" />
        <ListingCard
          v-for="item in results"
          :key="item.id"
          :listing="item"
          @toggle="listingsStore.toggleFavorite"
          @click="router.push(`/listing/${item.id}`)"
        />
      </div>

      <EmptyState
        v-if="!loading && !results.length && !error"
        title="Nothing nearby yet"
        subtitle="Move the map or widen the radius to explore more places."
        :icon="MapPin"
      />

      <div class="flex justify-center">
        <Button
          v-if="listingsStore.searchMeta && listingsStore.searchMeta.current_page < listingsStore.searchMeta.last_page"
          :loading="loadingMore"
          variant="secondary"
          @click="listingsStore.loadMoreSearch(currentQueryValue)"
        >
          Load more results
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
          <p class="font-semibold text-slate-900">Search radius</p>
          <span class="text-sm text-muted">{{ localFilters.radiusKm ?? radiusValue }} km</span>
        </div>
        <input
          v-model.number="localFilters.radiusKm"
          type="range"
          min="1"
          max="50"
          step="1"
          class="w-full accent-primary"
        />
        <p class="text-xs text-muted">Used in map view around the chosen center.</p>
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
