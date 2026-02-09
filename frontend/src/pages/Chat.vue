<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import Button from '../components/ui/Button.vue'
import { getTypingStatus, getUserPresence, pingPresence, setTypingStatus } from '../services'
import ErrorState from '../components/ui/ErrorState.vue'
import { getEcho } from '../services/echo'
import type { Message as ChatMessage } from '../types'

type ReverbMessage = ChatMessage & { body?: string }

const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()
const message = ref('')
const attachments = ref<File[]>([])
const uploading = ref(false)
const uploadProgress = ref<number | null>(null)
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const typingUsers = ref<Array<{ id: string; name: string; expiresIn: number }>>([])
const otherOnline = ref(false)

let typingPollTimer: number | null = null
let presencePollTimer: number | null = null
let presencePingTimer: number | null = null
let typingStopTimer: number | null = null
let attachmentPollTimer: number | null = null
let attachmentPollAttempts = 0
let lastTypingSentAt = 0
let activeChannelName: string | null = null

const echo = getEcho()

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
const hasPendingThumbs = computed(() =>
  messages.value.some((msg) => (msg.attachments ?? []).some((att) => att.kind === 'image' && !att.thumbUrl)),
)
const rateeId = computed(() => {
  if (!conversation.value?.participants) return null
  if (auth.hasRole('seeker')) return conversation.value.participants.landlordId
  if (auth.hasRole('landlord')) return conversation.value.participants.tenantId
  return null
})
const otherUserId = computed(() => rateeId.value)

watch(
  () => conversation.value?.id,
  () => {
    attachments.value = []
    typingUsers.value = []
    otherOnline.value = false
  },
)

const resolveConversation = async (id?: string) => {
  if (!id) return
  try {
    chatStore.clearError()
    chatStore.setActiveConversation(id)
    await chatStore.openByConversationId(id)
  } catch (err) {
    toast.push({ title: t('chat.unavailable'), message: (err as Error).message, type: 'error' })
  }
}

const retryChat = async () => {
  try {
    await chatStore.fetchConversations()
    if (conversation.value?.id) {
      await chatStore.fetchMessages(conversation.value.id)
    }
  } catch (err) {
    toast.push({ title: t('chat.retryFailed'), message: (err as Error).message, type: 'error' })
  }
}

const send = async () => {
  if (!message.value.trim() && !attachments.value.length) return
  if (!conversation.value?.id) {
    toast.push({ title: t('chat.selectChat'), message: t('chat.openConversationFirst'), type: 'error' })
    return
  }
  try {
    uploading.value = attachments.value.length > 0
    uploadProgress.value = 0
    await chatStore.sendMessage(conversation.value.id, message.value, attachments.value, (progress) => {
      uploadProgress.value = progress
    })
    message.value = ''
    attachments.value = []
    await setTyping(false)
  } catch (e) {
    // error state handled in store
  } finally {
    uploading.value = false
    uploadProgress.value = null
  }
}

const setTyping = async (isTyping: boolean) => {
  if (!conversation.value?.id) return
  try {
    const now = Date.now()
    if (isTyping && now - lastTypingSentAt < 1000) return
    lastTypingSentAt = now
    await setTypingStatus(conversation.value.id, isTyping)
  } catch (e) {
    // ignore transient typing errors
  }
}

const scheduleTypingStop = () => {
  if (typingStopTimer) window.clearTimeout(typingStopTimer)
  typingStopTimer = window.setTimeout(() => setTyping(false), 1600)
}

watch(
  () => message.value,
  (val) => {
    if (!conversation.value?.id) return
    if (!val.trim()) {
      setTyping(false)
      return
    }
    setTyping(true)
    scheduleTypingStop()
  },
)

const startTypingPoll = (id?: string) => {
  if (typingPollTimer) window.clearInterval(typingPollTimer)
  if (!id) return
  const fetchTyping = async () => {
    try {
      const data = await getTypingStatus(id)
      typingUsers.value = data.users
    } catch (e) {
      typingUsers.value = []
    }
  }
  fetchTyping()
  typingPollTimer = window.setInterval(fetchTyping, 4000)
}

const startPresencePolling = () => {
  if (presencePollTimer) window.clearInterval(presencePollTimer)
  if (!otherUserId.value) return
  const refresh = async () => {
    try {
      const data = await getUserPresence(String(otherUserId.value))
      otherOnline.value = data.online
    } catch (e) {
      otherOnline.value = false
    }
  }
  refresh()
  presencePollTimer = window.setInterval(refresh, 30000)
}

const startPresencePing = () => {
  if (presencePingTimer) window.clearInterval(presencePingTimer)
  presencePingTimer = window.setInterval(async () => {
    try {
      await pingPresence()
    } catch (e) {
      // ignore ping failures
    }
  }, 25000)
}

