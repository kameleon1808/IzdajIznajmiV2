<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch, type ComponentPublicInstance } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CalendarClock, CalendarRange, CheckCircle2, Clock3, Download, Inbox, MapPin, ShieldCheck, Star, XOctagon } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Input from '../components/ui/Input.vue'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import { useBookingsStore } from '../stores/bookings'
import { useLanguageStore } from '../stores/language'
import { useListingsStore } from '../stores/listings'
import { useRequestsStore } from '../stores/requests'
import { useViewingsStore } from '../stores/viewings'
import { useTransactionsStore } from '../stores/transactions'
import { useToastStore } from '../stores/toast'
import type { Application, ViewingRequest } from '../types'
import { resolveBookingsTabs } from '../utils/viewings'

const bookingsStore = useBookingsStore()
const requestsStore = useRequestsStore()
const viewingsStore = useViewingsStore()
const listingsStore = useListingsStore()
const transactionsStore = useTransactionsStore()
const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const useTranslations = computed(
  () => route.path === '/bookings' || route.path === '/applications' || route.path === '/landlord/applications',
)
const tx = (key: Parameters<typeof languageStore.t>[0], fallback: string) =>
  useTranslations.value ? t(key) : fallback

const primaryTab = ref<'reservations' | 'viewings'>('reservations')
const reservationTab = ref<'booked' | 'history' | 'requests'>('booked')
const highlightedApplicationId = computed(() => route.query.applicationId as string | undefined)
const highlightedViewingRequestId = computed(() => route.query.viewingRequestId as string | undefined)
const viewingRefs = ref<Record<string, HTMLElement | null>>({})
const selectedListingFilter = ref<string>('')
const showContractSheet = ref(false)
const contractDeposit = ref('')
const contractRent = ref('')
const contractLoading = ref(false)
const pendingContractRequest = ref<Application | null>(null)

const reservationTabs = computed<string[]>(() => {
  if (auth.hasRole('seeker')) return ['booked', 'history', 'requests']
  if (auth.hasRole('landlord') || auth.hasRole('admin')) return ['requests']
  return ['booked', 'history']
})

const normalizeReservationTab = () => {
  if (!reservationTabs.value.includes(reservationTab.value as any)) {
    reservationTab.value = reservationTabs.value[0] as any
  }
}

const syncTabFromRoute = () => {
  if (route.path.includes('/applications') || highlightedApplicationId.value) {
    primaryTab.value = 'reservations'
    reservationTab.value = 'requests'
    return
  }
  const resolved = resolveBookingsTabs(route.query as Record<string, any>)
  primaryTab.value = resolved.primaryTab
  reservationTab.value = resolved.reservationTab
  normalizeReservationTab()
}

const loadRequests = () => {
  if (auth.hasRole('seeker')) {
    requestsStore.fetchTenantRequests()
  } else if (auth.hasRole('landlord')) {
    requestsStore.fetchLandlordRequests()
  }
}

const loadViewingRequests = () => {
  if (auth.hasRole('landlord')) {
    viewingsStore.fetchLandlordRequests(selectedListingFilter.value || undefined)
  } else if (auth.hasRole('seeker')) {
    viewingsStore.fetchSeekerRequests()
  }
}

const retryBookings = () => {
  bookingsStore.fetchBookings()
  loadRequests()
  if (primaryTab.value === 'viewings') {
    loadViewingRequests()
  }
}

onMounted(() => {
  syncTabFromRoute()
  if (!listingsStore.recommended.length) listingsStore.fetchRecommended()
  bookingsStore.fetchBookings()
  loadRequests()
  if (primaryTab.value === 'viewings' || highlightedViewingRequestId.value) {
    loadViewingRequests()
  }
  scrollToHighlightedViewing()
})

watch(
  () => route.fullPath,
  () => {
    syncTabFromRoute()
    if (primaryTab.value === 'viewings') {
      loadViewingRequests()
    } else {
      loadRequests()
    }
  },
)

watch(
  () => auth.primaryRole,
  () => {
    normalizeReservationTab()
    if (primaryTab.value === 'viewings') {
      loadViewingRequests()
    } else {
      loadRequests()
    }
  },
)

