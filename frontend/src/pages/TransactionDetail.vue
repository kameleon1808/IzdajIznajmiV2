<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const route = useRoute()
const router = useRouter()
const transactionsStore = useTransactionsStore()
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const transactionId = computed(() => String(route.params.id || ''))
const startDate = ref(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
const terms = ref('')
const typedName = ref('')
const consent = ref(false)
const loading = ref(true)

const transaction = computed(() => transactionsStore.current)

const statusLabel = (value: string) => {
  if (value === 'initiated') return t('transactions.status.initiated')
  if (value === 'contract_generated') return t('transactions.status.contractGenerated')
  if (value === 'seeker_signed') return t('transactions.status.seekerSigned')
  if (value === 'landlord_signed') return t('transactions.status.landlordSigned')
  if (value === 'deposit_paid') return t('transactions.status.depositPaid')
  if (value === 'move_in_confirmed') return t('transactions.status.moveInConfirmed')
  if (value === 'completed') return t('transactions.status.completed')
  if (value === 'cancelled') return t('transactions.status.cancelled')
  if (value === 'disputed') return t('transactions.status.disputed')
  return value
}

const statusVariant: Record<string, any> = {
  initiated: 'pending',
  contract_generated: 'info',
  seeker_signed: 'info',
  landlord_signed: 'accepted',
  deposit_paid: 'accepted',
  move_in_confirmed: 'accepted',
  completed: 'accepted',
  cancelled: 'cancelled',
  disputed: 'rejected',
}

const timeline = [
  'initiated',
  'contract_generated',
  'seeker_signed',
  'landlord_signed',
  'deposit_paid',
  'move_in_confirmed',
  'completed',
]

const currentIndex = computed(() => timeline.indexOf(transaction.value?.status || ''))

const isLandlord = computed(() => auth.hasRole('landlord'))
const isSeeker = computed(() => auth.hasRole('seeker'))
const isAdmin = computed(() => auth.hasRole('admin'))

const landlordId = computed(() => transaction.value?.participants.landlordId || '')
const seekerId = computed(() => transaction.value?.participants.seekerId || '')

const counterpartId = computed(() => {
  if (isLandlord.value) return seekerId.value
  if (isSeeker.value) return landlordId.value
  return ''
})

const hasSigned = computed(() => {
  const contract = transaction.value?.contract
  const userId = String(auth.user.id || '')
  if (!contract || !userId) return false
  return contract.signatures.some((s) => String(s.userId) === userId)
})

const canGenerateContract = computed(() => {
  if (!transaction.value) return false
  if (!isLandlord.value) return false
  return !['deposit_paid', 'move_in_confirmed', 'completed', 'cancelled', 'disputed'].includes(transaction.value.status)
})

const canSign = computed(() => {
  const contract = transaction.value?.contract
  if (!contract) return false
  if (contract.status === 'final') return false
  return !hasSigned.value
})

const canPayDeposit = computed(() => {
  const tx = transaction.value
  if (!tx) return false
  if (!isSeeker.value) return false
  if (tx.contract?.status !== 'final') return false
  return !['deposit_paid', 'move_in_confirmed', 'completed', 'cancelled', 'disputed'].includes(tx.status)
})

const canConfirmMoveIn = computed(() => {
  const tx = transaction.value
  if (!tx) return false
  if (!isLandlord.value) return false
  return tx.status === 'deposit_paid'
})

const canComplete = computed(() => {
  const tx = transaction.value
  if (!tx) return false
  if (!isLandlord.value) return false
  return tx.status === 'move_in_confirmed'
})

const load = async () => {
  loading.value = true
  try {
    await transactionsStore.fetchTransaction(transactionId.value)
  } catch (error) {
    toast.push({ title: t('transactions.loadFailed'), message: (error as Error).message, type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)

const generateContract = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.generateContract(transaction.value.id, { startDate: startDate.value, terms: terms.value || undefined })
    toast.push({ title: t('transactions.contractGenerated'), type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: t('transactions.contractFailed'), message: (error as Error).message, type: 'error' })
  }
}

const signContract = async () => {
  if (!transaction.value?.contract) return
  if (!typedName.value || !consent.value) {
    toast.push({ title: t('transactions.missingSignature'), type: 'info' })
    return
  }
  try {
    await transactionsStore.signContract(transaction.value.contract.id, { typedName: typedName.value, consent: consent.value })
    toast.push({ title: t('transactions.contractSigned'), type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: t('transactions.signatureFailed'), message: (error as Error).message, type: 'error' })
  }
}

const payDeposit = async () => {
  if (!transaction.value) return
  try {
    const { checkoutUrl } = await transactionsStore.createDepositSession(transaction.value.id)
    if (checkoutUrl) {
      window.location.href = checkoutUrl
    }
  } catch (error) {
    toast.push({ title: t('transactions.paymentFailed'), message: (error as Error).message, type: 'error' })
  }
}

const payDepositCash = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.payDepositCash(transaction.value.id)
    toast.push({ title: t('transactions.cashDepositRecorded'), type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: t('transactions.cashDepositFailed'), message: (error as Error).message, type: 'error' })
  }
}

