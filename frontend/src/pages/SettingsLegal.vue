<script setup lang="ts">
import { computed } from 'vue'
import { marked } from 'marked'
import { useLanguageStore } from '../stores/language'

import privacyEn from '../assets/legal/privacy-policy.en.md?raw'
import privacySr from '../assets/legal/privacy-policy.sr.md?raw'
import contactEn from '../assets/legal/contact.en.md?raw'
import contactSr from '../assets/legal/contact.sr.md?raw'

const languageStore = useLanguageStore()

const privacyHtml = computed(() =>
  marked.parse(languageStore.current === 'sr' ? privacySr : privacyEn) as string
)
const contactHtml = computed(() =>
  marked.parse(languageStore.current === 'sr' ? contactSr : contactEn) as string
)
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-2xl bg-white p-5 shadow-soft border border-white/60">
      <div class="legal-prose" v-html="privacyHtml" />
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-soft border border-white/60">
      <div class="legal-prose" v-html="contactHtml" />
    </div>
  </div>
</template>

<style scoped>
.legal-prose :deep(h1) {
  @apply text-lg font-semibold text-slate-900 mb-3;
}
.legal-prose :deep(h2) {
  @apply text-base font-semibold text-slate-800 mt-5 mb-2;
}
.legal-prose :deep(p) {
  @apply text-sm leading-relaxed text-slate-700 mb-2;
}
.legal-prose :deep(ul) {
  @apply text-sm text-slate-700 list-disc pl-5 space-y-1 mb-2;
}
.legal-prose :deep(table) {
  @apply text-sm text-slate-700 w-full mb-2 border-collapse;
}
.legal-prose :deep(th) {
  @apply text-left font-semibold text-slate-800 pb-1 border-b border-slate-200;
}
.legal-prose :deep(td) {
  @apply py-1 border-b border-slate-100;
}
.legal-prose :deep(strong) {
  @apply font-semibold text-slate-800;
}
.legal-prose :deep(a) {
  @apply text-primary underline;
}
</style>
