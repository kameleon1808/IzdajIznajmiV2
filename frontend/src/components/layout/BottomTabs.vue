<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { CalendarCheck, Heart, Home, MessageSquare, UserRound } from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()

const tabs = [
  { label: 'Home', to: '/', icon: Home },
  { label: 'My Booking', to: '/bookings', icon: CalendarCheck },
  { label: 'Favorites', to: '/favorites', icon: Heart },
  { label: 'Message', to: '/messages', icon: MessageSquare },
  { label: 'Profile', to: '/profile', icon: UserRound },
]

const isActive = (path: string) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

const go = (path: string) => router.push(path)
</script>

<template>
  <nav class="mx-auto max-w-md px-4 pb-4 lg:mx-0 lg:max-w-none lg:px-0 lg:pb-0">
    <div class="safe-bottom rounded-3xl border border-white/60 bg-white/95 p-3 shadow-card backdrop-blur lg:p-4">
      <div class="grid grid-cols-5 gap-2 lg:flex lg:flex-col lg:gap-1">
        <button
          v-for="tab in tabs"
          :key="tab.to"
          class="flex flex-col items-center gap-1 rounded-2xl px-2 py-1 transition hover:bg-surface/70 lg:w-full lg:flex-row lg:justify-start lg:gap-3 lg:px-3 lg:py-2.5 lg:rounded-xl"
          :class="isActive(tab.to) ? 'bg-primary/10 text-primary' : 'text-muted'
          "
          @click="go(tab.to)"
        >
          <component :is="tab.icon" class="h-5 w-5" />
          <span class="text-xs font-semibold lg:text-sm">{{ tab.label }}</span>
        </button>
      </div>
    </div>
  </nav>
</template>
