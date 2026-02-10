<script setup lang="ts">
defineProps<{ count?: number }>()
</script>

<template>
  <div class="space-y-3">
    <div v-for="n in count || 3" :key="n" class="flex items-center gap-3 rounded-[22px] bg-surface-2 p-3 shadow-card border border-border">
      <div class="h-24 w-24 rounded-2xl bg-surface-2 shimmer"></div>
      <div class="flex flex-1 flex-col gap-2">
        <div class="h-4 w-32 rounded-full bg-surface-2 shimmer"></div>
        <div class="h-4 w-24 rounded-full bg-surface-2 shimmer"></div>
        <div class="h-3 w-20 rounded-full bg-surface-2 shimmer"></div>
      </div>
    </div>
  </div>
</template>
