<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  EllipsisVertical,
  MapPin,
  Phone,
  Search,
  Video,
} from 'lucide-vue-next'
import NotificationBell from '../notifications/NotificationBell.vue'
import AvatarPlaceholder from '../ui/AvatarPlaceholder.vue'
import { useAuthStore } from '../../stores/auth'
import { useChatStore } from '../../stores/chat'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{ config?: { type?: string; title?: string; titleKey?: string; location?: string; userName?: string } }>()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const chatStore = useChatStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const variant = computed(() => props.config?.type ?? 'title')
const location = computed(() => props.config?.location ?? auth.user?.residentialAddress ?? t('topbar.defaultLocation'))
const userName = computed(() => props.config?.userName ?? auth.user?.name ?? auth.user?.fullName ?? t('topbar.defaultGreeting'))
const homeAvatarUrl = computed(() => auth.user?.avatarUrl ?? null)
const titleText = computed(() => {
  const key = props.config?.titleKey
  if (key) return t(key as Parameters<typeof languageStore.t>[0])
  return props.config?.title ?? t('topbar.discover')
})
const chatConversation = computed(() => {
  const activeId = (route.params.id as string | undefined) || chatStore.activeConversationId || undefined
  if (!activeId) return undefined
  return chatStore.conversations.find((c) => c.id === activeId)
})
const chatProfileId = computed(() => {
  const participants = chatConversation.value?.participants
  if (!participants) return null
  if (auth.hasRole('seeker')) return String(participants.landlordId)
  if (auth.hasRole('landlord')) return String(participants.tenantId)
  const authId = auth.user?.id ? String(auth.user.id) : null
  if (authId && String(participants.landlordId) === authId) return String(participants.tenantId)
  if (authId && String(participants.tenantId) === authId) return String(participants.landlordId)
  return String(participants.landlordId ?? participants.tenantId ?? '')
})
const chatUserName = computed(() => chatConversation.value?.userName || props.config?.title || t('titles.chat'))
const chatAvatar = computed(() => chatConversation.value?.avatarUrl || 'https://i.pravatar.cc/100?img=12')
const chatOnline = computed(() => chatConversation.value?.online ?? false)

const goBack = () => router.back()
const goSearch = () => router.push('/search')
const goProfile = () => {
  if (!chatProfileId.value) return
  router.push(`/users/${chatProfileId.value}`)
}
</script>

<template>
  <header
    v-if="variant !== 'detail'"
    :class="[
      variant === 'chat'
        ? 'fixed left-0 right-0 top-0 z-30 mx-auto max-w-md px-4 pt-[calc(1rem+env(safe-area-inset-top))] lg:sticky lg:left-auto lg:right-auto lg:max-w-none lg:px-0 lg:pt-4 lg:pr-6'
        : 'sticky top-0 z-30 px-4 pt-4 lg:pl-0 lg:pr-6',
    ]"
  >
    <div v-if="variant === 'home'" class="flex items-center justify-between rounded-2xl bg-surface-2 px-4 py-3 shadow-soft border border-border">
      <div class="flex items-center gap-3">
        <img
          v-if="homeAvatarUrl"
          :src="homeAvatarUrl"
          :alt="t('topbar.avatarAlt')"
          class="h-12 w-12 rounded-2xl object-cover shadow-soft"
        />
        <AvatarPlaceholder v-else :alt="t('topbar.avatarAlt')" />
        <div class="flex flex-col">
          <span class="text-xs text-muted">{{ userName }}</span>
          <div class="flex items-center gap-1 text-sm font-semibold text-text">
            <MapPin class="h-4 w-4 text-primary" />
            <span>{{ location }}</span>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="rounded-full bg-surface-2 p-2 shadow-soft border border-border transition-colors duration-150 hover:bg-primary-soft"
          :aria-label="t('topbar.search')"
          @click="goSearch"
        >
          <Search class="h-5 w-5 text-text-2" />
        </button>
        <NotificationBell />
      </div>
    </div>

    <div v-else-if="variant === 'search' || variant === 'back'" class="flex items-center gap-3">
      <button
        class="rounded-full bg-surface-2 p-2 shadow-soft border border-border transition-colors duration-150 hover:bg-primary-soft"
        @click="goBack"
        :aria-label="t('topbar.back')"
      >
        <ArrowLeft class="h-5 w-5 text-text-2" />
      </button>
      <div class="flex flex-1 items-center justify-between rounded-2xl bg-surface-2 px-4 py-3 shadow-soft border border-border">
        <div class="flex flex-col">
          <span class="text-sm font-semibold text-text">{{ titleText }}</span>
          <span v-if="variant === 'search'" class="text-xs text-muted">{{ t('topbar.findBestPlace') }}</span>
        </div>
        <NotificationBell v-if="variant === 'search'" />
      </div>
    </div>

    <div v-else-if="variant === 'title'" class="flex items-center">
      <div class="flex w-full items-center justify-between rounded-2xl bg-surface-2 px-4 py-3 shadow-soft border border-border">
        <span class="text-base font-semibold text-text">{{ titleText }}</span>
        <NotificationBell />
      </div>
    </div>

    <div v-else-if="variant === 'chat'" class="flex items-center justify-between gap-3 rounded-2xl bg-surface-2 px-3 py-2 shadow-soft border border-border">
      <div
        class="flex items-center gap-3 rounded-2xl px-2 py-1 transition-colors duration-150 hover:bg-surface-2 cursor-pointer"
        role="button"
        tabindex="0"
        @click="goProfile"
        @keydown.enter="goProfile"
        @keydown.space.prevent="goProfile"
      >
        <button class="rounded-full bg-primary/10 p-2" @click.stop="goBack" aria-label="back">
          <ArrowLeft class="h-5 w-5 text-primary" />
        </button>
        <img
          :src="chatAvatar"
          :alt="t('topbar.guestAlt')"
          class="h-10 w-10 rounded-2xl object-cover"
        />
        <div class="flex flex-col leading-tight">
          <span class="font-semibold text-text">{{ chatUserName }}</span>
          <span class="text-xs" :class="chatOnline ? 'text-primary' : 'text-muted'">
            {{ chatOnline ? t('topbar.online') : t('topbar.offline') }}
          </span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-full bg-primary/10 p-2" :aria-label="t('topbar.call')">
          <Phone class="h-4 w-4 text-primary" />
        </button>
        <button class="rounded-full bg-primary p-2 text-white shadow-card" :aria-label="t('topbar.video')">
          <Video class="h-4 w-4" />
        </button>
      </div>
    </div>
  </header>

  <div v-else class="absolute left-0 right-0 top-0 z-30 flex items-center justify-between px-4 pt-6">
    <button
      class="rounded-full bg-surface-2 p-2 shadow-soft border border-border backdrop-blur transition-colors duration-150 hover:bg-primary-soft"
      @click="goBack"
      :aria-label="t('topbar.back')"
    >
      <ArrowLeft class="h-5 w-5 text-text" />
    </button>
    <button
      class="rounded-full bg-surface-2 p-2 shadow-soft border border-border backdrop-blur transition-colors duration-150 hover:bg-primary-soft"
      :aria-label="t('topbar.more')"
    >
      <EllipsisVertical class="h-5 w-5 text-text" />
    </button>
  </div>
</template>
