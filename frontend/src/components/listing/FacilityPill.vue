<script setup lang="ts">
import { Check } from 'lucide-vue-next'

defineProps<{ label: string }>()
</script>

<template>
  <div class="flex items-center gap-2 rounded-pill bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-soft">
    <Check class="h-4 w-4 text-primary" />
    <span>{{ label }}</span>
  </div>
</template>
