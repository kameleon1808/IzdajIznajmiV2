<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ShieldCheck, ShieldX, Star } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Badge from '../components/ui/Badge.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Button from '../components/ui/Button.vue'
import { getPublicProfile, getUserRatings, reportRating } from '../services'
import type { PublicProfile, Rating } from '../types'

const route = useRoute()
const profile = ref<PublicProfile | null>(null)
const loading = ref(true)
const error = ref('')
const ratings = ref<Rating[]>([])
const reportTarget = ref<Rating | null>(null)
const showReport = ref(false)
const reportReason = ref('spam')
const reportDetails = ref('')
const reportSubmitting = ref(false)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    profile.value = await getPublicProfile(route.params.id as string)
    ratings.value = await getUserRatings(route.params.id as string)
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load profile.'
    profile.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)

const formatDate = (value?: string) => (value ? new Date(value).toLocaleDateString() : '')

const submitReport = async () => {
  if (!reportTarget.value) return
  reportSubmitting.value = true
  try {
    await reportRating(reportTarget.value.id, reportReason.value, reportDetails.value || undefined)
    reportTarget.value = null
    showReport.value = false
    reportReason.value = 'spam'
    reportDetails.value = ''
  } catch (err) {
    error.value = (err as Error).message || 'Failed to report rating.'
  } finally {
    reportSubmitting.value = false
  }
}

const openReport = (rating: Rating) => {
  reportTarget.value = rating
  showReport.value = true
}
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
          <Badge
            v-if="profile.landlordVerification?.status === 'approved'"
            variant="accepted"
          >
            <span class="inline-flex items-center gap-1">
              <ShieldCheck class="h-4 w-4" />
              Verified landlord {{ profile.landlordVerification.verifiedAt ? `· ${formatDate(profile.landlordVerification.verifiedAt)}` : '' }}
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
        <EmptyState v-if="!ratings.length" title="No ratings yet" subtitle="This host has not received ratings." />
        <div v-else class="space-y-3">
          <div v-for="rating in ratings" :key="rating.id" class="rounded-xl border border-line bg-surface p-3">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-slate-900">{{ rating.rater?.name || 'Guest' }}</p>
              <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary">
                {{ rating.rating }} ★
              </span>
            </div>
            <p class="text-sm text-muted">
              {{ rating.comment || 'No comment provided.' }}
            </p>
            <p class="text-xs text-muted mt-1">{{ formatDate(rating.createdAt) }}</p>
            <div class="flex justify-end">
              <Button variant="secondary" size="sm" @click="openReport(rating)">
                Report
              </Button>
            </div>
          </div>
        </div>
      </div>
    </template>
    <EmptyState v-else title="Profile unavailable" subtitle="This user is not visible." />
  </div>

  <ModalSheet v-model="showReport" title="Report rating">
    <div class="space-y-3">
      <label class="text-sm font-semibold text-slate-900">Reason</label>
      <select v-model="reportReason" class="w-full rounded-xl border border-line px-3 py-2 text-sm">
        <option value="spam">Spam or fake</option>
        <option value="abuse">Abusive content</option>
        <option value="other">Other</option>
      </select>
      <textarea
        v-model="reportDetails"
        rows="3"
        class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
        placeholder="Additional details (optional)"
      ></textarea>
      <Button :disabled="reportSubmitting" variant="primary" block @click="submitReport">
        {{ reportSubmitting ? 'Submitting...' : 'Submit report' }}
      </Button>
    </div>
  </ModalSheet>
</template>
