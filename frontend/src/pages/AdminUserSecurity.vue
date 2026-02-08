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

const route = useRoute()
const userId = computed(() => String(route.params.id ?? ''))

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
    error.value = err.message ?? 'Neuspešno učitavanje korisnika.'
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
    error.value = err.message ?? 'Neuspešno uklanjanje oznake.'
  }
}

const markSuspicious = async () => {
  error.value = ''
  try {
    await flagUserSuspicious(userId.value, true)
    await load()
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno označavanje korisnika.'
  }
}

const revokeSessions = async () => {
  error.value = ''
  try {
    await revokeAdminUserSessions(userId.value)
    await load()
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno opozivanje sesija.'
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
    error.value = err.message ?? 'Neuspešno ažuriranje oznake.'
  } finally {
    badgeUpdating.value = false
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
          <p class="text-xs text-muted">User ID {{ payload.user?.id }}</p>
          <h1 class="text-lg font-semibold">{{ payload.user?.fullName || payload.user?.name || 'User' }}</h1>
          <p class="text-xs text-muted">{{ payload.user?.email }}</p>
        </div>
        <div class="flex flex-col items-end gap-1">
          <Badge :variant="payload.user?.mfaEnabled ? 'accepted' : 'pending'">
            MFA {{ payload.user?.mfaEnabled ? 'On' : 'Off' }}
          </Badge>
          <Badge v-if="payload.user?.mfaRequired" variant="pending">MFA Required</Badge>
          <Badge :variant="payload.user?.isSuspicious ? 'rejected' : 'accepted'">
            {{ payload.user?.isSuspicious ? 'Suspicious' : 'Normal' }}
          </Badge>
        </div>
      </div>
    </div>

    <div
      v-if="payload?.landlordMetrics"
      class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3"
    >
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">Landlord metrics</h2>
          <p class="text-xs text-muted">Updated: {{ payload.landlordMetrics.updatedAt ?? '—' }}</p>
        </div>
        <div class="flex flex-col items-end gap-1">
          <Badge v-if="payload.landlordBadges?.badges?.includes('top_landlord')" variant="accepted">
            Top landlord
          </Badge>
          <Badge v-else variant="pending">No badge</Badge>
          <Badge v-if="payload.landlordBadges?.suppressed" variant="rejected">Suppressed</Badge>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">Avg rating (30d)</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.avgRating30d ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">All-time avg</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.allTimeAvgRating ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">Ratings count</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.ratingsCount ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">Median response</p>
          <p class="text-base font-semibold">
            {{ payload.landlordMetrics.medianResponseTimeMinutes != null ? `${payload.landlordMetrics.medianResponseTimeMinutes} min` : '—' }}
          </p>
        </div>
        <div class="rounded-xl border border-line bg-surface px-3 py-2">
          <p class="text-xs text-muted">Completed rentals</p>
          <p class="text-base font-semibold">{{ payload.landlordMetrics.completedTransactionsCount ?? 0 }}</p>
        </div>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-semibold">Badge override</p>
        <div class="flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" :loading="badgeUpdating" @click="setTopLandlordOverride(true)">
            Force show
          </Button>
          <Button size="sm" variant="secondary" :loading="badgeUpdating" @click="setTopLandlordOverride(false)">
            Force hide
          </Button>
          <Button size="sm" variant="ghost" :loading="badgeUpdating" @click="setTopLandlordOverride(null)">
            Clear override
          </Button>
        </div>
        <p v-if="payload.landlordBadges?.suppressed" class="text-xs text-rose-500">
          Badge display suppressed because the landlord is marked suspicious.
        </p>
      </div>
    </div>

    <div v-if="payload" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">Fraud Score</h2>
          <p class="text-xs text-muted">Last calculated: {{ payload.fraudScore?.lastCalculatedAt ?? '—' }}</p>
        </div>
        <div class="text-2xl font-semibold text-rose-500">{{ payload.fraudScore?.score ?? 0 }}</div>
      </div>
      <div class="mt-3 flex gap-2">
        <Button size="sm" variant="secondary" :loading="loading" @click="load">Refresh</Button>
        <Button size="sm" variant="danger" :loading="loading" @click="clearSuspicion">
          Clear fraud
        </Button>
        <Button size="sm" variant="secondary" :loading="loading" @click="markSuspicious">
          Mark suspicious
        </Button>
      </div>
      <div class="mt-4 space-y-2">
        <h3 class="text-sm font-semibold">Recent signals</h3>
        <div v-if="!payload.fraudSignals?.length" class="text-xs text-muted">No recent signals.</div>
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
          <h2 class="text-lg font-semibold">Sessions</h2>
          <p class="text-xs text-muted">Active devices for this user.</p>
        </div>
        <Button size="sm" variant="danger" :loading="loading" @click="revokeSessions">Revoke all</Button>
      </div>
      <div v-if="!payload.sessions?.length" class="mt-3 text-xs text-muted">No sessions.</div>
      <div v-else class="mt-3 space-y-2">
        <div v-for="session in payload.sessions" :key="session.id" class="rounded-xl border border-line bg-surface px-3 py-2">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold">
                {{ session.deviceLabel || session.userAgent?.slice(0, 48) || 'Unknown device' }}
              </p>
              <p class="text-xs text-muted">IP: {{ session.ipTruncated ?? 'N/A' }}</p>
            </div>
            <div class="text-xs text-muted">Last active: {{ session.lastActiveAt ?? '—' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
