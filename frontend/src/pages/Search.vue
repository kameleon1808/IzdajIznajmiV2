<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'
import { Bookmark, BookmarkPlus, Flame, Map as MapIcon, MapPin, List as ListIcon, Search as SearchIcon, SlidersHorizontal } from 'lucide-vue-next'
import ListingCardHorizontal from '../components/listing/ListingCardHorizontal.vue'
import ListingCard from '../components/listing/ListingCard.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { geocodeLocation, suggestLocations, suggestSearch } from '../services'
import { defaultFilters, useListingsStore } from '../stores/listings'
import { useSavedSearchesStore } from '../stores/savedSearches'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import type { ListingFilters, SavedSearch, SearchSuggestion } from '../types'

const MapExplorer = defineAsyncComponent(() => import('../components/search/MapExplorer.vue'))

const listingsStore = useListingsStore()
const savedSearchesStore = useSavedSearchesStore()
const toast = useToastStore()
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const viewMode = ref<'list' | 'map'>((route.query.view as string) === 'map' ? 'map' : 'list')
const searchQuery = ref<string>((route.query.q as string) ?? '')
const filterOpen = ref(false)
const saveSearchOpen = ref(false)
const savingSearch = ref(false)
const geocoding = ref(false)
const geocodeError = ref('')
const suggestLoading = ref(false)
const suggestions = ref<SearchSuggestion[]>([])
const showSuggestions = ref(false)
const defaultCenter = { lat: 44.8125, lng: 20.4612 } // Belgrade
type FilterDraft = ListingFilters & {
  areaRange: [number, number]
  priceMinInput: string
  priceMaxInput: string
  areaMinInput: string
  areaMaxInput: string
  roomsInput: string
}

const buildFilterDraft = (filters: ListingFilters): FilterDraft => {
  const priceRange = filters.priceRange ?? defaultFilters.priceRange
  const areaRange = filters.areaRange ?? defaultFilters.areaRange ?? [0, 100000]
  const defaultPriceMin = defaultFilters.priceRange[0]
  const defaultPriceMax = defaultFilters.priceRange[1]
  const defaultAreaMin = defaultFilters.areaRange?.[0] ?? 0
  const defaultAreaMax = defaultFilters.areaRange?.[1] ?? 100000

  return {
    ...filters,
    priceRange: [...priceRange] as [number, number],
    facilities: [...filters.facilities],
    amenities: [...(filters.amenities ?? [])],
    areaRange: [...areaRange] as [number, number],
    radiusKm: filters.radiusKm ?? defaultFilters.radiusKm,
    priceMinInput: priceRange[0] !== defaultPriceMin ? String(priceRange[0]) : '',
    priceMaxInput: priceRange[1] !== defaultPriceMax ? String(priceRange[1]) : '',
    areaMinInput: areaRange[0] !== defaultAreaMin ? String(areaRange[0]) : '',
    areaMaxInput: areaRange[1] !== defaultAreaMax ? String(areaRange[1]) : '',
    roomsInput: filters.rooms ? String(filters.rooms) : '',
  }
}

const localFilters = ref<FilterDraft>(buildFilterDraft(listingsStore.filters))
const saveSearchForm = ref({
  name: '',
  alertsEnabled: true,
  frequency: 'instant' as SavedSearch['frequency'],
})
const activeSavedSearch = ref<SavedSearch | null>(null)
const appliedSavedSearchId = ref<string | null>(null)

const syncLocalFilters = () => {
  localFilters.value = buildFilterDraft(listingsStore.filters)
}
const currentQuery = ref('')
const debouncedSuggest = useDebounceFn(async () => {
  await fetchSuggestions()
}, 250)
const searchV2Enabled = computed(() => import.meta.env.VITE_SEARCH_V2 === 'true')
const searchFacets = computed(() => listingsStore.searchFacets)

