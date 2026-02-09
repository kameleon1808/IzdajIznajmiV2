<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Button from '../components/ui/Button.vue'
import type { AdminConversion, AdminKpiSummary, AdminTrendPoint } from '../types'
import { getAdminKpiConversion, getAdminKpiSummary, getAdminKpiTrends } from '../services'
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const loading = ref(true)
const error = ref('')
const summary = ref<AdminKpiSummary | null>(null)
const conversion = ref<AdminConversion | null>(null)
const trends = ref<AdminTrendPoint[]>([])
const range = ref<'7d' | '30d'>('7d')
const userLookupId = ref('')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    summary.value = await getAdminKpiSummary()
    conversion.value = await getAdminKpiConversion()
    trends.value = await getAdminKpiTrends(range.value)
  } catch (err) {
    error.value = (err as any)?.message || t('admin.dashboard.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(range, () => load())

const trendMax = (key: keyof AdminTrendPoint) =>
  Math.max(...(trends.value.map((t) => Number((t as any)[key] ?? 0)) || [1, 1]), 1)

const goToUser = () => {
  if (!userLookupId.value) return
  router.push(`/admin/users/${userLookupId.value}`)
}
</script>

<template>
  <div class="space-y-5">
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 to-slate-700 px-5 py-6 text-white shadow-lg">
      <p class="text-sm opacity-80">{{ t('admin.header.operations') }}</p>
      <h1 class="text-2xl font-semibold leading-tight">{{ t('admin.dashboard.title') }}</h1>
      <p class="mt-1 text-sm opacity-75">{{ t('admin.dashboard.subtitle') }}</p>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">{{ t('admin.nav.moderation') }}</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">{{ t('admin.nav.ratings') }}</router-link>
      <router-link to="/admin/transactions" class="opacity-80 hover:opacity-100">{{ t('admin.nav.transactions') }}</router-link>
      <router-link to="/admin/users" class="opacity-80 hover:opacity-100">{{ t('admin.nav.users') }}</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">{{ t('admin.nav.kyc') }}</router-link>
    </div>

    <div class="rounded-2xl border border-line bg-white p-4 shadow-soft">
      <p class="text-xs text-muted">{{ t('admin.dashboard.userLookup') }}</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <input
          v-model="userLookupId"
          type="text"
          class="flex-1 rounded-xl border border-line px-3 py-2 text-sm"
          :placeholder="t('admin.dashboard.userLookupPlaceholder')"
        />
        <Button size="sm" @click="goToUser">{{ t('common.open') }}</Button>
      </div>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-6">
      <section>
        <h2 class="mb-2 text-lg font-semibold">{{ t('admin.dashboard.primaryNumbers') }}</h2>
        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.listings') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.listings.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.last7d') }}: {{ summary?.listings.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.applications') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.applications.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.last7d') }}: {{ summary?.applications.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.messages') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.messages.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.last7d') }}: {{ summary?.messages.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.reports') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.reports.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.last7d') }}: {{ summary?.reports.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.ratings') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.ratings.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.last7d') }}: {{ summary?.ratings.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">{{ t('admin.dashboard.suspiciousUsers') }}</p>
            <p class="text-2xl font-semibold">{{ summary?.suspiciousUsers ?? '–' }}</p>
            <p class="text-xs text-muted">{{ t('admin.dashboard.suspiciousHint') }}</p>
          </div>
        </div>
      </section>

      <section v-if="conversion" class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">{{ t('admin.dashboard.conversions') }}</h2>
          <p class="text-xs text-muted">{{ t('admin.dashboard.conversionsHint') }}</p>
        </div>
        <div class="space-y-3 rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div class="space-y-1">
            <div class="flex justify-between text-sm font-medium">
              <span>{{ t('admin.dashboard.browseToApply') }}</span>
              <span>{{ Math.round(conversion.browseToApply.rate * 100) }}%</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
              <div
                class="h-2 rounded-full bg-indigo-500 transition-all"
                :style="{ width: `${Math.min(100, Math.round(conversion.browseToApply.rate * 100))}%` }"
              />
            </div>
          </div>
          <div class="space-y-1">
            <div class="flex justify-between text-sm font-medium">
              <span>{{ t('admin.dashboard.applyToChat') }}</span>
              <span>{{ Math.round(conversion.applyToChat.rate * 100) }}%</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
              <div
                class="h-2 rounded-full bg-emerald-500 transition-all"
                :style="{ width: `${Math.min(100, Math.round(conversion.applyToChat.rate * 100))}%` }"
              />
            </div>
          </div>
          <div class="space-y-1">
            <div class="flex justify-between text-sm font-medium">
              <span>{{ t('admin.dashboard.chatToRating') }}</span>
              <span>{{ Math.round(conversion.chatToRating.rate * 100) }}%</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
              <div
                class="h-2 rounded-full bg-amber-500 transition-all"
                :style="{ width: `${Math.min(100, Math.round(conversion.chatToRating.rate * 100))}%` }"
              />
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold">{{ t('admin.dashboard.trends') }}</h2>
            <p class="text-xs text-muted">{{ t('admin.dashboard.trendsHint') }}</p>
          </div>
          <div class="flex gap-2">
            <Button size="sm" :variant="range === '7d' ? 'primary' : 'secondary'" @click="range = '7d'">{{ t('admin.dashboard.range7d') }}</Button>
            <Button size="sm" :variant="range === '30d' ? 'primary' : 'secondary'" @click="range = '30d'">
              {{ t('admin.dashboard.range30d') }}
            </Button>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div v-if="!trends.length" class="py-6 text-center text-sm text-muted">{{ t('admin.dashboard.noData') }}</div>
          <div v-else class="space-y-3">
            <div class="grid grid-cols-5 gap-2 text-center text-[11px] text-muted">
              <span>{{ t('admin.dashboard.listings') }}</span>
              <span>{{ t('admin.dashboard.applications') }}</span>
              <span>{{ t('admin.dashboard.messages') }}</span>
              <span>{{ t('admin.dashboard.ratings') }}</span>
              <span>{{ t('admin.dashboard.reports') }}</span>
            </div>
            <div class="grid grid-cols-5 gap-2 text-center text-[11px] text-muted">
              <span>{{ trendMax('listings') }}</span>
              <span>{{ trendMax('applications') }}</span>
              <span>{{ trendMax('messages') }}</span>
              <span>{{ trendMax('ratings') }}</span>
              <span>{{ trendMax('reports') }}</span>
            </div>
            <div class="flex items-end gap-2 overflow-x-auto pb-2">
              <div
                v-for="point in trends"
                :key="point.date"
                class="w-12 rounded-lg bg-slate-50 p-1 text-center text-[10px] text-muted"
              >
                <div class="flex h-28 items-end gap-0.5">
                  <div
                    class="w-[6px] rounded-full bg-indigo-500"
                    :style="{ height: `${(point.listings / trendMax('listings')) * 100}%` }"
                  />
                  <div
                    class="w-[6px] rounded-full bg-emerald-500"
                    :style="{ height: `${(point.applications / trendMax('applications')) * 100}%` }"
                  />
                  <div
                    class="w-[6px] rounded-full bg-sky-500"
                    :style="{ height: `${(point.messages / trendMax('messages')) * 100}%` }"
                  />
                  <div
                    class="w-[6px] rounded-full bg-amber-500"
                    :style="{ height: `${(point.ratings / trendMax('ratings')) * 100}%` }"
                  />
                  <div
                    class="w-[6px] rounded-full bg-rose-500"
                    :style="{ height: `${(point.reports / trendMax('reports')) * 100}%` }"
                  />
                </div>
                <div class="mt-1 leading-tight">{{ point.date.slice(5) }}</div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
