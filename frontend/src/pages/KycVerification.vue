<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FileText, Home, IdCard, ShieldCheck, ShieldX, UserCircle } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { getMyKycSubmission, submitKycSubmission, withdrawKycSubmission } from '../services'
import { useToastStore } from '../stores/toast'
import type { KycSubmission } from '../types'

const toast = useToastStore()

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
      return 'Under review'
    case 'approved':
      return 'Verified'
    case 'rejected':
      return 'Rejected'
    case 'withdrawn':
      return 'Withdrawn'
    default:
      return 'Not submitted'
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
    error.value = (err as Error).message || 'Failed to load verification status.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

const goNext = () => {
  if (!canNext.value) {
    toast.push({ title: 'Missing file', message: 'Please upload the required file to continue.', type: 'error' })
    return
  }
  step.value = Math.min(4, step.value + 1)
}

const goBack = () => {
  step.value = Math.max(1, step.value - 1)
}

const submit = async () => {
  if (!idFront.value || !selfie.value || !proof.value) {
    toast.push({ title: 'Incomplete submission', message: 'Upload all required documents.', type: 'error' })
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
    toast.push({ title: 'Submitted', message: 'Your verification is now under review.', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Submission failed', message: (err as Error).message, type: 'error' })
  } finally {
    submitting.value = false
  }
}

const withdraw = async () => {
  if (!submission.value) return
  withdrawing.value = true
  try {
    submission.value = await withdrawKycSubmission(submission.value.id)
    toast.push({ title: 'Withdrawn', message: 'Your submission has been withdrawn.', type: 'info' })
  } catch (err) {
    toast.push({ title: 'Failed', message: (err as Error).message, type: 'error' })
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
            <h1 class="text-xl font-semibold text-slate-900">Landlord verification</h1>
            <p class="text-sm text-muted">Upload ID, selfie, and proof of address for review.</p>
          </div>
          <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
        </div>
        <div v-if="submission" class="mt-3 text-sm text-muted">
          <p>Submitted: {{ formatDate(submission.submittedAt) || '—' }}</p>
          <p v-if="submission.reviewedAt">Reviewed: {{ formatDate(submission.reviewedAt) }}</p>
        </div>
      </div>

      <div v-if="status === 'approved'" class="rounded-2xl bg-emerald-50 p-4 text-emerald-800 border border-emerald-100">
        <div class="flex items-center gap-2 font-semibold">
          <ShieldCheck class="h-5 w-5" />
          Verified landlord
        </div>
        <p class="text-sm mt-2">Verified on {{ formatDate(submission?.reviewedAt) || formatDate(submission?.submittedAt) }}</p>
      </div>

      <div v-else-if="status === 'pending'" class="rounded-2xl bg-amber-50 p-4 text-amber-800 border border-amber-100">
        <p class="font-semibold">Under review</p>
        <p class="text-sm mt-1">Our team is reviewing your documents. We'll notify you when it's done.</p>
        <Button class="mt-3" variant="secondary" :disabled="withdrawing" @click="withdraw">
          {{ withdrawing ? 'Withdrawing...' : 'Withdraw submission' }}
        </Button>
      </div>

      <div v-else-if="status === 'rejected'" class="rounded-2xl bg-rose-50 p-4 text-rose-800 border border-rose-100">
        <div class="flex items-center gap-2 font-semibold">
          <ShieldX class="h-5 w-5" />
          Verification rejected
        </div>
        <p v-if="submission?.reviewerNote" class="text-sm mt-2">Reason: {{ submission.reviewerNote }}</p>
        <Button class="mt-3" variant="primary" @click="resubmit">Resubmit</Button>
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
            Upload ID
          </div>
          <label class="text-sm text-muted">Front (required)</label>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (idFront = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="idFront" class="text-xs text-muted">Selected: {{ idFront.name }}</p>

          <label class="text-sm text-muted">Back (optional)</label>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (idBack = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="idBack" class="text-xs text-muted">Selected: {{ idBack.name }}</p>
        </div>

        <div v-else-if="step === 2" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <UserCircle class="h-5 w-5 text-primary" />
            Upload selfie
          </div>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            @change="(e) => (selfie = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="selfie" class="text-xs text-muted">Selected: {{ selfie.name }}</p>
        </div>

        <div v-else-if="step === 3" class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Home class="h-5 w-5 text-primary" />
            Proof of address
          </div>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            @change="(e) => (proof = (e.target as HTMLInputElement).files?.[0] ?? null)"
            class="w-full rounded-xl border border-line px-3 py-2 text-sm"
          />
          <p v-if="proof" class="text-xs text-muted">Selected: {{ proof.name }}</p>
        </div>

        <div v-else class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <FileText class="h-5 w-5 text-primary" />
            Review & submit
          </div>
          <ul class="space-y-2 text-sm text-muted">
            <li>ID front: {{ idFront?.name || 'Missing' }}</li>
            <li>ID back: {{ idBack?.name || 'Not provided' }}</li>
            <li>Selfie: {{ selfie?.name || 'Missing' }}</li>
            <li>Proof of address: {{ proof?.name || 'Missing' }}</li>
          </ul>
          <Button block :disabled="submitting" variant="primary" @click="submit">
            {{ submitting ? 'Submitting...' : 'Submit for review' }}
          </Button>
        </div>

        <div class="flex items-center justify-between">
          <Button variant="secondary" :disabled="step === 1" @click="goBack">Back</Button>
          <Button v-if="step < 4" variant="primary" :disabled="!canNext" @click="goNext">Next</Button>
        </div>
      </div>
    </div>
  </div>
</template>
