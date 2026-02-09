<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useTransactionsStore } from '../stores/transactions'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const transactionsStore = useTransactionsStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
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

const load = async () => {
  loading.value = true
  try {
    await transactionsStore.fetchAdminTransaction(String(route.params.id))
  } catch (error) {
    toast.push({ title: t('transactions.loadFailed'), message: (error as Error).message, type: 'error' })
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
    <Button variant="secondary" size="sm" @click="router.push('/admin/transactions')">{{ t('admin.transactions.backToList') }}</Button>

    <ErrorBanner v-if="transactionsStore.error" :message="transactionsStore.error" />
    <ListSkeleton v-if="loading" :count="2" />

    <div v-if="!loading && transactionsStore.current" class="space-y-4">
      <div class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-muted">{{ t('transactions.transactionLabel') }} #{{ transactionsStore.current.id }}</p>
            <h1 class="text-xl font-semibold text-slate-900">{{ transactionsStore.current.listing?.title ?? t('transactions.listingFallback') }}</h1>
            <p class="text-sm text-muted">{{ transactionsStore.current.listing?.address ?? transactionsStore.current.listing?.city }}</p>
          </div>
          <Badge :variant="statusVariant[transactionsStore.current.status]">{{ statusLabel(transactionsStore.current.status) }}</Badge>
        </div>
        <div class="mt-4 grid gap-2 text-sm text-slate-700">
          <div>{{ t('transactions.deposit') }}: {{ transactionsStore.current.depositAmount ?? '—' }} {{ transactionsStore.current.currency }}</div>
          <div>{{ t('transactions.rent') }}: {{ transactionsStore.current.rentAmount ?? '—' }} {{ transactionsStore.current.currency }}</div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" @click="openListing">{{ t('transactions.openListing') }}</Button>
          <Button variant="secondary" size="sm" @click="openProfile(transactionsStore.current.participants?.landlordId)">{{ t('transactions.openLandlord') }}</Button>
          <Button variant="secondary" size="sm" @click="openProfile(transactionsStore.current.participants?.seekerId)">{{ t('transactions.openSeeker') }}</Button>
        </div>
      </div>

      <div class="rounded-2xl border border-line bg-surface p-3 text-sm text-muted">
        {{ t('admin.transactions.readOnly') }}
      </div>

      <div class="rounded-3xl border border-line bg-white p-5 shadow-soft">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('transactions.payments') }}</h2>
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
            <a v-if="payment.receiptUrl" :href="payment.receiptUrl" target="_blank" class="text-xs font-semibold text-primary">{{ t('transactions.receipt') }}</a>
          </div>
        </div>
        <p v-else class="text-sm text-muted">{{ t('transactions.noPayments') }}</p>
      </div>
    </div>
  </div>
</template>
