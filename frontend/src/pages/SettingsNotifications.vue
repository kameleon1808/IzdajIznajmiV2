<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import { useNotificationStore } from '../stores/notifications'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'
import {
  getPushAvailability,
  disablePushEndpoint,
  fetchPushDevices,
  getCurrentPushEndpoint,
  getPushPermissionState,
  subscribeCurrentDevicePush,
  unsubscribeCurrentDevicePush,
  type PushDevice,
} from '../services/push'
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

const pushLoading = ref(false)
const pushBusy = ref(false)
const pushError = ref('')
const pushPermission = ref<'default' | 'granted' | 'denied' | 'unsupported'>('unsupported')
const pushDevices = ref<PushDevice[]>([])
const currentEndpoint = ref<string | null>(null)

const pushAvailability = ref(getPushAvailability())
const pushFeatureEnabled = computed(() => pushAvailability.value.code === 'ok')
const pushUnavailableReasonKey = computed<Parameters<typeof t>[0] | ''>(() => {
  const code = pushAvailability.value.code
  if (code === 'insecure_context') return 'settings.notifications.push.hint.secureContext'
  if (code === 'ios_home_screen_required') return 'settings.notifications.push.hint.iosHomeScreen'
  if (code === 'missing_vapid_key') return 'settings.notifications.push.hint.missingVapidKey'
  if (code === 'service_worker_unsupported' || code === 'notification_unsupported' || code === 'push_unsupported') {
    return 'settings.notifications.push.hint.browserUnsupported'
  }
  return ''
})

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

const pushPermissionLabel = computed(() => {
  if (pushPermission.value === 'granted') return t('settings.notifications.push.permission.granted')
  if (pushPermission.value === 'denied') return t('settings.notifications.push.permission.denied')
  if (pushPermission.value === 'default') return t('settings.notifications.push.permission.default')
  return t('settings.notifications.push.permission.unsupported')
})

const sortedDevices = computed(() =>
  [...pushDevices.value].sort((a, b) => {
    const timeA = a.updatedAt ? new Date(a.updatedAt).getTime() : 0
    const timeB = b.updatedAt ? new Date(b.updatedAt).getTime() : 0
    return timeB - timeA
  }),
)

const currentDeviceIsEnabled = computed(
  () => !!currentEndpoint.value && pushDevices.value.some((device) => device.endpoint === currentEndpoint.value && device.isEnabled),
)

const hasEnabledDevices = computed(() => pushDevices.value.some((device) => device.isEnabled))

const isDirty = computed(() => {
  if (!notificationStore.preferences) return false
  const prefs = notificationStore.preferences
  return (
    JSON.stringify(typeSettings.value) !== JSON.stringify(prefs.typeSettings) ||
    digestFrequency.value !== prefs.digestFrequency ||
    digestEnabled.value !== prefs.digestEnabled
  )
})

const loadPushState = async () => {
  pushAvailability.value = getPushAvailability()
  pushPermission.value = getPushPermissionState()
  pushError.value = ''

  if (!authStore.isAuthenticated || authStore.isMockMode) {
    pushDevices.value = []
    currentEndpoint.value = null
    return
  }

  pushLoading.value = true
  try {
    const endpointPromise =
      pushPermission.value === 'unsupported'
        ? Promise.resolve(null)
        : getCurrentPushEndpoint().catch(() => null)
    const [endpoint, devices] = await Promise.all([endpointPromise, fetchPushDevices()])
    currentEndpoint.value = endpoint
    pushDevices.value = devices
  } catch (error) {
    pushError.value = (error as Error).message || t('settings.notifications.push.loadFailed')
  } finally {
    pushLoading.value = false
  }
}

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

  await loadPushState()
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

const enablePush = async () => {
  pushBusy.value = true
  pushError.value = ''
  try {
    await subscribeCurrentDevicePush()
    toastStore.push({ title: t('common.success'), message: t('settings.notifications.push.enabled'), type: 'success' })
    await loadPushState()
  } catch (error) {
    const message = (error as Error).message || t('settings.notifications.push.enableFailed')
    pushError.value = message
    toastStore.push({ title: t('common.error'), message, type: 'error' })
  } finally {
    pushBusy.value = false
  }
}

