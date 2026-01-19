<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import Button from '../components/ui/Button.vue'
import { leaveRating } from '../services'

const route = useRoute()
const chatStore = useChatStore()
const message = ref('')
const auth = useAuthStore()
const toast = useToastStore()
const ratingScore = ref(0)
const ratingComment = ref('')
const ratingSubmitting = ref(false)
const ratingSubmitted = ref(false)

onMounted(() => {
  if (!chatStore.conversations.length) {
    chatStore.fetchConversations()
  }
  chatStore.fetchMessages(route.params.id as string)
})

const messages = computed(() => chatStore.messages[route.params.id as string] || [])
const loading = computed(() => chatStore.loading)
const error = computed(() => chatStore.error)
const conversation = computed(() => chatStore.conversations.find((c) => c.id === (route.params.id as string)))
const rateeId = computed(() => {
  if (!conversation.value?.participants) return null
  if (auth.hasRole('seeker')) return conversation.value.participants.landlordId
  if (auth.hasRole('landlord')) return conversation.value.participants.tenantId
  return null
})

const send = async () => {
  if (!message.value.trim()) return
  try {
    await chatStore.sendMessage(route.params.id as string, message.value)
    message.value = ''
  } catch (e) {
    // error state handled in store
  }
}

const submitRating = async () => {
  if (!conversation.value?.listingId || !rateeId.value || ratingScore.value < 1) {
    toast.push({ title: 'Rating incomplete', message: 'Select stars first.', type: 'error' })
    return
  }
  ratingSubmitting.value = true
  try {
    await leaveRating(conversation.value.listingId, rateeId.value, {
      rating: ratingScore.value,
      comment: ratingComment.value || undefined,
    })
    ratingSubmitted.value = true
    toast.push({ title: 'Thank you!', message: 'Rating submitted.', type: 'success' })
  } catch (err) {
    toast.push({ title: 'Could not rate', message: (err as any).message ?? 'Try again later.', type: 'error' })
  } finally {
    ratingSubmitting.value = false
  }
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
        <div
          v-if="conversation"
          class="mt-4 space-y-3 rounded-2xl border border-line bg-white p-4 shadow-soft"
        >
          <div class="flex items-center justify-between">
            <p class="font-semibold text-slate-900">Leave a rating</p>
            <span class="text-xs text-muted">Listing #{{ conversation?.listingId }}</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-for="n in 5"
              :key="n"
              class="h-10 w-10 rounded-full border border-line text-lg font-semibold"
              :class="n <= ratingScore ? 'bg-primary text-white' : 'bg-surface text-slate-700'"
              @click="ratingScore = n"
              type="button"
            >
              {{ n }}★
            </button>
          </div>
          <textarea
            v-model="ratingComment"
            rows="3"
            class="w-full rounded-2xl border border-line bg-surface px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none"
            placeholder="Share your experience (optional)"
          ></textarea>
          <Button
            block
            size="md"
            variant="primary"
            :disabled="ratingSubmitting || ratingSubmitted || ratingScore < 1"
            @click="submitRating"
          >
            {{ ratingSubmitted ? 'Rating submitted' : ratingSubmitting ? 'Submitting...' : 'Submit rating' }}
          </Button>
        </div>
      </template>
    </div>
    <ChatInput v-model="message" @send="send" />
  </div>
</template>
