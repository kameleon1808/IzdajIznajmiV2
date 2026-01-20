<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import BottomTabs from './BottomTabs.vue'
import TopBar from './TopBar.vue'
import Button from '../ui/Button.vue'
import { useAuthStore } from '../../stores/auth'

const props = defineProps<{ topBarConfig?: Record<string, any> | null; showTabs?: boolean; contentClass?: string }>()

const auth = useAuthStore()
const { impersonating, impersonator, user } = storeToRefs(auth)

const hasTopBar = computed(() => props.topBarConfig !== null && props.topBarConfig !== undefined)
const mainClasses = computed(() => {
  if (props.contentClass) return ['pb-28', props.contentClass]
  return ['pb-28', 'pt-4', 'px-4']
})

const stopImpersonation = async () => {
  await auth.stopImpersonation()
}
</script>

<template>
  <div class="min-h-screen bg-surface text-slate-900">
    <div class="relative mx-auto max-w-md min-h-screen">
      <TopBar v-if="hasTopBar" :config="topBarConfig || undefined" />

      <div v-if="impersonating" class="mx-4 mt-3 rounded-xl border border-amber-300 bg-amber-100 px-4 py-3 text-amber-900">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-semibold leading-tight">Impersonating {{ user.fullName || user.name }}</p>
            <p class="text-xs text-amber-800">
              Admin: {{ impersonator?.fullName || impersonator?.name || 'Unknown admin' }}
            </p>
          </div>
          <Button size="sm" variant="secondary" @click="stopImpersonation">Stop</Button>
        </div>
      </div>

      <main :class="mainClasses">
        <slot />
      </main>

      <BottomTabs v-if="showTabs !== false" class="fixed bottom-0 left-0 right-0 z-40" />
    </div>
  </div>
</template>
