<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Heart, MapPin, MessageSquare, Share2 } from 'lucide-vue-next'
import FacilityPill from '../components/listing/FacilityPill.vue'
import Button from '../components/ui/Button.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import RatingStars from '../components/ui/RatingStars.vue'
import { useListingsStore } from '../stores/listings'
import { getListingById, getListingFacilities, getListingReviews } from '../services/mockApi'
import type { Listing, Review } from '../types'

const route = useRoute()
const router = useRouter()
const listingsStore = useListingsStore()

const listing = ref<Listing | null>(null)
const facilities = ref<{ title: string; items: string[] }[]>([])
const reviews = ref<Review[]>([])
const showShare = ref(false)
const expanded = ref(false)

onMounted(async () => {
  const id = route.params.id as string
  listing.value = (await getListingById(id)) || null
  facilities.value = await getListingFacilities(id)
  reviews.value = await getListingReviews(id)
})

const description = computed(
  () =>
    'Enjoy a minimalist coastal escape with airy rooms, private pool, and endless ocean views. Perfect for workcation or slow holidays with friends.',
)

const toggleFavorite = () => {
  if (!listing.value) return
  listingsStore.toggleFavorite(listing.value.id)
  listing.value = { ...listing.value, isFavorite: !listing.value.isFavorite }
}
</script>

<template>
  <div>
    <div class="relative h-80 w-full overflow-hidden rounded-b-[28px]">
      <img :src="listing?.coverImage" alt="Hero" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10" />
    </div>

    <div class="-mt-6 space-y-6 rounded-t-[28px] bg-surface px-4 pb-28 pt-6">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
          <h1 class="text-xl font-semibold text-slate-900">{{ listing?.title }}</h1>
          <div class="flex items-center gap-1 text-sm text-muted">
            <MapPin class="h-4 w-4 text-primary" />
            <span>{{ listing?.city }}, {{ listing?.country }}</span>
          </div>
          <RatingStars v-if="listing" :rating="listing.rating" />
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-full bg-white p-3 shadow-soft" @click="toggleFavorite">
            <Heart :class="['h-5 w-5', listing?.isFavorite ? 'fill-primary text-primary' : 'text-slate-800']" />
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
          {{ expanded ? description : description.slice(0, 140) + '...' }}
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

    <div class="fixed bottom-4 left-0 right-0 z-40 mx-auto max-w-md px-4">
      <div class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-card">
        <div class="flex-1">
          <p class="text-xs text-muted">Price</p>
          <p class="text-lg font-semibold text-slate-900">${{ listing?.pricePerNight }}/night</p>
        </div>
        <button class="rounded-2xl bg-primary/10 p-3 text-primary" aria-label="message">
          <MessageSquare class="h-5 w-5" />
        </button>
        <Button size="lg" @click="router.push('/bookings')">Booking Now</Button>
      </div>
    </div>
  </div>

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
