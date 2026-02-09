<script setup lang="ts">
import { computed, reactive } from 'vue'
import Button from '../components/ui/Button.vue'
import { useLanguageStore } from '../stores/language'

const form = reactive({
  name: 'Marina Perić',
  email: 'marina@example.com',
  phone: '+385 91 555 123',
  location: 'Zagreb, Croatia',
})

const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const initial = { ...form }
const isDirty = computed(
  () => form.name !== initial.name || form.email !== initial.email || form.phone !== initial.phone || form.location !== initial.location,
)
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-2 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.fullName') }}
        <input v-model="form.name" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.email') }}
        <input v-model="form.email" type="email" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.phone') }}
        <input v-model="form.phone" type="tel" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.location') }}
        <input v-model="form.location" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
    </div>
    <Button block size="lg" :variant="isDirty ? 'primary' : 'secondary'" :disabled="!isDirty">
      {{ t('common.saveChanges') }}
    </Button>
  </div>
</template>
