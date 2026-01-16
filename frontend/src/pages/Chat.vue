<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import { useChatStore } from '../stores/chat'

const route = useRoute()
const chatStore = useChatStore()
const message = ref('')

onMounted(() => {
  chatStore.fetchMessages(route.params.id as string)
})

const messages = computed(() => chatStore.messages[route.params.id as string] || [])

const send = () => {
  if (!message.value.trim()) return
  chatStore.sendMessage(route.params.id as string, message.value)
  message.value = ''
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface">
    <div class="flex-1 space-y-3 px-4 pt-4 pb-28">
      <ChatBubble v-for="msg in messages" :key="msg.id" :from="msg.from" :text="msg.text" :time="msg.time" />
    </div>
    <ChatInput v-model="message" @send="send" />
  </div>
</template>
