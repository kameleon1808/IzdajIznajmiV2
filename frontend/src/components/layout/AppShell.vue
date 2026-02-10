<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import BottomTabs from './BottomTabs.vue'
import TopBar from './TopBar.vue'
import Button from '../ui/Button.vue'
import { useAuthStore } from '../../stores/auth'
import { useLanguageStore } from '../../stores/language'

const props = defineProps<{
  topBarConfig?: Record<string, any> | null
  showTabs?: boolean
  contentClass?: string
  shellClass?: string
  sidebarComponent?: any
}>()

const auth = useAuthStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const { impersonating, impersonator, user } = storeToRefs(auth)

const hasTopBar = computed(() => props.topBarConfig !== null && props.topBarConfig !== undefined)
const hasSidebar = computed(() => Boolean(props.sidebarComponent))
const showSidebarColumn = computed(() => props.showTabs !== false || hasSidebar.value)
const shellClasses = computed(() => {
  const desktopClass = props.shellClass ?? 'lg:max-w-6xl lg:px-6'
  return ['relative mx-auto min-h-screen', 'max-w-md', desktopClass]
})

const mainClasses = computed(() => {
  const base = ['min-w-0', 'pb-28', 'pt-4', 'px-4', 'lg:flex-1', 'lg:pb-10', 'lg:pt-6', 'lg:px-6']
  if (props.contentClass) return [...base, props.contentClass]
  return base
})

const stopImpersonation = async () => {
  await auth.stopImpersonation()
}
</script>

<template>
  <div class="min-h-screen">
    <div :class="shellClasses">
      <TopBar v-if="hasTopBar" :config="topBarConfig || undefined" />

      <div v-if="impersonating" class="mx-4 mt-3 rounded-xl border border-amber-300 bg-amber-100 px-4 py-3 text-amber-900">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-semibold leading-tight">{{ t('appShell.impersonating') }} {{ user.fullName || user.name }}</p>
            <p class="text-xs text-amber-800">
              {{ t('appShell.adminLabel') }}:
              {{ impersonator?.fullName || impersonator?.name || t('appShell.unknownAdmin') }}
            </p>
          </div>
          <Button size="sm" variant="secondary" @click="stopImpersonation">{{ t('common.stop') }}</Button>
        </div>
      </div>

      <div class="lg:flex lg:items-start lg:gap-6">
        <div v-if="showSidebarColumn" class="lg:mt-4 lg:w-60 lg:shrink-0 lg:self-start lg:space-y-4">
          <BottomTabs
            v-if="showTabs !== false"
            class="fixed bottom-0 left-0 right-0 z-40 lg:static lg:z-auto lg:w-full"
          />
          <component :is="sidebarComponent" v-if="hasSidebar" class="hidden lg:block" />
        </div>
        <main :class="mainClasses">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
