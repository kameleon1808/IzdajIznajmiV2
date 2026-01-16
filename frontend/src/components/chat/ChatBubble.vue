<script setup lang="ts">
defineProps<{ text: string; from: 'me' | 'them'; time?: string }>()
</script>

<template>
  <div :class="['flex', from === 'me' ? 'justify-end' : 'justify-start']">
    <div
      :class="[
        'max-w-[80%] rounded-2xl px-4 py-3 text-sm shadow-soft',
        from === 'me' ? 'bg-primary text-white rounded-br-sm' : 'bg-white text-slate-900 rounded-bl-sm border border-white/70',
      ]"
    >
      <p>{{ text }}</p>
      <p v-if="time" class="mt-1 text-[11px] opacity-80">{{ time }}</p>
    </div>
  </div>
</template>
