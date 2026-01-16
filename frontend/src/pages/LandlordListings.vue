<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Edit, Plus, Sparkles } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'

const router = useRouter()
const auth = useAuthStore()
const listingsStore = useListingsStore()

onMounted(() => {
  listingsStore.fetchLandlordListings(auth.user.id)
})

const listings = computed(() => listingsStore.landlordListings)
const loading = computed(() => listingsStore.landlordLoading)
const error = computed(() => listingsStore.landlordError)

const statusLabel = (createdAt?: string) => (createdAt ? 'Published' : 'Draft')
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="section-title">My listings</h2>
      <Button size="md" @click="router.push('/landlord/listings/new')">
        <Plus class="mr-2 h-4 w-4" />
        New Listing
      </Button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="item in listings"
        :key="item.id"
        class="flex gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
        @click="router.push(`/landlord/listings/${item.id}/edit`)"
      >
        <div class="h-24 w-24 overflow-hidden rounded-2xl">
          <img :src="item.coverImage" :alt="item.title" class="h-full w-full object-cover" />
        </div>
        <div class="flex flex-1 flex-col gap-1">
          <div class="flex items-start justify-between">
            <div class="space-y-1">
              <p class="text-base font-semibold text-slate-900">{{ item.title }}</p>
              <p class="text-xs text-muted">${{ item.pricePerNight }}/night · {{ item.city }}</p>
            </div>
            <Badge variant="pending">{{ statusLabel(item.createdAt) }}</Badge>
          </div>
          <p class="text-xs text-muted">{{ item.description }}</p>
          <div class="flex items-center justify-between text-xs text-muted">
            <span>Beds {{ item.beds }} · Baths {{ item.baths }}</span>
            <span class="flex items-center gap-1 text-primary"><Sparkles class="h-3.5 w-3.5" /> {{ item.rating }}</span>
          </div>
          <div class="flex justify-end">
            <Button variant="secondary" size="md">
              <Edit class="mr-2 h-4 w-4" /> Edit
            </Button>
          </div>
        </div>
      </div>
      <EmptyState
        v-if="!listings.length && !error"
        title="No listings yet"
        subtitle="Create your first property"
        :icon="Plus"
      >
        <Button class="mt-3" @click="router.push('/landlord/listings/new')">Create listing</Button>
      </EmptyState>
    </div>
  </div>
</template>