const confirmMoveIn = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.confirmMoveIn(transaction.value.id)
    toast.push({ title: t('transactions.moveInConfirmed'), type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: t('transactions.moveInFailed'), message: (error as Error).message, type: 'error' })
  }
}

const completeTransaction = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.completeTransaction(transaction.value.id)
    toast.push({ title: t('transactions.completedToast'), type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: t('transactions.completionFailed'), message: (error as Error).message, type: 'error' })
  }
}

const goBack = () => router.push('/bookings?tab=reservations&section=requests')

const openListing = () => {
  if (!transaction.value?.listing?.id) return
  router.push(`/listing/${transaction.value.listing.id}`)
}

const openProfile = (userId: string) => {
  if (!userId) return
  router.push(`/users/${userId}`)
}
</script>

<template>
  <div class="space-y-5">
    <Button variant="secondary" size="sm" @click="goBack">{{ t('common.back') }}</Button>

    <ErrorState v-if="transactionsStore.error" :message="transactionsStore.error" :retry-label="t('common.retry')" @retry="load" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-if="!loading && transaction" class="space-y-5">
      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-muted">{{ t('transactions.transactionLabel') }}</p>
            <h1 class="text-xl font-semibold text-slate-900">{{ transaction.listing?.title ?? t('transactions.listingFallback') }}</h1>
            <p class="text-sm text-muted">{{ transaction.listing?.address ?? transaction.listing?.city }}</p>
          </div>
          <Badge :variant="statusVariant[transaction.status]">{{ statusLabel(transaction.status) }}</Badge>
        </div>
        <div class="mt-4 grid gap-2 text-sm text-slate-700">
          <div>{{ t('transactions.deposit') }}: {{ transaction.depositAmount ?? '—' }} {{ transaction.currency }}</div>
          <div>{{ t('transactions.rent') }}: {{ transaction.rentAmount ?? '—' }} {{ transaction.currency }}</div>
          <div>{{ t('transactions.started') }}: {{ transaction.startedAt ? new Date(transaction.startedAt).toLocaleString() : '—' }}</div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" @click="openListing">{{ t('transactions.openListing') }}</Button>
          <Button v-if="counterpartId" variant="secondary" size="sm" @click="openProfile(counterpartId)">
            {{ t('transactions.openParticipant') }}
          </Button>
          <Button v-if="isAdmin && landlordId" variant="secondary" size="sm" @click="openProfile(landlordId)">
            {{ t('transactions.openLandlord') }}
          </Button>
          <Button v-if="isAdmin && seekerId" variant="secondary" size="sm" @click="openProfile(seekerId)">
            {{ t('transactions.openSeeker') }}
          </Button>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('transactions.timeline') }}</h2>
        <div class="mt-3 space-y-2 text-sm">
          <div
            v-for="(status, idx) in timeline"
            :key="status"
            class="flex items-center justify-between rounded-2xl border border-line px-3 py-2"
            :class="idx <= currentIndex ? 'bg-emerald-50 text-emerald-700' : 'bg-surface text-muted'"
          >
            <span>{{ statusLabel(status) }}</span>
            <span v-if="idx <= currentIndex" class="text-xs font-semibold">{{ t('transactions.done') }}</span>
          </div>
          <div v-if="['cancelled', 'disputed'].includes(transaction.status)" class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            {{ t('transactions.statusLabel') }}: {{ statusLabel(transaction.status) }}
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('transactions.contract') }}</h2>
        <div v-if="transaction.contract" class="mt-3 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted">
              {{ t('transactions.contractVersion') }} {{ transaction.contract.version }} · {{ transaction.contract.status }}
            </p>
            <a v-if="transaction.contract.pdfUrl" :href="transaction.contract.pdfUrl" target="_blank" class="text-sm font-semibold text-primary">
              {{ t('transactions.viewPdf') }}
            </a>
          </div>
          <div v-if="transaction.contract.signatures.length" class="space-y-2">
            <p class="text-xs font-semibold text-muted">{{ t('transactions.signatures') }}</p>
            <div
              v-for="sig in transaction.contract.signatures"
              :key="sig.id"
              class="rounded-2xl border border-line bg-surface p-3 text-sm text-slate-700"
            >
              <div class="font-semibold">{{ sig.role }} {{ t('transactions.signed') }}</div>
              <div class="text-xs text-muted">{{ sig.signedAt ? new Date(sig.signedAt).toLocaleString() : '—' }}</div>
            </div>
          </div>

          <div v-if="canSign" class="space-y-2">
            <label class="text-xs font-semibold text-muted">{{ t('transactions.typedName') }}</label>
            <Input v-model="typedName" :placeholder="t('transactions.fullNamePlaceholder')" />
            <label class="flex items-center gap-2 text-xs text-slate-700">
              <input v-model="consent" type="checkbox" /> {{ t('transactions.consent') }}
            </label>
            <Button variant="primary" class="w-full" @click="signContract">{{ t('transactions.signContract') }}</Button>
          </div>

          <div v-if="canGenerateContract" class="space-y-2">
            <label class="text-xs font-semibold text-muted">{{ t('transactions.moveInDate') }}</label>
            <Input v-model="startDate" type="date" />
            <label class="text-xs font-semibold text-muted">{{ t('transactions.termsOptional') }}</label>
            <Input v-model="terms" :placeholder="t('transactions.termsPlaceholder')" />
            <Button variant="secondary" class="w-full" @click="generateContract">{{ t('transactions.regenerateContract') }}</Button>
          </div>
        </div>

        <div v-else class="mt-3 space-y-3">
          <p class="text-sm text-muted">{{ t('transactions.noContract') }}</p>
          <div v-if="canGenerateContract" class="space-y-2">
            <label class="text-xs font-semibold text-muted">{{ t('transactions.moveInDate') }}</label>
            <Input v-model="startDate" type="date" />
            <label class="text-xs font-semibold text-muted">{{ t('transactions.termsOptional') }}</label>
            <Input v-model="terms" :placeholder="t('transactions.termsPlaceholder')" />
            <Button variant="primary" class="w-full" @click="generateContract">{{ t('transactions.generateContract') }}</Button>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('transactions.depositPayment') }}</h2>
        <p class="text-sm text-muted">{{ t('transactions.statusLabel') }}: {{ statusLabel(transaction.status) }}</p>
        <div class="mt-3 space-y-2">
          <div v-if="transaction.payments.length" class="space-y-2">
            <div
              v-for="payment in transaction.payments"
              :key="payment.id"
              class="flex items-center justify-between rounded-2xl border border-line bg-surface p-3 text-sm"
            >
              <div>
                <div class="font-semibold">{{ payment.type }} · {{ payment.amount }} {{ payment.currency }}</div>
                <div class="text-xs text-muted">{{ payment.status }}</div>
              </div>
              <a v-if="payment.receiptUrl" :href="payment.receiptUrl" target="_blank" class="text-xs font-semibold text-primary">
                {{ t('transactions.receipt') }}
              </a>
            </div>
          </div>
          <Button v-if="canPayDeposit" variant="primary" class="w-full" @click="payDeposit">
            {{ t('transactions.payOnline') }}
          </Button>
          <Button v-if="canPayDeposit" variant="secondary" class="w-full" @click="payDepositCash">
            {{ t('transactions.payCash') }}
          </Button>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('transactions.moveInConfirmation') }}</h2>
        <p class="text-sm text-muted" v-if="canConfirmMoveIn">{{ t('transactions.confirmMoveInHint') }}</p>
        <p class="text-sm text-muted" v-else-if="canComplete">{{ t('transactions.completeHint') }}</p>
        <p class="text-sm text-muted" v-else-if="transaction.status === 'move_in_confirmed'">{{ t('transactions.awaitingCompletion') }}</p>
        <Button v-if="canConfirmMoveIn" variant="primary" class="mt-3 w-full" @click="confirmMoveIn">
          {{ t('transactions.confirmMoveIn') }}
        </Button>
        <Button v-if="canComplete" variant="primary" class="mt-3 w-full" @click="completeTransaction">
          {{ t('transactions.completeTransaction') }}
        </Button>
      </section>
    </div>
  </div>
</template>
