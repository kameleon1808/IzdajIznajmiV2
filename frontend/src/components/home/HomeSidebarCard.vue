<script setup lang="ts">
import { AlertCircle, ArrowRight } from 'lucide-vue-next'
</script>

<template>
  <aside class="card-base p-4">
    <div class="flex items-start gap-3">
      <div class="rounded-2xl bg-primary/10 p-3 text-primary">
        <AlertCircle class="h-5 w-5" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Location controls</p>
        <p class="text-xs text-muted">You can change your location anytime from the pin icon.</p>
      </div>
    </div>
    <div class="mt-4 flex items-center justify-between rounded-2xl bg-surface px-3 py-2">
      <span class="text-xs font-semibold text-slate-700">Adjust your stay</span>
      <ArrowRight class="h-4 w-4 text-primary" />
    </div>
  </aside>
</template>
