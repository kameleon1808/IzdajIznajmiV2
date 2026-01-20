<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import type { Report } from '../types'
import { flagUserSuspicious, getAdminReports, updateAdminReport } from '../services'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'

const loading = ref(true)
const actionLoading = ref(false)
const error = ref('')
const reports = ref<Report[]>([])
const selected = ref<Report | null>(null)
const filters = ref<{ type: 'all' | 'rating' | 'message' | 'listing'; status: 'open' | 'resolved' | 'dismissed'; q: string }>({
  type: 'all',
  status: 'open',
  q: '',
})

const toast = useToastStore()
const auth = useAuthStore()

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const params: any = {}
    if (filters.value.type !== 'all') params.type = filters.value.type
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.q) params.q = filters.value.q
    reports.value = await getAdminReports(params)
  } catch (err) {
    error.value = (err as any)?.message || 'Neuspešno učitavanje prijava.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

const setSelected = (report: Report) => {
  selected.value = report
}

const updateReport = async (report: Report, action: 'dismiss' | 'resolve', deleteTarget = false) => {
  actionLoading.value = true
  try {
    const updated = await updateAdminReport(report.id, {
      action,
      deleteTarget,
      resolution: action === 'resolve' ? 'Content addressed' : 'No action',
    })
    reports.value = reports.value.map((r) => (r.id === updated.id ? updated : r))
    if (selected.value?.id === updated.id) selected.value = updated
    toast.push({ title: 'Ažurirano', message: 'Status prijave je sačuvan.', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Greška', message: (err as any)?.message || 'Neuspešno ažuriranje prijave.', type: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const formatDate = (value?: string) => (value ? new Date(value).toLocaleDateString() : '–')

const impersonateReporter = async () => {
  if (!selected.value?.reporter?.id) return
  try {
    await auth.startImpersonation(selected.value.reporter.id)
    toast.push({ title: 'Impersonacija aktivna', message: 'Prebačeni ste u nalog korisnika.', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Greška', message: (err as any)?.message || 'Neuspešna impersonacija.', type: 'error' })
  }
}
const flagReporter = async () => {
  if (!selected.value?.reporter?.id) return
  try {
    await flagUserSuspicious(selected.value.reporter.id, true)
    toast.push({ title: 'Označeno', message: 'Korisnik je označen kao sumnjiv.', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Greška', message: (err as any)?.message || 'Nije uspelo označavanje.', type: 'error' })
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-3xl bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-5 text-white shadow-lg">
      <p class="text-sm opacity-85">Moderacija</p>
      <h1 class="text-xl font-semibold leading-tight">Prijave i eskalacije</h1>
      <p class="text-sm opacity-80">Pregledaj i zatvori prijave uz brzo označavanje sadržaja.</p>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">Dashboard</router-link>
      <router-link to="/admin/moderation">Moderacija</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">Ocene</router-link>
    </div>

    <div class="flex flex-wrap gap-2">
      <Button
        v-for="type in ['all', 'rating', 'message', 'listing']"
        :key="type"
        size="sm"
        :variant="filters.type === type ? 'primary' : 'secondary'"
        @click="filters.type = type as any; load()"
      >
        {{ type === 'all' ? 'Sve' : type }}
      </Button>
      <div class="ml-auto flex gap-2">
        <Button
          v-for="status in ['open', 'resolved', 'dismissed']"
          :key="status"
          size="sm"
          :variant="filters.status === status ? 'primary' : 'ghost'"
          @click="filters.status = status as any; load()"
        >
          {{ status }}
        </Button>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <input
        v-model="filters.q"
        type="search"
        placeholder="Pretraži razlog/detalje"
        class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm shadow-inner focus:outline-none focus:ring-2 focus:ring-indigo-200"
        @keyup.enter="load()"
      />
      <Button size="sm" variant="secondary" @click="load()">Filtriraj</Button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="report in reports"
        :key="report.id"
        class="cursor-pointer rounded-2xl border border-line bg-white p-4 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lg"
        @click="setSelected(report)"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase text-muted">#{{ report.id }}</p>
            <p class="text-base font-semibold">{{ report.reason }}</p>
            <p class="text-sm text-muted">Tip: {{ report.type }} · Prijavio: {{ report.reporter?.name || 'N/A' }}</p>
          </div>
          <span
            class="rounded-full px-3 py-1 text-xs font-semibold"
            :class="{
              'bg-emerald-100 text-emerald-700': report.status === 'resolved',
              'bg-amber-100 text-amber-700': report.status === 'open',
              'bg-slate-100 text-slate-700': report.status === 'dismissed',
            }"
          >
            {{ report.status }}
          </span>
        </div>

        <div class="mt-2 text-sm text-slate-700">
          <div v-if="report.type === 'rating'">
            Ocena: {{ report.target?.rating ?? '—' }}★ · {{ report.target?.comment || 'Bez komentara' }}
          </div>
          <div v-else-if="report.type === 'message'">
            Poruka: “{{ report.target?.body || report.target?.text || 'Nedostupno' }}”
          </div>
          <div v-else-if="report.type === 'listing'">
            Oglas: {{ report.target?.title || report.target?.id }} · {{ report.target?.city || '' }}
          </div>
          <div v-else class="text-muted">Detalji nedostupni</div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted">
          <span>Prijavljeno: {{ formatDate(report.createdAt) }}</span>
          <span v-if="report.totalReports">· {{ report.totalReports }} prijave</span>
          <span v-if="report.resolution">· Rešenje: {{ report.resolution }}</span>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" :disabled="actionLoading" @click.stop="updateReport(report, 'dismiss')">
            Odbij
          </Button>
          <Button
            size="sm"
            variant="primary"
            :disabled="actionLoading"
            @click.stop="updateReport(report, 'resolve', report.type === 'rating')"
          >
            Reši (obriši sadržaj)
          </Button>
        </div>
      </div>

      <EmptyState v-if="!reports.length" title="Nema prijava" subtitle="Sve je čisto. Nema otvorenih prijava." />
    </div>

    <div v-if="selected" class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-inner">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-xs uppercase text-indigo-600">Detalj prijave</p>
          <p class="text-lg font-semibold">{{ selected.reason }}</p>
          <p class="text-sm text-indigo-900/70">
            Status: {{ selected.status }} · Kreirano: {{ formatDate(selected.createdAt) }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button
            v-if="selected.reporter?.id"
            size="sm"
            variant="secondary"
            :disabled="actionLoading"
            @click="impersonateReporter"
          >
            Impersoniraj prijavioca
          </Button>
          <Button
            v-if="selected.reporter?.id"
            size="sm"
            variant="secondary"
            :disabled="actionLoading"
            @click="flagReporter"
          >
            Označi sumnjivim
          </Button>
          <Button size="sm" variant="ghost" @click="selected = null">Zatvori</Button>
        </div>
      </div>
      <p class="mt-2 text-sm text-slate-800">{{ selected.details || 'Nema dodatnih detalja.' }}</p>
      <p class="mt-2 text-xs text-slate-600">
        Rezolucija: {{ selected.resolution || 'U toku' }} · Ažurirano: {{ formatDate(selected.reviewedAt) }}
      </p>
    </div>
  </div>
</template>
