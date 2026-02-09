<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ShieldCheck, ShieldX, Star } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Badge from '../components/ui/Badge.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Button from '../components/ui/Button.vue'
import { getPublicProfile, getUserRatings, reportRating, getSharedTransactions, reportTransaction, leaveRating } from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import type { PublicProfile, Rating, RentalTransaction } from '../types'

const route = useRoute()
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const profile = ref<PublicProfile | null>(null)
const loading = ref(true)
const error = ref('')
const ratings = ref<Rating[]>([])
const reportTarget = ref<Rating | null>(null)
const showReport = ref(false)
const reportReason = ref('spam')
const reportDetails = ref('')
const reportSubmitting = ref(false)
const sharedTransactions = ref<RentalTransaction[]>([])
const showTransactionReport = ref(false)
const reportTransactionId = ref('')
const transactionReason = ref('issue')
const transactionDetails = ref('')
const transactionSubmitting = ref(false)
const ratingListingId = ref('')
const ratingScore = ref(0)
const ratingComment = ref('')
const ratingSubmitting = ref(false)
const ratingSubmitted = ref(false)

const ratingListings = computed(() =>
  sharedTransactions.value
    .filter((tx) => Boolean(tx.listing?.id))
    .map((tx) => tx.listing!)
)
const hasRatingListings = computed(() => ratingListings.value.length > 0)
const isSelf = computed(() =>
  Boolean(profile.value) &&
  Boolean(auth.user?.id) &&
  String(profile.value?.id ?? '') === String(auth.user?.id ?? '')
)
const isGuest = computed(() => !auth.isAuthenticated)
const ratingLockedReason = computed(() => {
  if (isGuest.value) return t('publicProfile.rating.login')
  if (isSelf.value) return t('publicProfile.rating.self')
  if (!hasRatingListings.value) return t('publicProfile.rating.noShared')
  return ''
})
const canSubmitRating = computed(() => !isGuest.value && !isSelf.value && hasRatingListings.value)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    profile.value = await getPublicProfile(route.params.id as string)
    ratings.value = await getUserRatings(route.params.id as string)
    if (auth.user?.id) {
      try {
        sharedTransactions.value = await getSharedTransactions(route.params.id as string)
        const [firstTransaction] = sharedTransactions.value
        if (firstTransaction) {
          reportTransactionId.value = firstTransaction.id
        }
        const firstListingId = sharedTransactions.value.find((tx) => tx.listing?.id)?.listing?.id
        ratingListingId.value = firstListingId ? String(firstListingId) : ''
      } catch (err) {
        sharedTransactions.value = []
      }
    }
  } catch (err) {
    error.value = (err as Error).message || t('publicProfile.loadFailed')
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
    error.value = (err as Error).message || t('publicProfile.reportRatingFailed')
  } finally {
    reportSubmitting.value = false
  }
}

const openReport = (rating: Rating) => {
  reportTarget.value = rating
  showReport.value = true
}

const openTransactionReport = () => {
  if (!reportTransactionId.value) {
    const [firstTransaction] = sharedTransactions.value
    if (firstTransaction) {
      reportTransactionId.value = firstTransaction.id
    }
  }
  showTransactionReport.value = true
}

const submitTransactionReport = async () => {
  if (!reportTransactionId.value) return
  transactionSubmitting.value = true
  try {
    await reportTransaction(reportTransactionId.value, transactionReason.value, transactionDetails.value || undefined)
    showTransactionReport.value = false
    transactionReason.value = 'issue'
    transactionDetails.value = ''
  } catch (err) {
    error.value = (err as Error).message || t('publicProfile.reportTransactionFailed')
  } finally {
    transactionSubmitting.value = false
  }
}

watch(
  () => ratingListingId.value,
  () => {
    ratingScore.value = 0
    ratingComment.value = ''
    ratingSubmitted.value = false
  },
)

