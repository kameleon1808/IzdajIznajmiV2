<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ChevronDown } from 'lucide-vue-next'
import { getListingFacilities } from '../services/mockApi'

const route = useRoute()
const groups = ref<{ title: string; items: string[] }[]>([])

onMounted(async () => {
  groups.value = await getListingFacilities(route.params.id as string)
})
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="group in groups"
      :key="group.title"
      class="rounded-2xl border border-line bg-white p-3 shadow-soft"
    >
      <details open class="group">
        <summary class="flex cursor-pointer items-center justify-between text-lg font-semibold text-slate-900">
          <span>{{ group.title }}</span>
          <div class="flex items-center gap-2 text-sm text-muted">
            <span>{{ group.items.length }} items</span>
            <ChevronDown class="h-4 w-4 transition group-open:rotate-180" />
          </div>
        </summary>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
          <li v-for="item in group.items" :key="item" class="rounded-xl bg-surface px-3 py-2">{{ item }}</li>
        </ul>
      </details>
    </div>
  </div>
</template>
