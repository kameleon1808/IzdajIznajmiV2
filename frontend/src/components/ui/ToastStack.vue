<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next'
import { useToastStore } from '../../stores/toast'

const toastStore = useToastStore()
const { toasts } = storeToRefs(toastStore)

const iconMap = {
  success: CheckCircle2,
  error: XCircle,
  info: Info,
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 top-4 z-[120] flex justify-center px-4">
    <div class="flex w-full max-w-md flex-col gap-3">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-white/60 bg-white p-3 shadow-card"
      >
        <component
          :is="iconMap[toast.type || 'info']"
          class="mt-0.5 h-5 w-5"
          :class="toast.type === 'error' ? 'text-red-500' : toast.type === 'success' ? 'text-green-600' : 'text-primary'"
        />
        <div class="flex-1">
          <p class="text-sm font-semibold text-slate-900">{{ toast.title }}</p>
          <p v-if="toast.message" class="text-xs text-muted">{{ toast.message }}</p>
        </div>
        <button class="rounded-full bg-surface p-1 text-muted" @click="toastStore.remove(toast.id)">
          <X class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>
