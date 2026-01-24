<script setup lang="ts">
import Button from './Button.vue'

const props = defineProps<{
  message: string
  retryLabel?: string
}>()

const emit = defineEmits<{ (e: 'retry'): void }>()
</script>

<template>
  <div class="flex items-start gap-3 rounded-2xl border border-red-100 bg-red-50 px-3 py-3 text-sm text-red-700">
    <div class="flex-1">
      <p class="font-semibold text-red-800">Something went wrong</p>
      <p class="text-red-700">{{ message }}</p>
    </div>
    <Button v-if="retryLabel" size="sm" variant="secondary" @click="emit('retry')">
      {{ retryLabel }}
    </Button>
  </div>
</template>