watch(
  () => route.query.applicationId,
  (val) => {
    if (val) {
      primaryTab.value = 'reservations'
      reservationTab.value = 'requests'
    }
  },
)

watch(
  () => route.query.viewingRequestId,
  (val) => {
    if (val) {
      primaryTab.value = 'viewings'
      loadViewingRequests()
      scrollToHighlightedViewing()
    }
  },
)

watch(
  () => primaryTab.value,
  (val) => {
    if (val === 'viewings') {
      loadViewingRequests()
    } else {
      loadRequests()
    }
  },
)

watch(
  () => selectedListingFilter.value,
  () => {
    if (primaryTab.value === 'viewings' && auth.hasRole('landlord')) {
      loadViewingRequests()
    }
  },
)

const bookingItems = computed(() => (reservationTab.value === 'booked' ? bookingsStore.booked : bookingsStore.history))
const requestItems = computed(() => (auth.hasRole('landlord') ? requestsStore.landlordRequests : requestsStore.tenantRequests))
const viewingItems = computed(() => {
  const source = auth.hasRole('landlord') ? viewingsStore.landlordRequests : viewingsStore.seekerRequests
  if (selectedListingFilter.value) {
    return source.filter((req) => req.listing?.id === selectedListingFilter.value)
  }
  return source
})
const listingLookup = computed(() => {
  const map = new Map<string, string>()
  ;[...listingsStore.recommended, ...listingsStore.popular, ...listingsStore.landlordListings].forEach((l) => map.set(l.id, l.title))
  return map
})
const listingFallbackLabel = (id?: string) => {
  const base = tx('bookings.listingLabel', 'Listing')
  return id ? `${base} ${id}` : base
}
const landlordViewingListings = computed(() => {
  if (!auth.hasRole('landlord')) return []
  const map = new Map<string, string>()
  viewingsStore.landlordRequests.forEach((req) => {
    if (req.listing) {
      map.set(req.listing.id, req.listing.title ?? listingFallbackLabel(req.listing.id))
    }
  })
  return Array.from(map.entries()).map(([id, title]) => ({ id, title }))
})

const isLoading = computed(() => {
  if (primaryTab.value === 'viewings') return viewingsStore.loadingRequests
  return reservationTab.value === 'requests' ? requestsStore.loading : bookingsStore.loading
})

const errorMessage = computed(() => {
  if (primaryTab.value === 'viewings') return viewingsStore.error
  return reservationTab.value === 'requests' ? requestsStore.error : bookingsStore.error
})

const statusLabel = (status: Application['status']) => {
  if (status === 'submitted') return tx('bookings.status.submitted', 'Submitted')
  if (status === 'accepted') return tx('bookings.status.accepted', 'Accepted')
  if (status === 'rejected') return tx('bookings.status.rejected', 'Rejected')
  return tx('bookings.status.withdrawn', 'Withdrawn')
}

const statusVariant: Record<Application['status'], any> = {
  submitted: 'pending',
  accepted: 'accepted',
  rejected: 'rejected',
  withdrawn: 'cancelled',
}

const viewingStatusLabel = (status: ViewingRequest['status']) => {
  if (status === 'requested') return tx('bookings.viewingStatus.requested', 'Requested')
  if (status === 'confirmed') return tx('bookings.viewingStatus.confirmed', 'Confirmed')
  if (status === 'cancelled') return tx('bookings.viewingStatus.cancelled', 'Cancelled')
  return tx('bookings.viewingStatus.rejected', 'Rejected')
}

const viewingStatusVariant: Record<ViewingRequest['status'], any> = {
  requested: 'pending',
  confirmed: 'success',
  cancelled: 'secondary',
  rejected: 'rejected',
}

const requestToastLabel = (status: Application['status']) => {
  if (status === 'submitted') return tx('bookings.toast.requestSubmitted', 'Request submitted')
  if (status === 'accepted') return tx('bookings.toast.requestAccepted', 'Request accepted')
  if (status === 'rejected') return tx('bookings.toast.requestRejected', 'Request rejected')
  return tx('bookings.toast.requestWithdrawn', 'Request withdrawn')
}

