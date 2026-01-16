<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  Bell,
  EllipsisVertical,
  MapPin,
  Phone,
  Search,
  Video,
} from 'lucide-vue-next'

const props = defineProps<{ config?: { type?: string; title?: string; location?: string; userName?: string } }>()
const router = useRouter()

const variant = computed(() => props.config?.type ?? 'title')
const location = computed(() => props.config?.location ?? 'Bali, Indonesia')
const userName = computed(() => props.config?.userName ?? 'Hi, Marina')

const goBack = () => router.back()
const goSearch = () => router.push('/search')
</script>

<template>
  <header
    class="sticky top-0 z-30 flex flex-col gap-3 border-b border-white/40 bg-surface/90 px-4 pb-3 pt-4 backdrop-blur-lg"
    v-if="variant !== 'detail'"
  >
    <div v-if="variant === 'home'" class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img
          src="https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=200&q=80"
          alt="avatar"
          class="h-12 w-12 rounded-2xl object-cover shadow-soft"
        />
        <div class="flex flex-col">
          <span class="text-xs text-muted">{{ userName }}</span>
          <div class="flex items-center gap-1 text-sm font-semibold text-slate-900">
            <MapPin class="h-4 w-4 text-primary" />
            <span>{{ location }}</span>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-full bg-white p-2 shadow-soft" aria-label="search" @click="goSearch">
          <Search class="h-5 w-5 text-slate-800" />
        </button>
        <button class="relative rounded-full bg-white p-2 shadow-soft" aria-label="notifications">
          <Bell class="h-5 w-5 text-slate-800" />
          <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
        </button>
      </div>
    </div>

    <div v-else-if="variant === 'search' || variant === 'back'" class="flex items-center gap-3">
      <button class="rounded-full bg-white p-2 shadow-soft" @click="goBack" aria-label="back">
        <ArrowLeft class="h-5 w-5 text-slate-800" />
      </button>
      <div class="flex flex-1 items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-soft">
        <div class="flex flex-col">
          <span class="text-sm font-semibold text-slate-900">{{ props.config?.title ?? 'Discover' }}</span>
          <span v-if="variant === 'search'" class="text-xs text-muted">Find the best place</span>
        </div>
        <Bell v-if="variant === 'search'" class="h-5 w-5 text-slate-800" />
      </div>
    </div>

    <div v-else-if="variant === 'title'" class="flex items-center">
      <div class="flex w-full items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-soft">
        <span class="text-base font-semibold text-slate-900">{{ props.config?.title ?? 'Discover' }}</span>
        <Bell class="h-5 w-5 text-slate-800" />
      </div>
    </div>

    <div v-else-if="variant === 'chat'" class="flex items-center justify-between gap-3 rounded-2xl bg-white px-3 py-2 shadow-soft">
      <div class="flex items-center gap-3">
        <button class="rounded-full bg-primary/10 p-2" @click="goBack" aria-label="back">
          <ArrowLeft class="h-5 w-5 text-primary" />
        </button>
        <img
          src="https://i.pravatar.cc/100?img=12"
          alt="guest"
          class="h-10 w-10 rounded-2xl object-cover"
        />
        <div class="flex flex-col leading-tight">
          <span class="font-semibold text-slate-900">{{ props.config?.title ?? 'Evelyn Hunt' }}</span>
          <span class="text-xs text-primary">Online</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-full bg-primary/10 p-2" aria-label="call">
          <Phone class="h-4 w-4 text-primary" />
        </button>
        <button class="rounded-full bg-primary p-2 text-white shadow-card" aria-label="video">
          <Video class="h-4 w-4" />
        </button>
      </div>
    </div>
  </header>

  <div v-else class="absolute left-0 right-0 top-0 z-30 flex items-center justify-between px-4 pt-6">
    <button class="rounded-full bg-white/80 p-2 shadow-soft backdrop-blur" @click="goBack" aria-label="back">
      <ArrowLeft class="h-5 w-5 text-slate-900" />
    </button>
    <button class="rounded-full bg-white/80 p-2 shadow-soft backdrop-blur" aria-label="more">
      <EllipsisVertical class="h-5 w-5 text-slate-900" />
    </button>
  </div>
</template>
