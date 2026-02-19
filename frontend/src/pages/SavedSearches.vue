<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, Play, Trash2, Pencil } from 'lucide-vue-next'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import { useSavedSearchesStore } from '../stores/savedSearches'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import type { SavedSearch } from '../types'
import {
  LISTING_AMENITIES,
  LISTING_AMENITY_LABEL_KEY,
  normalizeListingAmenity,
  normalizeListingAmenities,
} from '../constants/listingAmenities'

const router = useRouter()
const store = useSavedSearchesStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const editOpen = ref(false)
const editing = ref<SavedSearch | null>(null)
const editForm = ref({
  name: '',
  alertsEnabled: true,
  frequency: 'instant' as SavedSearch['frequency'],
  filters: {
    location: '',
    city: '',
    category: 'all' as string,
    status: 'all' as string,
    guests: null as number | null,
    priceMin: null as number | null,
    priceMax: null as number | null,
    rooms: null as number | null,
    areaMin: null as number | null,
    areaMax: null as number | null,
    instantBook: false,
    rating: null as number | null,
    amenities: [] as string[],
    mapMode: false,
    centerLat: null as number | null,
    centerLng: null as number | null,
    radiusKm: null as number | null,
  },
})

const amenityOptions = [...LISTING_AMENITIES]

const amenityLabel = (value: string) => {
  const normalized = normalizeListingAmenity(value)
  if (!normalized) return value
  return t(LISTING_AMENITY_LABEL_KEY[normalized])
}

const categoryLabel = (value: string) => {
  if (value === 'villa') return t('listing.categoryVilla')
  if (value === 'hotel') return t('listing.categoryHotel')
  if (value === 'house') return t('listing.categoryHouse')
  if (value === 'apartment') return t('listing.categoryApartment')
  if (value === 'all') return t('filters.any')
  return value
}

const savedSearches = computed(() => store.savedSearches)
const loading = computed(() => store.loading)
const error = computed(() => store.error)

const load = async () => {
  try {
    await store.fetchSavedSearches()
  } catch (err) {
    // error handled in store
  }
}

onMounted(load)

const openEdit = (search: SavedSearch) => {
  editing.value = search
  const filters = search.filters ?? {}
  editForm.value = {
    name: search.name ?? '',
    alertsEnabled: search.alertsEnabled,
    frequency: search.frequency,
    filters: {
      location: filters.location ?? '',
      city: filters.city ?? '',
      category: filters.category ?? 'all',
      status: filters.status ?? 'all',
      guests: filters.guests ?? null,
      priceMin: filters.priceMin ?? null,
      priceMax: filters.priceMax ?? null,
      rooms: filters.rooms ?? null,
      areaMin: filters.areaMin ?? null,
      areaMax: filters.areaMax ?? null,
      instantBook: Boolean(filters.instantBook),
      rating: filters.rating ?? null,
      amenities: normalizeListingAmenities(filters.amenities ?? filters.facilities ?? []),
      mapMode: Boolean(filters.mapMode),
      centerLat: filters.centerLat ?? null,
      centerLng: filters.centerLng ?? null,
      radiusKm: filters.radiusKm ?? null,
    },
  }
  editOpen.value = true
}

const normalizeNumber = (value: unknown): number | null => {
  if (value === '' || value === null || value === undefined) return null
  const numeric = Number(value)
  return Number.isNaN(numeric) ? null : numeric
}

const buildFiltersPayload = () => {
  const f = editForm.value.filters
  const mapEnabled = Boolean(f.mapMode)
  return {
    location: f.location?.trim() ?? '',
    city: f.city?.trim() ?? '',
    category: f.category ?? 'all',
    status: f.status ?? 'all',
    guests: normalizeNumber(f.guests),
    priceMin: normalizeNumber(f.priceMin),
    priceMax: normalizeNumber(f.priceMax),
    rooms: normalizeNumber(f.rooms),
    areaMin: normalizeNumber(f.areaMin),
    areaMax: normalizeNumber(f.areaMax),
    instantBook: Boolean(f.instantBook),
    rating: normalizeNumber(f.rating),
    amenities: normalizeListingAmenities(f.amenities ?? []),
    mapMode: mapEnabled,
    centerLat: mapEnabled ? normalizeNumber(f.centerLat) : null,
    centerLng: mapEnabled ? normalizeNumber(f.centerLng) : null,
    radiusKm: mapEnabled ? normalizeNumber(f.radiusKm) : null,
  }
}

