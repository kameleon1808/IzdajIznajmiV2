<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FileText, Home, IdCard, ShieldCheck, ShieldX, UserCircle, Mail, Phone } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import {
  confirmEmailVerification,
  confirmPhoneVerification,
  getMyKycSubmission,
  requestEmailVerification,
  requestPhoneVerification,
  submitKycSubmission,
  withdrawKycSubmission,
} from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import type { KycSubmission } from '../types'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const showKyc = computed(() => auth.hasRole('landlord') || auth.hasRole('seeker') || auth.hasRole('admin'))
const kycRoleKey = computed(() => {
  if (auth.hasRole('landlord')) return 'landlord'
  if (auth.hasRole('seeker')) return 'seeker'
  return 'landlord'
})
const kycTitle = computed(() => t(`kyc.title.${kycRoleKey.value}` as Parameters<typeof languageStore.t>[0]))
const kycSubtitle = computed(() => t(`kyc.subtitle.${kycRoleKey.value}` as Parameters<typeof languageStore.t>[0]))
const kycVerifiedTitle = computed(() => t(`kyc.verifiedTitle.${kycRoleKey.value}` as Parameters<typeof languageStore.t>[0]))
const emailVerified = computed(() => Boolean(auth.user.emailVerified))
const phoneVerified = computed(() => Boolean(auth.user.phoneVerified))
const hasPhone = computed(() => Boolean(auth.user.phone))

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
const emailCode = ref('')
const phoneCode = ref('')
const emailSending = ref(false)
const phoneSending = ref(false)
const emailVerifying = ref(false)
const phoneVerifying = ref(false)
const emailDevCode = ref('')
const phoneDevCode = ref('')

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
  if (!showKyc.value) {
    loading.value = false
    error.value = ''
    return
  }
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

const sendEmailCode = async () => {
  if (emailVerified.value) return
  emailSending.value = true
  try {
    const response = await requestEmailVerification()
    emailDevCode.value = response.devCode ?? ''
    if (response.devCode) {
      emailCode.value = response.devCode
    }
    toast.push({ title: t('verification.emailSentTitle'), message: t('verification.emailSentMessage'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('verification.emailSendFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    emailSending.value = false
  }
}

const confirmEmailCode = async () => {
  if (!emailCode.value) return
  emailVerifying.value = true
  try {
    const response = await confirmEmailVerification({ code: emailCode.value })
    if (response?.user) {
      auth.setUser(response.user)
    }
    emailCode.value = ''
    emailDevCode.value = ''
    toast.push({ title: t('verification.emailVerifiedTitle'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('verification.emailVerifyFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    emailVerifying.value = false
  }
}

const sendPhoneCode = async () => {
  if (!hasPhone.value) {
    router.push('/settings/profile')
    return
  }
  if (phoneVerified.value) return
  phoneSending.value = true
  try {
    const response = await requestPhoneVerification()
    phoneDevCode.value = response.devCode ?? ''
    if (response.devCode) {
      phoneCode.value = response.devCode
    }
    toast.push({ title: t('verification.phoneSentTitle'), message: t('verification.phoneSentMessage'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('verification.phoneSendFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    phoneSending.value = false
  }
}

const confirmPhoneCode = async () => {
  if (!phoneCode.value) return
  phoneVerifying.value = true
  try {
    const response = await confirmPhoneVerification({ code: phoneCode.value })
    if (response?.user) {
      auth.setUser(response.user)
    }
    phoneCode.value = ''
    phoneDevCode.value = ''
    toast.push({ title: t('verification.phoneVerifiedTitle'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('verification.phoneVerifyFailed'), message: (err as Error).message, type: 'error' })
  } finally {
    phoneVerifying.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Mail class="h-5 w-5 text-primary" />
            {{ t('verification.emailTitle') }}
          </div>
          <p class="text-sm text-muted">{{ auth.user.email || t('verification.emailMissing') }}</p>
        </div>
        <Badge :variant="emailVerified ? 'accepted' : 'cancelled'">
          {{ emailVerified ? t('verification.verified') : t('verification.notVerified') }}
        </Badge>
      </div>
      <p v-if="emailVerified" class="text-sm text-emerald-700">{{ t('verification.emailVerifiedHint') }}</p>
      <div v-else class="space-y-2">
        <Button variant="secondary" :disabled="emailSending" @click="sendEmailCode">
          {{ emailSending ? t('verification.sending') : t('verification.sendCode') }}
        </Button>
        <div class="flex flex-col gap-2 sm:flex-row">
          <Input v-model="emailCode" :placeholder="t('verification.codePlaceholder')" />
          <Button variant="primary" :disabled="!emailCode || emailVerifying" @click="confirmEmailCode">
            {{ emailVerifying ? t('verification.verifying') : t('verification.verify') }}
          </Button>
        </div>
        <p class="text-xs text-muted">{{ t('verification.codeHint') }}</p>
        <p v-if="emailDevCode" class="text-xs text-amber-700">{{ t('verification.devCode') }}: {{ emailDevCode }}</p>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Phone class="h-5 w-5 text-primary" />
            {{ t('verification.phoneTitle') }}
          </div>
          <p class="text-sm text-muted">{{ auth.user.phone || t('verification.phoneMissing') }}</p>
        </div>
        <Badge :variant="phoneVerified ? 'accepted' : 'cancelled'">
          {{ phoneVerified ? t('verification.verified') : t('verification.notVerified') }}
        </Badge>
      </div>
      <p v-if="phoneVerified" class="text-sm text-emerald-700">{{ t('verification.phoneVerifiedHint') }}</p>
      <div v-else class="space-y-2">
        <p v-if="!hasPhone" class="text-sm text-muted">{{ t('verification.phoneMissingHint') }}</p>
        <Button v-if="!hasPhone" variant="secondary" @click="router.push('/settings/profile')">
          {{ t('verification.addPhone') }}
        </Button>
        <div v-else class="space-y-2">
          <Button variant="secondary" :disabled="phoneSending" @click="sendPhoneCode">
            {{ phoneSending ? t('verification.sending') : t('verification.sendCode') }}
          </Button>
          <div class="flex flex-col gap-2 sm:flex-row">
            <Input v-model="phoneCode" :placeholder="t('verification.codePlaceholder')" />
            <Button variant="primary" :disabled="!phoneCode || phoneVerifying" @click="confirmPhoneCode">
              {{ phoneVerifying ? t('verification.verifying') : t('verification.verify') }}
            </Button>
          </div>
          <p class="text-xs text-muted">{{ t('verification.codeHint') }}</p>
          <p v-if="phoneDevCode" class="text-xs text-amber-700">{{ t('verification.devCode') }}: {{ phoneDevCode }}</p>
        </div>
      </div>
    </div>

    <ErrorBanner v-if="showKyc && error" :message="error" />
    <ListSkeleton v-if="showKyc && loading" :count="2" />

    <div v-if="showKyc && !loading" class="space-y-4">
      <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">{{ kycTitle }}</h1>
            <p class="text-sm text-muted">{{ kycSubtitle }}</p>
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
          {{ kycVerifiedTitle }}
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
