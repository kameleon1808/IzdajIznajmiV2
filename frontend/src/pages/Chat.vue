<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ChatBubble from '../components/chat/ChatBubble.vue'
import ChatInput from '../components/chat/ChatInput.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useChatStore } from '../stores/chat'
import { useNotificationStore } from '../stores/notifications'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import Button from '../components/ui/Button.vue'
import { getTypingStatus, getUsersPresence, pingPresence, setTypingStatus } from '../services'
import ErrorState from '../components/ui/ErrorState.vue'
import { PollingBackoff } from '../utils/pollingBackoff'

const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()
const notificationStore = useNotificationStore()
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
const messagesViewport = ref<HTMLElement | null>(null)
const isNearBottom = ref(true)
const viewportHeight = ref(0)
const isDesktop = ref(false)
const composerHeight = ref(96)

let typingPollTimer: number | null = null
let presencePollTimer: number | null = null
let presencePingTimer: number | null = null
let typingStopTimer: number | null = null
let messagePollTimeout: number | null = null
let messagePollInFlight = false
let lastTypingSentAt = 0
const BOTTOM_THRESHOLD_PX = 64
const MESSAGES_BOTTOM_GAP_PX = 12
const MESSAGE_POLL_MIN_MS = 3000
const MESSAGE_POLL_MAX_MS = 30000
const messagePollBackoff = new PollingBackoff(MESSAGE_POLL_MIN_MS, MESSAGE_POLL_MAX_MS)

const isDocumentVisible = () => document.visibilityState === 'visible'

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
    await syncScrollToLatest(true)
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
    messagePollBackoff.recordActivity()
    startMessagePolling(conversation.value.id, false)
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
  if (!id || !isDocumentVisible()) return
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
  if (!otherUserId.value || !isDocumentVisible()) return
  const refresh = async () => {
    try {
      const userId = String(otherUserId.value)
      const data = await getUsersPresence([userId])
      otherOnline.value = Boolean(
        data.find((item: { userId: string; online: boolean; expiresIn: number }) => item.userId === userId)?.online,
      )
    } catch (e) {
      otherOnline.value = false
    }
  }
  refresh()
  presencePollTimer = window.setInterval(refresh, 30000)
}

const startPresencePing = () => {
  if (presencePingTimer) window.clearInterval(presencePingTimer)
  if (!isDocumentVisible()) return

  const ping = async () => {
    try {
      await pingPresence()
    } catch (e) {
      // ignore ping failures
    }
  }

  ping()
  presencePingTimer = window.setInterval(ping, 25000)
}

const updateViewportMetrics = () => {
  isDesktop.value = window.innerWidth >= 1024
  const viewport = window.visualViewport
  viewportHeight.value = Math.round(viewport?.height ?? window.innerHeight)
}

const chatContainerStyle = computed(() => {
  if (isDesktop.value) return undefined
  const height = viewportHeight.value || window.innerHeight
  return { height: `${height}px`, minHeight: `${height}px` }
})

const messagesViewportStyle = computed(() => {
  if (isDesktop.value) return { paddingBottom: '16px' }
  const padding = Math.max(16, Math.round(composerHeight.value + MESSAGES_BOTTOM_GAP_PX))
  return { paddingBottom: `${padding}px` }
})

const onComposerMetrics = (metrics: { height: number; keyboardOffset: number }) => {
  composerHeight.value = metrics.height || composerHeight.value
  if (isNearBottom.value) {
    syncScrollToLatest(true)
  }
}

const updateNearBottom = () => {
  const el = messagesViewport.value
  if (!el) return
  const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight
  isNearBottom.value = distanceFromBottom <= BOTTOM_THRESHOLD_PX
}

const scrollToBottom = (behavior: ScrollBehavior = 'auto') => {
  const el = messagesViewport.value
  if (!el) return
  el.scrollTo({ top: el.scrollHeight, behavior })
  isNearBottom.value = true
}

const syncScrollToLatest = async (force = false) => {
  await nextTick()
  requestAnimationFrame(() => {
    if (force || isNearBottom.value) {
      scrollToBottom('auto')
    }
  })
}

const clearMessagePollTimeout = () => {
  if (messagePollTimeout) {
    window.clearTimeout(messagePollTimeout)
    messagePollTimeout = null
  }
}

const scheduleMessagePoll = (id: string, delayMs = messagePollBackoff.current()) => {
  clearMessagePollTimeout()
  if (!isDocumentVisible() || conversation.value?.id !== id) return
  messagePollTimeout = window.setTimeout(() => {
    pollMessages(id)
  }, delayMs)
}