const saveEdit = async () => {
  if (!editing.value) return
  try {
    await store.updateSavedSearch(editing.value.id, {
      name: editForm.value.name.trim() ? editForm.value.name.trim() : null,
      alertsEnabled: editForm.value.alertsEnabled,
      frequency: editForm.value.frequency,
      filters: buildFiltersPayload(),
    })
    toast.push({ title: t('savedSearches.updated'), type: 'success' })
    editOpen.value = false
  } catch (err) {
    toast.push({ title: t('savedSearches.updateFailed'), message: (err as Error).message, type: 'error' })
  }
}

const toggleAlerts = async (search: SavedSearch) => {
  try {
    await store.updateSavedSearch(search.id, { alertsEnabled: !search.alertsEnabled })
  } catch (err) {
    toast.push({ title: t('savedSearches.updateFailed'), message: (err as Error).message, type: 'error' })
  }
}

const changeFrequency = async (search: SavedSearch, frequency: SavedSearch['frequency']) => {
  try {
    await store.updateSavedSearch(search.id, { frequency })
  } catch (err) {
    toast.push({ title: t('savedSearches.updateFailed'), message: (err as Error).message, type: 'error' })
  }
}

const removeSearch = async (search: SavedSearch) => {
  if (!confirm(t('savedSearches.deleteConfirm'))) return
  try {
    await store.deleteSavedSearch(search.id)
    toast.push({ title: t('savedSearches.deleted'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('savedSearches.deleteFailed'), message: (err as Error).message, type: 'error' })
  }
}

const runSearch = (search: SavedSearch) => {
  router.push(`/search?savedSearchId=${search.id}`)
}

const describeFilters = (filters: Record<string, any>) => {
  const items: string[] = []
  if (filters.location) items.push(`${t('filters.location')}: ${filters.location}`)
  if (filters.city) items.push(`${t('filters.city')}: ${filters.city}`)
  if (filters.category) items.push(`${t('filters.category')}: ${categoryLabel(filters.category)}`)
  if (filters.guests) items.push(`${t('filters.guests')}: ${filters.guests}+`)
  if (filters.priceMin != null || filters.priceMax != null) {
    const min = filters.priceMin ?? 0
    const max = filters.priceMax ?? t('filters.any')
    items.push(`${t('filters.price')}: $${min} - ${max}`)
  }
  if (filters.rooms) items.push(`${t('filters.rooms')}: ${filters.rooms}+`)
  if (filters.areaMin != null || filters.areaMax != null) {
    const min = filters.areaMin ?? 0
    const max = filters.areaMax ?? t('filters.any')
    items.push(`${t('filters.area')}: ${min} - ${max} ${t('filters.sqm')}`)
  }
  if (filters.instantBook) items.push(t('filters.instantBook'))
  if (filters.rating) items.push(`${t('filters.rating')}: ${filters.rating}+`)
  const normalizedAmenities = normalizeListingAmenities(filters.amenities ?? filters.facilities ?? [])
  if (normalizedAmenities.length) items.push(`${t('filters.amenities')}: ${normalizedAmenities.map(amenityLabel).join(', ')}`)
  if (filters.mapMode && (filters.radiusKm || (filters.centerLat && filters.centerLng))) {
    items.push(`${t('filters.mapRadius')}: ${filters.radiusKm ?? 10} km`)
  }
  if (!items.length) items.push(t('filters.allListings'))
  return items
}

const formatLastAlert = (value?: string | null) => {
  if (!value) return t('savedSearches.neverAlerted')
  const date = new Date(value)
  return `${t('savedSearches.lastAlert')}: ${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
}
</script>

<template>
  <div class="space-y-4">
    <ErrorState v-if="error" :message="error" :retry-label="t('savedSearches.retry')" @retry="load" />
    <ListSkeleton v-else-if="loading" :count="3" />

    <EmptyState
      v-else-if="!savedSearches.length"
      :title="t('savedSearches.emptyTitle')"
      :subtitle="t('savedSearches.emptySubtitle')"
    />

    <div v-else class="space-y-3">
      <div
        v-for="search in savedSearches"
        :key="search.id"
        class="rounded-2xl bg-white p-4 shadow-soft border border-white/60"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-slate-900">{{ search.name || t('savedSearches.savedSearch') }}</p>
            <p class="text-xs text-muted">{{ formatLastAlert(search.lastAlertedAt) }}</p>
          </div>
          <Button size="sm" variant="secondary" @click="runSearch(search)">
            <Play class="mr-1 h-4 w-4" />
            Run
          </Button>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <span
            v-for="item in describeFilters(search.filters)"
            :key="item"
            class="rounded-full bg-surface px-3 py-1 text-xs font-semibold text-slate-700"
          >
            {{ item }}
          </span>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <Bell class="h-4 w-4 text-primary" />
            {{ t('savedSearches.alerts') }}
            <button
              class="relative h-6 w-11 rounded-full transition-colors"
              :class="search.alertsEnabled ? 'bg-primary' : 'bg-slate-300'"
              @click="toggleAlerts(search)"
            >
              <span
                class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform"
                :class="search.alertsEnabled ? 'translate-x-5' : 'translate-x-0'"
              ></span>
            </button>
          </div>

          <div class="flex items-center gap-2 text-sm">
            <label class="text-xs uppercase tracking-[0.08em] text-muted">{{ t('savedSearches.frequency') }}</label>
            <select
              :value="search.frequency"
              class="rounded-xl border border-line bg-white px-3 py-2 text-xs font-semibold text-slate-700"
              @change="changeFrequency(search, ($event.target as HTMLSelectElement).value as SavedSearch['frequency'])"
            >
              <option value="instant">{{ t('common.instant') }}</option>
              <option value="daily">{{ t('common.daily') }}</option>
              <option value="weekly">{{ t('common.weekly') }}</option>
            </select>
          </div>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
          <Button size="sm" variant="ghost" @click="openEdit(search)">
            <Pencil class="mr-1 h-4 w-4" />
            {{ t('common.edit') }}
          </Button>
          <Button size="sm" variant="ghost" class="text-red-500" @click="removeSearch(search)">
            <Trash2 class="mr-1 h-4 w-4" />
            {{ t('common.delete') }}
          </Button>
        </div>
      </div>
    </div>
  </div>

  <ModalSheet v-model="editOpen" :title="t('savedSearches.editTitle')">
    <div class="space-y-4">
      <Input v-model="editForm.name" :placeholder="t('savedSearches.searchNamePlaceholder')" />

      <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <h3 class="text-sm font-semibold text-slate-900">{{ t('savedSearches.filtersTitle') }}</h3>

        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.location') }}</p>
          <Input v-model="editForm.filters.location" :placeholder="t('savedSearches.searchTextPlaceholder')" />
          <Input v-model="editForm.filters.city" :placeholder="t('savedSearches.cityPlaceholder')" />
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.category') }}</span>
            <select
              v-model="editForm.filters.category"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            >
              <option value="all">{{ t('filters.any') }}</option>
              <option value="villa">{{ t('listing.categoryVilla') }}</option>
              <option value="hotel">{{ t('listing.categoryHotel') }}</option>
              <option value="house">{{ t('listing.categoryHouse') }}</option>
              <option value="apartment">{{ t('listing.categoryApartment') }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.status') }}</span>
            <select
              v-model="editForm.filters.status"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            >
              <option value="all">{{ t('filters.any') }}</option>
              <option value="active">{{ t('status.active') }}</option>
              <option value="paused">{{ t('status.paused') }}</option>
              <option value="archived">{{ t('status.archived') }}</option>
              <option value="rented">{{ t('status.rented') }}</option>
              <option value="expired">{{ t('status.expired') }}</option>
              <option value="draft">{{ t('status.draft') }}</option>
            </select>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.priceMin') }}</span>
            <input
              v-model.number="editForm.filters.priceMin"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.priceMax') }}</span>
            <input
              v-model.number="editForm.filters.priceMax"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.areaMin') }}</span>
            <input
              v-model.number="editForm.filters.areaMin"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.areaMax') }}</span>
            <input
              v-model.number="editForm.filters.areaMax"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.rooms') }}</span>
            <input
              v-model.number="editForm.filters.rooms"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.guests') }}</span>
            <input
              v-model.number="editForm.filters.guests"
              type="number"
              min="1"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
        </div>

        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.rating') }}</span>
          <select
            v-model.number="editForm.filters.rating"
            class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
          >
            <option :value="null">{{ t('filters.any') }}</option>
            <option :value="5">5+</option>
            <option :value="4">4+</option>
            <option :value="3">3+</option>
            <option :value="2">2+</option>
            <option :value="1">1+</option>
          </select>
        </label>

        <div class="flex items-center justify-between rounded-2xl bg-surface px-4 py-3">
          <div>
            <p class="font-semibold text-slate-900">{{ t('filters.instantBook') }}</p>
            <p class="text-sm text-muted">{{ t('savedSearches.instantBookHint') }}</p>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input v-model="editForm.filters.instantBook" type="checkbox" class="peer sr-only" />
            <div
              class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-[18px]"
            ></div>
          </label>
      </div>

      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('filters.amenities') }}</p>
        <div class="grid grid-cols-2 gap-2">
            <label
              v-for="facility in amenityOptions"
              :key="facility"
              class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-xs font-semibold text-slate-700"
            >
              <input v-model="editForm.filters.amenities" :value="facility" type="checkbox" class="h-4 w-4 accent-primary" />
              {{ amenityLabel(facility) }}
            </label>
          </div>
        </div>

        <div class="space-y-3 rounded-2xl bg-surface px-4 py-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-900">{{ t('filters.mapRadius') }}</p>
              <p class="text-sm text-muted">{{ t('savedSearches.mapRadiusHint') }}</p>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
              <input v-model="editForm.filters.mapMode" type="checkbox" class="peer sr-only" />
              <div
                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-[18px]"
              ></div>
            </label>
          </div>
          <div v-if="editForm.filters.mapMode" class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('savedSearches.centerLat') }}</span>
              <input
                v-model.number="editForm.filters.centerLat"
                type="number"
                step="0.00001"
                class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
              />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('savedSearches.centerLng') }}</span>
              <input
                v-model.number="editForm.filters.centerLng"
                type="number"
                step="0.00001"
                class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
              />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('savedSearches.radiusKm') }}</span>
              <input
                v-model.number="editForm.filters.radiusKm"
                type="number"
                min="1"
                max="50"
                step="1"
                class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
              />
            </label>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between rounded-2xl bg-surface px-4 py-3">
        <div>
          <p class="font-semibold text-slate-900">{{ t('savedSearches.alerts') }}</p>
          <p class="text-sm text-muted">{{ t('savedSearches.alertsHint') }}</p>
        </div>
        <button
          class="relative h-6 w-11 rounded-full transition-colors"
          :class="editForm.alertsEnabled ? 'bg-primary' : 'bg-slate-300'"
          @click="editForm.alertsEnabled = !editForm.alertsEnabled"
        >
          <span
            class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform"
            :class="editForm.alertsEnabled ? 'translate-x-5' : 'translate-x-0'"
          ></span>
        </button>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-semibold text-slate-900">{{ t('savedSearches.frequency') }}</p>
        <select
          v-model="editForm.frequency"
          class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
        >
          <option value="instant">{{ t('common.instant') }}</option>
          <option value="daily">{{ t('common.daily') }}</option>
          <option value="weekly">{{ t('common.weekly') }}</option>
        </select>
      </div>

      <Button block @click="saveEdit">{{ t('savedSearches.saveChanges') }}</Button>
    </div>
  </ModalSheet>
</template>
