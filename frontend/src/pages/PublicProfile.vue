<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ShieldCheck, ShieldX, Star } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Badge from '../components/ui/Badge.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import Button from '../components/ui/Button.vue'
import ImageLightbox from '../components/ui/ImageLightbox.vue'
import AvatarPlaceholder from '../components/ui/AvatarPlaceholder.vue'
import { getPublicProfile, getUserRatings, reportRating, replyToRating, getSharedTransactions, reportTransaction, leaveRating } from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import type { PublicProfile, Rating, RentalTransaction } from '../types'

const route = useRoute()
const router = useRouter()
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
const replyInputs = ref<Record<string, string>>({})
const replySubmitting = ref<Record<string, boolean>>({})
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
const avatarLightboxOpen = ref(false)
const avatarLightboxIndex = ref(0)
const publicAvatarUrl = computed(() => profile.value?.avatarUrl ?? null)
const publicAvatarImages = computed(() => (publicAvatarUrl.value ? [publicAvatarUrl.value] : []))

const eligibleListingIds = computed(() => (profile.value?.eligibleListingIds ?? []).map((id) => String(id)))
const eligibleListingIdSet = computed(() => new Set(eligibleListingIds.value))
const ratingListings = computed(() =>
  sharedTransactions.value
    .filter((tx) => Boolean(tx.listing?.id))
    .map((tx) => tx.listing!)
    .filter((listing) => eligibleListingIdSet.value.size === 0 || eligibleListingIdSet.value.has(String(listing.id))),
)
const hasRatingListings = computed(() => ratingListings.value.length > 0)
const isSelf = computed(() =>
  Boolean(profile.value) &&
  Boolean(auth.user?.id) &&
  String(profile.value?.id ?? '') === String(auth.user?.id ?? '')
)
const isAdmin = computed(() => auth.hasRole('admin'))
const showVerifiedBadge = computed(() => profile.value?.verification?.status === 'approved')
const canRateLandlord = computed(() => Boolean(profile.value?.canRateLandlord))
const canRateSeeker = computed(() => Boolean(profile.value?.canRateSeeker))
const canSubmitRating = computed(() => {
  if (isSelf.value || !hasRatingListings.value) return false
  if (auth.hasRole('seeker')) return canRateLandlord.value
  if (auth.hasRole('landlord')) return canRateSeeker.value
  return false
})
const showRatingForm = computed(() => canSubmitRating.value)

const canReportRating = (rating: Rating) => {
  if (!isSelf.value) return false
  return String(rating.rater?.id ?? '') !== String(auth.user?.id ?? '')
}

const canReplyToRating = (rating: Rating) => {
  if (isAdmin.value) return true
  if (!isSelf.value) return false
  const replies = rating.replies ?? []
  return !replies.some((reply) => String(reply.author?.id ?? '') === String(auth.user?.id ?? '') && !reply.isAdmin)
}

