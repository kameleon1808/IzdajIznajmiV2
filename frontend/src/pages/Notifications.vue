<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, Check } from 'lucide-vue-next'
import { useNotificationStore } from '../stores/notifications'
import { useAuthStore } from '../stores/auth'
import EmptyState from '../components/ui/EmptyState.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import ErrorState from '../components/ui/ErrorState.vue'

const router = useRouter()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()

const activeTab = ref<'unread' | 'all'>('unread')
const currentPage = ref(1)
const hasMore = ref(true)

const notifications = computed(() => notificationStore.notifications)
const error = computed(() => notificationStore.error)
const filteredNotifications = computed(() => {
  if (activeTab.value === 'unread') {
    return notifications.value.filter((n) => !n.isRead)
  }
  return notifications.value
})

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.isMockMode) {
    await loadNotifications()
  }
})

const loadNotifications = async () => {
  try {
    const data = await notificationStore.fetchNotifications(activeTab.value, currentPage.value)
    hasMore.value = data.current_page < data.last_page
  } catch (error) {
    hasMore.value = false
  }
}

const switchTab = async (tab: 'unread' | 'all') => {
  activeTab.value = tab
  currentPage.value = 1
  await loadNotifications()
}

const markRead = async (notificationId: string) => {
  try {
    const notification = notifications.value.find((n) => n.id === notificationId)
    await notificationStore.markRead(notificationId)
    if (notification?.url) {
      router.push(notification.url)
    }
  } catch (error) {
    console.error('Failed to mark as read:', error)
  }
}

const markAllRead = async () => {
  try {
    await notificationStore.markAllRead()
  } catch (error) {
    console.error('Failed to mark all as read:', error)
  }
}

const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString()
}

const getNotificationIcon = (type: string) => {
  const icons: Record<string, string> = {
    'application.created': '📝',
    'application.status_changed': '✅',
    'message.received': '💬',
    'rating.received': '⭐',
    'report.update': '🚨',
    'digest.daily': '📧',
    'digest.weekly': '📧',
    'viewing.requested': '📅',
    'viewing.confirmed': '✅',
    'viewing.cancelled': '⚠️',
  }
  return icons[type] || '🔔'
}
</script>

<template>
  <div class="min-h-screen bg-surface pb-24">
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/40 bg-surface/90 px-4 py-3 backdrop-blur-lg">
      <button class="rounded-full bg-white p-2 shadow-soft" @click="router.back()" aria-label="back">
        <ArrowLeft class="h-5 w-5 text-slate-800" />
      </button>
      <h1 class="flex-1 text-lg font-semibold text-slate-900">Notifications</h1>
      <button
        v-if="activeTab === 'unread' && filteredNotifications.length > 0"
        @click="markAllRead"
        class="text-sm text-primary font-semibold"
      >
        Mark all read
      </button>
    </header>

    <div class="px-4 pt-4">
      <div class="flex gap-2 rounded-2xl bg-white p-1 shadow-soft border border-white/60">
        <button
          @click="switchTab('unread')"
          class="flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
          :class="
            activeTab === 'unread'
              ? 'bg-primary text-white shadow-sm'
              : 'text-slate-600 hover:text-slate-900'
          "
        >
          Unread
          <span
            v-if="notificationStore.unreadCount > 0"
            class="ml-1.5 rounded-full bg-primary/20 px-1.5 py-0.5 text-xs"
            :class="activeTab === 'unread' ? 'bg-white/20' : 'bg-primary/10'"
          >
            {{ notificationStore.unreadCount }}
          </span>
        </button>
        <button
          @click="switchTab('all')"
          class="flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
          :class="
            activeTab === 'all'
              ? 'bg-primary text-white shadow-sm'
              : 'text-slate-600 hover:text-slate-900'
          "
        >
          All
        </button>
      </div>
    </div>

    <div class="px-4 pt-4">
      <ErrorState v-if="error" :message="error" retry-label="Retry" @retry="loadNotifications" class="mb-3" />
      <ListSkeleton v-if="notificationStore.loading && notifications.length === 0" />
      <EmptyState
        v-else-if="filteredNotifications.length === 0"
        :title="activeTab === 'unread' ? 'No unread notifications' : 'No notifications'"
        subtitle="You're all caught up!"
      />
      <div v-else class="space-y-2">
        <button
          v-for="notification in filteredNotifications"
          :key="notification.id"
          @click="markRead(notification.id)"
          class="w-full rounded-2xl bg-white p-4 shadow-soft border border-white/60 text-left hover:shadow-card transition-shadow"
          :class="{ 'bg-primary/5 border-primary/20': !notification.isRead }"
        >
          <div class="flex items-start gap-3">
            <span class="text-2xl flex-shrink-0">{{ getNotificationIcon(notification.type) }}</span>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">{{ notification.title }}</h3>
                <Check
                  v-if="notification.isRead"
                  class="h-4 w-4 text-primary flex-shrink-0 mt-0.5"
                />
                <div
                  v-else
                  class="h-2 w-2 rounded-full bg-primary flex-shrink-0 mt-1.5"
                ></div>
              </div>
              <p class="text-sm text-muted mt-1 line-clamp-2">{{ notification.body }}</p>
              <p class="text-xs text-muted mt-2">{{ formatTime(notification.createdAt) }}</p>
            </div>
          </div>
        </button>
      </div>
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
