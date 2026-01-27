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
import type { SavedSearch } from '../types'

const router = useRouter()
const store = useSavedSearchesStore()
const toast = useToastStore()

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

const amenityOptions = ['Pool', 'Spa', 'Wi-Fi', 'Breakfast', 'Parking', 'Kitchen', 'Workspace']

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
      amenities: filters.amenities ?? filters.facilities ?? [],
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
    amenities: f.amenities ?? [],
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
    toast.push({ title: 'Saved search updated', type: 'success' })
    editOpen.value = false
  } catch (err) {
    toast.push({ title: 'Update failed', message: (err as Error).message, type: 'error' })
  }
}

const toggleAlerts = async (search: SavedSearch) => {
  try {
    await store.updateSavedSearch(search.id, { alertsEnabled: !search.alertsEnabled })
  } catch (err) {
    toast.push({ title: 'Update failed', message: (err as Error).message, type: 'error' })
  }
}

const changeFrequency = async (search: SavedSearch, frequency: SavedSearch['frequency']) => {
  try {
    await store.updateSavedSearch(search.id, { frequency })
  } catch (err) {
    toast.push({ title: 'Update failed', message: (err as Error).message, type: 'error' })
  }
}

const removeSearch = async (search: SavedSearch) => {
  if (!confirm('Delete this saved search?')) return
  try {
    await store.deleteSavedSearch(search.id)
    toast.push({ title: 'Saved search deleted', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Delete failed', message: (err as Error).message, type: 'error' })
  }
}

const runSearch = (search: SavedSearch) => {
  router.push(`/search?savedSearchId=${search.id}`)
}

const describeFilters = (filters: Record<string, any>) => {
  const items: string[] = []
  if (filters.location) items.push(`Location: ${filters.location}`)
  if (filters.city) items.push(`City: ${filters.city}`)
  if (filters.category) items.push(`Category: ${filters.category}`)
  if (filters.guests) items.push(`Guests: ${filters.guests}+`)
  if (filters.priceMin != null || filters.priceMax != null) {
    const min = filters.priceMin ?? 0
    const max = filters.priceMax ?? 'Any'
    items.push(`Price: $${min} - ${max}`)
  }
  if (filters.rooms) items.push(`Rooms: ${filters.rooms}+`)
  if (filters.areaMin != null || filters.areaMax != null) {
    const min = filters.areaMin ?? 0
    const max = filters.areaMax ?? 'Any'
    items.push(`Area: ${min} - ${max} sqm`)
  }
  if (filters.instantBook) items.push('Instant book')
  if (filters.rating) items.push(`Rating: ${filters.rating}+`)
  if (filters.amenities?.length) items.push(`Amenities: ${filters.amenities.join(', ')}`)
  if (filters.mapMode && (filters.radiusKm || (filters.centerLat && filters.centerLng))) {
    items.push(`Map radius: ${filters.radiusKm ?? 10} km`)
  }
  if (!items.length) items.push('All listings')
  return items
}

const formatLastAlert = (value?: string | null) => {
  if (!value) return 'Never alerted'
  const date = new Date(value)
  return `Last alert: ${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
}
</script>

<template>
  <div class="space-y-4">
    <ErrorState v-if="error" :message="error" retry-label="Retry" @retry="load" />
    <ListSkeleton v-else-if="loading" :count="3" />

    <EmptyState
      v-else-if="!savedSearches.length"
      title="No saved searches yet"
      subtitle="Save a search from the search page to get alerts."
    />

    <div v-else class="space-y-3">
      <div
        v-for="search in savedSearches"
        :key="search.id"
        class="rounded-2xl bg-white p-4 shadow-soft border border-white/60"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-slate-900">{{ search.name || 'Saved search' }}</p>
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
            Alerts
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
            <label class="text-xs uppercase tracking-[0.08em] text-muted">Frequency</label>
            <select
              :value="search.frequency"
              class="rounded-xl border border-line bg-white px-3 py-2 text-xs font-semibold text-slate-700"
              @change="changeFrequency(search, ($event.target as HTMLSelectElement).value as SavedSearch['frequency'])"
            >
              <option value="instant">Instant</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
            </select>
          </div>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
          <Button size="sm" variant="ghost" @click="openEdit(search)">
            <Pencil class="mr-1 h-4 w-4" />
            Edit
          </Button>
          <Button size="sm" variant="ghost" class="text-red-500" @click="removeSearch(search)">
            <Trash2 class="mr-1 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>
    </div>
  </div>

  <ModalSheet v-model="editOpen" title="Edit saved search">
    <div class="space-y-4">
      <Input v-model="editForm.name" placeholder="Search name (optional)" />

      <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <h3 class="text-sm font-semibold text-slate-900">Filters</h3>

        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Location</p>
          <Input v-model="editForm.filters.location" placeholder="Search text (location, title, etc.)" />
          <Input v-model="editForm.filters.city" placeholder="City contains (e.g. Zagreb)" />
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Category</span>
            <select
              v-model="editForm.filters.category"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            >
              <option value="all">Any</option>
              <option value="villa">Villa</option>
              <option value="hotel">Hotel</option>
              <option value="apartment">Apartment</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Status</span>
            <select
              v-model="editForm.filters.status"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            >
              <option value="all">Any</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="archived">Archived</option>
              <option value="rented">Rented</option>
              <option value="expired">Expired</option>
              <option value="draft">Draft</option>
            </select>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Price min</span>
            <input
              v-model.number="editForm.filters.priceMin"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Price max</span>
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
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Area min (sqm)</span>
            <input
              v-model.number="editForm.filters.areaMin"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Area max (sqm)</span>
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
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Rooms</span>
            <input
              v-model.number="editForm.filters.rooms"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
            />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Guests</span>
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
          <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Rating</span>
          <select
            v-model.number="editForm.filters.rating"
            class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
          >
            <option :value="null">Any</option>
            <option :value="5">5+</option>
            <option :value="4">4+</option>
            <option :value="3">3+</option>
            <option :value="2">2+</option>
            <option :value="1">1+</option>
          </select>
        </label>

        <div class="flex items-center justify-between rounded-2xl bg-surface px-4 py-3">
          <div>
            <p class="font-semibold text-slate-900">Instant book</p>
            <p class="text-sm text-muted">Only show instant book listings</p>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input v-model="editForm.filters.instantBook" type="checkbox" class="peer sr-only" />
            <div
              class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-[18px]"
            ></div>
          </label>
        </div>

        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Amenities</p>
          <div class="grid grid-cols-2 gap-2">
            <label
              v-for="facility in amenityOptions"
              :key="facility"
              class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-xs font-semibold text-slate-700"
            >
              <input v-model="editForm.filters.amenities" :value="facility" type="checkbox" class="h-4 w-4 accent-primary" />
              {{ facility }}
            </label>
          </div>
        </div>

        <div class="space-y-3 rounded-2xl bg-surface px-4 py-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-900">Map radius</p>
              <p class="text-sm text-muted">Enable map-based radius filtering</p>
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
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Center lat</span>
              <input
                v-model.number="editForm.filters.centerLat"
                type="number"
                step="0.00001"
                class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
              />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Center lng</span>
              <input
                v-model.number="editForm.filters.centerLng"
                type="number"
                step="0.00001"
                class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
              />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Radius (km)</span>
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
          <p class="font-semibold text-slate-900">Alerts</p>
          <p class="text-sm text-muted">Enable in-app notifications</p>
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
        <p class="text-sm font-semibold text-slate-900">Frequency</p>
        <select
          v-model="editForm.frequency"
          class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700"
        >
          <option value="instant">Instant</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </select>
      </div>

      <Button block @click="saveEdit">Save changes</Button>
    </div>
  </ModalSheet>
</template>
