<script setup lang="ts">
import { computed } from 'vue'
import { Star } from 'lucide-vue-next'

const props = defineProps<{ rating: number; size?: 'sm' | 'md' }>()
const sizeClass = computed(() => (props.size === 'sm' ? 'h-4 w-4' : 'h-5 w-5'))
</script>

<template>
  <div class="flex items-center gap-1">
    <Star
      v-for="i in 5"
      :key="i"
      :class="[sizeClass, i <= Math.round(rating) ? 'text-primary' : 'text-line']"
      :fill="i <= Math.round(rating) ? 'currentColor' : 'none'"
    />
    <span class="ml-1 text-sm font-semibold text-slate-900">{{ rating.toFixed(1) }}</span>
  </div>
</template>
