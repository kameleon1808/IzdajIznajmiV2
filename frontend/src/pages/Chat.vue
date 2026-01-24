<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import Button from '../components/ui/Button.vue'
import { leaveRating } from '../services'
import ErrorState from '../components/ui/ErrorState.vue'

const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()
const message = ref('')
const auth = useAuthStore()
const toast = useToastStore()
const ratingScore = ref(0)
const ratingComment = ref('')
const ratingSubmitting = ref(false)
const ratingSubmitted = ref(false)

const conversationId = computed(() => route.params.id as string | undefined)
const loading = computed(() => chatStore.loading || chatStore.resolving)
const error = computed(() => chatStore.error)
const conversation = computed(() => {
  const activeId = chatStore.activeConversationId || conversationId.value
  return chatStore.conversations.find((c) => c.id === activeId)
})
const messages = computed(() => {
  const activeId = chatStore.activeConversationId || conversationId.value
  if (!activeId) return []
  return chatStore.messages[activeId] || []
})
const rateeId = computed(() => {
  if (!conversation.value?.participants) return null
  if (auth.hasRole('seeker')) return conversation.value.participants.landlordId
  if (auth.hasRole('landlord')) return conversation.value.participants.tenantId
  return null
})

watch(
  () => conversation.value?.id,
  () => {
    ratingScore.value = 0
    ratingComment.value = ''
    ratingSubmitted.value = false
  },
)

const resolveConversation = async (id?: string) => {
  if (!id) return
  try {
    chatStore.clearError()
    chatStore.setActiveConversation(id)
    await chatStore.openByConversationId(id)
  } catch (err) {
    toast.push({ title: 'Chat unavailable', message: (err as Error).message, type: 'error' })
  }
}

const retryChat = async () => {
  try {
    await chatStore.fetchConversations()
    if (conversation.value?.id) {
      await chatStore.fetchMessages(conversation.value.id)
    }
  } catch (err) {
    toast.push({ title: 'Retry failed', message: (err as Error).message, type: 'error' })
  }
}

onMounted(() => {
  if (!chatStore.conversations.length) {
    chatStore.fetchConversations()
  }
  resolveConversation(conversationId.value)
})

watch(
  () => conversationId.value,
  (val) => resolveConversation(val),
)

const send = async () => {
  if (!message.value.trim()) return
  if (!conversation.value?.id) {
    toast.push({ title: 'Select a chat', message: 'Open a conversation first.', type: 'error' })
    return
  }
  try {
    await chatStore.sendMessage(conversation.value.id, message.value)
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
    <div class="mx-4 mt-4" v-if="error">
      <ErrorState :message="error" retry-label="Retry" @retry="retryChat" />
    </div>
    <div class="flex-1 space-y-3 px-4 pt-4 pb-28">
      <ListSkeleton v-if="loading" :count="3" />
      <template v-else>
        <div v-if="conversation" class="rounded-2xl border border-line bg-white p-3 shadow-soft">
          <div class="flex items-center justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ conversation.listingTitle || 'Chat thread' }}</p>
              <p class="text-xs text-muted">
                {{ conversation.userName }}
                <span v-if="conversation.listingCity">· {{ conversation.listingCity }}</span>
              </p>
            </div>
            <Button
              size="sm"
              variant="secondary"
              :disabled="!conversation.listingId"
              @click="router.push(`/listing/${conversation.listingId}`)"
            >
              View listing
            </Button>
          </div>
        </div>
        <ChatBubble v-for="msg in messages" :key="msg.id" :from="msg.from" :text="msg.text" :time="msg.time" />
        <EmptyState
          v-if="!messages.length && !error && conversation"
          title="No messages"
          subtitle="Say hello to start the conversation"
        />
        <EmptyState
          v-else-if="!conversation && !error"
          title="No conversation selected"
          subtitle="Open chat from Notifications or Messages."
        >
          <template #actions>
            <Button variant="primary" @click="router.push('/messages')">Back to messages</Button>
          </template>
        </EmptyState>
        <div v-if="conversation" class="mt-4 space-y-3 rounded-2xl border border-line bg-white p-4 shadow-soft">
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
    <ChatInput v-model="message" :disabled="!conversation" @send="send" />
  </div>
</template>
