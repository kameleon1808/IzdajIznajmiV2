<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'

defineProps<{ message: string }>()
</script>

<template>
  <div class="flex items-center gap-2 rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
    <AlertCircle class="h-4 w-4" />
    <span class="flex-1">{{ message }}</span>
  </div>
</template>