const disableCurrentPush = async () => {
  pushBusy.value = true
  pushError.value = ''
  try {
    const endpoint = await unsubscribeCurrentDevicePush()
    if (!endpoint && currentEndpoint.value) {
      await disablePushEndpoint(currentEndpoint.value)
    }
    toastStore.push({ title: t('common.success'), message: t('settings.notifications.push.disabled'), type: 'success' })
    await loadPushState()
  } catch (error) {
    const message = (error as Error).message || t('settings.notifications.push.disableFailed')
    pushError.value = message
    toastStore.push({ title: t('common.error'), message, type: 'error' })
  } finally {
    pushBusy.value = false
  }
}

const disableDevice = async (endpoint: string) => {
  pushBusy.value = true
  pushError.value = ''
  try {
    if (endpoint === currentEndpoint.value) {
      await disableCurrentPush()
      return
    }
    await disablePushEndpoint(endpoint)
    toastStore.push({ title: t('common.success'), message: t('settings.notifications.push.deviceDisabled'), type: 'success' })
    await loadPushState()
  } catch (error) {
    const message = (error as Error).message || t('settings.notifications.push.disableFailed')
    pushError.value = message
    toastStore.push({ title: t('common.error'), message, type: 'error' })
  } finally {
    pushBusy.value = false
  }
}

const formatDeviceTime = (value: string | null) => {
  if (!value) return t('common.unknown')
  return new Date(value).toLocaleString()
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

      <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-slate-900">{{ t('settings.notifications.push.title') }}</h2>
            <p class="text-xs text-muted mt-1">{{ t('settings.notifications.push.subtitle') }}</p>
          </div>
          <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{{ pushPermissionLabel }}</span>
        </div>

        <p v-if="pushAvailability.code === 'disabled_by_config'" class="text-xs text-amber-700">
          {{ t('settings.notifications.push.configDisabled') }}
        </p>
        <p v-else-if="pushUnavailableReasonKey" class="text-xs text-amber-700">
          {{ t(pushUnavailableReasonKey) }}
        </p>
        <p v-if="pushError" class="text-xs text-rose-700">{{ pushError }}</p>

        <div class="flex flex-wrap gap-2">
          <Button
            size="sm"
            :disabled="pushBusy || !pushFeatureEnabled || pushPermission === 'denied'"
            @click="enablePush"
          >
            {{ currentDeviceIsEnabled ? t('settings.notifications.push.reEnable') : t('settings.notifications.push.enable') }}
          </Button>
          <Button
            size="sm"
            variant="secondary"
            :disabled="pushBusy || !currentDeviceIsEnabled"
            @click="disableCurrentPush"
          >
            {{ t('settings.notifications.push.disableCurrent') }}
          </Button>
        </div>

        <div v-if="pushLoading" class="text-sm text-muted">{{ t('common.loading') }}</div>
        <div v-else-if="!hasEnabledDevices" class="text-sm text-muted">{{ t('settings.notifications.push.noDevices') }}</div>
        <div v-else class="space-y-2">
          <div
            v-for="device in sortedDevices"
            :key="device.id"
            class="rounded-xl border border-slate-200 p-3"
            :class="device.endpoint === currentEndpoint ? 'bg-primary/5 border-primary/30' : ''"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-slate-900">
                  {{ device.deviceLabel || t('settings.notifications.push.unknownDevice') }}
                  <span v-if="device.endpoint === currentEndpoint" class="text-xs font-medium text-primary">{{ t('settings.notifications.push.currentDevice') }}</span>
                </p>
                <p class="text-xs text-muted mt-0.5">{{ formatDeviceTime(device.updatedAt) }}</p>
              </div>
              <Button size="sm" variant="secondary" :disabled="pushBusy || !device.isEnabled" @click="disableDevice(device.endpoint)">
                {{ t('settings.notifications.push.disableDevice') }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <Button block size="lg" :variant="isDirty ? 'primary' : 'secondary'" :disabled="!isDirty || saving" @click="save">
        {{ saving ? t('common.saving') : t('common.saveChanges') }}
      </Button>
    </div>
  </div>
</template>
