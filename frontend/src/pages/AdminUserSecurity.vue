<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import Badge from '../components/ui/Badge.vue'
import {
  clearUserSuspicion,
  flagUserSuspicious,
  getAdminUserSecurity,
  revokeAdminUserSessions,
  updateAdminUserBadges,
} from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const route = useRoute()
const userId = computed(() => String(route.params.id ?? ''))
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(false)
const error = ref('')
const payload = ref<any>(null)
const badgeUpdating = ref(false)

const load = async () => {
  if (!userId.value) return
  loading.value = true
  error.value = ''
  try {
    payload.value = await getAdminUserSecurity(userId.value)
  } catch (err: any) {
    error.value = err.message ?? t('admin.userSecurity.loadFailed')
  } finally {
    loading.value = false
  }
}

const clearSuspicion = async () => {
  error.value = ''
  try {
    await clearUserSuspicion(userId.value)
    await load()
  } catch (err: any) {
    error.value = err.message ?? t('admin.userSecurity.clearFailed')
  }
}

const markSuspicious = async () => {
  error.value = ''
  try {
    await flagUserSuspicious(userId.value, true)
    await load()
  } catch (err: any) {
    error.value = err.message ?? t('admin.userSecurity.markFailed')
  }
}

const revokeSessions = async () => {
  error.value = ''
  try {
    await revokeAdminUserSessions(userId.value)
    await load()
  } catch (err: any) {
    error.value = err.message ?? t('admin.userSecurity.revokeFailed')
  }
}

const setTopLandlordOverride = async (value: boolean | null) => {
  if (!userId.value) return
  badgeUpdating.value = true
  error.value = ''
  try {
    const result = await updateAdminUserBadges(userId.value, { topLandlord: value })
    payload.value = {
      ...payload.value,
      landlordBadges: {
        ...(payload.value?.landlordBadges ?? {}),
        badges: result.badges ?? [],
        override: result.override ?? null,
        suppressed: result.suppressed ?? false,
      },
    }
  } catch (err: any) {
    error.value = err.message ?? t('admin.userSecurity.badgeFailed')
  } finally {
    badgeUpdating.value = false
  }
}

const GENDER_KEYS: Record<string, string> = {
  muski: 'common.gender.male',
  zenski: 'common.gender.female',
  ne_zelim_da_kazem: 'common.gender.ratherNotToSay',
}

const EMPLOYMENT_KEYS: Record<string, string> = {
  zaposlen: 'common.employmentStatus.employed',
  nezaposlen: 'common.employmentStatus.unemployed',
  student: 'common.employmentStatus.student',
  penzioner: 'common.employmentStatus.retired',
}

const formatGender = (value: string | null | undefined): string => {
  if (!value) return '—'
  const key = GENDER_KEYS[value]
  return key ? t(key as Parameters<typeof languageStore.t>[0]) : value
}

const formatEmployment = (value: string | null | undefined): string => {
  if (!value) return '—'
  const key = EMPLOYMENT_KEYS[value]
  return key ? t(key as Parameters<typeof languageStore.t>[0]) : value
}

