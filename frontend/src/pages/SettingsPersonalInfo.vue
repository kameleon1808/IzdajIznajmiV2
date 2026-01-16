<script setup lang="ts">
import { computed, reactive } from 'vue'
import Button from '../components/ui/Button.vue'

const form = reactive({
  name: 'Marina Perić',
  email: 'marina@example.com',
  phone: '+385 91 555 123',
  location: 'Zagreb, Croatia',
})

const initial = { ...form }
const isDirty = computed(
  () => form.name !== initial.name || form.email !== initial.email || form.phone !== initial.phone || form.location !== initial.location,
)
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-2 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        Full Name
        <input v-model="form.name" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        Email
        <input v-model="form.email" type="email" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        Phone
        <input v-model="form.phone" type="tel" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        Location
        <input v-model="form.location" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
    </div>
    <Button block size="lg" :variant="isDirty ? 'primary' : 'secondary'" :disabled="!isDirty">Save changes</Button>
  </div>
</template>
