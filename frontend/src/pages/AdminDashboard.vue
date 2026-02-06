<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Button from '../components/ui/Button.vue'
import type { AdminConversion, AdminKpiSummary, AdminTrendPoint } from '../types'
import { getAdminKpiConversion, getAdminKpiSummary, getAdminKpiTrends } from '../services'

const router = useRouter()
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
    error.value = (err as any)?.message || 'Neuspešno učitavanje KPI pregleda.'
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
      <p class="text-sm opacity-80">Admin Operations</p>
      <h1 class="text-2xl font-semibold leading-tight">KPI kontrolna tabla</h1>
      <p class="mt-1 text-sm opacity-75">Brzi puls novih oglasa, poruka i prijava.</p>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin">Dashboard</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">Moderacija</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">Ocene</router-link>
      <router-link to="/admin/transactions" class="opacity-80 hover:opacity-100">Transakcije</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">KYC</router-link>
    </div>

    <div class="rounded-2xl border border-line bg-white p-4 shadow-soft">
      <p class="text-xs text-muted">User security lookup</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <input
          v-model="userLookupId"
          type="text"
          class="flex-1 rounded-xl border border-line px-3 py-2 text-sm"
          placeholder="Enter user ID"
        />
        <Button size="sm" @click="goToUser">Open</Button>
      </div>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-6">
      <section>
        <h2 class="mb-2 text-lg font-semibold">Osnovni brojevi</h2>
        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Listings</p>
            <p class="text-2xl font-semibold">{{ summary?.listings.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">Poslednjih 7d: {{ summary?.listings.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Aplikacije</p>
            <p class="text-2xl font-semibold">{{ summary?.applications.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">Poslednjih 7d: {{ summary?.applications.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Poruke</p>
            <p class="text-2xl font-semibold">{{ summary?.messages.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">Poslednjih 7d: {{ summary?.messages.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Prijave</p>
            <p class="text-2xl font-semibold">{{ summary?.reports.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">7d: {{ summary?.reports.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Ocene</p>
            <p class="text-2xl font-semibold">{{ summary?.ratings.last24h ?? '–' }}</p>
            <p class="text-xs text-muted">7d: {{ summary?.ratings.last7d ?? '–' }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-soft">
            <p class="text-xs uppercase text-muted">Sumnjivi korisnici</p>
            <p class="text-2xl font-semibold">{{ summary?.suspiciousUsers ?? '–' }}</p>
            <p class="text-xs text-muted">Označeni za nadzor</p>
          </div>
        </div>
      </section>

      <section v-if="conversion" class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Konverzije</h2>
          <p class="text-xs text-muted">Brojke su aproksimacija toka korisnika</p>
        </div>
        <div class="space-y-3 rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div class="space-y-1">
            <div class="flex justify-between text-sm font-medium">
              <span>Pregled → Aplikacija</span>
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
              <span>Aplikacija → Chat</span>
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
              <span>Chat → Ocena</span>
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
            <h2 class="text-lg font-semibold">Trendovi</h2>
            <p class="text-xs text-muted">Aktivnost po danima</p>
          </div>
          <div class="flex gap-2">
            <Button size="sm" :variant="range === '7d' ? 'primary' : 'secondary'" @click="range = '7d'">7 dana</Button>
            <Button size="sm" :variant="range === '30d' ? 'primary' : 'secondary'" @click="range = '30d'">
              30 dana
            </Button>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white p-4 shadow-soft">
          <div v-if="!trends.length" class="py-6 text-center text-sm text-muted">Nema dostupnih podataka.</div>
          <div v-else class="space-y-3">
            <div class="grid grid-cols-5 gap-2 text-center text-[11px] text-muted">
              <span>Listings</span>
              <span>Aplikacije</span>
              <span>Poruke</span>
              <span>Ocene</span>
              <span>Prijave</span>
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
