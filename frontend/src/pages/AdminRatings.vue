<script setup lang="ts">
import { onMounted, ref } from 'vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { deleteAdminRating, flagUserSuspicious, getAdminRatings } from '../services'
import type { Rating } from '../types'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const loading = ref(true)
const error = ref('')
const ratings = ref<Rating[]>([])
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const load = async (reportedOnly = false) => {
  loading.value = true
  error.value = ''
  try {
    ratings.value = await getAdminRatings({ reported: reportedOnly })
  } catch (err) {
    error.value = (err as Error).message || t('admin.ratings.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(() => load(false))

const remove = async (id: string) => {
  try {
    await deleteAdminRating(id)
    ratings.value = ratings.value.filter((r) => r.id !== id)
    toast.push({ title: t('common.deleted'), type: 'info' })
  } catch (err) {
    toast.push({ title: t('common.deleteFailed'), message: (err as Error).message, type: 'error' })
  }
}

const flagSuspicious = async (userId: string | number, isSuspicious: boolean) => {
  try {
    await flagUserSuspicious(userId, isSuspicious)
    toast.push({ title: t('common.updated'), type: 'success' })
  } catch (err) {
    toast.push({ title: t('common.failed'), message: (err as Error).message, type: 'error' })
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">{{ t('admin.nav.moderation') }}</router-link>
      <router-link to="/admin/ratings">{{ t('admin.nav.ratings') }}</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">{{ t('admin.nav.kyc') }}</router-link>
    </div>

    <div class="flex items-center gap-3">
      <Button variant="secondary" @click="load(false)">{{ t('admin.ratings.all') }}</Button>
      <Button variant="primary" @click="load(true)">{{ t('admin.ratings.reported') }}</Button>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />
    <div v-else class="space-y-3">
      <div
        v-for="rating in ratings"
        :key="rating.id"
        class="rounded-2xl border border-line bg-white p-4 shadow-soft space-y-2"
      >
        <div class="flex items-center justify-between">
          <p class="font-semibold text-slate-900">{{ t('admin.ratings.rating') }} {{ rating.rating }}★</p>
          <span class="text-xs text-muted">{{ rating.createdAt }}</span>
        </div>
        <p class="text-sm text-muted">{{ rating.comment || t('admin.ratings.noComment') }}</p>
        <p class="text-xs text-muted">
          {{ t('admin.ratings.listing') }}: {{ rating.listing?.title || rating.listingId }} · {{ t('admin.ratings.reports') }}: {{ rating.reportCount ?? 0 }}
        </p>
        <div class="flex gap-2">
          <Button variant="primary" size="sm" @click="flagSuspicious(rating.rater?.id || '', true)">{{ t('admin.ratings.flagRater') }}</Button>
          <Button variant="secondary" size="sm" @click="remove(rating.id)">{{ t('common.delete') }}</Button>
        </div>
      </div>
      <EmptyState v-if="!ratings.length && !loading" :title="t('admin.ratings.emptyTitle')" :subtitle="t('admin.ratings.emptySubtitle')" />
    </div>
  </div>
</template>
