<script setup lang="ts">
defineProps<{ alt?: string }>()
</script>

<template>
  <div
    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 px-1 shadow-soft"
    role="img"
    :aria-label="alt ?? 'avatar placeholder'"
  >
    <svg viewBox="0 0 100 100" class="h-11 w-11" aria-hidden="true">
      <circle cx="50" cy="50" r="44" fill="#b8b9bc" />
      <circle cx="50" cy="37" r="16" fill="#d8d8da" />
      <ellipse cx="50" cy="86" rx="38" ry="31" fill="#d8d8da" />
    </svg>
  </div>
</template>
