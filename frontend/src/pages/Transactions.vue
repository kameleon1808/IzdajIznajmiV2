<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'

const router = useRouter()
const transactionsStore = useTransactionsStore()

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

const load = async () => {
  try {
    await transactionsStore.fetchTransactions()
  } catch (error) {
    // error handled in store
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner v-if="transactionsStore.error" :message="transactionsStore.error" />
    <ListSkeleton v-if="transactionsStore.listLoading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="tx in transactionsStore.list"
        :key="tx.id"
        class="rounded-3xl border border-line bg-white p-5 shadow-soft"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-muted">Transaction #{{ tx.id }}</p>
            <h2 class="text-lg font-semibold text-slate-900">{{ tx.listing?.title ?? 'Listing' }}</h2>
            <p class="text-sm text-muted">{{ tx.listing?.address ?? tx.listing?.city }}</p>
          </div>
          <Badge :variant="statusVariant[tx.status]">{{ tx.status }}</Badge>
        </div>
        <div class="mt-3 grid gap-2 text-sm text-slate-700">
          <div>Deposit: {{ tx.depositAmount ?? '—' }} {{ tx.currency }}</div>
          <div>Rent: {{ tx.rentAmount ?? '—' }} {{ tx.currency }}</div>
        </div>
        <div class="mt-4 flex justify-end">
          <Button size="sm" variant="secondary" @click="router.push(`/transactions/${tx.id}`)">Open</Button>
        </div>
      </div>

      <p v-if="!transactionsStore.list.length" class="text-sm text-muted">No transactions yet.</p>
    </div>
  </div>
</template>
