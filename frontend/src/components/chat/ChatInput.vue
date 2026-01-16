<script setup lang="ts">
import { Send } from 'lucide-vue-next'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits(['update:modelValue', 'send'])

const onKey = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    emit('send')
  }
}
</script>

<template>
  <div class="sticky bottom-0 left-0 right-0 bg-surface/90 px-4 pb-4 pt-2 backdrop-blur">
    <div class="flex items-center gap-2 rounded-2xl bg-white px-3 py-2 shadow-soft border border-white/70">
      <textarea
        class="min-h-[48px] flex-1 resize-none bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
        :value="modelValue"
        placeholder="Write a message"
        rows="1"
        @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        @keydown="onKey"
      ></textarea>
      <button class="rounded-full bg-primary p-3 text-white shadow-card" @click="emit('send')" aria-label="send">
        <Send class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>
