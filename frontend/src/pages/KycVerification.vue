<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FileText, Home, IdCard, ShieldCheck, ShieldX, UserCircle } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { getMyKycSubmission, submitKycSubmission, withdrawKycSubmission } from '../services'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import type { KycSubmission } from '../types'

const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(true)
const error = ref('')
const submission = ref<KycSubmission | null>(null)
const step = ref(1)
const submitting = ref(false)
const withdrawing = ref(false)

const idFront = ref<File | null>(null)
const idBack = ref<File | null>(null)
const selfie = ref<File | null>(null)
const proof = ref<File | null>(null)

const status = computed(() => submission.value?.status ?? 'none')

const statusLabel = computed(() => {
  switch (status.value) {
    case 'pending':
      return t('kyc.status.pending')
    case 'approved':
      return t('kyc.status.approved')
    case 'rejected':
      return t('kyc.status.rejected')
    case 'withdrawn':
      return t('kyc.status.withdrawn')
    default:
      return t('kyc.status.none')
  }
})

const statusVariant = computed(() => {
  switch (status.value) {
    case 'approved':
      return 'accepted'
    case 'rejected':
      return 'rejected'
    case 'pending':
      return 'pending'
    default:
      return 'info'
  }
})

const formatDate = (value?: string | null) => (value ? new Date(value).toLocaleDateString() : '')

const canNext = computed(() => {
  if (step.value === 1) return !!idFront.value
  if (step.value === 2) return !!selfie.value
  if (step.value === 3) return !!proof.value
  return true
})

