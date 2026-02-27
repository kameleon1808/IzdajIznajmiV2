<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ClipboardList } from 'lucide-vue-next'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useLanguageStore } from '../stores/language'
import { getKycAuditLog } from '../services'
import type { KycAuditEntry } from '../types'

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(true)
const error = ref('')
const entries = ref<KycAuditEntry[]>([])

onMounted(async () => {
  try {
    entries.value = await getKycAuditLog()
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load audit log.'
  } finally {
    loading.value = false
  }
})

const formatDate = (iso: string) => new Date(iso).toLocaleString()

const actionLabel = (action: string) =>
  action === 'kyc.document.admin_downloaded'
    ? t('admin.kyc.auditLog.adminAccess')
    : t('admin.kyc.auditLog.ownerAccess')
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">{{ t('admin.nav.kyc') }}</router-link>
      <router-link to="/admin/kyc/audit-log">{{ t('admin.kyc.auditLog') }}</router-link>
    </div>

    <div class="flex items-center gap-2">
      <ClipboardList class="h-5 w-5 text-primary" />
      <h1 class="text-lg font-semibold text-slate-900">{{ t('admin.kyc.auditLog.title') }}</h1>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="5" />

    <div v-else-if="!entries.length">
      <EmptyState :title="t('admin.kyc.auditLog.empty')" subtitle="" />
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-line bg-white shadow-soft">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-line bg-slate-50 text-left text-xs font-semibold text-muted uppercase tracking-wide">
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.actor') }}</th>
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.action') }}</th>
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.document') }}</th>
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.submission') }}</th>
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.ip') }}</th>
            <th class="px-4 py-3">{{ t('admin.kyc.auditLog.time') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in entries"
            :key="entry.id"
            class="border-b border-line last:border-0 hover:bg-slate-50"
          >
            <td class="px-4 py-3">
              <p class="font-semibold text-slate-900">{{ entry.actorName }}</p>
              <p v-if="entry.actorEmail" class="text-xs text-muted">{{ entry.actorEmail }}</p>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="entry.isAdmin ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'"
              >{{ actionLabel(entry.action) }}</span>
            </td>
            <td class="px-4 py-3 text-slate-700">
              <span v-if="entry.docType" class="capitalize">{{ entry.docType.replace(/_/g, ' ') }}</span>
              <span v-else class="text-muted">—</span>
              <p class="text-xs text-muted">#{{ entry.documentId }}</p>
            </td>
            <td class="px-4 py-3 text-slate-700">
              <span v-if="entry.submissionId">#{{ entry.submissionId }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-muted">{{ entry.ipAddress ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-muted whitespace-nowrap">{{ formatDate(entry.createdAt) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
