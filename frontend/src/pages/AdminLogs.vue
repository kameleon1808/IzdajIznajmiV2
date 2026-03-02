<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ScrollText, RefreshCw, ShieldAlert } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useLanguageStore } from '../stores/language'
import { getAdminLogs } from '../services'
import type { StructuredLogEntry } from '../services/realApi'

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(false)
const error = ref('')
const entries = ref<StructuredLogEntry[]>([])

// Filter state
const filterDate = ref(new Date().toISOString().slice(0, 10))
const filterAction = ref('')
const filterLevel = ref('')
const filterSecurityOnly = ref(false)
const filterUserId = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params: Record<string, unknown> = { date: filterDate.value, limit: 200 }
    if (filterAction.value) params.action = filterAction.value
    if (filterLevel.value) params.level = filterLevel.value
    if (filterSecurityOnly.value) params.security_event = true
    if (filterUserId.value) params.user_id = filterUserId.value
    entries.value = await getAdminLogs(params)
    currentPage.value = 1
    expandedIndex.value = null
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load logs.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function levelClass(levelName: string) {
  switch (levelName?.toUpperCase()) {
    case 'CRITICAL':
    case 'ALERT':
    case 'EMERGENCY':
      return 'bg-red-100 text-red-700'
    case 'ERROR':
      return 'bg-orange-100 text-orange-700'
    case 'WARNING':
      return 'bg-yellow-100 text-yellow-700'
    case 'INFO':
      return 'bg-blue-100 text-blue-700'
    case 'DEBUG':
      return 'bg-slate-100 text-slate-600'
    default:
      return 'bg-slate-100 text-slate-600'
  }
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString()
}

function actionOrMessage(entry: StructuredLogEntry) {
  return (entry.context?.action as string) || entry.message
}

function contextDetails(entry: StructuredLogEntry) {
  const ctx = { ...entry.context }
  delete ctx.action
  return ctx
}

const PAGE_SIZE = 50
const currentPage = ref(1)

const totalPages = computed(() => Math.max(1, Math.ceil(entries.value.length / PAGE_SIZE)))

const pagedEntries = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE
  return entries.value.slice(start, start + PAGE_SIZE)
})

function goToPage(page: number) {
  currentPage.value = Math.max(1, Math.min(page, totalPages.value))
  expandedIndex.value = null
}

const expandedIndex = ref<number | null>(null)
function toggleExpand(i: number) {
  expandedIndex.value = expandedIndex.value === i ? null : i
}
</script>

<template>
  <div class="space-y-4">
    <!-- Breadcrumb nav -->
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/logs">{{ t('admin.nav.logs') }}</router-link>
    </div>

    <div class="flex items-center gap-2">
      <ScrollText class="h-5 w-5 text-primary" />
      <h1 class="text-lg font-semibold text-slate-900">{{ t('admin.logs.title') }}</h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 rounded-2xl border border-line bg-white p-4 shadow-soft">
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-muted">{{ t('admin.logs.date') }}</label>
        <input
          v-model="filterDate"
          type="date"
          class="rounded-lg border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-muted">{{ t('admin.logs.action') }}</label>
        <input
          v-model="filterAction"
          type="text"
          placeholder="e.g. fraud.signal"
          class="rounded-lg border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-muted">{{ t('admin.logs.level') }}</label>
        <select
          v-model="filterLevel"
          class="rounded-lg border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="">All</option>
          <option value="debug">DEBUG</option>
          <option value="info">INFO</option>
          <option value="warning">WARNING</option>
          <option value="error">ERROR</option>
          <option value="critical">CRITICAL</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-muted">{{ t('admin.logs.userId') }}</label>
        <input
          v-model="filterUserId"
          type="text"
          placeholder="User ID"
          class="w-24 rounded-lg border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>
      <div class="flex items-end gap-2">
        <label class="flex cursor-pointer items-center gap-1.5 text-sm text-slate-700">
          <input v-model="filterSecurityOnly" type="checkbox" class="rounded" />
          <ShieldAlert class="h-4 w-4 text-indigo-500" />
          {{ t('admin.logs.securityOnly') }}
        </label>
      </div>
      <div class="flex items-end">
        <button
          @click="load"
          :disabled="loading"
          class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary/90 disabled:opacity-50"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          {{ t('admin.logs.refresh') }}
        </button>
      </div>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="8" />

    <div v-else-if="!entries.length">
      <EmptyState :title="t('admin.logs.empty')" subtitle="" />
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-line bg-white shadow-soft">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-line bg-slate-50 text-left text-xs font-semibold text-muted uppercase tracking-wide">
            <th class="px-4 py-3">{{ t('admin.logs.time') }}</th>
            <th class="px-4 py-3">{{ t('admin.logs.levelCol') }}</th>
            <th class="px-4 py-3">{{ t('admin.logs.actionCol') }}</th>
            <th class="px-4 py-3">{{ t('admin.logs.userCol') }}</th>
            <th class="px-4 py-3">{{ t('admin.logs.details') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(entry, i) in pagedEntries" :key="i">
            <tr
              class="border-b border-line last:border-0 hover:bg-slate-50 cursor-pointer"
              @click="toggleExpand(i)"
            >
              <td class="px-4 py-3 text-xs text-muted whitespace-nowrap">{{ formatDate(entry.datetime) }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="levelClass(entry.level_name)"
                >{{ entry.level_name }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5">
                  <ShieldAlert
                    v-if="entry.context?.security_event"
                    class="h-3.5 w-3.5 shrink-0 text-indigo-500"
                  />
                  <span class="font-mono text-xs text-slate-800">{{ actionOrMessage(entry) }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-muted">
                {{ entry.context?.user_id ?? '—' }}
              </td>
              <td class="px-4 py-3 text-xs text-indigo-500 hover:underline">
                {{ expandedIndex === i ? '▲ hide' : '▼ show' }}
              </td>
            </tr>
            <tr v-if="expandedIndex === i" class="bg-slate-50">
              <td colspan="5" class="px-4 py-3">
                <pre class="overflow-x-auto rounded-lg bg-slate-100 p-3 text-xs text-slate-700">{{ JSON.stringify(contextDetails(entry), null, 2) }}</pre>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Paginator -->
    <div v-if="totalPages > 1" class="flex items-center justify-between rounded-2xl border border-line bg-white px-4 py-3 shadow-soft text-sm">
      <span class="text-muted">
        {{ (currentPage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(currentPage * PAGE_SIZE, entries.length) }}
        / {{ entries.length }}
      </span>
      <div class="flex items-center gap-1">
        <button
          @click="goToPage(1)"
          :disabled="currentPage === 1"
          class="rounded-lg px-2 py-1 text-muted hover:bg-slate-100 disabled:opacity-30"
        >«</button>
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="rounded-lg px-2 py-1 text-muted hover:bg-slate-100 disabled:opacity-30"
        >‹</button>
        <span class="px-3 font-semibold text-slate-700">{{ currentPage }} / {{ totalPages }}</span>
        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="rounded-lg px-2 py-1 text-muted hover:bg-slate-100 disabled:opacity-30"
        >›</button>
        <button
          @click="goToPage(totalPages)"
          :disabled="currentPage === totalPages"
          class="rounded-lg px-2 py-1 text-muted hover:bg-slate-100 disabled:opacity-30"
        >»</button>
      </div>
    </div>
  </div>
</template>
