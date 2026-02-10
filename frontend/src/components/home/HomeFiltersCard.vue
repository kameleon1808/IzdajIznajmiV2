<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Chip from '../ui/Chip.vue'
import { useListingsStore } from '../../stores/listings'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{ accent?: boolean }>()

const router = useRouter()
const listingsStore = useListingsStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const categories = computed(() => [
  { key: 'all', label: t('home.categoryAll') },
  { key: 'villa', label: t('home.categoryVilla') },
  { key: 'hotel', label: t('home.categoryHotel') },
  { key: 'apartment', label: t('home.categoryApartment') },
])
</script>

<template>
  <aside :class="['card-base px-4 py-3', accent ? 'bg-primary-soft border-primary' : '']">
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-base font-semibold text-text">{{ t('home.browseByType') }}</p>
        <p class="text-xs text-muted">{{ t('home.quickFilters') }}</p>
      </div>
      <button class="text-xs font-semibold text-primary hover:text-primary-hover hover:underline" @click="router.push('/search')">
        {{ t('home.explore') }}
      </button>
    </div>
    <div class="mt-3 flex gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-visible">
      <Chip
        v-for="cat in categories"
        :key="cat.key"
        :active="listingsStore.filters.category === cat.key"
        @click="listingsStore.setFilters({ category: cat.key as any })"
      >
        {{ cat.label }}
      </Chip>
    </div>
  </aside>
</template>