const updateStatus = async (id: string, status: Application['status']) => {
  try {
    await requestsStore.updateStatus(id, status)
    toast.push({ title: requestToastLabel(status), type: status === 'accepted' ? 'success' : 'info' })
  } catch (error) {
    toast.push({ title: tx('bookings.updateFailed', 'Update failed'), message: (error as Error).message, type: 'error' })
  }
}

const openStartContract = (request: Application) => {
  if (!request.listing?.id) return
  pendingContractRequest.value = request
  const baseAmount = request.listing.pricePerNight ? String(request.listing.pricePerNight) : ''
  contractRent.value = baseAmount
  contractDeposit.value = baseAmount
  showContractSheet.value = true
}

const startContract = async () => {
  if (!pendingContractRequest.value?.listing?.id) return
  contractLoading.value = true
  try {
    const depositValue = contractDeposit.value ? Number(contractDeposit.value) : null
    const rentValue = contractRent.value ? Number(contractRent.value) : null
    const tx = await transactionsStore.startTransaction({
      listingId: pendingContractRequest.value.listing.id,
      seekerId: pendingContractRequest.value.participants.seekerId,
      depositAmount: Number.isFinite(depositValue) ? depositValue : null,
      rentAmount: Number.isFinite(rentValue) ? rentValue : null,
    })
    showContractSheet.value = false
    pendingContractRequest.value = null
    router.push(`/transactions/${tx.id}`)
  } catch (error) {
    toast.push({
      title: tx('bookings.contractFailed', 'Failed to start contract'),
      message: (error as Error).message,
      type: 'error',
    })
  } finally {
    contractLoading.value = false
  }
}

const closeContractSheet = () => {
  showContractSheet.value = false
  pendingContractRequest.value = null
}

const confirmViewing = async (id: string) => {
  try {
    await viewingsStore.confirmRequest(id)
    toast.push({ title: tx('bookings.viewingConfirmed', 'Viewing confirmed'), type: 'success' })
  } catch (error) {
    toast.push({ title: tx('bookings.confirmFailed', 'Confirm failed'), message: (error as Error).message, type: 'error' })
  }
}

const rejectViewing = async (id: string) => {
  try {
    await viewingsStore.rejectRequest(id)
    toast.push({ title: tx('bookings.viewingRejected', 'Viewing rejected'), type: 'info' })
  } catch (error) {
    toast.push({ title: tx('bookings.rejectFailed', 'Reject failed'), message: (error as Error).message, type: 'error' })
  }
}

const cancelViewing = async (id: string) => {
  try {
    await viewingsStore.cancelRequest(id)
    toast.push({ title: tx('bookings.viewingCancelled', 'Viewing cancelled'), type: 'info' })
  } catch (error) {
    toast.push({ title: tx('bookings.cancelFailed', 'Cancel failed'), message: (error as Error).message, type: 'error' })
  }
}

const downloadIcs = async (id: string, listingTitle?: string) => {
  try {
    const blob = await viewingsStore.downloadIcs(id)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `viewing-${listingTitle || id}.ics`
    link.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    toast.push({ title: tx('bookings.downloadFailed', 'Download failed'), message: (error as Error).message, type: 'error' })
  }
}

const startViewingChat = async (request: ViewingRequest) => {
  if (!request.listing?.id) return
  try {
    const seekerId = auth.hasRole('landlord') ? request.participants.seekerId : undefined
    const convo = await chatStore.fetchConversationForListing(request.listing.id, seekerId)
    await chatStore.fetchMessages(convo.id)
    router.push(`/messages/${convo.id}`)
  } catch (error) {
    toast.push({ title: tx('bookings.chatUnavailable', 'Chat unavailable'), message: (error as Error).message, type: 'error' })
  }
}

const openMessage = async (applicationId: string) => {
  try {
    const convo = await chatStore.fetchConversationForApplication(applicationId)
    await chatStore.fetchMessages(convo.id)
    router.push(`/messages/${convo.id}`)
  } catch (error) {
    toast.push({ title: tx('bookings.chatUnavailable', 'Chat unavailable'), message: (error as Error).message, type: 'error' })
  }
}

