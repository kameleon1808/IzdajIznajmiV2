<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import { useNotificationStore } from '../stores/notifications'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import Button from '../components/ui/Button.vue'
import ErrorState from '../components/ui/ErrorState.vue'

const router = useRouter()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const toastStore = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const loading = ref(false)
const saving = ref(false)
const loadError = ref('')

const typeSettings = ref<Record<string, boolean>>({})
const digestFrequency = ref<'none' | 'daily' | 'weekly'>('none')
const digestEnabled = ref(false)

const typeLabels = computed<Record<string, string>>(() => ({
  'application.created': t('settings.notifications.types.applicationCreated'),
  'application.status_changed': t('settings.notifications.types.applicationStatus'),
  'message.received': t('settings.notifications.types.messageReceived'),
  'rating.received': t('settings.notifications.types.ratingReceived'),
  'report.update': t('settings.notifications.types.reportUpdate'),
  'admin.notice': t('settings.notifications.types.adminNotice'),
  'kyc.submission_received': t('settings.notifications.types.kycSubmission'),
  'kyc.approved': t('settings.notifications.types.kycApproved'),
  'kyc.rejected': t('settings.notifications.types.kycRejected'),
  'transaction.contract_ready': t('settings.notifications.types.contractReady'),
  'transaction.signed_by_other_party': t('settings.notifications.types.contractSigned'),
  'transaction.fully_signed': t('settings.notifications.types.contractFullySigned'),
  'transaction.deposit_paid': t('settings.notifications.types.depositPaid'),
  'transaction.move_in_confirmed': t('settings.notifications.types.moveInConfirmed'),
}))

const isDirty = computed(() => {
  if (!notificationStore.preferences) return false
  const prefs = notificationStore.preferences
  return (
    JSON.stringify(typeSettings.value) !== JSON.stringify(prefs.typeSettings) ||
    digestFrequency.value !== prefs.digestFrequency ||
    digestEnabled.value !== prefs.digestEnabled
  )
})

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.isMockMode) {
    loading.value = true
    loadError.value = ''
    try {
      const prefs = await notificationStore.fetchPreferences()
      if (prefs) {
        typeSettings.value = { ...prefs.typeSettings }
        digestFrequency.value = prefs.digestFrequency
        digestEnabled.value = prefs.digestEnabled
      }
    } catch (error) {
      loadError.value = (error as Error).message || t('settings.notifications.loadFailed')
    } finally {
      loading.value = false
    }
  }
})

const toggleType = (type: string) => {
  typeSettings.value[type] = !(typeSettings.value[type] ?? false)
}

const save = async () => {
  saving.value = true
  try {
    await notificationStore.updatePreferences({
      typeSettings: typeSettings.value,
      digestFrequency: digestFrequency.value,
      digestEnabled: digestEnabled.value,
    })
    toastStore.push({ title: t('common.success'), message: t('settings.notifications.saved'), type: 'success' })
  } catch (error) {
    toastStore.push({ title: t('common.error'), message: t('settings.notifications.saveFailed'), type: 'error' })
  } finally {
    saving.value = false
  }
}

const retryLoad = async () => {
  loadError.value = ''
  loading.value = true
  try {
    const prefs = await notificationStore.fetchPreferences()
    if (prefs) {
      typeSettings.value = { ...prefs.typeSettings }
      digestFrequency.value = prefs.digestFrequency
      digestEnabled.value = prefs.digestEnabled
    }
  } catch (error) {
    loadError.value = (error as Error).message || t('settings.notifications.loadFailed')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-surface pb-24">
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/40 bg-surface/90 px-4 py-3 backdrop-blur-lg">
      <button class="rounded-full bg-white p-2 shadow-soft" @click="router.back()" :aria-label="t('common.back')">
        <ArrowLeft class="h-5 w-5 text-slate-800" />
      </button>
      <h1 class="flex-1 text-lg font-semibold text-slate-900">{{ t('settings.notifications.title') }}</h1>
    </header>

    <div v-if="loadError" class="p-4">
      <ErrorState :message="loadError" :retry-label="t('common.retry')" @retry="retryLoad" />
    </div>
    <div v-else-if="loading" class="p-4 text-center text-muted">{{ t('common.loading') }}</div>
    <div v-else class="space-y-4 p-4">
      <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <h2 class="text-base font-semibold text-slate-900">{{ t('settings.notifications.typesTitle') }}</h2>
        <div class="space-y-2">
          <label
            v-for="(label, type) in typeLabels"
            :key="type"
            class="flex items-center justify-between py-2"
          >
            <span class="text-sm text-slate-900">{{ label }}</span>
            <button
              @click="toggleType(type)"
              class="relative h-6 w-11 rounded-full transition-colors"
              :class="typeSettings[type] ? 'bg-primary' : 'bg-slate-300'"
            >
              <span
                class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform"
                :class="typeSettings[type] ? 'translate-x-5' : 'translate-x-0'"
              ></span>
            </button>
          </label>
        </div>
      </div>

      <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <h2 class="text-base font-semibold text-slate-900">{{ t('settings.notifications.digestTitle') }}</h2>
        <div class="space-y-2">
          <label class="flex items-center gap-3 py-2">
            <input
              v-model="digestFrequency"
              type="radio"
              value="none"
              class="h-4 w-4 text-primary focus:ring-primary"
            />
            <span class="text-sm text-slate-900">{{ t('settings.notifications.digest.none') }}</span>
          </label>
          <label class="flex items-center gap-3 py-2">
            <input
              v-model="digestFrequency"
              type="radio"
              value="daily"
              class="h-4 w-4 text-primary focus:ring-primary"
            />
            <span class="text-sm text-slate-900">{{ t('settings.notifications.digest.daily') }}</span>
          </label>
          <label class="flex items-center gap-3 py-2">
            <input
              v-model="digestFrequency"
              type="radio"
              value="weekly"
              class="h-4 w-4 text-primary focus:ring-primary"
            />
            <span class="text-sm text-slate-900">{{ t('settings.notifications.digest.weekly') }}</span>
          </label>
        </div>
        <label class="flex items-center justify-between py-2">
          <span class="text-sm text-slate-900">{{ t('settings.notifications.digest.enable') }}</span>
          <button
            @click="digestEnabled = !digestEnabled"
            class="relative h-6 w-11 rounded-full transition-colors"
            :class="digestEnabled ? 'bg-primary' : 'bg-slate-300'"
          >
            <span
              class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform"
              :class="digestEnabled ? 'translate-x-5' : 'translate-x-0'"
            ></span>
          </button>
        </label>
      </div>

      <Button block size="lg" :variant="isDirty ? 'primary' : 'secondary'" :disabled="!isDirty || saving" @click="save">
        {{ saving ? t('common.saving') : t('common.saveChanges') }}
      </Button>
    </div>
  </div>
</template>
