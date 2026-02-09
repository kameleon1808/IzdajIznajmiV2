<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell } from 'lucide-vue-next'
import { useNotificationStore } from '../../stores/notifications'
import { useAuthStore } from '../../stores/auth'
import { useLanguageStore } from '../../stores/language'

const router = useRouter()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const showDropdown = ref(false)

const unreadCount = computed(() => notificationStore.unreadCount)
const displayCount = computed(() => (unreadCount.value > 99 ? '99+' : unreadCount.value.toString()))
const hasUnread = computed(() => unreadCount.value > 0)

const latestNotifications = computed(() => {
  const unread = notificationStore.unreadNotifications.slice(0, 5)
  const read = notificationStore.notifications.filter((n) => n.isRead).slice(0, 5 - unread.length)
  return [...unread, ...read].slice(0, 5)
})

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.isMockMode) {
    await notificationStore.fetchUnreadCount()
    await notificationStore.fetchNotifications('all', 1)
  }
})

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value
}

const handleNotificationClick = async (notification: any) => {
  if (!notification.isRead) {
    await notificationStore.markRead(notification.id)
  }
  showDropdown.value = false
  if (notification.url) {
    router.push(notification.url)
  } else {
    router.push('/notifications')
  }
}

const goToNotifications = () => {
  showDropdown.value = false
  router.push('/notifications')
}

const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return t('time.justNow')
  if (diffMins < 60) return `${diffMins}${t('time.minutesAgoShort')}`
  if (diffHours < 24) return `${diffHours}${t('time.hoursAgoShort')}`
  if (diffDays < 7) return `${diffDays}${t('time.daysAgoShort')}`
  return date.toLocaleDateString()
}
</script>

<template>
  <div class="relative">
    <button
      class="relative rounded-full bg-white p-2 shadow-soft"
      :aria-label="t('notifications.aria')"
      @click="toggleDropdown"
    >
      <Bell class="h-5 w-5 text-slate-800" />
      <span
        v-if="hasUnread"
        class="absolute right-1 top-1 flex min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-white"
        :class="unreadCount > 9 ? 'px-1.5' : 'px-1'"
      >
        {{ displayCount }}
      </span>
    </button>

    <div
      v-if="showDropdown"
      class="absolute right-0 top-12 z-50 w-80 rounded-2xl bg-white shadow-card border border-white/60 overflow-hidden"
    >
      <div class="max-h-96 overflow-y-auto">
        <div class="p-3 border-b border-line">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">{{ t('notifications.title') }}</h3>
            <button
              v-if="hasUnread"
              @click="notificationStore.markAllRead()"
              class="text-xs text-primary hover:text-primary/80"
            >
              {{ t('notifications.markAllRead') }}
            </button>
          </div>
        </div>
        <div v-if="latestNotifications.length === 0" class="p-4 text-center text-sm text-muted">
          {{ t('notifications.empty') }}
        </div>
        <div v-else class="divide-y divide-line">
          <button
            v-for="notification in latestNotifications"
            :key="notification.id"
            @click="handleNotificationClick(notification)"
            class="w-full p-3 text-left hover:bg-slate-50 transition-colors"
            :class="{ 'bg-primary/5': !notification.isRead }"
          >
            <div class="flex items-start gap-2">
              <div
                v-if="!notification.isRead"
                class="mt-1.5 h-2 w-2 rounded-full bg-primary flex-shrink-0"
              ></div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-900 line-clamp-1">{{ notification.title }}</p>
                <p class="text-xs text-muted line-clamp-2 mt-0.5">{{ notification.body }}</p>
                <p class="text-xs text-muted mt-1">{{ formatTime(notification.createdAt) }}</p>
              </div>
            </div>
          </button>
        </div>
        <div class="p-2 border-t border-line">
          <button
            @click="goToNotifications"
            class="w-full rounded-xl bg-primary/10 px-3 py-2 text-sm font-semibold text-primary hover:bg-primary/20 transition-colors"
          >
            {{ t('notifications.viewAll') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