const pollMessages = async (id: string) => {
  if (messagePollInFlight || !isDocumentVisible() || conversation.value?.id !== id) {
    return
  }

  messagePollInFlight = true
  try {
    const result = await chatStore.fetchMessages(id, { silent: true, incremental: true })
    if (!result.failed && result.newMessages > 0) {
      messagePollBackoff.recordActivity()
    } else {
      messagePollBackoff.recordIdle()
    }
  } finally {
    messagePollInFlight = false
    scheduleMessagePoll(id)
  }
}

const startMessagePolling = (id?: string, immediate = true) => {
  clearMessagePollTimeout()
  messagePollInFlight = false
  if (!id || !isDocumentVisible()) return

  messagePollBackoff.recordActivity()
  if (immediate) {
    pollMessages(id)
    return
  }
  scheduleMessagePoll(id)
}

const stopRealtimePolling = () => {
  if (typingPollTimer) window.clearInterval(typingPollTimer)
  if (presencePollTimer) window.clearInterval(presencePollTimer)
  if (presencePingTimer) window.clearInterval(presencePingTimer)
  clearMessagePollTimeout()
}

const handleVisibilityOrFocus = () => {
  if (!isDocumentVisible()) {
    stopRealtimePolling()
    return
  }

  startMessagePolling(conversation.value?.id)
  startTypingPoll(conversation.value?.id)
  startPresencePolling()
  startPresencePing()
}

onMounted(() => {
  updateViewportMetrics()
  window.addEventListener('resize', updateViewportMetrics)
  const viewport = window.visualViewport
  if (viewport) {
    viewport.addEventListener('resize', updateViewportMetrics)
    viewport.addEventListener('scroll', updateViewportMetrics)
  }
  if (!chatStore.conversations.length) {
    chatStore.fetchConversations()
  }
  resolveConversation(conversationId.value)
  document.addEventListener('visibilitychange', handleVisibilityOrFocus)
  window.addEventListener('focus', handleVisibilityOrFocus)
  handleVisibilityOrFocus()
  syncScrollToLatest(true)
})

watch(
  () => conversationId.value,
  (val) => resolveConversation(val),
)

watch(
  () => conversation.value?.id,
  async (id) => {
    if (id) {
      notificationStore.markMessageNotificationsForConversation(id)
      await notificationStore.fetchUnreadCount()
    }
    startMessagePolling(id)
    startTypingPoll(id)
    startPresencePolling()
    startPresencePing()
    isNearBottom.value = true
    syncScrollToLatest(true)
  },
)

watch(
  () => messages.value[messages.value.length - 1]?.id ?? '',
  (nextId, prevId) => {
    if (nextId && nextId !== prevId) {
      messagePollBackoff.recordActivity()
      startMessagePolling(conversation.value?.id, false)
    }
    syncScrollToLatest()
  },
)

watch(
  () => loading.value,
  (isLoading) => {
    if (!isLoading) {
      syncScrollToLatest(true)
    }
  },
)

watch(
  () => otherUserId.value,
  () => startPresencePolling(),
)

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateViewportMetrics)
  const viewport = window.visualViewport
  if (viewport) {
    viewport.removeEventListener('resize', updateViewportMetrics)
    viewport.removeEventListener('scroll', updateViewportMetrics)
  }
  stopRealtimePolling()
  document.removeEventListener('visibilitychange', handleVisibilityOrFocus)
  window.removeEventListener('focus', handleVisibilityOrFocus)
  if (typingStopTimer) window.clearTimeout(typingStopTimer)
  setTyping(false)
})
</script>

<template>
  <div
    class="flex min-h-0 flex-col overflow-hidden bg-surface pt-24 lg:min-h-screen lg:overflow-visible lg:pt-0"
    :style="chatContainerStyle"
  >
    <div class="mx-4 mt-4" v-if="error">
      <ErrorState :message="error" :retry-label="t('chat.retry')" @retry="retryChat" />
    </div>
    <div
      ref="messagesViewport"
      class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pt-4 lg:overscroll-auto"
      :style="messagesViewportStyle"
      @scroll.passive="updateNearBottom"
    >
      <div class="flex min-h-full flex-col justify-end space-y-3">
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
    </div>
    <ChatInput
      v-model="message"
      v-model:attachments="attachments"
      :disabled="!conversation"
      :uploading="uploading"
      :upload-progress="uploadProgress"
      @metrics="onComposerMetrics"
      @send="send"
      @blur="setTyping(false)"
    />
  </div>
</template>