const load = async () => {
  const rawId = String(route.params.id ?? '')
  if (!/^\d+$/.test(rawId) || rawId.toLowerCase() === 'guest') {
    await router.replace('/')
    return
  }

  loading.value = true
  error.value = ''
  try {
    profile.value = await getPublicProfile(rawId)
    ratings.value = await getUserRatings(rawId)
    if (auth.user?.id) {
      try {
        sharedTransactions.value = await getSharedTransactions(rawId)
        const [firstTransaction] = sharedTransactions.value
        if (firstTransaction) {
          reportTransactionId.value = firstTransaction.id
        }
        const firstListingId = sharedTransactions.value.find((tx) => {
          const listingId = tx.listing?.id
          return listingId && eligibleListingIdSet.value.has(String(listingId))
        })?.listing?.id
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

const submitReply = async (rating: Rating) => {
  const input = replyInputs.value[rating.id] ?? ''
  if (!input.trim()) return
  replySubmitting.value = { ...replySubmitting.value, [rating.id]: true }
  try {
    const updated = await replyToRating(rating.id, input.trim())
    ratings.value = ratings.value.map((r) => (r.id === rating.id ? updated : r))
    replyInputs.value = { ...replyInputs.value, [rating.id]: '' }
  } catch (err) {
    error.value = (err as Error).message || t('publicProfile.replyFailed')
  } finally {
    replySubmitting.value = { ...replySubmitting.value, [rating.id]: false }
  }
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

const goToEditProfile = () => {
  router.push('/settings/profile')
}

const openAvatarLightbox = () => {
  if (!publicAvatarUrl.value) return
  avatarLightboxIndex.value = 0
  avatarLightboxOpen.value = true
}
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />

    <template v-else-if="profile">
      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-2">
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <button
              v-if="publicAvatarUrl"
              type="button"
              class="cursor-zoom-in rounded-2xl"
              :aria-label="t('common.avatarAlt')"
              @click="openAvatarLightbox"
            >
              <img
                :src="publicAvatarUrl"
                :alt="t('common.avatarAlt')"
                class="h-12 w-12 rounded-2xl object-cover shadow-soft"
              />
            </button>
            <AvatarPlaceholder v-else :alt="t('common.avatarAlt')" />
            <h1 class="min-w-0 break-words text-xl font-semibold text-slate-900">{{ profile.fullName }}</h1>
          </div>
          <Button v-if="isSelf" variant="secondary" size="sm" @click="goToEditProfile">
            {{ t('publicProfile.editProfile') }}
          </Button>
        </div>
        <p class="text-sm text-muted">{{ t('publicProfile.joined') }} {{ formatDate(profile.joinedAt) }}</p>
        <div class="flex flex-wrap gap-2 pt-2">
          <Badge :variant="profile.verifications.email ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.email" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              {{ t('publicProfile.email') }}
            </span>
          </Badge>
          <Badge :variant="profile.verifications.address ? 'accepted' : 'cancelled'">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck v-if="profile.verifications.address" class="h-4 w-4" />
              <ShieldX v-else class="h-4 w-4" />
              {{ t('publicProfile.address') }}
            </span>
          </Badge>
          <Badge v-if="showVerifiedBadge" variant="accepted">
            <span class="inline-flex items-center gap-1">
              <ShieldCheck class="h-4 w-4" />
              {{ t('publicProfile.verifiedUser') }}
              {{ profile.verification?.verifiedAt ? `· ${formatDate(profile.verification.verifiedAt)}` : '' }}
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

      <div v-if="showRatingForm" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">{{ t('publicProfile.leaveRating') }}</p>
          <span v-if="ratingListingId" class="text-xs text-muted">{{ t('publicProfile.listingLabel') }} #{{ ratingListingId }}</span>
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
            <p class="text-xs text-muted mt-1">
              {{ t('publicProfile.listingLabel') }}:
              {{ rating.listing?.title || `#${rating.listingId}` }}
              {{ rating.listing?.city ? `· ${rating.listing.city}` : '' }}
            </p>
            <p class="text-xs text-muted mt-1">{{ formatDate(rating.createdAt) }}</p>
            <div v-if="rating.replies?.length" class="mt-3 space-y-2">
              <div v-for="reply in rating.replies" :key="reply.id" class="rounded-xl border border-line bg-white p-3 text-sm">
                <div class="flex items-center justify-between">
                  <p class="font-semibold text-slate-900">{{ reply.author?.name || t('publicProfile.guest') }}</p>
                  <Badge v-if="reply.isAdmin" variant="info">{{ t('publicProfile.adminBadge') }}</Badge>
                </div>
                <p class="text-sm text-slate-700 mt-1">{{ reply.body }}</p>
                <p class="text-xs text-muted mt-1">{{ formatDate(reply.createdAt) }}</p>
              </div>
            </div>
            <div v-if="canReplyToRating(rating)" class="mt-3 space-y-2">
              <label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">{{ t('publicProfile.reply') }}</label>
              <textarea
                v-model="replyInputs[rating.id]"
                rows="2"
                class="w-full rounded-2xl border border-line bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
                :placeholder="t('publicProfile.replyPlaceholder')"
              ></textarea>
              <Button
                variant="secondary"
                size="sm"
                :disabled="replySubmitting[rating.id]"
                @click="submitReply(rating)"
              >
                {{ replySubmitting[rating.id] ? t('common.submitting') : t('publicProfile.replySubmit') }}
              </Button>
            </div>
            <div class="flex justify-end mt-3" v-if="canReportRating(rating)">
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

  <ImageLightbox
    :images="publicAvatarImages"
    :open="avatarLightboxOpen"
    :index="avatarLightboxIndex"
    :alt="t('common.avatarAlt')"
    @update:open="avatarLightboxOpen = $event"
    @update:index="avatarLightboxIndex = $event"
  />
</template>