const hasActiveListFilters = computed(() => {
  const f = listingsStore.filters
  if (f.category !== defaultFilters.category) return true
  if (f.guests !== defaultFilters.guests) return true
  if (f.priceBucket) return true
  if (f.areaBucket) return true
  if (f.instantBook) return true
  if (f.location) return true
  if (f.city) return true
  if (f.rooms) return true
  if (f.rating) return true
  if (f.status && f.status !== 'all') return true
  if (f.amenities?.length) return true
  if (f.facilities?.length) return true
  if (f.centerLat != null || f.centerLng != null) return true
  if (
    f.priceRange?.length &&
    (f.priceRange[0] !== defaultFilters.priceRange[0] || f.priceRange[1] !== defaultFilters.priceRange[1])
  ) {
    return true
  }
  if (
    f.areaRange?.length &&
    (f.areaRange[0] !== defaultFilters.areaRange?.[0] || f.areaRange[1] !== defaultFilters.areaRange?.[1])
  ) {
    return true
  }
  return false
})

const results = computed(() => {
  if (viewMode.value === 'map') return listingsStore.searchResults
  if (searchQuery.value || hasActiveListFilters.value) return listingsStore.searchResults
  return listingsStore.filteredRecommended
})
const popular = computed(() => listingsStore.popular.slice(0, 2))
const loading = computed(() => listingsStore.loading)
const loadingMore = computed(() => listingsStore.loadingMore)
const error = computed(() => listingsStore.error)
const savedSearchId = computed(() =>
  typeof route.query.savedSearchId === 'string' ? route.query.savedSearchId : null,
)
const mapCenter = computed(() =>
  listingsStore.filters.centerLat != null && listingsStore.filters.centerLng != null
    ? { lat: listingsStore.filters.centerLat, lng: listingsStore.filters.centerLng }
    : null,
)
const radiusValue = computed<number>(() => listingsStore.filters.radiusKm ?? defaultFilters.radiusKm ?? 0)
const inlineRadius = ref(radiusValue.value)
const missingGeoCount = computed(() => results.value.filter((item) => item.lat == null || item.lng == null).length)
const facetCityOptions = computed(() => (searchFacets.value.city ?? []).slice(0, 8))
const facetStatusOptions = computed(() => (searchFacets.value.status ?? []).slice(0, 6))
const facetRoomsOptions = computed(() => {
  const rooms = (searchFacets.value.rooms ?? [])
    .map((item) => ({ value: item.value, count: item.count, numeric: Number(item.value) }))
    .filter((item) => Number.isFinite(item.numeric))
    .sort((a, b) => a.numeric - b.numeric)

  if (!rooms.length) return []

  let running = 0
  const cumulative = [...rooms]
    .reverse()
    .map((item) => {
      running += item.count
      return { value: item.value, count: running, numeric: item.numeric }
    })
    .reverse()

  return cumulative.slice(0, 6).map(({ value, count }) => ({ value, count }))
})
const facetAmenityOptions = computed(() => (searchFacets.value.amenities ?? []).slice(0, 10))
const facetPriceOptions = computed(() => searchFacets.value.price_bucket ?? [])
const facetAreaOptions = computed(() => searchFacets.value.area_bucket ?? [])

const buildSavedSearchFilters = () => {
  const f = listingsStore.filters
  const amenities = f.amenities?.length ? f.amenities : f.facilities
  return {
    category: f.category,
    guests: f.guests,
    priceMin: f.priceRange?.[0],
    priceMax: f.priceRange?.[1],
    priceBucket: f.priceBucket ?? null,
    rooms: f.rooms,
    areaMin: f.areaRange?.[0],
    areaMax: f.areaRange?.[1],
    areaBucket: f.areaBucket ?? null,
    instantBook: f.instantBook,
    location: searchQuery.value || f.location || '',
    city: f.city,
    rating: f.rating,
    status: f.status,
    amenities,
    centerLat: f.centerLat,
    centerLng: f.centerLng,
    radiusKm: f.radiusKm,
    mapMode: viewMode.value === 'map',
  }
}

