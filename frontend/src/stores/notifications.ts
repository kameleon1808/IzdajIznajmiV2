import { defineStore } from 'pinia'
import { apiClient } from '../services/apiClient'

export interface Notification {
  id: string
  type: string
  title: string
  body: string
  data?: Record<string, any>
  url?: string | null
  isRead: boolean
  readAt?: string | null
  createdAt: string
}

interface NotificationPreferences {
  typeSettings: Record<string, boolean>
  digestFrequency: 'none' | 'daily' | 'weekly'
  digestEnabled: boolean
}

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    notifications: [] as Notification[],
    unreadCount: 0,
    unreadCountEtag: null as string | null,
    loading: false,
    error: '',
    preferences: null as NotificationPreferences | null,
    preferencesLoading: false,
  }),
  getters: {
    unreadNotifications: (state) => state.notifications.filter((n) => !n.isRead),
  },
  actions: {
    async fetchUnreadCount() {
      try {
        const { data, status, headers } = await apiClient.get('/notifications/unread-count', {
          headers: this.unreadCountEtag ? { 'If-None-Match': this.unreadCountEtag } : undefined,
          validateStatus: (responseStatus) => (responseStatus >= 200 && responseStatus < 300) || responseStatus === 304,
        })
        const etag = (headers as Record<string, unknown> | undefined)?.etag
        if (typeof etag === 'string' && etag.trim().length) {
          this.unreadCountEtag = etag.trim()
        }
        if (status === 304) {
          return
        }
        this.unreadCount = data.count ?? 0
      } catch (error) {
        console.error('Failed to fetch unread count:', error)
      }
    },
    async fetchNotifications(status: 'unread' | 'all' = 'all', page: number = 1) {
      this.loading = true
      this.error = ''
      try {
        const { data } = await apiClient.get('/notifications', {
          params: { status, page },
        })
        if (page === 1) {
          this.notifications = data.data ?? []
        } else {
          this.notifications = [...this.notifications, ...(data.data ?? [])]
        }
        const payloadUnreadCount =
          data.unread_count ?? data.unreadCount ?? data.meta?.unread_count ?? data.meta?.unreadCount ?? null
        if (typeof payloadUnreadCount === 'number') {
          this.unreadCount = payloadUnreadCount
        }
        return data
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load notifications.'
        throw error
      } finally {
        this.loading = false
      }
    },
    async markRead(notificationId: string) {
      try {
        await apiClient.patch(`/notifications/${notificationId}/read`)
        const notification = this.notifications.find((n) => n.id === notificationId)
        if (notification) {
          notification.isRead = true
          notification.readAt = new Date().toISOString()
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        }
        this.unreadCountEtag = null
      } catch (error) {
        console.error('Failed to mark notification as read:', error)
        throw error
      }
    },
    async markAllRead() {
      try {
        await apiClient.patch('/notifications/read-all')
        this.notifications.forEach((n) => {
          n.isRead = true
          n.readAt = new Date().toISOString()
        })
        this.unreadCount = 0
        this.unreadCountEtag = null
      } catch (error) {
        console.error('Failed to mark all as read:', error)
        throw error
      }
    },
    async fetchPreferences() {
      this.preferencesLoading = true
      try {
        const { data } = await apiClient.get('/notification-preferences')
        this.preferences = {
          typeSettings: data.type_settings ?? {},
          digestFrequency: data.digest_frequency ?? 'none',
          digestEnabled: data.digest_enabled ?? false,
        }
        return this.preferences
      } catch (error) {
        console.error('Failed to fetch preferences:', error)
        throw error
      } finally {
        this.preferencesLoading = false
      }
    },
    async updatePreferences(prefs: Partial<NotificationPreferences>) {
      try {
        const payload: any = {
          digest_frequency: prefs.digestFrequency ?? this.preferences?.digestFrequency ?? 'none',
          digest_enabled: prefs.digestEnabled ?? this.preferences?.digestEnabled ?? false,
        }
        if (prefs.typeSettings) {
          payload.type_settings = prefs.typeSettings
        }
        const { data } = await apiClient.put('/notification-preferences', payload)
        this.preferences = {
          typeSettings: data.type_settings ?? {},
          digestFrequency: data.digest_frequency ?? 'none',
          digestEnabled: data.digest_enabled ?? false,
        }
        return this.preferences
      } catch (error) {
        console.error('Failed to update preferences:', error)
        throw error
      }
    },
  },
})
