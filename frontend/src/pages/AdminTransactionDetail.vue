<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'
import { useToastStore } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const transactionsStore = useTransactionsStore()
const loading = ref(true)

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
  loading.value = true
  try {
    await transactionsStore.fetchAdminTransaction(String(route.params.id))
  } catch (error) {
    toast.push({ title: 'Failed to load transaction', message: (error as Error).message, type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)

const openListing = () => {
  if (!transactionsStore.current?.listing?.id) return
  router.push(`/listing/${transactionsStore.current.listing.id}`)
}

const openProfile = (userId: string | undefined) => {
  if (!userId) return
  router.push(`/users/${userId}`)
}

// Admin view is read-only for transactions.
</script>

<template>
  <div class="space-y-5">
    <Button variant="secondary" size="sm" @click="router.push('/admin/transactions')">Back to list</Button>

    <ErrorBanner v-if="transactionsStore.error" :message="transactionsStore.error" />
    <ListSkeleton v-if="loading" :count="2" />

    <div v-if="!loading && transactionsStore.current" class="space-y-4">
      <div class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-muted">Transaction #{{ transactionsStore.current.id }}</p>
            <h1 class="text-xl font-semibold text-slate-900">{{ transactionsStore.current.listing?.title ?? 'Listing' }}</h1>
            <p class="text-sm text-muted">{{ transactionsStore.current.listing?.address ?? transactionsStore.current.listing?.city }}</p>
          </div>
          <Badge :variant="statusVariant[transactionsStore.current.status]">{{ transactionsStore.current.status }}</Badge>
        </div>
        <div class="mt-4 grid gap-2 text-sm text-slate-700">
          <div>Deposit: {{ transactionsStore.current.depositAmount ?? '—' }} {{ transactionsStore.current.currency }}</div>
          <div>Rent: {{ transactionsStore.current.rentAmount ?? '—' }} {{ transactionsStore.current.currency }}</div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" @click="openListing">Open listing</Button>
          <Button variant="secondary" size="sm" @click="openProfile(transactionsStore.current.participants?.landlordId)">Open landlord profile</Button>
          <Button variant="secondary" size="sm" @click="openProfile(transactionsStore.current.participants?.seekerId)">Open seeker profile</Button>
        </div>
      </div>

      <div class="rounded-2xl border border-line bg-surface p-3 text-sm text-muted">
        Transactions are read-only for admins. Resolve issues through reports on participant profiles.
      </div>

      <div class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">Payments</h2>
        <div v-if="transactionsStore.current.payments.length" class="mt-3 space-y-2">
          <div
            v-for="payment in transactionsStore.current.payments"
            :key="payment.id"
            class="flex items-center justify-between rounded-2xl border border-line bg-surface p-3 text-sm"
          >
            <div>
              <div class="font-semibold">{{ payment.type }} · {{ payment.amount }} {{ payment.currency }}</div>
              <div class="text-xs text-muted">{{ payment.status }}</div>
            </div>
            <a v-if="payment.receiptUrl" :href="payment.receiptUrl" target="_blank" class="text-xs font-semibold text-primary">Receipt</a>
          </div>
        </div>
        <p v-else class="text-sm text-muted">No payments recorded.</p>
      </div>
    </div>
  </div>
</template>
