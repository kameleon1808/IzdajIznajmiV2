<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CalendarClock, Inbox, MapPin, ShieldCheck, Star, XOctagon } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import { useBookingsStore } from '../stores/bookings'
import { useListingsStore } from '../stores/listings'
import { useRequestsStore } from '../stores/requests'
import { useToastStore } from '../stores/toast'
import type { Application } from '../types'

const bookingsStore = useBookingsStore()
const requestsStore = useRequestsStore()
const listingsStore = useListingsStore()
const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()

const tab = ref<'booked' | 'history' | 'requests'>((route.query.tab as any) || 'booked')

const tabs = computed<string[]>(() => {
  if (auth.hasRole('seeker')) return ['booked', 'history', 'requests']
  if (auth.hasRole('landlord')) return ['requests']
  return ['booked', 'history']
})

const normalizeTab = () => {
  if (!tabs.value.includes(tab.value as any)) {
    tab.value = tabs.value[0] as any
  }
}

onMounted(() => {
  normalizeTab()
  if (!listingsStore.recommended.length) listingsStore.fetchRecommended()
  bookingsStore.fetchBookings()
  loadRequests()
})

watch(
  () => auth.primaryRole,
  () => {
    normalizeTab()
    loadRequests()
  },
)

const loadRequests = () => {
  if (auth.hasRole('seeker')) {
    requestsStore.fetchTenantRequests()
  } else if (auth.hasRole('landlord')) {
    requestsStore.fetchLandlordRequests()
  }
}

const bookingItems = computed(() => (tab.value === 'booked' ? bookingsStore.booked : bookingsStore.history))
const requestItems = computed(() =>
  auth.hasRole('landlord') ? requestsStore.landlordRequests : requestsStore.tenantRequests,
)
const listingLookup = computed(() => {
  const map = new Map<string, string>()
  ;[...listingsStore.recommended, ...listingsStore.popular, ...listingsStore.landlordListings].forEach((l) =>
    map.set(l.id, l.title),
  )
  return map
})

const isLoading = computed(() =>
  (tab.value === 'requests' ? requestsStore.loading : bookingsStore.loading),
)

const errorMessage = computed(() =>
  tab.value === 'requests' ? requestsStore.error : bookingsStore.error,
)

const statusCopy: Record<Application['status'], string> = {
  submitted: 'Submitted',
  accepted: 'Accepted',
  rejected: 'Rejected',
  withdrawn: 'Withdrawn',
}

const statusVariant: Record<Application['status'], any> = {
  submitted: 'pending',
  accepted: 'accepted',
  rejected: 'rejected',
  withdrawn: 'cancelled',
}

const updateStatus = async (id: string, status: Application['status']) => {
  try {
    await requestsStore.updateStatus(id, status)
    toast.push({ title: `Request ${statusCopy[status]}`, type: status === 'accepted' ? 'success' : 'info' })
  } catch (error) {
    toast.push({ title: 'Update failed', message: (error as Error).message, type: 'error' })
  }
}

const openMessage = async (applicationId: string) => {
  try {
    const convo = await chatStore.fetchConversationForApplication(applicationId)
    await chatStore.fetchMessages(convo.id)
    router.push(`/messages/${convo.id}`)
  } catch (error) {
    toast.push({ title: 'Chat unavailable', message: (error as Error).message, type: 'error' })
  }
}

const badgeLabel = (request: Application) =>
  `${statusCopy[request.status]} • ${new Date(request.createdAt ?? Date.now()).toLocaleDateString()}`

const listingTitle = (request: Application) =>
  request.listing.title ?? listingLookup.value.get(request.listing.id) ?? request.listing.id

const goToListing = (listingId: string) => router.push(`/listing/${listingId}`)
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-3 gap-2 rounded-2xl bg-surface p-1" v-if="tabs.length > 1">
      <button
        v-for="key in tabs"
        :key="key"
        class="rounded-xl py-3 text-sm font-semibold"
        :class="tab === key ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
        @click="() => { tab = key as any; router.replace({ query: { tab: key } }) }"
      >
        {{ key === 'requests' ? 'Requests' : key === 'booked' ? 'Booked' : 'History' }}
      </button>
    </div>

    <ErrorBanner v-if="errorMessage" :message="errorMessage" />
    <ListSkeleton v-if="isLoading && tab !== 'requests'" :count="2" />

    <template v-if="tab === 'requests'">
      <ListSkeleton v-if="isLoading" :count="2" />
      <div v-else class="space-y-3">
        <div
          v-for="request in requestItems"
          :key="request.id"
          class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-base font-semibold text-slate-900">{{ listingTitle(request) }}</p>
              <p class="text-xs text-muted">
                {{ request.listing.city || 'View details' }}
                <span v-if="request.listing.pricePerNight">· ${{ request.listing.pricePerNight }}/night</span>
              </p>
            </div>
            <Badge :variant="statusVariant[request.status]">{{ badgeLabel(request) }}</Badge>
          </div>
          <p class="rounded-2xl bg-surface p-3 text-sm text-slate-800">{{ request.message || 'No message' }}</p>
          <div class="flex items-center justify-between text-xs text-muted">
            <span>Seeker: {{ request.participants.seekerId }}</span>
            <span>Landlord: {{ request.participants.landlordId }}</span>
          </div>
          <div class="flex gap-2" v-if="auth.hasRole('landlord') && request.status === 'submitted'">
            <Button class="flex-1" variant="primary" @click="updateStatus(request.id, 'accepted')">Accept</Button>
            <Button class="flex-1" variant="secondary" @click="updateStatus(request.id, 'rejected')">Reject</Button>
          </div>
          <div class="flex justify-end" v-else-if="auth.hasRole('seeker') && request.status === 'submitted'">
            <Button variant="secondary" size="md" @click="updateStatus(request.id, 'withdrawn')">Withdraw</Button>
          </div>
          <div class="flex justify-end gap-2">
            <Button
              v-if="auth.hasRole('landlord')"
              variant="primary"
              size="md"
              @click="openMessage(request.id)"
            >
              Message
            </Button>
            <Button variant="secondary" size="md" @click="goToListing(request.listing.id)">View listing</Button>
          </div>
        </div>
        <EmptyState
          v-if="!requestItems.length && !errorMessage"
          title="No requests yet"
          subtitle="Send an inquiry or wait for seekers to contact you"
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
          class="flex gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
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
              <span>${{ booking.pricePerNight }}/night</span>
            </div>

            <div class="flex gap-2">
              <Button v-if="tab === 'booked'" variant="primary" class="flex-1">View Ticket</Button>
              <Button v-else variant="secondary" class="flex-1">Book Again</Button>
              <button class="rounded-xl bg-surface px-3 py-2 text-primary">
                <CalendarClock class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <EmptyState
          v-if="!bookingItems.length && !errorMessage"
          title="No bookings yet"
          subtitle="Send an inquiry to start your trip"
          :icon="XOctagon"
        />
      </div>
    </template>
  </div>
</template>
