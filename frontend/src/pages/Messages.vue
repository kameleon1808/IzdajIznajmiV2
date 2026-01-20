<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus, Search as SearchIcon } from 'lucide-vue-next'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import Input from '../components/ui/Input.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useRouter } from 'vue-router'

const chatStore = useChatStore()
const router = useRouter()
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
</script>

<template>
  <div class="space-y-4">
    <ErrorBanner v-if="error" :message="error" />
    <Input v-model="query" placeholder="Search messages" :left-icon="SearchIcon" />

    <ListSkeleton v-if="loading" :count="3" />
    <div v-else class="space-y-3">
      <div
        v-for="conv in items"
        :key="conv.id"
        class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
        @click="router.push(`/chat/${conv.id}`)"
      >
        <div class="relative">
          <img :src="conv.listingCoverImage || conv.avatarUrl" alt="avatar" class="h-12 w-12 rounded-2xl object-cover" />
          <span
            v-if="conv.online"
            class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-primary"
          ></span>
        </div>
        <div class="flex-1">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-900">{{ conv.listingTitle || conv.userName }}</p>
              <p class="text-xs text-muted">{{ conv.userName }}</p>
            </div>
            <span class="text-xs text-muted">{{ conv.time }}</span>
          </div>
          <p class="truncate text-sm text-muted">{{ conv.lastMessage }}</p>
          <span v-if="conv.unreadCount" class="ml-auto mt-1 inline-block rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">
            {{ conv.unreadCount }}
          </span>
        </div>
      </div>
      <EmptyState
        v-if="!items.length && !error"
        title="No conversations"
        subtitle="Start a chat with a host or seeker"
        :icon="SearchIcon"
      />
    </div>

    <button
      class="fixed bottom-24 right-6 rounded-full bg-primary p-4 text-white shadow-card"
      aria-label="new message"
    >
      <Plus class="h-5 w-5" />
    </button>
  </div>
</template>
