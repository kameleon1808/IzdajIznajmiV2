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

const route = useRoute()
const router = useRouter()
const transactionsStore = useTransactionsStore()
const auth = useAuthStore()
const toast = useToastStore()

const transactionId = computed(() => String(route.params.id || ''))
const startDate = ref(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
const terms = ref('')
const typedName = ref('')
const consent = ref(false)
const loading = ref(true)

const transaction = computed(() => transactionsStore.current)

const statusLabels: Record<string, string> = {
  initiated: 'Initiated',
  contract_generated: 'Contract generated',
  seeker_signed: 'Seeker signed',
  landlord_signed: 'Landlord signed',
  deposit_paid: 'Deposit paid',
  move_in_confirmed: 'Move-in confirmed',
  completed: 'Completed',
  cancelled: 'Cancelled',
  disputed: 'Disputed',
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

const load = async () => {
  loading.value = true
  try {
    await transactionsStore.fetchTransaction(transactionId.value)
  } catch (error) {
    toast.push({ title: 'Failed to load transaction', message: (error as Error).message, type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)

const generateContract = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.generateContract(transaction.value.id, { startDate: startDate.value, terms: terms.value || undefined })
    toast.push({ title: 'Contract generated', type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: 'Contract failed', message: (error as Error).message, type: 'error' })
  }
}

const signContract = async () => {
  if (!transaction.value?.contract) return
  if (!typedName.value || !consent.value) {
    toast.push({ title: 'Please provide a typed name and consent', type: 'info' })
    return
  }
  try {
    await transactionsStore.signContract(transaction.value.contract.id, { typedName: typedName.value, consent: consent.value })
    toast.push({ title: 'Contract signed', type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: 'Signature failed', message: (error as Error).message, type: 'error' })
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
    toast.push({ title: 'Payment failed', message: (error as Error).message, type: 'error' })
  }
}

const confirmMoveIn = async () => {
  if (!transaction.value) return
  try {
    await transactionsStore.confirmMoveIn(transaction.value.id)
    toast.push({ title: 'Move-in confirmed', type: 'success' })
    await load()
  } catch (error) {
    toast.push({ title: 'Move-in failed', message: (error as Error).message, type: 'error' })
  }
}

const goBack = () => router.push('/bookings?tab=reservations&section=requests')
</script>

<template>
  <div class="space-y-5">
    <Button variant="secondary" size="sm" @click="goBack">Back</Button>

    <ErrorState v-if="transactionsStore.error" :message="transactionsStore.error" retry-label="Retry" @retry="load" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-if="!loading && transaction" class="space-y-5">
      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-muted">Transaction</p>
            <h1 class="text-xl font-semibold text-slate-900">{{ transaction.listing?.title ?? 'Listing' }}</h1>
            <p class="text-sm text-muted">{{ transaction.listing?.address ?? transaction.listing?.city }}</p>
          </div>
          <Badge :variant="statusVariant[transaction.status]">{{ statusLabels[transaction.status] ?? transaction.status }}</Badge>
        </div>
        <div class="mt-4 grid gap-2 text-sm text-slate-700">
          <div>Deposit: {{ transaction.depositAmount ?? '—' }} {{ transaction.currency }}</div>
          <div>Rent: {{ transaction.rentAmount ?? '—' }} {{ transaction.currency }}</div>
          <div>Started: {{ transaction.startedAt ? new Date(transaction.startedAt).toLocaleString() : '—' }}</div>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">Timeline</h2>
        <div class="mt-3 space-y-2 text-sm">
          <div
            v-for="(status, idx) in timeline"
            :key="status"
            class="flex items-center justify-between rounded-2xl border border-line px-3 py-2"
            :class="idx <= currentIndex ? 'bg-emerald-50 text-emerald-700' : 'bg-surface text-muted'"
          >
            <span>{{ statusLabels[status] }}</span>
            <span v-if="idx <= currentIndex" class="text-xs font-semibold">Done</span>
          </div>
          <div v-if="['cancelled', 'disputed'].includes(transaction.status)" class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            Status: {{ statusLabels[transaction.status] }}
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">Contract</h2>
        <div v-if="transaction.contract" class="mt-3 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted">Version {{ transaction.contract.version }} · {{ transaction.contract.status }}</p>
            <a v-if="transaction.contract.pdfUrl" :href="transaction.contract.pdfUrl" target="_blank" class="text-sm font-semibold text-primary">
              View PDF
            </a>
          </div>
          <div v-if="transaction.contract.signatures.length" class="space-y-2">
            <p class="text-xs font-semibold text-muted">Signatures</p>
            <div
              v-for="sig in transaction.contract.signatures"
              :key="sig.id"
              class="rounded-2xl border border-line bg-surface p-3 text-sm text-slate-700"
            >
              <div class="font-semibold">{{ sig.role }} signed</div>
              <div class="text-xs text-muted">{{ sig.signedAt ? new Date(sig.signedAt).toLocaleString() : '—' }}</div>
            </div>
          </div>

          <div v-if="canSign" class="space-y-2">
            <label class="text-xs font-semibold text-muted">Typed name</label>
            <Input v-model="typedName" placeholder="Your full name" />
            <label class="flex items-center gap-2 text-xs text-slate-700">
              <input v-model="consent" type="checkbox" /> I agree to sign this contract electronically.
            </label>
            <Button variant="primary" class="w-full" @click="signContract">Sign contract</Button>
          </div>

          <div v-if="canGenerateContract" class="space-y-2">
            <label class="text-xs font-semibold text-muted">Move-in date</label>
            <Input v-model="startDate" type="date" />
            <label class="text-xs font-semibold text-muted">Terms (optional)</label>
            <Input v-model="terms" placeholder="Add custom terms" />
            <Button variant="secondary" class="w-full" @click="generateContract">Regenerate contract</Button>
          </div>
        </div>

        <div v-else class="mt-3 space-y-3">
          <p class="text-sm text-muted">No contract generated yet.</p>
          <div v-if="canGenerateContract" class="space-y-2">
            <label class="text-xs font-semibold text-muted">Move-in date</label>
            <Input v-model="startDate" type="date" />
            <label class="text-xs font-semibold text-muted">Terms (optional)</label>
            <Input v-model="terms" placeholder="Add custom terms" />
            <Button variant="primary" class="w-full" @click="generateContract">Generate contract</Button>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">Deposit payment</h2>
        <p class="text-sm text-muted">Status: {{ statusLabels[transaction.status] ?? transaction.status }}</p>
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
                Receipt
              </a>
            </div>
          </div>
          <Button v-if="canPayDeposit" variant="primary" class="w-full" @click="payDeposit">Pay deposit</Button>
        </div>
      </section>

      <section class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">Move-in confirmation</h2>
        <p class="text-sm text-muted">Confirm when the seeker has moved in.</p>
        <Button v-if="canConfirmMoveIn" variant="primary" class="mt-3 w-full" @click="confirmMoveIn">
          Confirm move-in
        </Button>
      </section>
    </div>
  </div>
</template>
