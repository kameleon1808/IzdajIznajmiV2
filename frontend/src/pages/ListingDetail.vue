<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Heart, MapPin, Share2 } from 'lucide-vue-next'
import FacilityPill from '../components/listing/FacilityPill.vue'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import RatingStars from '../components/ui/RatingStars.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'
import { useRequestsStore } from '../stores/requests'
import { useToastStore } from '../stores/toast'
import { getListingById, getListingFacilities, getListingReviews } from '../services'
import type { Listing, Review } from '../types'

const route = useRoute()
const router = useRouter()
const listingsStore = useListingsStore()
const auth = useAuthStore()
const requestsStore = useRequestsStore()
const toast = useToastStore()

const listing = ref<Listing | null>(null)
const facilities = ref<{ title: string; items: string[] }[]>([])
const reviews = ref<Review[]>([])
const showShare = ref(false)
const requestSheet = ref(false)
const expanded = ref(false)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)

const requestForm = reactive({
  startDate: '',
  endDate: '',
  guests: 2,
  message: '',
})

const description = computed(
  () =>
    listing.value?.description ||
    'Enjoy a minimalist coastal escape with airy rooms, private pool, and endless ocean views. Perfect for workcation or slow holidays with friends.',
)

const isFormValid = computed(() => requestForm.guests > 0 && requestForm.message.trim().length >= 5)

const loadData = async () => {
  loading.value = true
  error.value = ''
  try {
    const id = route.params.id as string
    listing.value = (await getListingById(id)) || null
    facilities.value = await getListingFacilities(id)
    reviews.value = await getListingReviews(id)
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load listing.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})

const toggleFavorite = () => {
  if (!listing.value) return
  listingsStore.toggleFavorite(listing.value.id)
  listing.value = { ...listing.value, isFavorite: !listing.value.isFavorite }
}

const openInquiry = () => {
  if (!auth.isAuthenticated && !auth.isMockMode) {
    router.push({ path: '/login', query: { returnUrl: route.fullPath } })
    return
  }
  if (auth.user.role !== 'tenant') {
    toast.push({ title: 'Access denied', message: 'Switch to Tenant role to send request.', type: 'error' })
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
      landlordId: String(listing.value.ownerId || 'landlord-1'),
      startDate: requestForm.startDate || undefined,
      endDate: requestForm.endDate || undefined,
      guests: requestForm.guests,
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
      <ErrorBanner :message="error" />
    </div>

    <div v-if="listing" class="-mt-6 space-y-6 rounded-t-[28px] bg-surface px-4 pb-28 pt-6">
      <ErrorBanner v-if="error" :message="error" />
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
        <div class="flex items-center justify-between">
          <h3 class="section-title">Location</h3>
          <button class="text-sm font-semibold text-primary">Open Map</button>
        </div>
        <div class="overflow-hidden rounded-2xl border border-white/60">
          <div class="h-32 w-full bg-[url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=60')] bg-cover bg-center" />
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
    </div>

    <EmptyState v-else-if="!loading" title="Listing unavailable" subtitle="Try again later or choose another stay" />

    <div v-if="listing" class="fixed bottom-4 left-0 right-0 z-40 mx-auto max-w-md px-4">
      <div class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-card">
        <div class="flex-1">
          <p class="text-xs text-muted">Price</p>
          <p class="text-lg font-semibold text-slate-900">${{ listing.pricePerNight }}/night</p>
        </div>
        <Badge variant="info">Rating {{ listing.rating }}</Badge>
        <Button size="lg" @click="openInquiry">Send Inquiry</Button>
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
