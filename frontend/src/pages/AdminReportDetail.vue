<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertTriangle } from 'lucide-vue-next'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { getAdminReport, updateAdminReport } from '../services'
import type { Report } from '../types'
import { useToastStore } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const report = ref<Report | null>(null)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    report.value = await getAdminReport(route.params.id as string)
  } catch (err) {
    error.value = (err as Error).message || 'Report not found.'
  } finally {
    loading.value = false
  }
}

const statusBadge = computed(() => {
  if (!report.value) return ''
  if (report.value.status === 'resolved') return 'bg-emerald-100 text-emerald-700'
  if (report.value.status === 'dismissed') return 'bg-slate-100 text-slate-700'
  return 'bg-amber-100 text-amber-700'
})

const update = async (action: 'dismiss' | 'resolve') => {
  if (!report.value) return
  saving.value = true
  error.value = ''
  try {
    report.value = await updateAdminReport(report.value.id, {
      action,
      resolution: action === 'resolve' ? 'Resolved via deep link' : 'Dismissed',
      deleteTarget: action === 'resolve' && report.value.type === 'rating',
    })
    toast.push({ title: 'Updated', message: 'Report status saved.', type: 'success' })
  } catch (err) {
    error.value = (err as Error).message || 'Could not update report.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4 bg-surface px-4 py-4">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />
    <template v-else>
      <div v-if="report" class="space-y-3 rounded-2xl border border-white/60 bg-white p-4 shadow-soft">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs uppercase text-muted">Report #{{ report.id }}</p>
            <h1 class="text-lg font-semibold text-slate-900">{{ report.reason }}</h1>
            <p class="text-sm text-muted">Type: {{ report.type }} · Created: {{ report.createdAt }}</p>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadge">
            {{ report.status }}
          </span>
        </div>
        <p class="text-sm text-slate-800">{{ report.details || 'No additional details.' }}</p>
        <div class="rounded-xl bg-surface p-3 text-sm text-muted">
          <p class="mb-1 font-semibold text-slate-900">Target</p>
          <pre class="overflow-auto text-xs text-slate-700">{{ JSON.stringify(report.target, null, 2) }}</pre>
        </div>
        <div class="flex gap-2">
          <Button size="sm" variant="secondary" :disabled="saving" @click="update('dismiss')">Dismiss</Button>
          <Button size="sm" variant="primary" :disabled="saving" @click="update('resolve')">Resolve</Button>
          <Button size="sm" variant="ghost" @click="router.push('/admin/moderation')">Back</Button>
        </div>
      </div>
      <EmptyState
        v-else
        title="Report missing"
        subtitle="The report could not be found or you lack access."
        :icon="AlertTriangle"
      >
        <template #actions>
          <Button variant="secondary" @click="router.push('/admin/moderation')">Back to moderation</Button>
        </template>
      </EmptyState>
    </template>
  </div>
</template>
