<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertTriangle, Loader2 } from 'lucide-vue-next'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import Badge from '../components/ui/Badge.vue'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import { useRequestsStore } from '../stores/requests'
import type { Application } from '../types'
import { resolveChatTarget, type ChatDeepLinkTarget } from '../utils/deepLink'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const chatStore = useChatStore()
const requestsStore = useRequestsStore()

const error = ref('')
const selectionListingId = ref<string | null>(null)

const target = computed<ChatDeepLinkTarget>(() =>
  resolveChatTarget(route.query as Record<string, any>, auth.primaryRole ?? 'guest'),
)

const selectionOptions = computed<Application[]>(() => {
  if (!selectionListingId.value) return []
  return requestsStore.landlordRequests.filter((a) => a.listing.id === selectionListingId.value)
})

const selectionListingTitle = computed(() => selectionOptions.value[0]?.listing.title || selectionListingId.value)

const header = computed(() => {
  if (target.value.kind === 'conversation') return 'Opening chat thread'
  if (target.value.kind === 'application') return 'Opening application chat'
  if (target.value.kind === 'listing') return 'Opening listing chat'
  return 'Chat'
})

const resolveAndRoute = async () => {
  error.value = ''

  try {
    if (target.value.kind === 'conversation') {
      const conversation = await chatStore.openByConversationId(target.value.id)
      return router.replace({ path: `/chat/${conversation.id}` })
    }

    if (target.value.kind === 'application') {
      const conversation = await chatStore.openByApplicationId(target.value.id)
      return router.replace({ path: `/chat/${conversation.id}` })
    }

    if (target.value.kind === 'listing') {
      if (auth.hasRole('seeker')) {
        const conversation = await chatStore.openByListingId(target.value.id)
        return router.replace({ path: `/chat/${conversation.id}` })
      }

      if (auth.hasRole('landlord')) {
        selectionListingId.value = target.value.id
        await requestsStore.fetchLandlordRequests(target.value.id)
        if (requestsStore.error) {
          throw new Error(requestsStore.error)
        }
        if (!selectionOptions.value.length) {
          throw new Error('Choose an application to start the conversation for this listing.')
        }
        return
      }
    }

    throw new Error('No deep-link target found. Open a conversation from Messages.')
  } catch (err) {
    error.value = (err as Error).message || 'Unable to open chat.'
  }
}

const openFromSelection = async (appId: string) => {
  error.value = ''
  try {
    const conversation = await chatStore.openByApplicationId(appId)
    await router.replace({ path: `/chat/${conversation.id}` })
  } catch (err) {
    error.value = (err as Error).message || 'Unable to open chat.'
  }
}

onMounted(() => {
  if (!selectionListingId.value) {
    resolveAndRoute()
  }
})
</script>

<template>
  <div class="min-h-screen bg-surface px-4 pb-16 pt-6">
    <div class="mb-4 flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/60">
      <div>
        <p class="text-xs uppercase text-muted">Notifications</p>
        <h1 class="text-lg font-semibold text-slate-900">{{ header }}</h1>
      </div>
      <div class="rounded-full bg-primary/10 p-2">
        <Loader2 v-if="chatStore.resolving" class="h-5 w-5 animate-spin text-primary" />
        <AlertTriangle v-else-if="error" class="h-5 w-5 text-amber-600" />
      </div>
    </div>

    <ErrorBanner v-if="error && !selectionListingId" :message="error" />

    <div v-if="selectionListingId" class="space-y-3">
      <ErrorBanner v-if="error" :message="error" />
      <p class="text-sm text-muted">
        Select which application for listing
        <strong>{{ selectionListingTitle }}</strong>
        you want to open in chat.
      </p>
      <ListSkeleton v-if="requestsStore.loading" :count="2" />
      <EmptyState
        v-else-if="!selectionOptions.length"
        title="No applications yet"
        subtitle="Ask applicants to apply before starting a chat."
      />
      <div v-else class="space-y-3">
        <div
          v-for="app in selectionOptions"
          :key="app.id"
          class="rounded-2xl border border-white/60 bg-white p-3 shadow-soft"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ app.listing.title || 'Listing' }}</p>
              <p class="text-xs text-muted">
                Seeker: {{ app.participants.seekerId }} · {{ app.listing.city || 'N/A' }}
              </p>
            </div>
            <Badge :variant="app.status === 'submitted' ? 'pending' : app.status === 'accepted' ? 'accepted' : 'info'">
              {{ app.status }}
            </Badge>
          </div>
          <p class="mt-2 text-sm text-muted line-clamp-2">{{ app.message || 'No message' }}</p>
          <div class="mt-3 flex justify-end">
            <Button size="sm" variant="primary" @click="openFromSelection(app.id)">Open chat</Button>
          </div>
        </div>
      </div>
      <div class="flex justify-end" v-if="!requestsStore.loading && !selectionOptions.length">
        <Button variant="secondary" size="sm" @click="router.push('/messages')">Back to messages</Button>
      </div>
    </div>

    <div v-else class="space-y-3">
      <ListSkeleton v-if="chatStore.resolving" :count="3" />
      <EmptyState
        v-else
        title="Preparing chat..."
        subtitle="Opening conversation from your notification."
      />
    </div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
