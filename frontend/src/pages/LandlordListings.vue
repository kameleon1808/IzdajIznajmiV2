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
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const auth = useAuthStore()
const listingsStore = useListingsStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const statusFilter = ref<'all' | 'draft' | 'active' | 'paused' | 'archived' | 'rented' | 'expired'>('all')

onMounted(() => {
  listingsStore.fetchLandlordListings(auth.user.id)
})

const listings = computed(() =>
  listingsStore.landlordListings.filter((l) => (statusFilter.value === 'all' ? true : l.status === statusFilter.value)),
)
const loading = computed(() => listingsStore.landlordLoading)
const error = computed(() => listingsStore.landlordError)
const goToPublicListing = (id: string) => router.push(`/listing/${id}`)

const statusBadge = (status?: string): { label: string; variant: 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'info' } => {
  if (status === 'active') return { label: t('status.active'), variant: 'accepted' }
  if (status === 'paused') return { label: t('status.paused'), variant: 'info' }
  if (status === 'rented') return { label: t('status.rented'), variant: 'info' }
  if (status === 'expired') return { label: t('status.expired'), variant: 'cancelled' }
  if (status === 'archived') return { label: t('status.archived'), variant: 'cancelled' }
  return { label: t('status.draft'), variant: 'pending' }
}

const handleAction = async (
  action: 'publish' | 'unpublish' | 'archive' | 'restore' | 'rent' | 'activate',
  id: string,
) => {
  try {
    const labels: Record<string, string> = {
      publish: t('landlordListings.toast.activated'),
      activate: t('landlordListings.toast.activated'),
      unpublish: t('landlordListings.toast.paused'),
      archive: t('landlordListings.toast.archived'),
      restore: t('landlordListings.toast.restored'),
      rent: t('landlordListings.toast.rented'),
    }
    let updated: any = null
    if (action === 'publish') updated = await listingsStore.publishListingAction(id)
    if (action === 'unpublish') updated = await listingsStore.unpublishListingAction(id)
    if (action === 'archive') updated = await listingsStore.archiveListingAction(id)
    if (action === 'restore') updated = await listingsStore.restoreListingAction(id)
    if (action === 'rent') updated = await listingsStore.markListingRentedAction(id)
    if (action === 'activate') updated = await listingsStore.markListingAvailableAction(id)
    toast.push({ title: labels[action] ?? `${t('landlordListings.toast.listing')} ${action}`, type: 'success' })
    if (updated?.warnings?.length) {
      toast.push({ title: t('common.headsUp'), message: updated.warnings[0], type: 'info' })
    }
  } catch (err: any) {
    toast.push({ title: t('common.actionFailed'), message: err.message, type: 'error' })
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="section-title">{{ t('landlordListings.title') }}</h2>
      <Button size="md" @click="router.push('/landlord/listings/new')">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('landlordListings.newListing') }}
      </Button>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in ['all','draft','active','paused','rented','archived','expired']"
        :key="opt"
        class="rounded-full px-3 py-2 text-sm font-semibold capitalize shadow-soft"
        :class="statusFilter === opt ? 'bg-primary text-white' : 'bg-white text-slate-800 border border-line'"
        @click="statusFilter = opt as any"
      >
        {{ opt === 'all' ? t('common.all') : t(`status.${opt}` as any) }}
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
        <button class="h-24 w-24 overflow-hidden rounded-2xl" @click="goToPublicListing(item.id)">
          <img :src="item.coverImage" :alt="item.title" class="h-full w-full object-cover" />
        </button>
        <div class="flex flex-1 flex-col gap-1">
          <div class="flex items-start justify-between">
            <div class="space-y-1">
              <button class="text-left text-base font-semibold text-slate-900 hover:text-primary" @click="goToPublicListing(item.id)">
                {{ item.title }}
              </button>
              <p class="text-xs text-muted">€{{ item.pricePerMonth }}/{{ t('listing.month') }} · {{ item.city }}</p>
            </div>
            <Badge :variant="statusBadge(item.status).variant">{{ statusBadge(item.status).label }}</Badge>
          </div>
          <p class="text-xs text-muted">{{ item.description }}</p>
          <p v-if="item.warnings?.length" class="text-xs font-semibold text-amber-600">
            {{ item.warnings[0] }}
          </p>
          <div class="flex items-center justify-between text-xs text-muted">
            <span>{{ t('listing.bedsLabel') }} {{ item.beds }} · {{ t('listing.bathsLabel') }} {{ item.baths }}</span>
            <span class="flex items-center gap-1 text-primary"><Sparkles class="h-3.5 w-3.5" /> {{ item.rating }}</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="md" @click="router.push(`/landlord/listings/${item.id}/edit`)">
              <Edit class="mr-2 h-4 w-4" /> {{ t('common.edit') }}
            </Button>
            <Button
              v-if="['draft', 'paused', 'rented', 'expired'].includes(item.status || '')"
              size="md"
              variant="primary"
              @click="handleAction('activate', item.id)"
            >
              <Upload class="mr-2 h-4 w-4" /> {{ t('landlordListings.activate') }}
            </Button>
            <Button
              v-if="item.status === 'active'"
              size="md"
              variant="secondary"
              @click="handleAction('unpublish', item.id)"
            >
              <XCircle class="mr-2 h-4 w-4" /> {{ t('landlordListings.pause') }}
            </Button>
            <Button
              v-if="item.status === 'active'"
              size="md"
              variant="secondary"
              @click="handleAction('rent', item.id)"
            >
              <Sparkles class="mr-2 h-4 w-4" /> {{ t('landlordListings.markRented') }}
            </Button>
            <Button
              v-if="item.status !== 'archived'"
              size="md"
              variant="secondary"
              @click="handleAction('archive', item.id)"
            >
              <Archive class="mr-2 h-4 w-4" /> {{ t('landlordListings.archive') }}
            </Button>
            <Button
              v-if="item.status === 'archived'"
              size="md"
              variant="secondary"
              @click="handleAction('restore', item.id)"
            >
              <Undo class="mr-2 h-4 w-4" /> {{ t('landlordListings.restore') }}
            </Button>
          </div>
        </div>
      </div>
      <EmptyState
        v-if="!listings.length && !error"
        :title="t('landlordListings.emptyTitle')"
        :subtitle="t('landlordListings.emptySubtitle')"
        :icon="Plus"
      >
        <Button class="mt-3" @click="router.push('/landlord/listings/new')">{{ t('landlordListings.createListing') }}</Button>
      </EmptyState>
    </div>
  </div>
</template>
