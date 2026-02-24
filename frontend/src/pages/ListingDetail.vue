<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CalendarClock, Heart, MapPin, Share2 } from 'lucide-vue-next'
import FacilityPill from '../components/listing/FacilityPill.vue'
import ListingCard from '../components/listing/ListingCard.vue'
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
import { useLanguageStore } from '../stores/language'
import {
  geocodeLocation,
  getListingById,
  getListingFacilities,
  getListingReviews,
  getSimilarListings,
  resetListingLocation,
  updateListingLocation,
} from '../services'
import type { Listing, Review, ViewingSlot } from '../types'
import ListingGallery from '../components/listing/ListingGallery.vue'

const route = useRoute()
const router = useRouter()
const listingsStore = useListingsStore()
const auth = useAuthStore()
const requestsStore = useRequestsStore()
const viewingsStore = useViewingsStore()
const chatStore = useChatStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const ListingMap = defineAsyncComponent(() => import('../components/listing/ListingMap.vue'))

const listing = ref<Listing | null>(null)
const facilities = ref<{ title: string; items: string[] }[]>([])
const reviews = ref<Review[]>([])
const similarListings = ref<Listing[]>([])
const showShare = ref(false)
const requestSheet = ref(false)
const expanded = ref(false)
const loading = ref(true)
const error = ref('')
const similarLoading = ref(false)
const similarError = ref('')
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
const viewingDates = reactive<Record<string, string>>({})
const viewingTimes = reactive<Record<string, string>>({})

const description = computed(
  () =>
    listing.value?.description ||
    t('listing.descriptionFallback'),
)

