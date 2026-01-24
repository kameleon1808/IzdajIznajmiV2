<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CalendarClock, Heart, MapPin, Share2 } from 'lucide-vue-next'
import FacilityPill from '../components/listing/FacilityPill.vue'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import RatingStars from '../components/ui/RatingStars.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'
import { useChatStore } from '../stores/chat'
import { useRequestsStore } from '../stores/requests'
import { useViewingsStore } from '../stores/viewings'
import { useToastStore } from '../stores/toast'
import {
  geocodeLocation,
  getListingById,
  getListingFacilities,
  getListingReviews,
  resetListingLocation,
  updateListingLocation,
} from '../services'
import type { Listing, Review, ViewingSlot } from '../types'

const route = useRoute()
const router = useRouter()
const listingsStore = useListingsStore()
const auth = useAuthStore()
const requestsStore = useRequestsStore()
const viewingsStore = useViewingsStore()
const chatStore = useChatStore()
const toast = useToastStore()

const ListingMap = defineAsyncComponent(() => import('../components/listing/ListingMap.vue'))

const listing = ref<Listing | null>(null)
const facilities = ref<{ title: string; items: string[] }[]>([])
const reviews = ref<Review[]>([])
const showShare = ref(false)
const requestSheet = ref(false)
const expanded = ref(false)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)
const chatLoading = ref(false)
const viewingError = ref('')
const slotSubmitting = ref(false)
const viewingSubmitting = ref(false)
const mapVisible = ref(false)
const adjustingLocation = ref(false)
const savingLocation = ref(false)
const resettingLocation = ref(false)
const draftLocation = ref<{ lat: number; lng: number } | null>(null)
const fallbackLocation = ref<{ lat: number; lng: number } | null>(null)
const geocodingFallback = ref(false)
const fallbackError = ref('')

const requestForm = reactive({
  startDate: '',
  endDate: '',
  guests: 2,
  message: '',
})

const slotForm = reactive({
  startsAt: '',
  endsAt: '',
  capacity: 1,
  pattern: 'everyday' as ViewingSlot['pattern'],
  daysOfWeek: [] as number[],
  timeFrom: '17:00',
  timeTo: '19:00',
})

const viewingNotes = reactive<Record<string, string>>({})

const description = computed(
  () =>
    listing.value?.description ||
    'Enjoy a minimalist coastal escape with airy rooms, private pool, and endless ocean views. Perfect for workcation or slow holidays with friends.',
)

const isFormValid = computed(() => requestForm.guests > 0 && requestForm.message.trim().length >= 5)
const hasApplied = computed(
  () => !!listing.value && requestsStore.tenantRequests.some((app) => app.listing.id === listing.value?.id),
)
const landlordName = computed(() => listing.value?.landlord?.fullName || `User ${listing.value?.ownerId ?? ''}`)
const viewingSlots = computed(() => {
  const listingId = listing.value?.id
  if (!listingId) return []
  return viewingsStore.slotsByListing[listingId] ?? []
})
const sortedViewingSlots = computed(() =>
  [...viewingSlots.value].sort(
    (a, b) => new Date(a.startsAt).getTime() - new Date(b.startsAt).getTime(),
  ),
)
const visibleViewingSlots = computed(() =>
  isOwner.value ? sortedViewingSlots.value : sortedViewingSlots.value.filter((slot) => slot.isActive),
)
const isOwner = computed(
  () => !!listing.value && (auth.hasRole('admin') || String(listing.value.ownerId) === String(auth.user.id)),
)
const isValidCoord = (lat?: number | null, lng?: number | null) =>
  lat != null && lng != null && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
const listingCoords = computed(() =>
  listing.value && isValidCoord(listing.value.lat, listing.value.lng)
    ? { lat: listing.value.lat as number, lng: listing.value.lng as number }
    : null,
)
const mapCoords = computed(() => draftLocation.value ?? listingCoords.value ?? fallbackLocation.value)
const hasCoords = computed(() => !!mapCoords.value)
const locationSource = computed(() => listing.value?.locationSource ?? 'geocoded')
const mapUrl = computed(() => {
  if (!hasCoords.value || !listing.value) return ''
  const coords = mapCoords.value!
  return `https://www.openstreetmap.org/?mlat=${coords.lat}&mlon=${coords.lng}#map=18/${coords.lat}/${coords.lng}`
})
const canAdjustLocation = computed(() => isOwner.value && hasCoords.value)
const showDevCoords = computed(() => import.meta.env.DEV)

