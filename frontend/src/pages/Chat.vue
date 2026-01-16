<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'

const route = useRoute()
const chatStore = useChatStore()
const message = ref('')

onMounted(() => {
  chatStore.fetchMessages(route.params.id as string)
})

const messages = computed(() => chatStore.messages[route.params.id as string] || [])
const loading = computed(() => chatStore.loading)
const error = computed(() => chatStore.error)

const send = () => {
  if (!message.value.trim()) return
  chatStore.sendMessage(route.params.id as string, message.value)
  message.value = ''
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface">
    <ErrorBanner v-if="error" :message="error" class="mx-4 mt-4" />
    <div class="flex-1 space-y-3 px-4 pt-4 pb-28">
      <ListSkeleton v-if="loading" :count="3" />
      <template v-else>
        <ChatBubble v-for="msg in messages" :key="msg.id" :from="msg.from" :text="msg.text" :time="msg.time" />
        <EmptyState v-if="!messages.length && !error" title="No messages" subtitle="Say hello to start the conversation" />
      </template>
    </div>
    <ChatInput v-model="message" @send="send" />
  </div>
</template>
