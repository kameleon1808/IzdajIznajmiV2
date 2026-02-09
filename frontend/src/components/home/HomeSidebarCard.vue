<script setup lang="ts">
import { AlertCircle, ArrowRight } from 'lucide-vue-next'
import { useLanguageStore } from '../../stores/language'

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
</script>

<template>
  <aside class="card-base p-4">
    <div class="flex items-start gap-3">
      <div class="rounded-2xl bg-primary/10 p-3 text-primary">
        <AlertCircle class="h-5 w-5" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('home.locationControls') }}</p>
        <p class="text-xs text-muted">{{ t('home.locationHint') }}</p>
      </div>
    </div>
    <div class="mt-4 flex items-center justify-between rounded-2xl bg-surface px-3 py-2">
      <span class="text-xs font-semibold text-slate-700">{{ t('home.adjustStay') }}</span>
      <ArrowRight class="h-4 w-4 text-primary" />
    </div>
  </aside>
</template>