const loadData = async () => {
  loading.value = true
  error.value = ''
  try {
    const id = route.params.id as string
    listing.value = (await getListingById(id)) || null
    mapVisible.value = !!listingCoords.value
    adjustingLocation.value = false
    draftLocation.value = null
    fallbackLocation.value = null
    if (!listingCoords.value && listing.value) {
      await geocodeFallback()
    }
    facilities.value = await getListingFacilities(id)
    reviews.value = await getListingReviews(id)
    if (auth.hasRole('seeker')) {
      requestsStore.fetchTenantRequests()
    }
    await loadViewingSlots()
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load listing.'
  } finally {
    loading.value = false
  }
}

const retryLoad = () => loadData()

onMounted(() => {
  loadData()
})

watch(
  () => route.params.id,
  () => loadData(),
)

watch(
  hasCoords,
  (val) => {
    if (val) {
      mapVisible.value = true
    }
  },
)

const loadViewingSlots = async () => {
  if (!listing.value) return
  viewingError.value = ''
  try {
    await viewingsStore.fetchSlots(listing.value.id)
    autoFillSingleSlotTimes()
  } catch (err) {
    viewingError.value = (err as Error).message || 'Failed to load viewing slots.'
  }
}

const autoFillSingleSlotTimes = () => {
  if (slotForm.startsAt || !viewingSlots.value.length) return
  const first = viewingSlots.value[0]
  if (first?.timeFrom && first?.timeTo) {
    slotForm.timeFrom = first.timeFrom
    slotForm.timeTo = first.timeTo
  }
}

const toggleFavorite = () => {
  if (!listing.value) return
  listingsStore.toggleFavorite(listing.value.id)
  listing.value = { ...listing.value, isFavorite: !listing.value.isFavorite }
}

const openExternalMap = () => {
  if (!mapUrl.value) {
    toast.push({ title: 'No coordinates', message: 'Location is not available yet.', type: 'error' })
    return
  }
  window.open(mapUrl.value, '_blank')
}

const geocodeFallback = async () => {
  if (!listing.value || geocodingFallback.value) return
  const addressString = [listing.value.address, listing.value.city, listing.value.country]
    .filter(Boolean)
    .join(', ')
  if (!addressString.trim()) return
  geocodingFallback.value = true
  fallbackError.value = ''
  try {
    const coords = await geocodeLocation(addressString)
    if (isValidCoord(coords.lat, coords.lng)) {
      fallbackLocation.value = coords
      mapVisible.value = true
    } else {
      fallbackError.value = 'Geocoding returned invalid coordinates. Please adjust manually.'
    }
  } catch (err) {
    fallbackError.value = (err as Error).message || 'Geocode failed'
  } finally {
    geocodingFallback.value = false
  }
}

const startAdjustLocation = () => {
  if (!listing.value) {
    toast.push({ title: 'No coordinates', message: 'Add an address first to place the pin.', type: 'error' })
    return
  }
  if (!mapCoords.value) {
    toast.push({ title: 'No coordinates', message: 'Location missing—trying to re-geocode from address.', type: 'error' })
    geocodeFallback()
    return
  }
  draftLocation.value = { lat: mapCoords.value.lat, lng: mapCoords.value.lng }
  adjustingLocation.value = true
  mapVisible.value = true
}

const onMarkerMove = (coords: { lat: number; lng: number }) => {
  draftLocation.value = coords
}

const cancelAdjustLocation = () => {
  adjustingLocation.value = false
  draftLocation.value = null
}