const startRealtime = (id?: string) => {
  if (!echo) return
  if (activeChannelName) {
    echo.leave(activeChannelName)
    activeChannelName = null
  }
  if (!id) return
  const channelName = `conversation.${id}`
  activeChannelName = channelName
  echo.private(channelName).listen('.message.sent', (payload: { message: ReverbMessage }) => {
    const message = payload?.message
    if (!message?.conversationId) return
    const authId = auth.user?.id ? String(auth.user.id) : ''
    const senderId = message.senderId ? String(message.senderId) : ''
    if (authId && senderId && authId === senderId) return
    chatStore.receiveMessage({
      ...message,
      conversationId: String(message.conversationId),
      from: authId && senderId && authId === senderId ? 'me' : 'them',
      text: message.text ?? message.body ?? '',
    })
  })
}

const stopRealtime = () => {
  if (!echo || !activeChannelName) return
  echo.leave(activeChannelName)
  activeChannelName = null
}

const stopAttachmentPolling = () => {
  if (attachmentPollTimer) window.clearInterval(attachmentPollTimer)
  attachmentPollTimer = null
  attachmentPollAttempts = 0
}

const startAttachmentPolling = (id?: string) => {
  stopAttachmentPolling()
  if (!id || !hasPendingThumbs.value) return
  const poll = async () => {
    if (!conversation.value?.id || !hasPendingThumbs.value) {
      stopAttachmentPolling()
      return
    }
    attachmentPollAttempts += 1
    await chatStore.fetchMessages(id, { silent: true })
    if (attachmentPollAttempts >= 6) {
      stopAttachmentPolling()
    }
  }
  poll()
  attachmentPollTimer = window.setInterval(poll, 5000)
}

onMounted(() => {
  if (!chatStore.conversations.length) {
    chatStore.fetchConversations()
  }
  resolveConversation(conversationId.value)
  pingPresence()
  startPresencePing()
})

watch(
  () => conversationId.value,
  (val) => resolveConversation(val),
)

watch(
  () => conversation.value?.id,
  (id) => {
    startTypingPoll(id)
    startPresencePolling()
    startAttachmentPolling(id)
    startRealtime(id)
  },
)

watch(
  () => otherUserId.value,
  () => startPresencePolling(),
)

watch(
  () => hasPendingThumbs.value,
  (pending) => {
    if (pending) {
      startAttachmentPolling(conversation.value?.id)
    } else {
      stopAttachmentPolling()
    }
  },
)

onBeforeUnmount(() => {
  if (typingPollTimer) window.clearInterval(typingPollTimer)
  if (presencePollTimer) window.clearInterval(presencePollTimer)
  if (presencePingTimer) window.clearInterval(presencePingTimer)
  if (typingStopTimer) window.clearTimeout(typingStopTimer)
  stopAttachmentPolling()
  stopRealtime()
  setTyping(false)
})
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface">
    <div class="mx-4 mt-4" v-if="error">
      <ErrorState :message="error" :retry-label="t('chat.retry')" @retry="retryChat" />
    </div>
    <div class="flex-1 space-y-3 px-4 pt-4 pb-28">
      <ListSkeleton v-if="loading" :count="3" />
      <template v-else>
        <div v-if="conversation" class="rounded-2xl border border-line bg-white p-3 shadow-soft">
          <div class="flex items-center justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ conversation.listingTitle || t('chat.thread') }}</p>
              <p class="text-xs text-muted">
                {{ conversation.userName }}
                <span v-if="conversation.listingCity">· {{ conversation.listingCity }}</span>
                <span
                  v-if="otherOnline"
                  class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                  {{ t('chat.online') }}
                </span>
              </p>
              <p v-if="typingUsers.length" class="mt-1 text-[11px] text-primary">
                {{ typingUsers[0]?.name || conversation.userName }} {{ t('chat.typing') }}
              </p>
            </div>
            <Button
              size="sm"
              variant="secondary"
              :disabled="!conversation.listingId"
              @click="router.push(`/listing/${conversation.listingId}`)"
            >
              {{ t('chat.viewListing') }}
            </Button>
          </div>
        </div>
        <ChatBubble
          v-for="msg in messages"
          :key="msg.id"
          :from="msg.from"
          :text="msg.text"
          :attachments="msg.attachments"
          :time="msg.time"
        />
        <EmptyState
          v-if="!messages.length && !error && conversation"
          :title="t('chat.noMessagesTitle')"
          :subtitle="t('chat.noMessagesSubtitle')"
        />
        <EmptyState
          v-else-if="!conversation && !error"
          :title="t('chat.noConversationTitle')"
          :subtitle="t('chat.noConversationSubtitle')"
        >
          <template #actions>
            <Button variant="primary" @click="router.push('/messages')">{{ t('chat.backToMessages') }}</Button>
          </template>
        </EmptyState>
      </template>
    </div>
    <ChatInput
      v-model="message"
      v-model:attachments="attachments"
      :disabled="!conversation"
      :uploading="uploading"
      :upload-progress="uploadProgress"
      @send="send"
      @blur="setTyping(false)"
    />
  </div>
</template>