const badgeLabel = (request: Application) =>
  `${statusLabel(request.status)} • ${new Date(request.createdAt ?? Date.now()).toLocaleDateString()}`

const viewingBadgeLabel = (request: ViewingRequest) =>
  `${viewingStatusLabel(request.status)} • ${new Date(request.createdAt ?? Date.now()).toLocaleDateString()}`

const listingTitle = (request: Application) =>
  request.listing.title ?? listingLookup.value.get(request.listing.id) ?? request.listing.id

const viewingListingTitle = (request: ViewingRequest) =>
  request.listing?.title ??
  listingLookup.value.get(request.listing?.id ?? '') ??
  listingFallbackLabel(request.listing?.id)

const formatSlotTime = (request: ViewingRequest) => {
  if (request.scheduledAt) {
    const start = new Date(request.scheduledAt)
    if (Number.isNaN(start.getTime())) return tx('bookings.timePending', 'Time pending')
    let end = new Date(start.getTime() + 60 * 60 * 1000)
    if (request.slot?.timeTo) {
      const [hStr, mStr] = request.slot.timeTo.split(':')
      if (hStr && mStr) {
        const h = Number.parseInt(hStr, 10)
        const m = Number.parseInt(mStr, 10)
        if (!Number.isNaN(h) && !Number.isNaN(m)) {
          const candidate = new Date(start)
          candidate.setHours(h, m, 0, 0)
          if (candidate > start && candidate < end) {
            end = candidate
          }
        }
      }
    } else if (request.slot?.endsAt) {
      const slotEnd = new Date(request.slot.endsAt)
      if (!Number.isNaN(slotEnd.getTime()) && slotEnd > start && slotEnd < end) {
        end = slotEnd
      }
    }
    return `${start.toLocaleDateString()} · ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
  }
  if (!request.slot) return tx('bookings.timePending', 'Time pending')
  const start = new Date(request.slot.startsAt)
  const end = new Date(request.slot.endsAt)
  return `${start.toLocaleDateString()} · ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
}

const goToListing = (listingId: string) => router.push(`/listing/${listingId}`)

const reservationTabLabel = (key: string) => {
  if (key === 'requests') return tx('bookings.tabs.requests', 'Requests')
  if (key === 'booked') return tx('bookings.tabs.booked', 'Booked')
  return tx('bookings.tabs.history', 'History')
}

const setViewingRef = (id: string, el: Element | ComponentPublicInstance | null) => {
  const node = (el as any)?.$el ? ((el as any).$el as Element) : (el as Element | null)
  if (node) {
    viewingRefs.value[id] = node as HTMLElement
  }
}

const scrollToHighlightedViewing = () => {
  const id = highlightedViewingRequestId.value
  if (!id) return
  nextTick(() => {
    const el = viewingRefs.value[id]
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-2 gap-2 rounded-2xl bg-surface p-1">
      <button
        class="rounded-xl py-3 text-sm font-semibold"
        :class="primaryTab === 'reservations' ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
        @click="
          () => {
            primaryTab = 'reservations'
            router.replace({ query: { ...route.query, tab: 'reservations', section: reservationTab } })
          }
        "
      >
        {{ tx('bookings.tabs.reservations', 'Reservations') }}
      </button>
      <button
        class="rounded-xl py-3 text-sm font-semibold"
        :class="primaryTab === 'viewings' ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
        @click="
          () => {
            primaryTab = 'viewings'
            router.replace({ query: { ...route.query, tab: 'viewings' } })
          }
        "
      >
        {{ tx('bookings.tabs.viewings', 'Viewings') }}
      </button>
    </div>

    <ErrorState v-if="errorMessage" :message="errorMessage" :retry-label="tx('bookings.retry', 'Retry')" @retry="retryBookings" />

    <template v-if="primaryTab === 'reservations'">
      <div class="grid grid-cols-3 gap-2 rounded-2xl bg-surface p-1" v-if="reservationTabs.length > 1">
        <button
          v-for="key in reservationTabs"
          :key="key"
          class="rounded-xl py-3 text-sm font-semibold"
          :class="reservationTab === key ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
          @click="
            () => {
              reservationTab = key as any
              router.replace({ query: { ...route.query, tab: 'reservations', section: key } })
            }
          "
        >
          {{ reservationTabLabel(key) }}
        </button>
      </div>

      <ListSkeleton v-if="isLoading && reservationTab !== 'requests'" :count="2" />

      <template v-if="reservationTab === 'requests'">
        <ListSkeleton v-if="isLoading" :count="2" />
        <div v-else class="space-y-3">
          <div
            v-for="request in requestItems"
            :key="request.id"
            class="flex flex-col gap-3 rounded-2xl border border-white/60 bg-white p-3 shadow-soft"
            :class="highlightedApplicationId === request.id ? 'ring-2 ring-primary' : ''"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-base font-semibold text-slate-900">{{ listingTitle(request) }}</p>
                <p class="text-xs text-muted">
                  {{ request.listing.city || tx('bookings.viewDetails', 'View details') }}
                  <span v-if="request.listing.pricePerNight">· ${{ request.listing.pricePerNight }}/{{ tx('listing.night', 'night') }}</span>
                </p>
              </div>
              <Badge :variant="statusVariant[request.status]">{{ badgeLabel(request) }}</Badge>
            </div>
            <p class="rounded-2xl bg-surface p-3 text-sm text-slate-800">{{ request.message || tx('bookings.noMessage', 'No message') }}</p>
            <div class="flex items-center justify-between text-xs text-muted">
              <span>{{ tx('bookings.seeker', 'Seeker') }}: {{ request.participants.seekerId }}</span>
              <span>{{ tx('bookings.landlord', 'Landlord') }}: {{ request.participants.landlordId }}</span>
            </div>
            <div class="flex gap-2" v-if="auth.hasRole('landlord') && request.status === 'submitted'">
              <Button class="flex-1" variant="primary" @click="updateStatus(request.id, 'accepted')">
                {{ tx('bookings.accept', 'Accept') }}
              </Button>
              <Button class="flex-1" variant="secondary" @click="updateStatus(request.id, 'rejected')">
                {{ tx('bookings.reject', 'Reject') }}
              </Button>
            </div>
            <div class="flex justify-end" v-else-if="auth.hasRole('seeker') && request.status === 'submitted'">
              <Button variant="secondary" size="md" @click="updateStatus(request.id, 'withdrawn')">
                {{ tx('bookings.withdraw', 'Withdraw') }}
              </Button>
            </div>
            <div class="flex justify-end" v-else-if="auth.hasRole('landlord') && request.status === 'accepted'">
              <Button variant="primary" size="md" @click="openStartContract(request)">
                {{ tx('bookings.startContract', 'Start contract') }}
              </Button>
            </div>
            <div class="flex justify-end gap-2">
              <Button v-if="auth.hasRole('landlord')" variant="primary" size="md" @click="openMessage(request.id)">
                {{ tx('bookings.message', 'Message') }}
              </Button>
              <Button variant="secondary" size="md" @click="goToListing(request.listing.id)">
                {{ tx('bookings.viewListing', 'View listing') }}
              </Button>
            </div>
          </div>
          <EmptyState
            v-if="!requestItems.length && !errorMessage"
            :title="tx('bookings.noRequestsTitle', 'No requests yet')"
            :subtitle="tx('bookings.noRequestsSubtitle', 'Send an inquiry or wait for seekers to contact you')"
            :icon="auth.hasRole('landlord') ? ShieldCheck : Inbox"
          />
        </div>
      </template>

      <template v-else>
        <ListSkeleton v-if="isLoading" :count="2" />
        <div v-else class="space-y-3">
          <div
            v-for="booking in bookingItems"
            :key="booking.id"
            class="flex gap-3 rounded-2xl border border-white/60 bg-white p-3 shadow-soft"
          >
            <div class="h-28 w-28 overflow-hidden rounded-2xl">
              <img :src="booking.coverImage" :alt="booking.listingTitle" class="h-full w-full object-cover" />
            </div>
            <div class="flex flex-1 flex-col gap-2">
              <div class="flex items-start justify-between">
                <div class="space-y-1">
                  <p class="text-base font-semibold text-slate-900">{{ booking.listingTitle }}</p>
                  <div class="flex items-center gap-1 text-xs text-muted">
                    <MapPin class="h-4 w-4 text-primary" />
                    <span>{{ booking.datesRange }}</span>
                  </div>
                </div>
                <div class="flex items-center gap-1 text-sm font-semibold text-slate-900">
                  <Star class="h-4 w-4 fill-primary text-primary" />
                  <span>{{ booking.rating }}</span>
                </div>
              </div>

              <div class="flex items-center justify-between text-xs text-muted">
                <span>{{ booking.guestsText }}</span>
                <span>${{ booking.pricePerNight }}/{{ tx('listing.night', 'night') }}</span>
              </div>

              <div class="flex gap-2">
                <Button v-if="reservationTab === 'booked'" variant="primary" class="flex-1">
                  {{ tx('bookings.viewTicket', 'View Ticket') }}
                </Button>
                <Button v-else variant="secondary" class="flex-1">
                  {{ tx('bookings.bookAgain', 'Book Again') }}
                </Button>
                <button class="rounded-xl bg-surface px-3 py-2 text-primary">
                  <CalendarClock class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
          <EmptyState
            v-if="!bookingItems.length && !errorMessage"
            :title="tx('bookings.noBookingsTitle', 'No bookings yet')"
            :subtitle="tx('bookings.noBookingsSubtitle', 'Send an inquiry to start your trip')"
            :icon="XOctagon"
          />
        </div>
      </template>
    </template>

    <template v-else>
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <CalendarRange class="h-5 w-5 text-primary" />
          <p class="text-sm font-semibold text-slate-900">{{ tx('bookings.viewingRequests', 'Viewing requests') }}</p>
        </div>
        <div v-if="auth.hasRole('landlord') && landlordViewingListings.length" class="flex items-center gap-2">
          <label class="text-xs font-semibold text-muted">{{ tx('bookings.listingLabel', 'Listing') }}</label>
          <select
            v-model="selectedListingFilter"
            class="rounded-xl border border-line bg-white px-3 py-2 text-sm text-slate-900"
          >
            <option value="">{{ tx('bookings.allListings', 'All') }}</option>
            <option v-for="item in landlordViewingListings" :key="item.id" :value="item.id">{{ item.title }}</option>
          </select>
        </div>
      </div>

      <ListSkeleton v-if="isLoading" :count="2" />

      <div v-else class="space-y-3">
        <div
          v-for="request in viewingItems"
          :key="request.id"
          :ref="(el) => setViewingRef(request.id, el)"
          class="flex flex-col gap-3 rounded-2xl border border-white/60 bg-white p-3 shadow-soft"
          :class="highlightedViewingRequestId === request.id ? 'ring-2 ring-primary' : ''"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-base font-semibold text-slate-900">{{ viewingListingTitle(request) }}</p>
              <p class="text-xs text-muted">
                {{ request.listing?.city || tx('bookings.viewDetails', 'View details') }}
                <span v-if="request.listing?.pricePerNight">· ${{ request.listing?.pricePerNight }}/{{ tx('listing.night', 'night') }}</span>
              </p>
              <div class="mt-1 flex items-center gap-2 text-xs text-slate-600">
                <Clock3 class="h-4 w-4 text-primary" />
                <span>{{ formatSlotTime(request) }}</span>
              </div>
            </div>
            <Badge :variant="viewingStatusVariant[request.status]">{{ viewingBadgeLabel(request) }}</Badge>
          </div>

          <p class="rounded-2xl bg-surface p-3 text-sm text-slate-800">{{ request.message || tx('bookings.noMessage', 'No message') }}</p>

          <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
            <span class="inline-flex items-center gap-1 rounded-full bg-surface px-2 py-1">
              {{ tx('bookings.seeker', 'Seeker') }}: {{ request.participants.seekerId }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-surface px-2 py-1">
              {{ tx('bookings.landlord', 'Landlord') }}: {{ request.participants.landlordId }}
            </span>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="auth.hasRole('landlord') && request.status === 'requested'"
              variant="primary"
              size="md"
              class="flex-1 min-w-[120px]"
              @click="confirmViewing(request.id)"
            >
              <CheckCircle2 class="mr-1 h-4 w-4" /> {{ tx('bookings.confirm', 'Confirm') }}
            </Button>
            <Button
              v-if="auth.hasRole('landlord') && request.status === 'requested'"
              variant="secondary"
              size="md"
              class="flex-1 min-w-[120px]"
              @click="rejectViewing(request.id)"
            >
              {{ tx('bookings.reject', 'Reject') }}
            </Button>
            <Button
              v-if="['requested', 'confirmed'].includes(request.status) && auth.hasRole('seeker')"
              variant="secondary"
              size="md"
              class="flex-1 min-w-[120px]"
              @click="cancelViewing(request.id)"
            >
              {{ tx('bookings.cancel', 'Cancel') }}
            </Button>
            <Button
              v-if="['requested', 'confirmed'].includes(request.status) && auth.hasRole('landlord')"
              variant="ghost"
              size="md"
              class="flex-1 min-w-[120px]"
              @click="cancelViewing(request.id)"
            >
              {{ tx('bookings.cancel', 'Cancel') }}
            </Button>
            <Button
              v-if="['requested', 'confirmed'].includes(request.status)"
              variant="secondary"
              size="md"
              class="flex-1 min-w-[140px]"
              @click="() => goToListing(request.listing?.id || '')"
            >
              {{ tx('bookings.viewListing', 'View listing') }}
            </Button>
            <Button
              v-if="['requested', 'confirmed'].includes(request.status)"
              variant="primary"
              size="md"
              class="flex-1 min-w-[140px]"
              @click="() => startViewingChat(request)"
            >
              {{ tx('bookings.message', 'Message') }}
            </Button>
            <Button
              v-if="request.status === 'confirmed'"
              variant="secondary"
              size="md"
              class="flex-1 min-w-[160px]"
              @click="downloadIcs(request.id, request.listing?.title)"
            >
              <Download class="mr-2 h-4 w-4" /> {{ tx('bookings.addToCalendar', 'Add to calendar') }}
            </Button>
          </div>
        </div>
        <EmptyState
          v-if="!viewingItems.length && !errorMessage"
          :title="tx('bookings.noViewingsTitle', 'No viewings yet')"
          :subtitle="tx('bookings.noViewingsSubtitle', 'Schedule a slot to start viewing listings')"
          :icon="CalendarClock"
        />
      </div>
    </template>
  </div>

  <ModalSheet v-model="showContractSheet" :title="tx('bookings.contractTitle', 'Start contract')">
    <div class="space-y-4">
      <div class="rounded-2xl border border-line bg-surface p-3 text-sm text-slate-700">
        <p class="font-semibold text-slate-900">{{ pendingContractRequest?.listing?.title ?? tx('bookings.listingLabel', 'Listing') }}</p>
        <p class="text-xs text-muted">{{ tx('bookings.contractHint', 'Set deposit and rent before creating the transaction.') }}</p>
      </div>
      <div class="space-y-2">
        <label class="text-xs font-semibold text-muted">{{ tx('bookings.depositAmount', 'Deposit amount') }}</label>
        <Input v-model="contractDeposit" type="number" :placeholder="tx('bookings.depositAmount', 'Deposit amount')" />
      </div>
      <div class="space-y-2">
        <label class="text-xs font-semibold text-muted">{{ tx('bookings.rentAmount', 'Rent amount') }}</label>
        <Input v-model="contractRent" type="number" :placeholder="tx('bookings.rentAmount', 'Rent amount')" />
      </div>
      <div class="flex gap-2">
        <Button variant="secondary" class="flex-1" @click="closeContractSheet">
          {{ tx('bookings.cancel', 'Cancel') }}
        </Button>
        <Button variant="primary" class="flex-1" :disabled="contractLoading" @click="startContract">
          {{ contractLoading ? tx('bookings.creating', 'Creating...') : tx('bookings.createTransaction', 'Create transaction') }}
        </Button>
      </div>
    </div>
  </ModalSheet>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