const saveAdjustedLocation = async () => {
  if (!listing.value || !draftLocation.value) return
  if (!isValidCoord(draftLocation.value.lat, draftLocation.value.lng)) {
    toast.push({ title: 'Invalid coordinates', message: 'Lat must be between -90..90 and lng between -180..180.', type: 'error' })
    return
  }
  savingLocation.value = true
  try {
    const updated = await updateListingLocation(listing.value.id, {
      latitude: draftLocation.value.lat,
      longitude: draftLocation.value.lng,
    })
    listing.value = updated
    adjustingLocation.value = false
    toast.push({ title: 'Location updated', message: 'Map pin saved for this listing.', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Unable to save', message: (err as Error).message, type: 'error' })
  } finally {
    savingLocation.value = false
  }
}

const resetLocationToGeocoded = async () => {
  if (!listing.value) return
  resettingLocation.value = true
  try {
    const updated = await resetListingLocation(listing.value.id)
    listing.value = updated
    draftLocation.value = null
    adjustingLocation.value = false
    mapVisible.value = !!(updated.lat != null && updated.lng != null)
    toast.push({ title: 'Location reset', message: 'Pin refreshed from the address.', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Reset failed', message: (err as Error).message, type: 'error' })
  } finally {
    resettingLocation.value = false
  }
}

const openInquiry = () => {
  if (!auth.isAuthenticated && !auth.isMockMode) {
    router.push({ path: '/login', query: { returnUrl: route.fullPath } })
    return
  }
  if (!auth.hasRole('seeker')) {
    toast.push({ title: 'Access denied', message: 'Switch to Seeker role to send request.', type: 'error' })
    return
  }
  if (hasApplied.value) {
    toast.push({ title: 'Already applied', message: 'You can only apply once to this listing.', type: 'info' })
    return
  }
  requestSheet.value = true
}

const submitRequest = async () => {
  if (!listing.value || !isFormValid.value) return
  submitting.value = true
  try {
    await requestsStore.sendRequest({
      listingId: listing.value.id,
      message: requestForm.message,
    })
    toast.push({ title: 'Request sent', message: 'Landlord will respond shortly.', type: 'success' })
    requestSheet.value = false
    requestForm.startDate = ''
    requestForm.endDate = ''
    requestForm.message = ''
    router.push({ path: '/bookings', query: { tab: 'requests' } })
  } catch (err) {
    toast.push({ title: 'Failed to send', message: (err as Error).message, type: 'error' })
  } finally {
    submitting.value = false
  }
}

const openChat = async () => {
  if (!listing.value) return
  if (!auth.isAuthenticated && !auth.isMockMode) {
    router.push({ path: '/login', query: { returnUrl: route.fullPath } })
    return
  }
  if (!auth.hasRole('seeker')) {
    toast.push({ title: 'Access denied', message: 'Switch to Seeker role to chat with hosts.', type: 'error' })
    return
  }
  chatLoading.value = true
  try {
    const conversation = await chatStore.fetchConversationForListing(listing.value.id)
    await chatStore.fetchMessages(conversation.id)
    router.push(`/chat/${conversation.id}`)
  } catch (err) {
    toast.push({ title: 'Chat unavailable', message: (err as Error).message, type: 'error' })
  } finally {
    chatLoading.value = false
  }
}

const viewProfile = () => {
  if (!listing.value?.ownerId) return
  router.push(`/users/${listing.value.ownerId}`)
}

const formatSlotWindow = (slot: ViewingSlot) => {
  if (slot.timeFrom && slot.timeTo) {
    const label =
      slot.pattern === 'weekdays'
        ? 'Radni dani'
        : slot.pattern === 'weekends'
          ? 'Vikend'
          : slot.pattern === 'everyday'
            ? 'Svaki dan'
            : 'Dani u nedelji'
    return `${label}: ${slot.timeFrom} - ${slot.timeTo}`
  }
  const start = new Date(slot.startsAt)
  const end = new Date(slot.endsAt)
  return `${start.toLocaleDateString()} · ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })}`
}

const requestViewingSlot = async (slotId: string) => {
  if (!auth.isAuthenticated && !auth.isMockMode) {
    router.push({ path: '/login', query: { returnUrl: route.fullPath } })
    return
  }
  if (!auth.hasRole('seeker')) {
    toast.push({ title: 'Switch to seeker', message: 'Only seekers can request viewings.', type: 'error' })
    return
  }
  viewingSubmitting.value = true
  try {
    const note = viewingNotes[slotId] ?? ''
    const created = await viewingsStore.requestSlot(slotId, note)
    viewingNotes[slotId] = ''
    toast.push({ title: 'Viewing requested', message: 'Host will confirm your visit.', type: 'success' })
    router.push({ path: '/bookings', query: { tab: 'viewings', viewingRequestId: created.id } })
  } catch (err) {
    toast.push({ title: 'Unable to request', message: (err as Error).message, type: 'error' })
  } finally {
    viewingSubmitting.value = false
  }
}

const pickStartDate = () => {
  const today = new Date()
  return today.toISOString().split('T')[0]
}

const createViewingSlot = async () => {
  if (!listing.value) return
  if (!slotForm.timeFrom || !slotForm.timeTo) {
    toast.push({ title: 'Izaberi vremenski opseg', type: 'error' })
    return
  }
  const [fromH, fromM] = slotForm.timeFrom.split(':').map((v) => parseInt(v, 10))
  const [toH, toM] = slotForm.timeTo.split(':').map((v) => parseInt(v, 10))
  const baseDate = slotForm.startsAt || pickStartDate()
  const start = new Date(`${baseDate}T00:00:00`)
  start.setHours(fromH || 0, fromM || 0, 0, 0)
  const end = new Date(`${baseDate}T00:00:00`)
  end.setHours(toH || 0, toM || 0, 0, 0)
  if (end <= start) {
    toast.push({ title: 'Kraj mora biti posle početka', type: 'error' })
    return
  }
  slotSubmitting.value = true
  try {
    await viewingsStore.createSlot(listing.value.id, {
      startsAt: start.toISOString(),
      endsAt: end.toISOString(),
      capacity: slotForm.capacity || 1,
      pattern: slotForm.pattern,
      daysOfWeek: slotForm.daysOfWeek,
      timeFrom: slotForm.timeFrom,
      timeTo: slotForm.timeTo,
    })
    toast.push({ title: 'Termin dodat', type: 'success' })
    slotForm.startsAt = ''
    slotForm.endsAt = ''
    slotForm.capacity = 1
    slotForm.pattern = 'everyday'
    slotForm.daysOfWeek = []
  } catch (err) {
    toast.push({ title: 'Failed to add slot', message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

const toggleSlotActive = async (slotId: string, isActive: boolean) => {
  slotSubmitting.value = true
  try {
    await viewingsStore.updateSlot(slotId, { isActive })
    toast.push({ title: isActive ? 'Slot activated' : 'Slot paused', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Update failed', message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

const deleteViewingSlot = async (slotId: string) => {
  slotSubmitting.value = true
  try {
    await viewingsStore.deleteSlot(slotId)
    toast.push({ title: 'Viewing slot removed', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Cannot remove slot', message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

</script>

<template>
  <div>
    <div class="relative h-80 w-full overflow-hidden rounded-b-[28px]">
      <div v-if="loading" class="h-full w-full bg-surface shimmer"></div>
      <template v-else-if="listing">
        <img :src="listing.coverImage" alt="Hero" class="h-full w-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10" />
      </template>
    </div>

    <div v-if="error && !listing" class="px-4 pt-4">
      <ErrorState :message="error" retry-label="Retry" @retry="retryLoad" />
    </div>

    <div v-if="listing" class="-mt-6 space-y-6 rounded-t-[28px] bg-surface px-4 pb-28 pt-6">
      <ErrorState v-if="error" :message="error" retry-label="Reload" @retry="retryLoad" />
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
          <h1 class="text-xl font-semibold text-slate-900">{{ listing.title }}</h1>
          <div class="flex items-center gap-1 text-sm text-muted">
            <MapPin class="h-4 w-4 text-primary" />
            <span>{{ listing.city }}, {{ listing.country }}</span>
          </div>
          <p v-if="listing.address" class="text-xs text-muted">{{ listing.address }}</p>
          <RatingStars :rating="listing.rating" />
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-full bg-white p-3 shadow-soft" @click="toggleFavorite">
            <Heart :class="['h-5 w-5', listing.isFavorite ? 'fill-primary text-primary' : 'text-slate-800']" />
          </button>
          <button class="rounded-full bg-white p-3 shadow-soft" @click="showShare = true">
            <Share2 class="h-5 w-5 text-slate-800" />
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Common Facilities</h3>
          <button class="text-sm font-semibold text-primary" @click="router.push(`/listing/${route.params.id}/facilities`)">
            See all
          </button>
        </div>
        <div class="flex gap-2 overflow-x-auto pb-1">
          <FacilityPill v-for="item in facilities.flatMap((g) => g.items).slice(0, 6)" :key="item" :label="item" />
        </div>
        <p class="text-sm text-muted">
          Rooms: {{ listing.rooms ?? listing.beds }}
          · Beds: {{ listing.beds }}
          · Baths: {{ listing.baths }}
          <span v-if="listing.area">· Area: {{ listing.area }} sqm</span>
          <span v-if="listing.beds">· Guests: {{ Math.max(listing.beds, listing.rooms ?? listing.beds) }}</span>
        </p>
      </div>

      <div class="space-y-2">
        <h3 class="section-title">Description</h3>
        <p class="text-sm leading-relaxed text-muted">
          {{ expanded ? description : description.slice(0, 160) + (description.length > 160 ? '...' : '') }}
        </p>
        <button class="text-sm font-semibold text-primary" @click="expanded = !expanded">
          {{ expanded ? 'Read less' : 'Read more' }}
        </button>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <h3 class="section-title">Location</h3>
            <Badge v-if="locationSource === 'manual'" variant="info">Manual pin</Badge>
          </div>
          <Button variant="ghost" size="sm" class="text-sm font-semibold text-primary" :disabled="!hasCoords" @click="openExternalMap">
            View on map
          </Button>
        </div>
        <p class="text-xs text-muted">Preview uses stored coordinates—adjust if the pin looks off.</p>
        <div class="space-y-3 rounded-2xl border border-line bg-white p-3 shadow-soft">
          <component
            :is="ListingMap"
            v-if="mapVisible && hasCoords"
            :lat="mapCoords?.lat!"
            :lng="mapCoords?.lng!"
            :draggable="adjustingLocation"
            @update="onMarkerMove"
          />
          <div v-else class="flex h-64 items-center justify-center rounded-2xl bg-surface text-sm text-muted">
            Location not available yet.
          </div>
          <div v-if="showDevCoords && hasCoords" class="flex items-center justify-between text-[11px] font-mono text-muted">
            <span>lat: {{ mapCoords?.lat?.toFixed(6) }}</span>
            <span>lng: {{ mapCoords?.lng?.toFixed(6) }}</span>
          </div>
          <p v-if="fallbackError" class="text-xs font-semibold text-red-500">{{ fallbackError }}</p>
          <div v-if="canAdjustLocation" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-muted">
              {{ adjustingLocation ? 'Drag the pin to the correct spot, then save.' : 'Owners can fine-tune the map pin.' }}
            </p>
            <div class="flex flex-wrap gap-2">
              <Button
                v-if="adjustingLocation"
                size="sm"
                :loading="savingLocation"
                @click="saveAdjustedLocation"
              >
                Save pin
              </Button>
              <Button v-if="adjustingLocation" variant="ghost" size="sm" @click="cancelAdjustLocation">Cancel</Button>
              <Button v-else variant="secondary" size="sm" @click="startAdjustLocation">Adjust pin</Button>
              <Button variant="ghost" size="sm" :loading="resettingLocation" @click="resetLocationToGeocoded">
                Reset to address
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Reviews</h3>
          <button class="text-sm font-semibold text-primary" @click="router.push(`/listing/${route.params.id}/reviews`)">
            View all
          </button>
        </div>
        <div class="space-y-2">
          <div
            v-for="review in reviews"
            :key="review.id"
            class="flex items-start gap-3 rounded-2xl bg-white p-3 shadow-soft"
          >
            <img :src="review.avatarUrl" alt="avatar" class="h-10 w-10 rounded-2xl object-cover" />
            <div class="flex-1 space-y-1">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold text-slate-900">{{ review.userName }}</p>
                  <p class="text-xs text-muted">{{ review.date }}</p>
                </div>
                <span class="rounded-pill bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">{{ review.rating }} ★</span>
              </div>
              <p class="text-sm text-slate-700">{{ review.text }}</p>
            </div>
          </div>
          <p v-if="!reviews.length" class="text-sm text-muted">No reviews yet.</p>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Viewings</h3>
          <Badge variant="info">Visit in person</Badge>
        </div>
        <ErrorBanner v-if="viewingError" :message="viewingError" />
        <div class="space-y-3">
          <div v-if="isOwner" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <label class="space-y-1 text-xs font-semibold text-slate-900">
                Vremenski opseg od
                <input
                  v-model="slotForm.timeFrom"
                  type="time"
                  class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </label>
              <label class="space-y-1 text-xs font-semibold text-slate-900">
                do
                <input
                  v-model="slotForm.timeTo"
                  type="time"
                  class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </label>
              <label class="space-y-1 text-xs font-semibold text-slate-900">
                Kapacitet
                <input
                  v-model.number="slotForm.capacity"
                  min="1"
                  type="number"
                  class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </label>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
              <label class="space-y-1 text-xs font-semibold text-slate-900">
                Počinje od (datum)
                <input
                  v-model="slotForm.startsAt"
                  type="date"
                  class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </label>
              <div class="space-y-1 text-xs font-semibold text-slate-900">
                Opseg dana
                <div class="grid grid-cols-2 gap-2 text-[11px] font-semibold text-slate-800">
                  <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                    <input type="radio" value="everyday" v-model="slotForm.pattern" /> Svaki dan
                  </label>
                  <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                    <input type="radio" value="weekdays" v-model="slotForm.pattern" /> Radni dani
                  </label>
                  <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                    <input type="radio" value="weekends" v-model="slotForm.pattern" /> Vikend
                  </label>
                  <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                    <input type="radio" value="custom" v-model="slotForm.pattern" /> Odabrani dani
                  </label>
                </div>
                <div v-if="slotForm.pattern === 'custom'" class="mt-2 flex flex-wrap gap-2">
                  <label
                    v-for="(day, idx) in ['N','P','U','S','Č','P','S']"
                    :key="day"
                    class="flex items-center gap-1 rounded-xl border border-line bg-surface px-2 py-1 text-[11px] font-semibold text-slate-800"
                  >
                    <input
                      type="checkbox"
                      :value="idx"
                      v-model="slotForm.daysOfWeek"
                    />
                    {{ day }}
                  </label>
                </div>
              </div>
            </div>
            <div class="mt-3 flex justify-end">
              <Button size="md" :loading="slotSubmitting" @click="createViewingSlot">Dodaj slot</Button>
            </div>
          </div>

          <ListSkeleton v-if="viewingsStore.loadingSlots" :count="2" />

          <EmptyState
            v-else-if="!visibleViewingSlots.length"
            :title="isOwner ? 'No slots yet' : 'No viewing slots'"
            :subtitle="isOwner ? 'Add slots for seekers to book a visit' : 'Check back soon for available viewing times'"
            :icon="CalendarClock"
          />

          <div v-else class="space-y-2">
            <div
              v-for="slot in visibleViewingSlots"
              :key="slot.id"
              class="rounded-2xl border border-line bg-white p-4 shadow-soft"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-slate-900">{{ formatSlotWindow(slot) }}</p>
                  <p class="text-xs text-muted">
                    Capacity {{ slot.capacity }} · {{ slot.isActive ? 'Active' : 'Paused' }}
                  </p>
                </div>
                <Badge :variant="slot.isActive ? 'accepted' : 'info'">{{ slot.isActive ? 'Open' : 'Paused' }}</Badge>
              </div>

              <div class="mt-3 flex flex-col gap-2">
                <template v-if="isOwner">
                  <div class="flex gap-2">
                    <Button
                      variant="secondary"
                      size="md"
                      :loading="slotSubmitting"
                      class="flex-1"
                      @click="toggleSlotActive(slot.id, !slot.isActive)"
                    >
                      {{ slot.isActive ? 'Pause slot' : 'Activate slot' }}
                    </Button>
                    <Button
                      variant="ghost"
                      size="md"
                      :loading="slotSubmitting"
                      class="flex-1"
                      @click="deleteViewingSlot(slot.id)"
                    >
                      Delete
                    </Button>
                  </div>
                </template>
                <template v-else>
                  <textarea
                    v-model="viewingNotes[slot.id]"
                    rows="2"
                    class="w-full rounded-xl border border-line px-3 py-2 text-sm text-slate-900 placeholder:text-muted focus:border-primary focus:outline-none"
                    placeholder="Optional note for the host"
                  ></textarea>
                  <Button
                    variant="primary"
                    size="md"
                    class="w-full"
                    :loading="viewingSubmitting"
                    @click="requestViewingSlot(slot.id)"
                  >
                    Request this slot
                  </Button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-line bg-white p-4 shadow-soft">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs text-muted">Published by</p>
            <p class="text-base font-semibold text-slate-900">{{ landlordName }}</p>
          </div>
          <Button variant="secondary" size="md" @click="viewProfile">View profile</Button>
        </div>
      </div>
    </div>

    <EmptyState v-else-if="!loading" title="Listing unavailable" subtitle="Try again later or choose another stay" />

    <div v-if="listing" class="fixed bottom-4 left-0 right-0 z-40 mx-auto max-w-md px-4">
      <div class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-card">
        <div class="flex-1">
          <p class="text-xs text-muted">Price</p>
          <p class="text-lg font-semibold text-slate-900">${{ listing.pricePerNight }}/night</p>
        </div>
        <Badge variant="info">Rating {{ listing.rating }}</Badge>
        <Button variant="secondary" size="lg" :disabled="chatLoading" @click="openChat">
          {{ chatLoading ? 'Opening...' : 'Message host' }}
        </Button>
        <Button size="lg" :disabled="hasApplied" @click="openInquiry">
          {{ hasApplied ? 'Already applied' : 'Apply' }}
        </Button>
      </div>
    </div>
  </div>

  <ModalSheet v-model="requestSheet" title="Request to book">
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1 text-xs font-semibold text-slate-900">
          Check in
          <input v-model="requestForm.startDate" type="date" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
        </label>
        <label class="space-y-1 text-xs font-semibold text-slate-900">
          Check out
          <input v-model="requestForm.endDate" type="date" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
        </label>
      </div>
      <div class="flex items-center justify-between rounded-2xl bg-surface px-3 py-3">
        <div>
          <p class="text-sm font-semibold text-slate-900">Guests</p>
          <p class="text-xs text-muted">Choose group size</p>
        </div>
        <div class="flex items-center gap-2">
          <Button size="md" variant="secondary" @click="requestForm.guests = Math.max(1, requestForm.guests - 1)">-</Button>
          <span class="w-10 text-center text-sm font-semibold">{{ requestForm.guests }}</span>
          <Button size="md" variant="secondary" @click="requestForm.guests = requestForm.guests + 1">+</Button>
        </div>
      </div>
      <label class="space-y-2 text-sm font-semibold text-slate-900">
        Message to host
        <textarea
          v-model="requestForm.message"
          rows="3"
          class="w-full rounded-2xl border border-line bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-muted focus:border-primary focus:outline-none"
          placeholder="Share your plans or timing"
        ></textarea>
      </label>
      <Button block size="lg" :disabled="!isFormValid || submitting" @click="submitRequest">
        {{ submitting ? 'Sending...' : 'Send Request' }}
      </Button>
    </div>
  </ModalSheet>

  <ModalSheet v-model="showShare" title="Share this stay">
    <div class="space-y-4">
      <div class="flex items-center gap-3 rounded-2xl bg-surface px-3 py-2">
        <div class="h-16 w-16 overflow-hidden rounded-2xl">
          <img :src="listing?.coverImage" alt="preview" class="h-full w-full object-cover" />
        </div>
        <div class="flex-1">
          <h4 class="font-semibold text-slate-900">{{ listing?.title }}</h4>
          <p class="text-sm text-muted">{{ listing?.rating }} ★ · ${{ listing?.pricePerNight }}/night</p>
        </div>
      </div>

      <div class="rounded-2xl border border-line p-3">
        <p class="text-sm font-semibold text-slate-900">Copy link</p>
        <div class="mt-2 flex items-center gap-2 rounded-xl bg-surface px-3 py-2">
          <span class="flex-1 truncate text-sm text-muted">https://izdaj-iznajmi.app/l/{{ listing?.id }}</span>
          <Button variant="secondary" size="md">Copy</Button>
        </div>
      </div>

      <div class="grid grid-cols-4 gap-3 text-center">
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-surface p-3 text-sm font-semibold text-slate-800">
          <div class="rounded-full bg-primary/10 px-3 py-2 text-primary">WA</div>
          WhatsApp
        </div>
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-surface p-3 text-sm font-semibold text-slate-800">
          <div class="rounded-full bg-primary/10 px-3 py-2 text-primary">FB</div>
          Facebook
        </div>
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-surface p-3 text-sm font-semibold text-slate-800">
          <div class="rounded-full bg-primary/10 px-3 py-2 text-primary">IG</div>
          Instagram
        </div>
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-surface p-3 text-sm font-semibold text-slate-800">
          <div class="rounded-full bg-primary/10 px-3 py-2 text-primary">TG</div>
          Telegram
        </div>
      </div>
    </div>
  </ModalSheet>
</template>
