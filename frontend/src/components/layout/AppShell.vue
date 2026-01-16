<script setup lang="ts">
import { computed } from 'vue'
import BottomTabs from './BottomTabs.vue'
import TopBar from './TopBar.vue'

const props = defineProps<{ topBarConfig?: Record<string, any> | null; showTabs?: boolean; contentClass?: string }>()

const hasTopBar = computed(() => props.topBarConfig !== null && props.topBarConfig !== undefined)
const mainClasses = computed(() => {
  if (props.contentClass) return ['pb-28', props.contentClass]
  return ['pb-28', 'pt-4', 'px-4']
})
</script>

<template>
  <div class="min-h-screen bg-surface text-slate-900">
    <div class="relative mx-auto max-w-md min-h-screen">
      <TopBar v-if="hasTopBar" :config="topBarConfig || undefined" />

      <main :class="mainClasses">
        <slot />
      </main>

      <BottomTabs v-if="showTabs !== false" class="fixed bottom-0 left-0 right-0 z-40" />
    </div>
  </div>
</template>
