<script setup lang="ts">
const props = defineProps({
  active: { type: Boolean, default: false },
})
</script>

<template>
  <button
    class="rounded-pill px-4 py-2 text-sm font-semibold transition"
    :class="active ? 'bg-primary text-white shadow-soft' : 'bg-white text-slate-700 border border-line shadow-soft'
    "
  >
    <slot />
  </button>
</template>
