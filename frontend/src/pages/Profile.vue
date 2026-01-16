<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, ChevronRight, CreditCard, HelpCircle, Languages, LogOut, Shield } from 'lucide-vue-next'
import Button from '../components/ui/Button.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'

const router = useRouter()
const showLogout = ref(false)

const items = [
  { label: 'Your Card', icon: CreditCard, action: () => {} },
  { label: 'Security', icon: Shield, action: () => {} },
  { label: 'Notification', icon: Bell, action: () => {} },
  { label: 'Languages', icon: Languages, action: () => router.push('/settings/language') },
  { label: 'Help & Support', icon: HelpCircle, action: () => router.push('/settings/legal') },
]
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <img
        src="https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=300&q=80"
        alt="avatar"
        class="h-16 w-16 rounded-3xl object-cover"
      />
      <div>
        <p class="text-lg font-semibold text-slate-900">Marina Perić</p>
        <p class="text-sm text-muted">@marinatravels</p>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-2 shadow-soft border border-white/60">
      <button
        v-for="item in items"
        :key="item.label"
        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-surface"
        @click="item.action()"
      >
        <component :is="item.icon" class="h-5 w-5 text-primary" />
        <span class="flex-1 text-sm font-semibold text-slate-900">{{ item.label }}</span>
        <ChevronRight class="h-4 w-4 text-muted" />
      </button>
    </div>

    <button
      class="flex w-full items-center gap-3 rounded-xl bg-white px-3 py-3 text-left text-red-500 shadow-soft border border-white/60"
      @click="showLogout = true"
    >
      <LogOut class="h-5 w-5" />
      <span class="flex-1 text-sm font-semibold">Logout</span>
      <ChevronRight class="h-4 w-4" />
    </button>
  </div>

  <ModalSheet v-model="showLogout" title="Are you sure?">
    <p class="text-sm text-muted">You will be logged out of this device. Continue?</p>
    <div class="mt-4 flex gap-2">
      <Button variant="secondary" class="flex-1" @click="showLogout = false">Cancel</Button>
      <Button variant="danger" class="flex-1" @click="showLogout = false">Logout</Button>
    </div>
  </ModalSheet>
</template>