const resetForm = () => {
  idFront.value = null
  idBack.value = null
  selfie.value = null
  proof.value = null
  step.value = 1
}

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    submission.value = await getMyKycSubmission()
  } catch (err) {
    error.value = (err as Error).message || t('kyc.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const goNext = () => {
  if (!canNext.value) {
    toast.push({ title: t('kyc.missingFileTitle'), message: t('kyc.missingFileMessage'), type: 'error' })
    return
  }
  step.value = Math.min(4, step.value + 1)
}

const goBack = () => {
  step.value = Math.max(1, step.value - 1)
}

const submit = async () => {
  if (!idFront.value || !selfie.value || !proof.value) {
    toast.push({ title: t('kyc.incompleteTitle'), message: t('kyc.incompleteMessage'), type: 'error' })
    return
  }
  submitting.value = true
  try {
    const form = new FormData()
    form.append('id_front', idFront.value)
    if (idBack.value) form.append('id_back', idBack.value)
    form.append('selfie', selfie.value)
    form.append('proof_of_address', proof.value)
    submission.value = await submitKycSubmission(form)
    resetForm()
    toast.push({ title: t('kyc.submittedTitle'), message: t('kyc.submittedMessage'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('kyc.submitFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    submitting.value = false
  }
}

const withdraw = async () => {
  if (!submission.value) return
  withdrawing.value = true
  try {
    submission.value = await withdrawKycSubmission(submission.value.id)
    toast.push({ title: t('kyc.withdrawnTitle'), message: t('kyc.withdrawnMessage'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('common.failed'), message: (err as Error).message, type: 'error' })
  } finally {
    withdrawing.value = false
  }
}

const resubmit = () => {
  submission.value = null
  resetForm()
}
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />

    <div v-else class="space-y-4">
      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">{{ t('kyc.title') }}</h1>
            <p class="text-sm text-muted">{{ t('kyc.subtitle') }}</p>
          </div>
          <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
        </div>
        <div v-if="submission" class="mt-3 text-sm text-muted">
          <p>{{ t('kyc.submittedAt') }}: {{ formatDate(submission.submittedAt) || '—' }}</p>
          <p v-if="submission.reviewedAt">{{ t('kyc.reviewedAt') }}: {{ formatDate(submission.reviewedAt) }}</p>
        </div>
      </div>

      <div v-if="status === 'approved'" class="rounded-2xl bg-emerald-50 p-4 text-emerald-800 border border-emerald-100">
        <div class="flex items-center gap-2 font-semibold">
          <ShieldCheck class="h-5 w-5" />
          {{ t('kyc.verifiedTitle') }}
        </div>
        <p class="text-sm mt-2">
          {{ t('kyc.verifiedOn') }} {{ formatDate(submission?.reviewedAt) || formatDate(submission?.submittedAt) }}
        </p>
      </div>

      <div v-else-if="status === 'pending'" class="rounded-2xl bg-amber-50 p-4 text-amber-800 border border-amber-100">
        <p class="font-semibold">{{ t('kyc.pendingTitle') }}</p>
        <p class="text-sm mt-1">{{ t('kyc.pendingMessage') }}</p>
        <Button class="mt-3" variant="secondary" :disabled="withdrawing" @click="withdraw">
          {{ withdrawing ? t('kyc.withdrawing') : t('kyc.withdraw') }}
        </Button>
      </div>

      <div v-else-if="status === 'rejected'" class="rounded-2xl bg-rose-50 p-4 text-rose-800 border border-rose-100">
        <div class="flex items-center gap-2 font-semibold">
          <ShieldX class="h-5 w-5" />
          {{ t('kyc.rejectedTitle') }}
        </div>
        <p v-if="submission?.reviewerNote" class="text-sm mt-2">{{ t('kyc.reason') }}: {{ submission.reviewerNote }}</p>
        <Button class="mt-3" variant="primary" @click="resubmit">{{ t('kyc.resubmit') }}</Button>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-4 gap-2">
          <div
            v-for="n in 4"
            :key="n"
            class="h-2 rounded-full"
            :class="n <= step ? 'bg-primary' : 'bg-slate-200'"
          />
        </div>

        <div v-if="step === 1" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <IdCard class="h-5 w-5 text-primary" />
            {{ t('kyc.step.id.title') }}
          </div>
          <label class="text-sm text-muted">{{ t('kyc.step.id.frontRequired') }}</label>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (idFront = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="idFront" class="text-xs text-muted">{{ t('kyc.selected') }}: {{ idFront.name }}</p>

          <label class="text-sm text-muted">{{ t('kyc.step.id.backOptional') }}</label>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (idBack = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="idBack" class="text-xs text-muted">{{ t('kyc.selected') }}: {{ idBack.name }}</p>
        </div>

        <div v-else-if="step === 2" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <UserCircle class="h-5 w-5 text-primary" />
            {{ t('kyc.step.selfie.title') }}
          </div>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            @change="(e) => (selfie = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="selfie" class="text-xs text-muted">{{ t('kyc.selected') }}: {{ selfie.name }}</p>
        </div>

        <div v-else-if="step === 3" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Home class="h-5 w-5 text-primary" />
            {{ t('kyc.step.address.title') }}
          </div>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (proof = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="proof" class="text-xs text-muted">{{ t('kyc.selected') }}: {{ proof.name }}</p>
        </div>

        <div v-else class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <FileText class="h-5 w-5 text-primary" />
            {{ t('kyc.step.review.title') }}
          </div>
          <ul class="space-y-2 text-sm text-muted">
            <li>{{ t('kyc.step.review.idFront') }}: {{ idFront?.name || t('common.missing') }}</li>
            <li>{{ t('kyc.step.review.idBack') }}: {{ idBack?.name || t('common.notProvided') }}</li>
            <li>{{ t('kyc.step.review.selfie') }}: {{ selfie?.name || t('common.missing') }}</li>
            <li>{{ t('kyc.step.review.address') }}: {{ proof?.name || t('common.missing') }}</li>
          </ul>
          <Button block :disabled="submitting" variant="primary" @click="submit">
            {{ submitting ? t('common.submitting') : t('kyc.submitForReview') }}
          </Button>
        </div>

        <div class="flex items-center justify-between">
          <Button variant="secondary" :disabled="step === 1" @click="goBack">{{ t('common.back') }}</Button>
          <Button v-if="step < 4" variant="primary" :disabled="!canNext" @click="goNext">{{ t('common.next') }}</Button>
        </div>
      </div>
    </div>
  </div>
</template>
