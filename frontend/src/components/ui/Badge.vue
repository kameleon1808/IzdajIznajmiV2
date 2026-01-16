<script setup lang="ts">
const props = defineProps<{ variant?: 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'info' }>()

const variants: Record<string, string> = {
  pending: 'bg-primary/10 text-primary',
  accepted: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-600',
  cancelled: 'bg-slate-100 text-slate-600',
  info: 'bg-surface text-slate-800',
}
</script>

<template>
  <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="variants[props.variant || 'info']">
    <slot />
  </span>
</template>
