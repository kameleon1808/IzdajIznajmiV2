<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Search as SearchIcon } from 'lucide-vue-next'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import Input from '../components/ui/Input.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useRoute, useRouter } from 'vue-router'
import { useLanguageStore } from '../stores/language'

const chatStore = useChatStore()
const router = useRouter()
const route = useRoute()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const useTranslations = computed(() => route.path === '/messages')
const tx = (key: Parameters<typeof languageStore.t>[0], fallback: string) =>
  useTranslations.value ? t(key) : fallback
const query = ref('')

onMounted(() => {
  chatStore.fetchConversations()
})

const items = computed(() =>
  chatStore.conversations.filter((c) =>
    `${c.userName} ${c.listingTitle ?? ''}`.toLowerCase().includes(query.value.toLowerCase()),
  ),
)
const loading = computed(() => chatStore.loading)
const error = computed(() => chatStore.error)

const retryMessages = () => {
  chatStore.clearError()
  return chatStore.fetchConversations()
}
</script>

<template>
  <div class="space-y-4 overflow-x-hidden">
    <ErrorState
      v-if="error"
      :message="error"
      :retry-label="tx('messages.retry', 'Retry')"
      @retry="retryMessages"
    />
    <Input v-model="query" :placeholder="tx('messages.searchPlaceholder', 'Search messages')" :left-icon="SearchIcon" />

    <ListSkeleton v-if="loading" :count="3" />
    <div v-else class="space-y-3">
      <div
        v-for="conv in items"
        :key="conv.id"
        class="flex w-full items-center gap-3 overflow-hidden rounded-2xl border border-white/60 bg-white p-3 shadow-soft"
        @click="router.push(`/chat/${conv.id}`)"
      >
        <div class="relative shrink-0">
          <img :src="conv.listingCoverImage || conv.avatarUrl" alt="avatar" class="h-12 w-12 rounded-2xl object-cover" />
          <span
            v-if="conv.online"
            class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-primary"
          ></span>
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex min-w-0 items-center justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate font-semibold text-slate-900">{{ conv.listingTitle || conv.userName }}</p>
              <p class="text-xs text-muted">{{ conv.userName }}</p>
            </div>
            <span class="shrink-0 text-xs text-muted">{{ conv.time }}</span>
          </div>
          <p class="truncate text-sm text-muted">{{ conv.lastMessage }}</p>
          <span v-if="conv.unreadCount" class="ml-auto mt-1 inline-block rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">
            {{ conv.unreadCount }}
          </span>
        </div>
      </div>
      <EmptyState
        v-if="!items.length && !error"
        :title="tx('messages.emptyTitle', 'No conversations')"
        :subtitle="tx('messages.emptySubtitle', 'Start a chat with a host or seeker')"
        :icon="SearchIcon"
      />
    </div>
  </div>
</template>
