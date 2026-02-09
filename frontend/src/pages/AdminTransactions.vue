<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const transactionsStore = useTransactionsStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const statusFilter = ref('')

const load = async () => {
  await transactionsStore.fetchAdminTransactions(statusFilter.value || undefined)
}

onMounted(load)
watch(statusFilter, () => load())

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
</script>

<template>
  <div class="space-y-5">
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 to-slate-700 px-5 py-6 text-white shadow-lg">
      <p class="text-sm opacity-80">{{ t('admin.header.operations') }}</p>
      <h1 class="text-2xl font-semibold leading-tight">{{ t('admin.transactions.title') }}</h1>
      <p class="mt-1 text-sm opacity-75">{{ t('admin.transactions.subtitle') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs font-semibold text-muted">{{ t('admin.transactions.statusFilter') }}</label>
      <select v-model="statusFilter" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">{{ t('common.all') }}</option>
        <option value="initiated">{{ t('transactions.status.initiated') }}</option>
        <option value="contract_generated">{{ t('transactions.status.contractGenerated') }}</option>
        <option value="seeker_signed">{{ t('transactions.status.seekerSigned') }}</option>
        <option value="landlord_signed">{{ t('transactions.status.landlordSigned') }}</option>
        <option value="deposit_paid">{{ t('transactions.status.depositPaid') }}</option>
        <option value="move_in_confirmed">{{ t('transactions.status.moveInConfirmed') }}</option>
        <option value="completed">{{ t('transactions.status.completed') }}</option>
        <option value="cancelled">{{ t('transactions.status.cancelled') }}</option>
        <option value="disputed">{{ t('transactions.status.disputed') }}</option>
      </select>
    </div>

    <ErrorBanner v-if="transactionsStore.error" :message="transactionsStore.error" />
    <ListSkeleton v-if="transactionsStore.loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="tx in transactionsStore.adminList"
        :key="tx.id"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white p-4 shadow-soft"
      >
        <div>
          <p class="text-base font-semibold text-slate-900">{{ tx.listing?.title ?? t('transactions.listingFallback') }}</p>
          <p class="text-xs text-muted">
            {{ t('transactions.transactionLabel') }} #{{ tx.id }} · {{ tx.currency }} {{ tx.depositAmount ?? '—' }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Badge :variant="statusVariant[tx.status]">{{ statusLabel(tx.status) }}</Badge>
          <Button size="sm" variant="secondary" @click="router.push(`/admin/transactions/${tx.id}`)">{{ t('common.view') }}</Button>
        </div>
      </div>
      <p v-if="!transactionsStore.adminList.length" class="text-sm text-muted">{{ t('admin.transactions.empty') }}</p>
    </div>
  </div>
</template>
