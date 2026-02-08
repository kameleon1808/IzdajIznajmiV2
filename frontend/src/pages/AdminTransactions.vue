<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'

const router = useRouter()
const transactionsStore = useTransactionsStore()
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
</script>

<template>
  <div class="space-y-5">
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 to-slate-700 px-5 py-6 text-white shadow-lg">
      <p class="text-sm opacity-80">Admin Operations</p>
      <h1 class="text-2xl font-semibold leading-tight">Transactions</h1>
      <p class="mt-1 text-sm opacity-75">Monitor contracts, deposits, and move-ins.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs font-semibold text-muted">Status filter</label>
      <select v-model="statusFilter" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">All</option>
        <option value="initiated">Initiated</option>
        <option value="contract_generated">Contract generated</option>
        <option value="seeker_signed">Seeker signed</option>
        <option value="landlord_signed">Landlord signed</option>
        <option value="deposit_paid">Deposit paid</option>
        <option value="move_in_confirmed">Move-in confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
        <option value="disputed">Disputed</option>
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
          <p class="text-base font-semibold text-slate-900">{{ tx.listing?.title ?? 'Listing' }}</p>
          <p class="text-xs text-muted">Transaction #{{ tx.id }} · {{ tx.currency }} {{ tx.depositAmount ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Badge :variant="statusVariant[tx.status]">{{ tx.status }}</Badge>
          <Button size="sm" variant="secondary" @click="router.push(`/admin/transactions/${tx.id}`)">View</Button>
        </div>
      </div>
      <p v-if="!transactionsStore.adminList.length" class="text-sm text-muted">No transactions found.</p>
    </div>
  </div>
</template>
