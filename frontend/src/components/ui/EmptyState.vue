<script setup lang="ts">
import { type Component } from 'vue'

const props = defineProps<{ title: string; subtitle?: string; icon?: Component }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-surface px-4 py-8 text-center">
    <div v-if="icon" class="rounded-full bg-white p-3 shadow-soft">
      <component :is="icon" class="h-6 w-6 text-primary" />
    </div>
    <p class="text-base font-semibold text-slate-900">{{ title }}</p>
    <p v-if="subtitle" class="text-sm text-muted">{{ subtitle }}</p>
    <div class="mt-1">
      <slot />
    </div>
  </div>
</template>