const applySavedSearch = async (savedSearch: SavedSearch) => {
  const filters = savedSearch.filters ?? {}

  viewMode.value = filters.mapMode ? 'map' : 'list'
  searchQuery.value = filters.location ?? ''

  const nextFilters: Partial<ListingFilters> = {
    ...listingsStore.filters,
    category: filters.category ?? defaultFilters.category,
    guests: filters.guests ?? defaultFilters.guests,
    priceRange: [
      filters.priceMin ?? defaultFilters.priceRange[0],
      filters.priceMax ?? defaultFilters.priceRange[1],
    ] as [number, number],
    priceBucket: filters.priceBucket ?? null,
    rooms: filters.rooms ?? null,
    areaRange: [
      filters.areaMin ?? defaultFilters.areaRange?.[0] ?? 0,
      filters.areaMax ?? defaultFilters.areaRange?.[1] ?? 100000,
    ] as [number, number],
    areaBucket: filters.areaBucket ?? null,
    instantBook: Boolean(filters.instantBook),
    location: filters.location ?? '',
    city: filters.city ?? '',
    facilities: filters.amenities ?? filters.facilities ?? [],
    amenities: filters.amenities ?? filters.facilities ?? [],
    rating: filters.rating ?? null,
    status: filters.status ?? defaultFilters.status,
    centerLat: filters.centerLat ?? null,
    centerLng: filters.centerLng ?? null,
    radiusKm: filters.radiusKm ?? defaultFilters.radiusKm,
  }

  listingsStore.setFilters(nextFilters, { fetch: false })
  syncLocalFilters()

  if (viewMode.value === 'map' && (filters.centerLat == null || filters.centerLng == null)) {
    await ensureMapCenter()
  }
}

const loadSavedSearchFromRoute = async () => {
  if (!savedSearchId.value) {
    activeSavedSearch.value = null
    appliedSavedSearchId.value = null
    return false
  }
  if (!authStore.isAuthenticated && !authStore.isMockMode) {
    toast.push({ title: 'Log in to use saved searches', type: 'info' })
    return false
  }
  if (appliedSavedSearchId.value === savedSearchId.value && activeSavedSearch.value) {
    return true
  }
  if (!savedSearchesStore.savedSearches.length) {
    try {
      await savedSearchesStore.fetchSavedSearches()
    } catch (error) {
      toast.push({ title: 'Unable to load saved search', type: 'error' })
      return false
    }
  }
  const saved = savedSearchesStore.byId(savedSearchId.value)
  if (!saved) {
    toast.push({ title: 'Saved search not found', type: 'error' })
    activeSavedSearch.value = null
    appliedSavedSearchId.value = null
    return false
  }
  appliedSavedSearchId.value = saved.id
  activeSavedSearch.value = saved
  await applySavedSearch(saved)
  await runSearch()
  return true
}

const clearSavedSearch = async () => {
  activeSavedSearch.value = null
  appliedSavedSearchId.value = null
  const { savedSearchId, ...rest } = route.query
  await router.replace({ query: rest })
}

const saveSearch = async () => {
  if (!authStore.isAuthenticated && !authStore.isMockMode) {
    toast.push({ title: 'Log in to save searches', type: 'info' })
    router.push('/login')
    return
  }

  savingSearch.value = true
  try {
    const saved = await savedSearchesStore.createSavedSearch({
      name: saveSearchForm.value.name.trim() ? saveSearchForm.value.name.trim() : null,
      filters: buildSavedSearchFilters(),
      alertsEnabled: saveSearchForm.value.alertsEnabled,
      frequency: saveSearchForm.value.frequency,
    })
    activeSavedSearch.value = saved
    saveSearchOpen.value = false
    toast.push({ title: 'Saved search created', type: 'success' })
  } catch (error) {
    const err = error as { status?: number; message?: string }
    if (err.status === 409) {
      toast.push({ title: 'Already saved', message: err.message ?? 'Search already exists.', type: 'info' })
    } else {
      toast.push({ title: 'Save failed', message: err.message ?? 'Unable to save search.', type: 'error' })
    }
  } finally {
    savingSearch.value = false
  }
}

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
  if (query.priceBucket) parsed.priceBucket = String(query.priceBucket)
  if (query.rooms) parsed.rooms = Number(query.rooms)
  if (query.areaMin) parsed.areaRange = [Number(query.areaMin), listingsStore.filters.areaRange?.[1] ?? 100000] as [number, number]
  if (query.areaMax) parsed.areaRange = [listingsStore.filters.areaRange?.[0] ?? 0, Number(query.areaMax)] as [number, number]
  if (query.areaBucket) parsed.areaBucket = String(query.areaBucket)
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

  syncLocalFilters()
}

