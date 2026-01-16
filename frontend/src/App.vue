<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppShell from './components/layout/AppShell.vue'
import ToastStack from './components/ui/ToastStack.vue'

const route = useRoute()
const topBarConfig = computed(() => route.meta.topBar as Record<string, any> | null | undefined)
const showTabs = computed(() => route.meta.showTabs !== false)
const contentClass = computed(() => route.meta.contentClass as string | undefined)
</script>

<template>
  <ToastStack />
  <RouterView v-slot="{ Component }">
    <AppShell :top-bar-config="topBarConfig" :show-tabs="showTabs" :content-class="contentClass">
      <component :is="Component" />
    </AppShell>
  </RouterView>
</template>