const submitRating = async () => {
  if (!profile.value?.id) return
  if (!ratingListingId.value || ratingScore.value < 1) {
    toast.push({ title: t('publicProfile.rating.incompleteTitle'), message: t('publicProfile.rating.incompleteMessage'), type: 'error' })
    return
  }
  ratingSubmitting.value = true
  try {
    const rating = await leaveRating(ratingListingId.value, profile.value.id, {
      rating: ratingScore.value,
      comment: ratingComment.value || undefined,
    })
    ratings.value = [rating, ...ratings.value]
    if (profile.value?.ratingStats) {
      const total = profile.value.ratingStats.total ?? 0
      const avg = profile.value.ratingStats.average ?? 0
      profile.value.ratingStats.total = total + 1
      profile.value.ratingStats.average = total === 0
        ? ratingScore.value
        : (avg * total + ratingScore.value) / (total + 1)
    }
    ratingSubmitted.value = true
    ratingComment.value = ''
    toast.push({ title: t('publicProfile.rating.thankYou'), message: t('publicProfile.rating.submitted'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('publicProfile.rating.failedTitle'), message: (err as Error).message ?? t('publicProfile.rating.failedMessage'), type: 'error' })
  } finally {
    ratingSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />

    <template v-else-if="profile">
      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-2">
        <h1 class="text-xl font-semibold text-slate-900">{{ profile.fullName }}</h1>
        <p class="text-sm text-muted">{{ t('publicProfile.joined') }} {{ formatDate(profile.joinedAt) }}</p>
        <div class="flex flex-wrap gap-2 pt-2">
          <Badge :variant="profile.verifications.email ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.email" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              {{ t('publicProfile.email') }}
            </span>
          </Badge>
          <Badge :variant="profile.verifications.phone ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.phone" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              {{ t('publicProfile.phone') }}
            </span>
          </Badge>
          <Badge :variant="profile.verifications.address ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.address" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              {{ t('publicProfile.address') }}
            </span>
          </Badge>
          <Badge
            v-if="profile.landlordVerification?.status === 'approved'"
            variant="accepted"
          >
            <span class="inline-flex items-center gap-1">
              <ShieldCheck class="h-4 w-4" />
              {{ t('publicProfile.verifiedLandlord') }}
              {{ profile.landlordVerification.verifiedAt ? `· ${formatDate(profile.landlordVerification.verifiedAt)}` : '' }}
            </span>
          </Badge>
          <Badge v-if="profile.badges?.includes('top_landlord')" variant="info">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck class="h-4 w-4" />
              {{ t('publicProfile.topLandlord') }}
            </span>
          </Badge>
        </div>
        <div v-if="sharedTransactions.length" class="pt-2">
          <Button variant="secondary" size="sm" @click="openTransactionReport">{{ t('publicProfile.reportTransaction') }}</Button>
        </div>
      </div>

      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">{{ t('publicProfile.leaveRating') }}</p>
          <span v-if="ratingListingId" class="text-xs text-muted">{{ t('publicProfile.listingLabel') }} #{{ ratingListingId }}</span>
        </div>
        <div v-if="ratingLockedReason" class="rounded-xl border border-dashed border-line bg-surface px-3 py-2 text-sm text-muted">
          {{ ratingLockedReason }}
        </div>
        <div class="space-y-3">
          <label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('publicProfile.listing') }}</label>
          <select v-model="ratingListingId" class="w-full rounded-xl border border-line px-3 py-2 text-sm" :disabled="!hasRatingListings">
            <option v-for="listing in ratingListings" :key="listing.id" :value="listing.id">
              {{ listing.title ?? `${t('publicProfile.listingLabel')} #${listing.id}` }}{{ listing.city ? ` · ${listing.city}` : '' }}
            </option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-for="n in 5"
            :key="n"
            class="h-10 w-10 rounded-full border border-line text-lg font-semibold disabled:opacity-60"
            :class="n <= ratingScore ? 'bg-primary text-white' : 'bg-surface text-slate-700'"
            :disabled="!canSubmitRating"
            @click="ratingScore = n"
            type="button"
          >
            {{ n }}★
          </button>
        </div>
        <textarea
          v-model="ratingComment"
          rows="3"
          class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
          :placeholder="t('publicProfile.rating.placeholder')"
          :disabled="!canSubmitRating"
        ></textarea>
        <Button
          block
          size="md"
          variant="primary"
          :disabled="!canSubmitRating || ratingSubmitting || ratingSubmitted || ratingScore < 1"
          @click="submitRating"
        >
          {{ ratingSubmitted ? t('publicProfile.rating.submittedButton') : ratingSubmitting ? t('common.submitting') : t('publicProfile.rating.submit') }}
        </Button>
      </div>

      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
        <div class="flex items-center gap-3">
          <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xl font-bold">
            <Star class="h-5 w-5" />
          </div>
          <div>
            <p class="text-2xl font-semibold text-slate-900">{{ profile.ratingStats.average.toFixed(1) }}</p>
            <p class="text-sm text-muted">{{ profile.ratingStats.total }} {{ t('publicProfile.ratingsCount') }}</p>
          </div>
        </div>
        <EmptyState v-if="!ratings.length" :title="t('publicProfile.noRatingsTitle')" :subtitle="t('publicProfile.noRatingsSubtitle')" />
        <div v-else class="space-y-3">
          <div v-for="rating in ratings" :key="rating.id" class="rounded-xl border border-line bg-surface p-3">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-slate-900">{{ rating.rater?.name || t('publicProfile.guest') }}</p>
              <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary">
                {{ rating.rating }} ★
              </span>
            </div>
            <p class="text-sm text-muted">
              {{ rating.comment || t('publicProfile.noComment') }}
            </p>
            <p class="text-xs text-muted mt-1">{{ formatDate(rating.createdAt) }}</p>
            <div class="flex justify-end">
              <Button variant="secondary" size="sm" @click="openReport(rating)">
                {{ t('publicProfile.report') }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </template>
    <EmptyState v-else :title="t('publicProfile.unavailableTitle')" :subtitle="t('publicProfile.unavailableSubtitle')" />
  </div>

  <ModalSheet v-model="showReport" :title="t('publicProfile.reportRatingTitle')">
    <div class="space-y-3">
      <label class="text-sm font-semibold text-slate-900">{{ t('publicProfile.reason') }}</label>
      <select v-model="reportReason" class="w-full rounded-xl border border-line px-3 py-2 text-sm">
        <option value="spam">{{ t('publicProfile.reportReasons.spam') }}</option>
        <option value="abuse">{{ t('publicProfile.reportReasons.abuse') }}</option>
        <option value="other">{{ t('publicProfile.reportReasons.other') }}</option>
      </select>
      <textarea
        v-model="reportDetails"
        rows="3"
        class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
        :placeholder="t('publicProfile.detailsOptional')"
      ></textarea>
      <Button :disabled="reportSubmitting" variant="primary" block @click="submitReport">
        {{ reportSubmitting ? t('common.submitting') : t('publicProfile.submitReport') }}
      </Button>
    </div>
  </ModalSheet>

  <ModalSheet v-model="showTransactionReport" :title="t('publicProfile.reportTransactionTitle')">
    <div class="space-y-3">
      <label class="text-sm font-semibold text-slate-900">{{ t('publicProfile.transaction') }}</label>
      <select v-model="reportTransactionId" class="w-full rounded-xl border border-line px-3 py-2 text-sm">
        <option v-for="tx in sharedTransactions" :key="tx.id" :value="tx.id">
          #{{ tx.id }} · {{ tx.listing?.title ?? t('publicProfile.listingLabel') }} · {{ tx.status }}
        </option>
      </select>
      <label class="text-sm font-semibold text-slate-900">{{ t('publicProfile.reason') }}</label>
      <select v-model="transactionReason" class="w-full rounded-xl border border-line px-3 py-2 text-sm">
        <option value="issue">{{ t('publicProfile.transactionReasons.issue') }}</option>
        <option value="abuse">{{ t('publicProfile.transactionReasons.abuse') }}</option>
        <option value="other">{{ t('publicProfile.reportReasons.other') }}</option>
      </select>
      <textarea
        v-model="transactionDetails"
        rows="3"
        class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
        :placeholder="t('publicProfile.detailsOptional')"
      ></textarea>
      <Button :disabled="transactionSubmitting" variant="primary" block @click="submitTransactionReport">
        {{ transactionSubmitting ? t('common.submitting') : t('publicProfile.submitReport') }}
      </Button>
    </div>
  </ModalSheet>
</template>
