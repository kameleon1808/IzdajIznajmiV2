<script setup lang="ts">
const props = defineProps({
  active: { type: Boolean, default: false },
})
</script>

<template>
  <button
    class="rounded-pill px-4 py-2 text-sm font-semibold transition-colors duration-150"
    :class="active
      ? 'bg-primary text-white shadow-soft'
      : 'bg-primary-soft text-text-2 border border-border shadow-soft hover:bg-primary-soft'
    "
  >
    <slot />
  </button>
</template>
