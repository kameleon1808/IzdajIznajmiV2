<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Archive, Edit, Plus, Sparkles, Upload, Undo, XCircle } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'
import { useToastStore } from '../stores/toast'

const router = useRouter()
const auth = useAuthStore()
const listingsStore = useListingsStore()
const toast = useToastStore()
const statusFilter = ref<'all' | 'draft' | 'published' | 'archived'>('all')

onMounted(() => {
  listingsStore.fetchLandlordListings(auth.user.id)
})

const listings = computed(() =>
  listingsStore.landlordListings.filter((l) => (statusFilter.value === 'all' ? true : l.status === statusFilter.value)),
)
const loading = computed(() => listingsStore.landlordLoading)
const error = computed(() => listingsStore.landlordError)

const statusBadge = (status?: string): { label: string; variant: 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'info' } => {
  if (status === 'published') return { label: 'Published', variant: 'accepted' }
  if (status === 'archived') return { label: 'Archived', variant: 'cancelled' }
  return { label: 'Draft', variant: 'pending' }
}

const handleAction = async (action: 'publish' | 'unpublish' | 'archive' | 'restore', id: string) => {
  try {
    if (action === 'publish') await listingsStore.publishListingAction(id)
    if (action === 'unpublish') await listingsStore.unpublishListingAction(id)
    if (action === 'archive') await listingsStore.archiveListingAction(id)
    if (action === 'restore') await listingsStore.restoreListingAction(id)
    toast.push({ title: `Listing ${action}d`, type: 'success' })
  } catch (err: any) {
    toast.push({ title: 'Action failed', message: err.message, type: 'error' })
  }
}
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

    <div class="flex gap-2">
      <button
        v-for="opt in ['all','draft','published','archived']"
        :key="opt"
        class="rounded-full px-3 py-2 text-sm font-semibold capitalize shadow-soft"
        :class="statusFilter === opt ? 'bg-primary text-white' : 'bg-white text-slate-800 border border-line'"
        @click="statusFilter = opt as any"
      >
        {{ opt }}
      </button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="item in listings"
        :key="item.id"
        class="flex gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
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
            <Badge :variant="statusBadge(item.status).variant">{{ statusBadge(item.status).label }}</Badge>
          </div>
          <p class="text-xs text-muted">{{ item.description }}</p>
          <div class="flex items-center justify-between text-xs text-muted">
            <span>Beds {{ item.beds }} · Baths {{ item.baths }}</span>
            <span class="flex items-center gap-1 text-primary"><Sparkles class="h-3.5 w-3.5" /> {{ item.rating }}</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="md" @click="router.push(`/landlord/listings/${item.id}/edit`)">
              <Edit class="mr-2 h-4 w-4" /> Edit
            </Button>
            <Button
              v-if="item.status === 'draft'"
              size="md"
              variant="primary"
              @click="handleAction('publish', item.id)"
            >
              <Upload class="mr-2 h-4 w-4" /> Publish
            </Button>
            <Button
              v-if="item.status === 'published'"
              size="md"
              variant="secondary"
              @click="handleAction('unpublish', item.id)"
            >
              <XCircle class="mr-2 h-4 w-4" /> Unpublish
            </Button>
            <Button
              v-if="item.status !== 'archived'"
              size="md"
              variant="secondary"
              @click="handleAction('archive', item.id)"
            >
              <Archive class="mr-2 h-4 w-4" /> Archive
            </Button>
            <Button
              v-if="item.status === 'archived'"
              size="md"
              variant="secondary"
              @click="handleAction('restore', item.id)"
            >
              <Undo class="mr-2 h-4 w-4" /> Restore
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
