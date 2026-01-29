<script setup lang="ts">
import { onMounted, ref } from 'vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { deleteAdminRating, flagUserSuspicious, getAdminRatings } from '../services'
import type { Rating } from '../types'
import { useToastStore } from '../stores/toast'

const loading = ref(true)
const error = ref('')
const ratings = ref<Rating[]>([])
const toast = useToastStore()

const load = async (reportedOnly = false) => {
  loading.value = true
  error.value = ''
  try {
    ratings.value = await getAdminRatings({ reported: reportedOnly })
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load ratings.'
  } finally {
    loading.value = false
  }
}

onMounted(() => load(false))

const remove = async (id: string) => {
  try {
    await deleteAdminRating(id)
    ratings.value = ratings.value.filter((r) => r.id !== id)
    toast.push({ title: 'Deleted', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Delete failed', message: (err as Error).message, type: 'error' })
  }
}

const flagSuspicious = async (userId: string | number, isSuspicious: boolean) => {
  try {
    await flagUserSuspicious(userId, isSuspicious)
    toast.push({ title: 'Updated', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Failed', message: (err as Error).message, type: 'error' })
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">Dashboard</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">Moderacija</router-link>
      <router-link to="/admin/ratings">Ocene</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">KYC</router-link>
    </div>

    <div class="flex items-center gap-3">
      <Button variant="secondary" @click="load(false)">All</Button>
      <Button variant="primary" @click="load(true)">Reported</Button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />
    <div v-else class="space-y-3">
      <div
        v-for="rating in ratings"
        :key="rating.id"
        class="rounded-2xl border border-line bg-white p-4 shadow-soft space-y-2"
      >
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">Rating {{ rating.rating }}★</p>
          <span class="text-xs text-muted">{{ rating.createdAt }}</span>
        </div>
        <p class="text-sm text-muted">{{ rating.comment || 'No comment' }}</p>
        <p class="text-xs text-muted">
          Listing: {{ rating.listing?.title || rating.listingId }} · Reports: {{ rating.reportCount ?? 0 }}
        </p>
        <div class="flex gap-2">
          <Button variant="primary" size="sm" @click="flagSuspicious(rating.rater?.id || '', true)">Flag rater</Button>
          <Button variant="secondary" size="sm" @click="remove(rating.id)">Delete</Button>
        </div>
      </div>
      <EmptyState v-if="!ratings.length && !loading" title="No ratings" subtitle="Nothing to review right now." />
    </div>
  </div>
</template>
