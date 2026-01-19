<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ShieldCheck, ShieldX, Star } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Badge from '../components/ui/Badge.vue'
import { getPublicProfile } from '../services'
import type { PublicProfile } from '../types'

const route = useRoute()
const profile = ref<PublicProfile | null>(null)
const loading = ref(true)
const error = ref('')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    profile.value = await getPublicProfile(route.params.id as string)
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load profile.'
    profile.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)

const formatDate = (value?: string) => (value ? new Date(value).toLocaleDateString() : '')
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />

    <template v-else-if="profile">
      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-2">
        <h1 class="text-xl font-semibold text-slate-900">{{ profile.fullName }}</h1>
        <p class="text-sm text-muted">Joined {{ formatDate(profile.joinedAt) }}</p>
        <div class="flex flex-wrap gap-2 pt-2">
          <Badge :variant="profile.verifications.email ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.email" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              Email
            </span>
          </Badge>
          <Badge :variant="profile.verifications.phone ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.phone" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              Phone
            </span>
          </Badge>
          <Badge :variant="profile.verifications.address ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.address" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              Address
            </span>
          </Badge>
        </div>
      </div>

      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
        <div class="flex items-center gap-3">
          <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xl font-bold">
            <Star class="h-5 w-5" />
          </div>
          <div>
            <p class="text-2xl font-semibold text-slate-900">{{ profile.ratingStats.average.toFixed(1) }}</p>
            <p class="text-sm text-muted">{{ profile.ratingStats.total }} ratings</p>
          </div>
        </div>
        <EmptyState
          v-if="!profile.recentRatings.length"
          title="No ratings yet"
          subtitle="This host has not received ratings."
        />
        <div v-else class="space-y-3">
          <div
            v-for="(rating, idx) in profile.recentRatings"
            :key="idx"
            class="rounded-xl border border-line bg-surface p-3"
          >
            <div class="flex items-center justify-between">
              <p class="font-semibold text-slate-900">{{ rating.raterName || 'Guest' }}</p>
              <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary">
                {{ rating.rating }} ★
              </span>
            </div>
            <p class="text-sm text-muted">
              {{ rating.comment || 'No comment provided.' }}
            </p>
            <p class="text-xs text-muted mt-1">{{ formatDate(rating.createdAt) }}</p>
          </div>
        </div>
      </div>
    </template>
    <EmptyState v-else title="Profile unavailable" subtitle="This user is not visible." />
  </div>
</template>
