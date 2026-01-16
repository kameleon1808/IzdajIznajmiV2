<script setup lang="ts">
import { ref } from 'vue'
import { Check } from 'lucide-vue-next'

const languages = ['English', 'Hrvatski', 'Deutsch', 'Español']
const selected = ref('English')
</script>

<template>
  <div class="space-y-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60">
    <h2 class="text-lg font-semibold text-slate-900">Language</h2>
    <div>
      <button
        v-for="lang in languages"
        :key="lang"
        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-900 hover:bg-surface"
        @click="selected = lang"
      >
        <span>{{ lang }}</span>
        <Check v-if="selected === lang" class="h-5 w-5 text-primary" />
      </button>
    </div>
  </div>
</template>
