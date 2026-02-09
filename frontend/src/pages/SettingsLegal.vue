<script setup lang="ts">
import { useLanguageStore } from '../stores/language'

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
</script>

<template>
  <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
    <h2 class="text-lg font-semibold text-slate-900">{{ t('settings.legal.title') }}</h2>
    <p class="text-sm leading-relaxed text-slate-700">
      {{ t('settings.legal.p1') }}
    </p>
    <p class="text-sm leading-relaxed text-slate-700">
      {{ t('settings.legal.p2') }}
    </p>
  </div>
</template>
