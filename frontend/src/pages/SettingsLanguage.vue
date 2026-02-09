<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { Check } from 'lucide-vue-next'
import { useLanguageStore } from '../stores/language'

const languageStore = useLanguageStore()
const { current } = storeToRefs(languageStore)
const languages = languageStore.languages
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
</script>

<template>
  <div class="space-y-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60">
    <h2 class="text-lg font-semibold text-slate-900">{{ t('titles.language') }}</h2>
    <div>
      <button
        v-for="lang in languages"
        :key="lang.code"
        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-900 hover:bg-surface"
        @click="languageStore.setLanguage(lang.code)"
      >
        <span>{{ lang.label }}</span>
        <Check v-if="current === lang.code" class="h-5 w-5 text-primary" />
      </button>
    </div>
  </div>
</template>