const loginAsUser = async () => {
  try {
    await auth.startImpersonation(userId.value)
    toast.push({ title: t('common.success'), message: t('admin.userSecurity.loginAsUserSuccess'), type: 'info' })
  } catch (err: any) {
    toast.push({ title: t('common.error'), message: err?.message || t('admin.userSecurity.loginAsUserFailed'), type: 'error' })
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <ErrorBanner v-if="error" :message="error" />

    <div v-if="payload" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-muted">{{ t('admin.userSecurity.userId') }} {{ payload.user?.id }}</p>
          <h1 class="text-lg font-semibold">{{ payload.user?.fullName || payload.user?.name || t('common.user') }}</h1>
          <p class="text-xs text-muted">{{ payload.user?.email }}</p>
        </div>
        <div class="flex flex-col items-end gap-1">
          <Badge :variant="payload.user?.mfaEnabled ? 'accepted' : 'pending'">
            MFA {{ payload.user?.mfaEnabled ? t('admin.userSecurity.on') : t('admin.userSecurity.off') }}
          </Badge>
          <Badge v-if="payload.user?.mfaRequired" variant="pending">{{ t('admin.userSecurity.mfaRequired') }}</Badge>
          <Badge :variant="payload.user?.isSuspicious ? 'rejected' : 'accepted'">
            {{ payload.user?.isSuspicious ? t('admin.userSecurity.suspicious') : t('admin.userSecurity.normal') }}
          </Badge>
        </div>
      </div>
      <div class="mt-3">
        <Button size="sm" variant="secondary" @click="loginAsUser">{{ t('admin.userSecurity.loginAsUser') }}</Button>
      </div>
    </div>

    <!-- Personal Data (PII) — admin-only section -->
    <div v-if="payload" class="rounded-2xl bg-white shadow-soft border border-amber-200">
      <!-- Header with admin-only warning -->
      <div class="flex items-center gap-2 rounded-t-2xl bg-amber-50 border-b border-amber-200 px-4 py-3">
        <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <span class="text-xs font-semibold text-amber-700 uppercase tracking-wide">{{ t('admin.userSecurity.piiSection') }}</span>
        <span class="text-xs text-amber-600">— {{ t('admin.userSecurity.piiWarning') }}</span>
      </div>

      <div class="p-4 space-y-4">
        <!-- Identity -->
        <div>
          <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ t('admin.userSecurity.piiIdentity') }}</p>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiFullName') }}</p>
              <p class="text-sm font-medium break-all">{{ payload.user?.fullName || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiDateOfBirth') }}</p>
              <p class="text-sm font-medium">{{ payload.user?.dateOfBirth || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiGender') }}</p>
              <p class="text-sm font-medium">{{ formatGender(payload.user?.gender) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiEmployment') }}</p>
              <p class="text-sm font-medium">{{ formatEmployment(payload.user?.employmentStatus) }}</p>
            </div>
          </div>
        </div>

        <!-- Contact -->
        <div>
          <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ t('admin.userSecurity.piiContact') }}</p>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted">{{ t('admin.userSecurity.piiEmail') }}</p>
                <Badge :variant="payload.user?.emailVerified ? 'accepted' : 'pending'" class="text-[10px]">
                  {{ payload.user?.emailVerified ? t('admin.userSecurity.piiVerified') : t('admin.userSecurity.piiNotVerified') }}
                </Badge>
              </div>
              <p class="text-sm font-medium break-all">{{ payload.user?.email || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted">{{ t('admin.userSecurity.piiPhone') }}</p>
                <Badge :variant="payload.user?.phoneVerified ? 'accepted' : 'pending'" class="text-[10px]">
                  {{ payload.user?.phoneVerified ? t('admin.userSecurity.piiVerified') : t('admin.userSecurity.piiNotVerified') }}
                </Badge>
              </div>
              <p class="text-sm font-medium">{{ payload.user?.phone || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2 sm:col-span-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted">{{ t('admin.userSecurity.piiAddress') }}</p>
                <Badge :variant="payload.user?.addressVerified ? 'accepted' : 'pending'" class="text-[10px]">
                  {{ payload.user?.addressVerified ? t('admin.userSecurity.piiVerified') : t('admin.userSecurity.piiNotVerified') }}
                </Badge>
              </div>
              <p class="text-sm font-medium">{{ payload.user?.residentialAddress || '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Address book -->
        <div v-if="payload.user?.addressBook?.length">
          <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ t('admin.userSecurity.piiAddressBook') }}</p>
          <div class="space-y-1">
            <div
              v-for="(entry, idx) in payload.user.addressBook"
              :key="idx"
              class="rounded-xl border border-line bg-surface px-3 py-2 text-sm"
            >
              <p v-if="entry?.label" class="text-xs text-muted">{{ entry.label }}</p>
              <p class="font-medium">{{ entry?.address || entry }}</p>
            </div>
          </div>
        </div>

        <!-- Verification & account info -->
        <div>
          <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ t('admin.userSecurity.piiAccount') }}</p>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiRole') }}</p>
              <p class="text-sm font-medium capitalize">{{ payload.user?.role || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiRegistered') }}</p>
              <p class="text-sm font-medium">{{ payload.user?.createdAt ? new Date(payload.user.createdAt).toLocaleDateString() : '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiKycStatus') }}</p>
              <p class="text-sm font-medium capitalize">{{ payload.user?.verificationStatus || '—' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface px-3 py-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiKycVerifiedAt') }}</p>
              <p class="text-sm font-medium">{{ payload.user?.verifiedAt ? new Date(payload.user.verifiedAt).toLocaleDateString() : '—' }}</p>
            </div>
            <div v-if="payload.user?.verificationNotes" class="rounded-xl border border-line bg-surface px-3 py-2 sm:col-span-2">
              <p class="text-xs text-muted">{{ t('admin.userSecurity.piiKycNotes') }}</p>
              <p class="text-sm font-medium whitespace-pre-wrap">{{ payload.user.verificationNotes }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="payload?.landlordMetrics"
      class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3"
    >
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ t('admin.userSecurity.landlordMetrics') }}</h2>
          <p class="text-xs text-muted">{{ t('admin.userSecurity.updatedAt') }}: {{ payload.landlordMetrics.updatedAt ?? '—' }}</p>
        </div>
        <div class="flex flex-col items-end gap-1">
          <Badge v-if="payload.landlordBadges?.badges?.includes('top_landlord')" variant="accepted">
            {{ t('publicProfile.topLandlord') }}
          </Badge>
          <Badge v-else variant="pending">{{ t('admin.userSecurity.noBadge') }}</Badge>
          <Badge v-if="payload.landlordBadges?.suppressed" variant="rejected">{{ t('admin.userSecurity.suppressed') }}</Badge>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">{{ t('admin.userSecurity.avgRating30d') }}</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.avgRating30d ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">{{ t('admin.userSecurity.allTimeAvg') }}</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.allTimeAvgRating ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">{{ t('admin.userSecurity.ratingsCount') }}</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.ratingsCount ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">{{ t('admin.userSecurity.medianResponse') }}</p>
          <p class="text-base font-semibold">
            {{ payload.landlordMetrics.medianResponseTimeMinutes != null ? `${payload.landlordMetrics.medianResponseTimeMinutes} ${t('admin.userSecurity.minutes')}` : '—' }}
          </p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">{{ t('admin.userSecurity.completedRentals') }}</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.completedTransactionsCount ?? 0 }}</p>
        </div>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-semibold">{{ t('admin.userSecurity.badgeOverride') }}</p>
        <div class="flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" :loading="badgeUpdating" @click="setTopLandlordOverride(true)">
            {{ t('admin.userSecurity.forceShow') }}
          </Button>
          <Button size="sm" variant="secondary" :loading="badgeUpdating" @click="setTopLandlordOverride(false)">
            {{ t('admin.userSecurity.forceHide') }}
          </Button>
          <Button size="sm" variant="ghost" :loading="badgeUpdating" @click="setTopLandlordOverride(null)">
            {{ t('admin.userSecurity.clearOverride') }}
          </Button>
        </div>
        <p v-if="payload.landlordBadges?.suppressed" class="text-xs text-rose-500">
          {{ t('admin.userSecurity.suppressedHint') }}
        </p>
      </div>
    </div>

    <div v-if="payload" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ t('admin.userSecurity.fraudScore') }}</h2>
          <p class="text-xs text-muted">{{ t('admin.userSecurity.lastCalculated') }}: {{ payload.fraudScore?.lastCalculatedAt ?? '—' }}</p>
        </div>
        <div class="text-2xl font-semibold text-rose-500">{{ payload.fraudScore?.score ?? 0 }}</div>
      </div>
      <div class="mt-3 flex gap-2">
        <Button size="sm" variant="secondary" :loading="loading" @click="load">{{ t('common.refresh') }}</Button>
        <Button size="sm" variant="danger" :loading="loading" @click="clearSuspicion">{{ t('admin.userSecurity.clearFraud') }}</Button>
        <Button size="sm" variant="secondary" :loading="loading" @click="markSuspicious">{{ t('admin.userSecurity.markSuspicious') }}</Button>
      </div>
      <div class="mt-4 space-y-2">
        <h3 class="text-sm font-semibold">{{ t('admin.userSecurity.recentSignals') }}</h3>
        <div v-if="!payload.fraudSignals?.length" class="text-xs text-muted">{{ t('admin.userSecurity.noSignals') }}</div>
        <div v-else class="space-y-2">
          <div
            v-for="signal in payload.fraudSignals"
            :key="signal.id"
            class="flex items-center justify-between rounded-xl border border-line bg-surface px-3 py-2"
          >
            <div>
              <p class="text-sm font-semibold">{{ signal.signalKey }}</p>
              <p class="text-xs text-muted">{{ signal.createdAt }}</p>
            </div>
            <Badge variant="pending">+{{ signal.weight }}</Badge>
          </div>
        </div>
      </div>
    </div>

    <div v-if="payload" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ t('admin.userSecurity.sessions') }}</h2>
          <p class="text-xs text-muted">{{ t('admin.userSecurity.sessionsHint') }}</p>
        </div>
        <Button size="sm" variant="danger" :loading="loading" @click="revokeSessions">{{ t('admin.userSecurity.revokeAll') }}</Button>
      </div>
      <div v-if="!payload.sessions?.length" class="mt-3 text-xs text-muted">{{ t('admin.userSecurity.noSessions') }}</div>
      <div v-else class="mt-3 space-y-2">
        <div v-for="session in payload.sessions" :key="session.id" class="rounded-xl border border-line bg-surface px-3 py-2">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold">
                {{ session.deviceLabel || session.userAgent?.slice(0, 48) || t('settings.security.unknownDevice') }}
              </p>
              <p class="text-xs text-muted">IP: {{ session.ipTruncated ?? t('common.na') }}</p>
            </div>
            <div class="text-xs text-muted">{{ t('settings.security.lastActive') }}: {{ session.lastActiveAt ?? '—' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
