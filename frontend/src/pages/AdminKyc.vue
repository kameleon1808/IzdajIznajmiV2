<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FileText, ShieldCheck, ShieldX, Trash2 } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import {
  approveAdminKycSubmission,
  getAdminKycSubmission,
  getAdminKycSubmissions,
  redactAdminKycSubmission,
  rejectAdminKycSubmission,
} from '../services'
import type { KycDocument, KycSubmission } from '../types'

const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(true)
const error = ref('')
const submissions = ref<KycSubmission[]>([])
const filter = ref<'pending' | 'approved' | 'rejected' | 'withdrawn' | 'all'>('pending')

const selected = ref<KycSubmission | null>(null)
const detailOpen = ref(false)
const detailLoading = ref(false)
const reviewNote = ref('')
const actionLoading = ref(false)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    submissions.value = await getAdminKycSubmissions(filter.value === 'all' ? undefined : { status: filter.value })
  } catch (err) {
    error.value = (err as Error).message || t('admin.kyc.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const openDetail = async (submission: KycSubmission) => {
  detailOpen.value = true
  detailLoading.value = true
  reviewNote.value = submission.reviewerNote ?? ''
  try {
    selected.value = await getAdminKycSubmission(submission.id)
  } catch (err) {
    toast.push({ title: t('common.loadFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    detailLoading.value = false
  }
}

const statusVariant = (status: string) => {
  if (status === 'approved') return 'accepted'
  if (status === 'rejected') return 'rejected'
  if (status === 'pending') return 'pending'
  return 'info'
}

const statusLabel = (status: string) => {
  switch (status) {
    case 'approved':
      return t('admin.kyc.status.approved')
    case 'rejected':
      return t('admin.kyc.status.rejected')
    case 'pending':
      return t('admin.kyc.status.pending')
    case 'withdrawn':
      return t('admin.kyc.status.withdrawn')
    default:
      return status
  }
}

const docLabel = (doc: KycDocument) => {
  switch (doc.docType) {
    case 'id_front':
      return t('admin.kyc.docs.idFront')
    case 'id_back':
      return t('admin.kyc.docs.idBack')
    case 'selfie':
      return t('admin.kyc.docs.selfie')
    case 'proof_of_address':
      return t('admin.kyc.docs.proof')
    default:
      return doc.docType
  }
}

const isImage = (doc: KycDocument) => doc.mimeType.startsWith('image/')

const updateListItem = (updated: KycSubmission) => {
  const idx = submissions.value.findIndex((item) => item.id === updated.id)
  if (idx >= 0) {
    if (filter.value !== 'all' && updated.status !== filter.value) {
      submissions.value.splice(idx, 1)
    } else {
      submissions.value[idx] = updated
    }
  }
  selected.value = updated
}

const approve = async () => {
  if (!selected.value) return
  actionLoading.value = true
  try {
    const updated = await approveAdminKycSubmission(selected.value.id, reviewNote.value || undefined)
    updateListItem(updated)
    toast.push({ title: t('admin.kyc.approvedToast'), type: 'success' })
    detailOpen.value = false
  } catch (err) {
    toast.push({ title: t('common.failed'), message: (err as Error).message, type: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const reject = async () => {
  if (!selected.value) return
  actionLoading.value = true
  try {
    const updated = await rejectAdminKycSubmission(selected.value.id, reviewNote.value || undefined)
    updateListItem(updated)
    toast.push({ title: t('admin.kyc.rejectedToast'), type: 'info' })
    detailOpen.value = false
  } catch (err) {
    toast.push({ title: t('common.failed'), message: (err as Error).message, type: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const redact = async () => {
  if (!selected.value) return
  actionLoading.value = true
  try {
    const updated = await redactAdminKycSubmission(selected.value.id, reviewNote.value || undefined)
    updateListItem(updated)
    toast.push({ title: t('admin.kyc.redactedToast'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('common.failed'), message: (err as Error).message, type: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const submittedAt = computed(() => (selected.value?.submittedAt ? new Date(selected.value.submittedAt).toLocaleString() : '—'))
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">{{ t('admin.nav.moderation') }}</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">{{ t('admin.nav.ratings') }}</router-link>
      <router-link to="/admin/kyc">{{ t('admin.nav.kyc') }}</router-link>
    </div>

    <div class="flex flex-wrap gap-2">
      <Button size="sm" :variant="filter === 'pending' ? 'primary' : 'secondary'" @click="filter = 'pending'; load()">{{ t('admin.kyc.status.pending') }}</Button>
      <Button size="sm" :variant="filter === 'approved' ? 'primary' : 'secondary'" @click="filter = 'approved'; load()">{{ t('admin.kyc.status.approved') }}</Button>
      <Button size="sm" :variant="filter === 'rejected' ? 'primary' : 'secondary'" @click="filter = 'rejected'; load()">{{ t('admin.kyc.status.rejected') }}</Button>
      <Button size="sm" :variant="filter === 'withdrawn' ? 'primary' : 'secondary'" @click="filter = 'withdrawn'; load()">{{ t('admin.kyc.status.withdrawn') }}</Button>
      <Button size="sm" :variant="filter === 'all' ? 'primary' : 'secondary'" @click="filter = 'all'; load()">{{ t('admin.kyc.all') }}</Button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="submission in submissions"
        :key="submission.id"
        class="rounded-2xl border border-line bg-white p-4 shadow-soft space-y-2"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-900">{{ submission.user?.fullName || t('admin.kyc.landlord') }}</p>
            <p class="text-xs text-muted">{{ submission.user?.email || '—' }}</p>
          </div>
          <Badge :variant="statusVariant(submission.status)">{{ statusLabel(submission.status) }}</Badge>
        </div>
        <p class="text-xs text-muted">
          {{ t('admin.kyc.submittedAt') }}
          {{ submission.submittedAt ? new Date(submission.submittedAt).toLocaleString() : '—' }}
        </p>
        <div class="flex items-center gap-2">
          <Button size="sm" variant="secondary" @click="openDetail(submission)">{{ t('common.view') }}</Button>
        </div>
      </div>

      <EmptyState v-if="!submissions.length" :title="t('admin.kyc.emptyTitle')" :subtitle="t('admin.kyc.emptySubtitle')" />
    </div>
  </div>

  <ModalSheet v-model="detailOpen" :title="t('admin.kyc.detailTitle')">
    <div v-if="detailLoading" class="py-6 text-center text-sm text-muted">{{ t('common.loading') }}</div>
    <div v-else-if="selected" class="space-y-4">
      <div class="rounded-2xl bg-slate-50 p-3">
        <p class="text-sm font-semibold text-slate-900">{{ selected.user?.fullName || t('admin.kyc.landlord') }}</p>
        <p class="text-xs text-muted">{{ selected.user?.email }}</p>
        <p class="text-xs text-muted">{{ t('admin.kyc.submittedAtLabel') }}: {{ submittedAt }}</p>
        <Badge :variant="statusVariant(selected.status)" class="mt-2 inline-flex">{{ statusLabel(selected.status) }}</Badge>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-semibold text-slate-900">{{ t('admin.kyc.documents') }}</p>
        <div v-if="!selected.documents?.length" class="text-sm text-muted">{{ t('admin.kyc.noDocuments') }}</div>
        <div v-else class="space-y-3">
          <div v-for="doc in selected.documents" :key="doc.id" class="rounded-2xl border border-line p-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <FileText class="h-4 w-4 text-slate-500" />
                <div>
                  <p class="text-sm font-semibold text-slate-900">{{ docLabel(doc) }}</p>
                  <p class="text-xs text-muted">{{ doc.originalName }}</p>
                </div>
              </div>
              <a v-if="doc.downloadUrl" :href="doc.downloadUrl" target="_blank" rel="noopener" class="text-xs font-semibold text-primary">
                {{ t('common.open') }}
              </a>
            </div>
            <img
              v-if="doc.downloadUrl && isImage(doc)"
              :src="doc.downloadUrl"
              :alt="doc.originalName"
              class="mt-2 h-40 w-full rounded-xl object-cover"
            />
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <label class="text-sm font-semibold text-slate-900">{{ t('admin.kyc.reviewerNote') }}</label>
        <textarea
          v-model="reviewNote"
          rows="3"
          class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
          :placeholder="t('admin.kyc.reviewerNotePlaceholder')"
        ></textarea>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          variant="primary"
          size="sm"
          :disabled="actionLoading || selected.status !== 'pending'"
          @click="approve"
        >
          <ShieldCheck class="mr-1 h-4 w-4" />
          {{ t('admin.kyc.approve') }}
        </Button>
        <Button
          variant="secondary"
          size="sm"
          :disabled="actionLoading || selected.status !== 'pending'"
          @click="reject"
        >
          <ShieldX class="mr-1 h-4 w-4" />
          {{ t('admin.kyc.reject') }}
        </Button>
        <Button variant="danger" size="sm" :disabled="actionLoading" @click="redact">
          <Trash2 class="mr-1 h-4 w-4" />
          {{ t('admin.kyc.redact') }}
        </Button>
      </div>
    </div>
  </ModalSheet>
</template>