const parseDateOnly = (value: string): Date | null => {
  if (!value) return null
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

const addOneMonth = (date: Date): Date => {
  const day = date.getDate()
  const next = new Date(date.getFullYear(), date.getMonth() + 1, 1)
  const lastDay = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate()
  next.setDate(Math.min(day, lastDay))
  return next
}

const formatYmd = (date: Date): string => {
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

const hasMinimumMonthReservation = computed(() => {
  const start = parseDateOnly(requestForm.startDate)
  const end = parseDateOnly(requestForm.endDate)
  if (!start || !end) return false
  return end.getTime() >= addOneMonth(start).getTime()
})

const minEndDate = computed(() => {
  const start = parseDateOnly(requestForm.startDate)
  if (!start) return pickStartDate()
  return formatYmd(addOneMonth(start))
})

const activeRequestEndDate = computed<string | null>(() => {
  if (!listing.value) return null
  const today = pickStartDate()
  const active = requestsStore.tenantRequests.filter(
    (app) =>
      app.listing.id === listing.value!.id &&
      (app.status === 'submitted' || app.status === 'accepted') &&
      !!app.endDate &&
      app.endDate > today,
  )
  if (!active.length) return null
  return active.reduce((max, app) => (app.endDate! > max ? app.endDate! : max), active[0]!.endDate!)
})

const minStartDate = computed(() => {
  if (!activeRequestEndDate.value) return pickStartDate()
  const d = new Date(`${activeRequestEndDate.value}T00:00:00`)
  d.setDate(d.getDate() + 1)
  return formatYmd(d)
})

const isFormValid = computed(() => {
  if (!requestForm.guests || !requestForm.startDate || !requestForm.endDate) return false
  if (!hasMinimumMonthReservation.value) return false
  if (requestForm.startDate < minStartDate.value) return false
  return true
})
const landlordName = computed(() => listing.value?.landlord?.fullName || `${t('common.user')} ${listing.value?.ownerId ?? ''}`)
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
const dayLabels = computed(() => [
  t('days.sun'),
  t('days.mon'),
  t('days.tue'),
  t('days.wed'),
  t('days.thu'),
  t('days.fri'),
  t('days.sat'),
])

const loadData = async () => {
  loading.value = true
  error.value = ''
  similarListings.value = []
  similarError.value = ''
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
    setTimeout(() => {
      void loadSimilar()
    }, 0)
  } catch (err) {
    error.value = (err as Error).message || t('listing.loadFailed')
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
    seedViewingDefaults()
  } catch (err) {
    viewingError.value = (err as Error).message || t('listing.viewingLoadFailed')
  }
}

const loadSimilar = async () => {
  if (!listing.value) return
  similarLoading.value = true
  similarError.value = ''
  try {
    similarListings.value = await getSimilarListings(String(listing.value.id), 8)
  } catch (err) {
    similarError.value = (err as Error).message || t('listing.similarLoadFailed')
  } finally {
    similarLoading.value = false
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

const toDateInput = (iso?: string): string => {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const [ymd] = date.toISOString().split('T')
  return ymd ?? ''
}

const toTimeInput = (iso?: string): string => {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toTimeString().slice(0, 5)
}

const seedViewingDefaults = () => {
  viewingSlots.value.forEach((slot) => {
    if (!viewingDates[slot.id]) {
      viewingDates[slot.id] = toDateInput(slot.startsAt) || toDateInput(new Date().toISOString())
    }
    if (!viewingTimes[slot.id]) {
      viewingTimes[slot.id] = slot.timeFrom || toTimeInput(slot.startsAt) || '10:00'
    }
  })
}

const parseTimeToMinutes = (time?: string | null) => {
  if (!time) return null
  const [hStr, mStr] = time.split(':')
  if (!hStr || !mStr) return null
  const h = Number.parseInt(hStr, 10)
  const m = Number.parseInt(mStr, 10)
  if (Number.isNaN(h) || Number.isNaN(m)) return null
  return h * 60 + m
}

const isWeekend = (day: number) => day === 0 || day === 6

const buildScheduledAt = (slot: ViewingSlot): string | null => {
  const date = viewingDates[slot.id]
  const time = viewingTimes[slot.id]
  if (!date || !time) {
    toast.push({
      title: t('listing.viewing.selectDateTimeTitle'),
      message: t('listing.viewing.selectDateTimeMessage'),
      type: 'error',
    })
    return null
  }
  const scheduled = new Date(`${date}T${time}:00`)
  if (Number.isNaN(scheduled.getTime())) {
    toast.push({
      title: t('listing.viewing.invalidDateTitle'),
      message: t('listing.viewing.invalidDateMessage'),
      type: 'error',
    })
    return null
  }
  if (scheduled.getTime() <= Date.now()) {
    toast.push({ title: t('listing.viewing.futureOnly'), type: 'error' })
    return null
  }

  const slotStart = new Date(slot.startsAt)
  const slotEnd = new Date(slot.endsAt)
  const selectedDate = new Date(scheduled.getFullYear(), scheduled.getMonth(), scheduled.getDate())
  const startDate = new Date(slotStart.getFullYear(), slotStart.getMonth(), slotStart.getDate())
  const endDate = new Date(slotEnd.getFullYear(), slotEnd.getMonth(), slotEnd.getDate())
  const isRecurring = slot.pattern && slot.pattern !== 'once'
  const hasRangeEnd = endDate.getTime() - startDate.getTime() >= 24 * 60 * 60 * 1000
  if (!isRecurring) {
    if (selectedDate < startDate || selectedDate > endDate) {
      toast.push({ title: t('listing.viewing.dateOutOfRange'), type: 'error' })
      return null
    }
  } else {
    if (selectedDate < startDate) {
      toast.push({ title: t('listing.viewing.dateOutOfRange'), type: 'error' })
      return null
    }
    if (hasRangeEnd && selectedDate > endDate) {
      toast.push({ title: t('listing.viewing.dateOutOfRange'), type: 'error' })
      return null
    }
  }

  const dayOfWeek = scheduled.getDay()
  if (slot.pattern === 'weekends' && !isWeekend(dayOfWeek)) {
    toast.push({ title: t('listing.viewing.weekendOnly'), type: 'error' })
    return null
  }
  if (slot.pattern === 'weekdays' && isWeekend(dayOfWeek)) {
    toast.push({ title: t('listing.viewing.weekdayOnly'), type: 'error' })
    return null
  }
  if (slot.pattern === 'custom' && slot.daysOfWeek?.length && !slot.daysOfWeek.includes(dayOfWeek)) {
    toast.push({ title: t('listing.viewing.dayUnavailable'), type: 'error' })
    return null
  }

  if (slot.timeFrom && slot.timeTo) {
    const selectedMinutes = scheduled.getHours() * 60 + scheduled.getMinutes()
    const fromMinutes = parseTimeToMinutes(slot.timeFrom)
    const toMinutes = parseTimeToMinutes(slot.timeTo)
    if (fromMinutes == null || toMinutes == null || toMinutes < fromMinutes) {
      toast.push({ title: t('listing.viewing.invalidTimeRange'), type: 'error' })
      return null
    }
    if (selectedMinutes < fromMinutes || selectedMinutes > toMinutes) {
      toast.push({ title: t('listing.viewing.timeOutOfRange'), type: 'error' })
      return null
    }
  } else if (scheduled < slotStart || scheduled > slotEnd) {
    toast.push({ title: t('listing.viewing.timeOutOfRange'), type: 'error' })
    return null
  }

  return scheduled.toISOString()
}

const toggleFavorite = () => {
  if (!listing.value) return
  listingsStore.toggleFavorite(listing.value.id)
  listing.value = { ...listing.value, isFavorite: !listing.value.isFavorite }
}

const openExternalMap = () => {
  if (!mapUrl.value) {
    toast.push({
      title: t('listing.location.noCoordsTitle'),
      message: t('listing.location.noCoordsMessage'),
      type: 'error',
    })
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
      fallbackError.value = t('listing.location.geocodeInvalid')
    }
  } catch (err) {
    fallbackError.value = (err as Error).message || t('listing.location.geocodeFailed')
  } finally {
    geocodingFallback.value = false
  }
}

const startAdjustLocation = () => {
  if (!listing.value) {
    toast.push({
      title: t('listing.location.noCoordsTitle'),
      message: t('listing.location.addAddressFirst'),
      type: 'error',
    })
    return
  }
  if (!mapCoords.value) {
    toast.push({
      title: t('listing.location.noCoordsTitle'),
      message: t('listing.location.reGeocode'),
      type: 'error',
    })
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
    toast.push({
      title: t('listing.location.invalidCoordsTitle'),
      message: t('listing.location.invalidCoordsMessage'),
      type: 'error',
    })
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
    toast.push({ title: t('listing.location.updatedTitle'), message: t('listing.location.updatedMessage'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('common.unableToSave'), message: (err as Error).message, type: 'error' })
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
    toast.push({ title: t('listing.location.resetTitle'), message: t('listing.location.resetMessage'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('common.resetFailed'), message: (err as Error).message, type: 'error' })
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
    toast.push({ title: t('listing.request.accessDenied'), message: t('listing.request.switchToSeeker'), type: 'error' })
    return
  }
  requestSheet.value = true
}

const submitRequest = async () => {
  if (!listing.value || !isFormValid.value) return
  if (!hasMinimumMonthReservation.value) {
    toast.push({
      title: t('listing.requestMinMonth'),
      type: 'error',
    })
    return
  }
  submitting.value = true
  try {
    await requestsStore.sendRequest({
      listingId: listing.value.id,
      message: requestForm.message,
      startDate: requestForm.startDate,
      endDate: requestForm.endDate,
    })
    toast.push({ title: t('listing.request.sentTitle'), message: t('listing.request.sentMessage'), type: 'success' })
    requestSheet.value = false
    requestForm.startDate = ''
    requestForm.endDate = ''
    requestForm.message = ''
    router.push({ path: '/bookings', query: { tab: 'requests' } })
  } catch (err) {
    toast.push({ title: t('common.failedToSend'), message: (err as Error).message, type: 'error' })
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
    toast.push({ title: t('listing.chat.accessDenied'), message: t('listing.chat.switchToSeeker'), type: 'error' })
    return
  }
  chatLoading.value = true
  try {
    const conversation = await chatStore.fetchConversationForListing(listing.value.id)
    await chatStore.fetchMessages(conversation.id)
    router.push(`/chat/${conversation.id}`)
  } catch (err) {
    toast.push({ title: t('chat.unavailable'), message: (err as Error).message, type: 'error' })
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
        ? t('listing.weekdays')
        : slot.pattern === 'weekends'
          ? t('listing.weekends')
          : slot.pattern === 'everyday'
            ? t('listing.everyday')
            : t('listing.customDays')
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
    toast.push({ title: t('listing.viewing.switchToSeeker'), message: t('listing.viewing.onlySeekers'), type: 'error' })
    return
  }
  viewingSubmitting.value = true
  try {
    const slot = viewingSlots.value.find((item) => item.id === slotId)
    if (!slot) {
      toast.push({ title: t('listing.viewing.slotNotFound'), type: 'error' })
      return
    }
    const scheduledAt = buildScheduledAt(slot)
    if (!scheduledAt) return
    const note = viewingNotes[slotId] ?? ''
    const created = await viewingsStore.requestSlot(slotId, note, scheduledAt)
    viewingNotes[slotId] = ''
    toast.push({ title: t('listing.viewing.requestedTitle'), message: t('listing.viewing.requestedMessage'), type: 'success' })
    router.push({ path: '/bookings', query: { tab: 'viewings', viewingRequestId: created.id } })
  } catch (err) {
    toast.push({ title: t('listing.viewing.unableToRequest'), message: (err as Error).message, type: 'error' })
  } finally {
    viewingSubmitting.value = false
  }
}

const pickStartDate = () => {
  const today = new Date()
  return formatYmd(today)
}

const galleryImages = computed(() => {
  if (!listing.value) return []
  if (listing.value.imagesDetailed?.length) {
    const sorted = [...listing.value.imagesDetailed].sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0))
    const urls = sorted.map((i) => i.url).filter(Boolean)
    if (urls.length) return urls
  }
  if (listing.value.images?.length) return listing.value.images
  return listing.value.coverImage ? [listing.value.coverImage] : []
})

const createViewingSlot = async () => {
  if (!listing.value) return
  if (!slotForm.timeFrom || !slotForm.timeTo) {
    toast.push({ title: t('listing.viewing.selectTimeRange'), type: 'error' })
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
    toast.push({ title: t('listing.viewing.endAfterStart'), type: 'error' })
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
    toast.push({ title: t('listing.viewing.slotAdded'), type: 'success' })
    slotForm.startsAt = ''
    slotForm.endsAt = ''
    slotForm.capacity = 1
    slotForm.pattern = 'everyday'
    slotForm.daysOfWeek = []
  } catch (err) {
    toast.push({ title: t('listing.viewing.addSlotFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

const toggleSlotActive = async (slotId: string, isActive: boolean) => {
  slotSubmitting.value = true
  try {
    await viewingsStore.updateSlot(slotId, { isActive })
    toast.push({ title: isActive ? t('listing.viewing.slotActivated') : t('listing.viewing.slotPaused'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('common.updateFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

const deleteViewingSlot = async (slotId: string) => {
  slotSubmitting.value = true
  try {
    await viewingsStore.deleteSlot(slotId)
    toast.push({ title: t('listing.viewing.slotRemoved'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('listing.viewing.removeFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    slotSubmitting.value = false
  }
}

</script>

<template>
  <div>
    <div class="relative w-full overflow-hidden rounded-b-[28px] bg-surface">
      <div v-if="loading" class="h-80 w-full bg-surface shimmer md:h-96"></div>
      <div v-else-if="listing" class="px-4 pb-4 pt-3">
        <ListingGallery :images="galleryImages" :alt="listing.title" />
      </div>
    </div>

    <div v-if="error && !listing" class="px-4 pt-4">
      <ErrorState :message="error" :retry-label="t('common.retry')" @retry="retryLoad" />
    </div>

    <div v-if="listing" class="-mt-6 space-y-6 rounded-t-[28px] bg-surface px-4 pb-28 pt-6">
      <ErrorState v-if="error" :message="error" :retry-label="t('common.reload')" @retry="retryLoad" />
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
      <div class="grid gap-3">
        <div class="flex-1">
          <p class="text-xs text-muted">{{ t('listing.price') }}</p>
          <p class="text-lg font-semibold text-slate-900">€{{ listing.pricePerMonth }}/{{ t('listing.month') }}</p>
        </div>
      </div>

      <div class="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:space-y-0">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="section-title">{{ t('listing.commonFacilities') }}</h3>
            <button class="text-sm font-semibold text-primary" @click="router.push(`/listing/${route.params.id}/facilities`)">
              {{ t('common.seeAll') }}
            </button>
          </div>
          <div class="flex gap-2 overflow-x-auto pb-1">
            <FacilityPill v-for="item in facilities.flatMap((g) => g.items).slice(0, 6)" :key="item" :label="item" />
          </div>
          <p class="text-sm text-muted">
            {{ t('listing.rooms') }}: {{ listing.rooms ?? listing.beds }}
            · {{ t('listing.bedsLabel') }}: {{ listing.beds }}
            · {{ t('listing.bathsLabel') }}: {{ listing.baths }}
            <span v-if="listing.area">· {{ t('listing.area') }}: {{ listing.area }} {{ t('filters.sqm') }}</span>
            <span v-if="listing.beds">· {{ t('listing.guests') }}: {{ Math.max(listing.beds, listing.rooms ?? listing.beds) }}</span>
          </p>
        </div>

        <div class="space-y-2">
          <h3 class="section-title">{{ t('listing.description') }}</h3>
          <p class="text-sm leading-relaxed text-muted">
            {{ expanded ? description : description.slice(0, 160) + (description.length > 160 ? '...' : '') }}
          </p>
          <button class="text-sm font-semibold text-primary" @click="expanded = !expanded">
            {{ expanded ? t('common.readLess') : t('common.readMore') }}
          </button>
        </div>
      </div>

      <div class="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:space-y-0">
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-2">
              <h3 class="section-title">{{ t('listing.location') }}</h3>
              <Badge v-if="locationSource === 'manual'" variant="info">{{ t('listing.manualPin') }}</Badge>
            </div>
            <Button variant="ghost" size="sm" class="text-sm font-semibold text-primary" :disabled="!hasCoords" @click="openExternalMap">
              {{ t('listing.viewOnMap') }}
            </Button>
          </div>
          <p class="text-xs text-muted">{{ t('listing.locationHint') }}</p>
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
              {{ t('listing.locationUnavailable') }}
            </div>
            <div v-if="showDevCoords && hasCoords" class="flex items-center justify-between text-[11px] font-mono text-muted">
              <span>{{ t('listing.lat') }}: {{ mapCoords?.lat?.toFixed(6) }}</span>
              <span>{{ t('listing.lng') }}: {{ mapCoords?.lng?.toFixed(6) }}</span>
            </div>
            <p v-if="fallbackError" class="text-xs font-semibold text-red-500">{{ fallbackError }}</p>
            <div v-if="canAdjustLocation" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-muted">
                {{ adjustingLocation ? t('listing.locationDragPin') : t('listing.locationOwnerHint') }}
              </p>
              <div class="flex flex-wrap gap-2">
                <Button
                  v-if="adjustingLocation"
                  size="sm"
                  :loading="savingLocation"
                  @click="saveAdjustedLocation"
                >
                  {{ t('listing.savePin') }}
                </Button>
                <Button v-if="adjustingLocation" variant="ghost" size="sm" @click="cancelAdjustLocation">{{ t('common.cancel') }}</Button>
                <Button v-else variant="secondary" size="sm" @click="startAdjustLocation">{{ t('listing.adjustPin') }}</Button>
                <Button variant="ghost" size="sm" :loading="resettingLocation" @click="resetLocationToGeocoded">
                  {{ t('listing.resetToAddress') }}
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex items-start justify-between">
            <h3 class="section-title">{{ t('listing.viewings') }}</h3>
            <Badge variant="info">{{ t('listing.viewingsHint') }}</Badge>
          </div>
          <ErrorBanner v-if="viewingError" :message="viewingError" />
          <div class="space-y-3">
            <div v-if="isOwner" class="rounded-2xl border border-line bg-white p-4 shadow-soft">
              <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <label class="space-y-1 text-xs font-semibold text-slate-900">
                  {{ t('listing.timeRangeFrom') }}
                  <input
                    v-model="slotForm.timeFrom"
                    type="time"
                    class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  />
                </label>
                <label class="space-y-1 text-xs font-semibold text-slate-900">
                  {{ t('listing.timeRangeTo') }}
                  <input
                    v-model="slotForm.timeTo"
                    type="time"
                    class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  />
                </label>
                <label class="space-y-1 text-xs font-semibold text-slate-900">
                  {{ t('listing.capacity') }}
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
                  {{ t('listing.startsAtDate') }}
                  <input
                    v-model="slotForm.startsAt"
                    type="date"
                    class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  />
                </label>
                <div class="space-y-1 text-xs font-semibold text-slate-900">
                  {{ t('listing.dayRange') }}
                  <div class="grid grid-cols-2 gap-2 text-[11px] font-semibold text-slate-800">
                    <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                      <input type="radio" value="everyday" v-model="slotForm.pattern" /> {{ t('listing.everyday') }}
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                      <input type="radio" value="weekdays" v-model="slotForm.pattern" /> {{ t('listing.weekdays') }}
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                      <input type="radio" value="weekends" v-model="slotForm.pattern" /> {{ t('listing.weekends') }}
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                      <input type="radio" value="custom" v-model="slotForm.pattern" /> {{ t('listing.customDays') }}
                    </label>
                  </div>
                  <div v-if="slotForm.pattern === 'custom'" class="mt-2 flex flex-wrap gap-2">
                    <label
                      v-for="(day, idx) in dayLabels"
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
                <Button size="md" :loading="slotSubmitting" @click="createViewingSlot">{{ t('listing.addSlot') }}</Button>
              </div>
            </div>

            <ListSkeleton v-if="viewingsStore.loadingSlots" :count="2" />

            <EmptyState
              v-else-if="!visibleViewingSlots.length"
              :title="isOwner ? t('listing.noSlotsTitleOwner') : t('listing.noSlotsTitle')"
              :subtitle="isOwner ? t('listing.noSlotsSubtitleOwner') : t('listing.noSlotsSubtitle')"
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
                      {{ t('listing.capacity') }} {{ slot.capacity }} · {{ slot.isActive ? t('listing.active') : t('listing.paused') }}
                    </p>
                  </div>
                  <Badge :variant="slot.isActive ? 'accepted' : 'info'">{{ slot.isActive ? t('listing.open') : t('listing.paused') }}</Badge>
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
                        {{ slot.isActive ? t('listing.pauseSlot') : t('listing.activateSlot') }}
                      </Button>
                      <Button
                        variant="ghost"
                        size="md"
                        :loading="slotSubmitting"
                        class="flex-1"
                        @click="deleteViewingSlot(slot.id)"
                      >
                        {{ t('common.delete') }}
                      </Button>
                    </div>
                  </template>
                  <template v-else>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                      <label class="space-y-1 text-xs font-semibold text-slate-900">
                        {{ t('listing.date') }}
                        <input
                          v-model="viewingDates[slot.id]"
                          type="date"
                          class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        />
                      </label>
                      <label class="space-y-1 text-xs font-semibold text-slate-900">
                        {{ t('listing.time') }}
                        <input
                          v-model="viewingTimes[slot.id]"
                          type="time"
                          :min="slot.timeFrom ?? undefined"
                          :max="slot.timeTo ?? undefined"
                          class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        />
                      </label>
                    </div>
                    <textarea
                      v-model="viewingNotes[slot.id]"
                      rows="2"
                      class="w-full rounded-xl border border-line px-3 py-2 text-sm text-slate-900 placeholder:text-muted focus:border-primary focus:outline-none"
                      :placeholder="t('listing.optionalNote')"
                    ></textarea>
                    <Button
                      variant="primary"
                      size="md"
                      class="w-full"
                      :loading="viewingSubmitting"
                      @click="requestViewingSlot(slot.id)"
                    >
                      {{ t('listing.requestSlot') }}
                    </Button>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:space-y-0">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="section-title">{{ t('titles.reviews') }}</h3>
            <button class="text-sm font-semibold text-primary" @click="router.push(`/listing/${route.params.id}/reviews`)">
              {{ t('common.viewAll') }}
            </button>
          </div>
          <div class="space-y-2">
            <div
            v-for="review in reviews"
            :key="review.id"
            class="flex items-start gap-3 rounded-2xl bg-white p-3 shadow-soft"
          >
              <img :src="review.avatarUrl" :alt="t('topbar.avatarAlt')" class="h-10 w-10 rounded-2xl object-cover" />
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
            <p v-if="!reviews.length" class="text-sm text-muted">{{ t('reviews.emptyTitle') }}.</p>
          </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-xs text-muted">{{ t('listing.publishedBy') }}</p>
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-base font-semibold text-slate-900">{{ landlordName }}</p>
                <Badge v-if="listing?.landlord?.verificationStatus === 'approved'" variant="accepted">
                  {{ t('listing.verifiedLandlord') }}
                </Badge>
                <Badge v-if="listing?.landlord?.badges?.includes('top_landlord')" variant="info">
                  {{ t('listing.topLandlord') }}
                </Badge>
              </div>
            </div>
            <Button variant="secondary" size="md" @click="viewProfile">{{ t('listing.viewProfile') }}</Button>
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
          <h2 class="section-title">{{ t('listing.similarListings') }}</h2>
        </div>
        <ErrorBanner v-if="similarError" :message="similarError" />
        <ListSkeleton v-if="similarLoading && !similarListings.length" :count="3" />
        <div v-else class="flex gap-4 overflow-x-auto pb-2">
          <ListingCard
            v-for="item in similarListings"
            :key="item.id"
            class="w-72 shrink-0"
            :listing="item"
            :use-translations="true"
            @toggle="listingsStore.toggleFavorite"
            @click="router.push(`/listing/${item.id}`)"
          />
        </div>
        <EmptyState
          v-if="!similarLoading && !similarListings.length && !similarError"
          :title="t('listing.noSimilarTitle')"
          :subtitle="t('listing.noSimilarSubtitle')"
        />
      </div>
    </div>

    <EmptyState
      v-else-if="!loading"
      :title="t('listing.unavailableTitle')"
      :subtitle="t('listing.unavailableSubtitle')"
    />

    <div v-if="listing" class="fixed bottom-4 left-0 right-0 z-[1200] mx-auto max-w-md px-4">
      <div class="rounded-3xl bg-white p-4 shadow-card">
        <div class="grid grid-cols-2 gap-3">
          <Button block variant="secondary" size="lg" :disabled="chatLoading" @click="openChat">
            {{ chatLoading ? t('listing.openingChat') : t('listing.messageHost') }}
          </Button>
          <Button block size="lg" @click="openInquiry">
            {{ t('listing.apply') }}
          </Button>
        </div>
      </div>
    </div>
  </div>

  <ModalSheet v-model="requestSheet" :title="t('listing.requestTitle')">
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1 text-xs font-semibold text-slate-900">
          {{ t('listing.checkIn') }}
          <input
            v-model="requestForm.startDate"
            type="date"
            :min="minStartDate"
            class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
          />
        </label>
        <label class="space-y-1 text-xs font-semibold text-slate-900">
          {{ t('listing.checkOut') }}
          <input
            v-model="requestForm.endDate"
            type="date"
            :min="minEndDate"
            class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
          />
        </label>
      </div>
      <p class="text-xs text-muted">{{ t('listing.requestMinMonth') }}</p>
      <div class="flex items-center justify-between rounded-2xl bg-surface px-3 py-3">
        <div>
          <p class="text-sm font-semibold text-slate-900">{{ t('filters.guests') }}</p>
          <p class="text-xs text-muted">{{ t('listing.chooseGroupSize') }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Button size="md" variant="secondary" @click="requestForm.guests = Math.max(1, requestForm.guests - 1)">-</Button>
          <span class="w-10 text-center text-sm font-semibold">{{ requestForm.guests }}</span>
          <Button size="md" variant="secondary" @click="requestForm.guests = requestForm.guests + 1">+</Button>
        </div>
      </div>
      <label class="space-y-2 text-sm font-semibold text-slate-900">
        {{ t('listing.messageToHost') }}
        <textarea
          v-model="requestForm.message"
          rows="3"
          class="w-full rounded-2xl border border-line bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-muted focus:border-primary focus:outline-none"
          :placeholder="t('listing.messagePlaceholder')"
        ></textarea>
      </label>
      <Button block size="lg" :disabled="!isFormValid || submitting" @click="submitRequest">
        {{ submitting ? t('listing.sending') : t('listing.sendRequest') }}
      </Button>
    </div>
  </ModalSheet>

  <ModalSheet v-model="showShare" :title="t('listing.shareTitle')">
    <div class="space-y-4">
      <div class="flex items-center gap-3 rounded-2xl bg-surface px-3 py-2">
        <div class="h-16 w-16 overflow-hidden rounded-2xl">
          <img :src="listing?.coverImage" :alt="t('listing.previewAlt')" class="h-full w-full object-cover" />
        </div>
        <div class="flex-1">
          <h4 class="font-semibold text-slate-900">{{ listing?.title }}</h4>
          <p class="text-sm text-muted">{{ listing?.rating }} ★ · €{{ listing?.pricePerMonth }}/{{ t('listing.month') }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-line p-3">
        <p class="text-sm font-semibold text-slate-900">{{ t('listing.copyLink') }}</p>
        <div class="mt-2 flex items-center gap-2 rounded-xl bg-surface px-3 py-2">
          <span class="flex-1 truncate text-sm text-muted">https://izdaj-iznajmi.app/l/{{ listing?.id }}</span>
          <Button variant="secondary" size="md">{{ t('common.copy') }}</Button>
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