const skipQuerySync = ref(false)

const buildQueryFromState = () => {
  const f = listingsStore.filters
  const nextQuery: Record<string, any> = {}
  if (searchQuery.value) nextQuery.q = searchQuery.value
  if (viewMode.value === 'map') nextQuery.view = 'map'
  if (f.category !== defaultFilters.category) nextQuery.category = f.category
  if (f.guests !== defaultFilters.guests) nextQuery.guests = f.guests
  if (f.priceRange?.[0] !== defaultFilters.priceRange[0]) nextQuery.priceMin = f.priceRange?.[0]
  if (f.priceRange?.[1] !== defaultFilters.priceRange[1]) nextQuery.priceMax = f.priceRange?.[1]
  if (f.priceBucket) nextQuery.priceBucket = f.priceBucket
  if (f.instantBook) nextQuery.instantBook = '1'
  if (f.location) nextQuery.location = f.location
  if (f.city) nextQuery.city = f.city
  if (f.rooms) nextQuery.rooms = f.rooms
  if (f.areaRange?.[0] !== defaultFilters.areaRange?.[0]) nextQuery.areaMin = f.areaRange?.[0]
  if (f.areaRange?.[1] !== defaultFilters.areaRange?.[1]) nextQuery.areaMax = f.areaRange?.[1]
  if (f.areaBucket) nextQuery.areaBucket = f.areaBucket
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

const syncQueryParams = async () => {
  if (skipQuerySync.value) return
  const desired = buildQueryFromState()
  const current = { ...route.query }
  if (JSON.stringify(desired) === JSON.stringify(current)) return
  skipQuerySync.value = true
  try {
    await router.replace({ query: desired })
  } finally {
    // small delay to let watcher skip this navigation tick
    setTimeout(() => (skipQuerySync.value = false), 0)
  }
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
  await syncQueryParams()
  await runSearch()
}

const fetchSuggestions = async () => {
  const q = searchQuery.value.trim()
  if (!q) {
    suggestions.value = []
    return
  }
  suggestLoading.value = true
  try {
    if (searchV2Enabled.value) {
      suggestions.value = await suggestSearch(q, 8)
    } else {
      const legacy = await suggestLocations(q, 5)
      suggestions.value = legacy.map((item: { label: string }) => ({
        label: item.label,
        type: 'city' as const,
        value: item.label,
      }))
    }
  } catch (err) {
    // swallow
  } finally {
    suggestLoading.value = false
  }
}

const selectSuggestion = async (item: SearchSuggestion) => {
  if (item.type === 'query' || item.type === 'city') {
    searchQuery.value = item.label
  }
  if (item.type === 'amenity') {
    searchQuery.value = ''
  }
  if (item.type === 'city') {
    listingsStore.setFilters({ city: item.value }, { fetch: false })
  }
  if (item.type === 'amenity') {
    const current = new Set(listingsStore.filters.amenities ?? [])
    current.add(item.value)
    listingsStore.setFilters({ amenities: Array.from(current) }, { fetch: false })
  }
  syncLocalFilters()
  showSuggestions.value = false
  await runSearch()
}

const hideSuggestions = () => {
  setTimeout(() => (showSuggestions.value = false), 150)
}

const loadMoreResults = async () => {
  await listingsStore.loadMoreSearch(currentQuery.value, { mapMode: viewMode.value === 'map' })
}

const runSearch = async () => {
  currentQuery.value = searchQuery.value || ''
  await listingsStore.search(currentQuery.value, { mapMode: viewMode.value === 'map' })
  await syncQueryParams()
}

const retrySearch = async () => {
  listingsStore.error = ''
  await runSearch()
}

const widenRadius = async () => {
  await ensureMapCenter()
  const current = listingsStore.filters.radiusKm ?? defaultFilters.radiusKm ?? 10
  const next = Math.min(current + 5, 50)
  listingsStore.updateGeoFilters(listingsStore.filters.centerLat ?? defaultCenter.lat, listingsStore.filters.centerLng ?? defaultCenter.lng, next)
  await syncQueryParams()
  await runSearch()
}

const parseOptionalNumber = (value: string | number | null | undefined) => {
  if (value === null || value === undefined) return null
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  const trimmed = value.trim()
  if (!trimmed) return null
  const parsed = Number(trimmed)
  return Number.isFinite(parsed) ? parsed : null
}

const applyFilters = async () => {
  const {
    priceMinInput,
    priceMaxInput,
    areaMinInput,
    areaMaxInput,
    roomsInput,
    ...rest
  } = localFilters.value
  const priceMin = parseOptionalNumber(priceMinInput)
  const priceMax = parseOptionalNumber(priceMaxInput)
  const areaMin = parseOptionalNumber(areaMinInput)
  const areaMax = parseOptionalNumber(areaMaxInput)
  const rooms = parseOptionalNumber(roomsInput)
  const nextFilters: ListingFilters = {
    ...rest,
    priceRange: [
      priceMin ?? defaultFilters.priceRange[0],
      priceMax ?? defaultFilters.priceRange[1],
    ] as [number, number],
    priceBucket: null,
    areaRange: [
      areaMin ?? (defaultFilters.areaRange?.[0] ?? 0),
      areaMax ?? (defaultFilters.areaRange?.[1] ?? 100000),
    ] as [number, number],
    areaBucket: null,
    rooms: rooms ?? null,
  }
  nextFilters.amenities = [...(nextFilters.amenities ?? nextFilters.facilities ?? [])]
  nextFilters.facilities = [...(nextFilters.amenities ?? [])]
  listingsStore.setFilters(nextFilters)
  filterOpen.value = false
  await runSearch()
}

const resetFilters = async () => {
  viewMode.value = 'list'
  listingsStore.resetFilters()
  searchQuery.value = ''
  syncLocalFilters()
  filterOpen.value = false
  await runSearch()
}

const applyFacetFilters = async (next: Partial<ListingFilters>) => {
  listingsStore.setFilters(next, { fetch: false })
  syncLocalFilters()
  await runSearch()
}

const selectCityFacet = async (value: string) => {
  searchQuery.value = value
  await applyFacetFilters({ city: value })
}

const clearCityFacet = async () => {
  searchQuery.value = ''
  await applyFacetFilters({ city: '' })
}

const toggleAmenityFacet = async (value: string) => {
  const current = new Set(listingsStore.filters.amenities ?? [])
  if (current.has(value)) {
    current.delete(value)
  } else {
    current.add(value)
  }
  await applyFacetFilters({ amenities: Array.from(current) })
}

const selectRoomsFacet = async (value: string) => {
  const numeric = Number(value)
  const nextValue = listingsStore.filters.rooms === numeric ? null : numeric
  await applyFacetFilters({ rooms: nextValue })
}

const selectStatusFacet = async (value: string) => {
  const nextValue = listingsStore.filters.status === value ? 'all' : (value as ListingFilters['status'])
  await applyFacetFilters({ status: nextValue })
}

const selectPriceBucketFacet = async (value: string) => {
  await applyFacetFilters({
    priceBucket: value,
    priceRange: [...defaultFilters.priceRange] as [number, number],
  })
}

const clearPriceBucketFacet = async () => {
  await applyFacetFilters({ priceBucket: null })
}

const selectAreaBucketFacet = async (value: string) => {
  const nextValue = listingsStore.filters.areaBucket === value ? null : value
  await applyFacetFilters({
    areaBucket: nextValue,
    areaRange: [...(defaultFilters.areaRange ?? [0, 100000])] as [number, number],
  })
}

const handleSearchArea = async (coords: { lat: number; lng: number }) => {
  listingsStore.updateGeoFilters(coords.lat, coords.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
  await syncQueryParams()
  await runSearch()
}

const handleCenterChange = (coords: { lat: number; lng: number }) => {
  // Update store but defer URL + search until user taps "Search this area"/Snap.
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
  await syncQueryParams()
}

onMounted(async () => {
  hydrateFromRoute()
  await listingsStore.fetchRecommended()
  await listingsStore.fetchPopular()
  const applied = await loadSavedSearchFromRoute()
  if (!applied) {
    await runSearch()
  }
  if (viewMode.value === 'map' && (!listingsStore.filters.centerLat || !listingsStore.filters.centerLng)) {
    listingsStore.updateGeoFilters(defaultCenter.lat, defaultCenter.lng, listingsStore.filters.radiusKm ?? defaultFilters.radiusKm)
    syncQueryParams()
  }
})

watch(filterOpen, (open) => {
  if (open) syncLocalFilters()
})

watch(searchQuery, () => {
  debouncedSuggest()
})

watch(saveSearchOpen, (open) => {
  if (open) {
    saveSearchForm.value = {
      name: '',
      alertsEnabled: true,
      frequency: 'instant',
    }
  }
})

watch(
  () => route.query,
  async (newQuery) => {
    if (skipQuerySync.value) return
    if (await loadSavedSearchFromRoute()) return
    if (JSON.stringify(buildQueryFromState()) === JSON.stringify(newQuery)) return
    hydrateFromRoute()
    runSearch()
  },
  { deep: true },
)

// Radius changes should not auto-trigger search; user confirms via Snap/Search buttons.
</script>

<template>
  <div class="space-y-5">
    <ErrorState v-if="error" :message="error" retry-label="Retry search" @retry="retrySearch" />
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div class="w-full md:max-w-xl relative">
        <Input
          v-model="searchQuery"
          class="w-full"
          placeholder="Search location or stay"
          :left-icon="SearchIcon"
          :right-icon="SlidersHorizontal"
          @leftIconClick="runSearch"
          @rightIconClick="filterOpen = true"
          @focus="showSuggestions = true; fetchSuggestions()"
          @blur="hideSuggestions"
          @input="showSuggestions = true"
        />
        <div
          v-if="showSuggestions && suggestions.length"
          class="absolute z-20 mt-1 w-full rounded-2xl border border-line bg-white shadow-soft"
        >
          <button
            v-for="item in suggestions"
            :key="item.label"
            class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-surface"
            @mousedown.prevent="selectSuggestion(item)"
          >
            <MapPin v-if="item.type === 'city'" class="h-4 w-4 text-primary" />
            <SearchIcon v-else class="h-4 w-4 text-primary" />
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
              <p class="text-[11px] uppercase tracking-[0.08em] text-muted">{{ item.type }}</p>
            </div>
          </button>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
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
        <Button size="sm" variant="secondary" @click="saveSearchOpen = true">
          <BookmarkPlus class="mr-2 h-4 w-4" />
          Save search
        </Button>
        <Button size="sm" variant="ghost" @click="router.push('/saved-searches')">
          <Bookmark class="mr-2 h-4 w-4" />
          Saved searches
        </Button>
      </div>
    </div>

    <div
      v-if="activeSavedSearch"
      class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm"
    >
      <div>
        <p class="font-semibold text-slate-900">
          Showing results for saved search
          <span class="text-primary">{{ activeSavedSearch.name || 'Saved search' }}</span>
        </p>
        <p class="text-xs text-muted">Results are refreshed from the saved filters.</p>
      </div>
      <Button size="sm" variant="ghost" @click="clearSavedSearch">Clear</Button>
    </div>

    <div v-if="viewMode === 'list'" :class="searchV2Enabled ? 'grid gap-6 md:grid-cols-[260px,1fr]' : 'space-y-5'">
      <aside v-if="searchV2Enabled" class="space-y-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">City</p>
            <button v-if="listingsStore.filters.city" class="text-xs font-semibold text-primary" @click="clearCityFacet">
              Clear
            </button>
          </div>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetCityOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                listingsStore.filters.city === item.value ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-slate-800',
              ]"
              @click="selectCityFacet(item.value)"
            >
              <span>{{ item.value }}</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
            <p v-if="!facetCityOptions.length" class="text-xs text-muted">No city facets yet.</p>
          </div>
        </div>

        <div v-if="facetPriceOptions.length" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Price</p>
            <button
              v-if="listingsStore.filters.priceBucket"
              class="text-xs font-semibold text-primary"
              @click="clearPriceBucketFacet"
            >
              Clear
            </button>
          </div>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetPriceOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                listingsStore.filters.priceBucket === item.value ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-slate-800',
              ]"
              @click="selectPriceBucketFacet(item.value)"
            >
              <span>{{ item.value }}</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
          </div>
        </div>

        <div v-if="facetRoomsOptions.length" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Rooms</p>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetRoomsOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                Number(item.value) === listingsStore.filters.rooms ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-slate-800',
              ]"
              @click="selectRoomsFacet(item.value)"
            >
              <span>{{ item.value }}+</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
          </div>
        </div>

        <div v-if="facetAmenityOptions.length" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Amenities</p>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetAmenityOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                listingsStore.filters.amenities?.includes(item.value)
                  ? 'border-primary/40 bg-primary/10 text-primary'
                  : 'border-line text-slate-800',
              ]"
              @click="toggleAmenityFacet(item.value)"
            >
              <span>{{ item.value }}</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
          </div>
        </div>

        <div v-if="facetAreaOptions.length" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Area</p>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetAreaOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                listingsStore.filters.areaBucket === item.value ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-slate-800',
              ]"
              @click="selectAreaBucketFacet(item.value)"
            >
              <span>{{ item.value }}</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
          </div>
        </div>

        <div v-if="facetStatusOptions.length" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Status</p>
          <div class="mt-3 space-y-2">
            <button
              v-for="item in facetStatusOptions"
              :key="item.value"
              :class="[
                'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm font-semibold transition',
                listingsStore.filters.status === item.value ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-slate-800',
              ]"
              @click="selectStatusFacet(item.value)"
            >
              <span class="capitalize">{{ item.value }}</span>
              <span class="text-xs text-muted">{{ item.count }}</span>
            </button>
          </div>
        </div>
      </aside>

      <div class="space-y-5">
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
          >
            <Button size="sm" variant="secondary" @click="resetFilters">Reset filters</Button>
          </EmptyState>
          <div class="flex justify-center">
            <Button
              v-if="listingsStore.hasMoreSearchResults"
              :loading="loadingMore"
              variant="secondary"
              @click="loadMoreResults"
            >
              Load more
            </Button>
          </div>
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
      >
        <div class="flex justify-center gap-2">
          <Button size="sm" variant="secondary" @click="widenRadius">Widen radius</Button>
          <Button size="sm" variant="ghost" @click="resetFilters">Reset filters</Button>
        </div>
      </EmptyState>

      <div class="flex justify-center">
        <Button
          v-if="listingsStore.hasMoreSearchResults"
          :loading="loadingMore"
          variant="secondary"
          @click="loadMoreResults"
        >
          Load more results
        </Button>
      </div>
    </div>
  </div>

  <ModalSheet v-model="filterOpen" title="Filter by">
    <div class="space-y-4">
      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Location</p>
        <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70 mt-2">
          <input
            v-model="localFilters.city"
            placeholder="City contains (e.g. Zagreb)"
            type="text"
            class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
          />
        </label>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Price range</p>
          <span class="text-sm text-muted">
            {{ localFilters.priceMinInput ? `$${localFilters.priceMinInput}` : 'Any' }} -
            {{ localFilters.priceMaxInput ? `$${localFilters.priceMaxInput}` : 'Any' }}
          </span>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70" inputmode="decimal" step="0.01" min="0">
            <input
              v-model="localFilters.priceMinInput"
              placeholder="Min"
              type="number"
              class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
              min="0"
              step="0.01"
            />
          </label>
          <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70" inputmode="decimal" step="0.01" min="0">
            <input
              v-model="localFilters.priceMaxInput"
              placeholder="Max"
              type="number"
              class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
              min="0"
              step="0.01"
            />
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Area (sqm)</p>
          <span class="text-sm text-muted">
            {{ localFilters.areaMinInput || 'Any' }} - {{ localFilters.areaMaxInput || 'Any' }}
          </span>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70" min="0">
            <input
              v-model="localFilters.areaMinInput"
              placeholder="Min"
              type="number"
              class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
              min="0"
            />
          </label>
          <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70" min="0">
            <input
              v-model="localFilters.areaMaxInput"
              placeholder="Max"
              type="number"
              class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
              min="0"
            />
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Rooms</p>
          <span class="text-sm text-muted">{{ localFilters.roomsInput || 'Any' }}</span>
        </div>
        <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70">
          <input
            v-model="localFilters.roomsInput"
            placeholder="Any"
            type="number"
            class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
            min="0"
          />
        </label>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Amenities</p>
        <div class="grid grid-cols-2 gap-2">
          <label
            v-for="facility in ['Pool', 'Spa', 'Wi-Fi', 'Breakfast', 'Parking', 'Kitchen', 'Workspace']"
            :key="facility"
            class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-slate-800"
          >
            <input v-model="localFilters.amenities" :value="facility" type="checkbox" class="h-4 w-4 accent-primary" />
            {{ facility }}
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Guests</p>
          <span class="text-sm text-muted">{{ localFilters.guests }} people</span>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft bg-white text-slate-900 border border-line hover:border-primary/50 h-12 px-5 text-sm"
            @click="localFilters.guests = Math.max(1, localFilters.guests - 1)"
          >
            -
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft bg-white text-slate-900 border border-line hover:border-primary/50 h-12 px-5 text-sm flex-1"
          >
            {{ localFilters.guests }}
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft bg-white text-slate-900 border border-line hover:border-primary/50 h-12 px-5 text-sm"
            @click="localFilters.guests = localFilters.guests + 1"
          >
            +
          </button>
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
        <p class="font-semibold text-slate-900">Rating</p>
        <div class="flex gap-2">
          <button
            v-for="rate in [5, 4, 3, 2, 1]"
            :key="rate"
            type="button"
            :class="[
              'rounded-pill px-4 py-2 text-sm font-semibold transition border border-line shadow-soft',
              localFilters.rating === rate ? 'bg-primary text-white' : 'bg-white text-slate-700',
            ]"
            @click="localFilters.rating = rate"
          >
            {{ rate }}+
          </button>
          <button
            type="button"
            :class="[
              'rounded-pill px-4 py-2 text-sm font-semibold transition border border-line shadow-soft',
              localFilters.rating === null ? 'bg-primary text-white' : 'bg-white text-slate-700',
            ]"
            @click="localFilters.rating = null"
          >
            Any
          </button>
        </div>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft bg-primary text-white hover:bg-primary-dark h-14 px-6 text-base w-full"
        @click="applyFilters"
      >
        Apply Filters
      </button>
      <button
        type="button"
        class="inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft bg-white text-slate-900 border border-line hover:border-primary/50 h-14 px-6 text-base w-full"
        @click="resetFilters"
      >
        Reset Filters
      </button>
    </div>
  </ModalSheet>

  <ModalSheet v-model="saveSearchOpen" title="Save search">
    <div class="space-y-4">
      <Input v-model="saveSearchForm.name" placeholder="Search name (optional)" />

      <div class="flex items-center justify-between rounded-2xl bg-surface px-4 py-3">
        <div>
          <p class="font-semibold text-slate-900">Alerts</p>
          <p class="text-sm text-muted">Enable in-app notifications</p>
        </div>
        <label class="relative inline-flex cursor-pointer items-center">
          <input v-model="saveSearchForm.alertsEnabled" type="checkbox" class="peer sr-only" />
          <div
            class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-[18px]"
          ></div>
        </label>
      </div>

      <div class="space-y-2">
        <p class="font-semibold text-slate-900">Alert frequency</p>
        <select
          v-model="saveSearchForm.frequency"
          class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
        >
          <option value="instant">Instant</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </select>
      </div>

      <Button size="lg" :loading="savingSearch" block @click="saveSearch">Save search</Button>
    </div>
  </ModalSheet>
</template>
