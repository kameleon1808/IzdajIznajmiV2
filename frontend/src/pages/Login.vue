<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const router = useRouter()
const route = useRoute()

const email = ref('tena@demo.com')
const password = ref('password')
const error = ref('')
const mfaError = ref('')
const mfaCode = ref('')
const recoveryCode = ref('')
const useRecovery = ref(false)
const rememberDevice = ref(true)

onMounted(() => {
  resetMfa()
})

onBeforeRouteLeave(() => {
  if (auth.mfaRequired) {
    resetMfa()
  }
})

const onSubmit = async () => {
  error.value = ''
  mfaError.value = ''
  try {
    const result = await auth.login(email.value, password.value)
    if (result?.mfaRequired) {
      return
    }
    toast.push({ title: t('auth.welcomeBack'), type: 'success' })
    const redirect = (route.query.returnUrl as string) || '/'
    router.replace(redirect)
  } catch (err: any) {
    error.value = err.message ?? t('auth.loginFailed')
  }
}

const onVerifyMfa = async () => {
  mfaError.value = ''
  if (!auth.mfaChallengeId) {
    mfaError.value = t('auth.mfaMissingChallenge')
    return
  }
  try {
    await auth.verifyMfa({
      challengeId: auth.mfaChallengeId,
      code: useRecovery.value ? undefined : mfaCode.value,
      recoveryCode: useRecovery.value ? recoveryCode.value : undefined,
      rememberDevice: rememberDevice.value,
    })
    toast.push({ title: t('auth.mfaVerified'), type: 'success' })
    const redirect = (route.query.returnUrl as string) || '/'
    router.replace(redirect)
  } catch (err: any) {
    mfaError.value = err.message ?? t('auth.mfaFailed')
  }
}

const resetMfa = () => {
  auth.clearMfaChallenge()
  mfaCode.value = ''
  recoveryCode.value = ''
  useRecovery.value = false
  rememberDevice.value = true
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-900">{{ t('auth.login') }}</h1>
    <p class="text-sm text-muted">{{ t('auth.loginHint') }}</p>

    <ErrorBanner v-if="error" :message="error" />

    <div v-if="!auth.mfaRequired" class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.email') }}</p>
        <Input v-model="email" :placeholder="t('auth.emailPlaceholder')" type="email" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.password') }}</p>
        <Input v-model="password" :placeholder="t('auth.passwordPlaceholder')" type="password" />
      </div>
      <Button block size="lg" :loading="auth.loading" @click="onSubmit">{{ t('auth.login') }}</Button>
      <p class="text-center text-sm text-muted">
        {{ t('auth.noAccount') }}
        <button class="text-primary font-semibold" @click="router.push('/register')">{{ t('auth.register') }}</button>
      </p>
    </div>

    <div v-else class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.mfaTitle') }}</p>
        <button class="text-xs text-muted hover:text-slate-700" @click="resetMfa">{{ t('common.back') }}</button>
      </div>
      <p class="text-xs text-muted">{{ t('auth.mfaHint') }}</p>
      <ErrorBanner v-if="mfaError" :message="mfaError" />
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ useRecovery ? t('auth.recoveryCode') : t('auth.authenticatorCode') }}</p>
        <Input v-if="useRecovery" v-model="recoveryCode" placeholder="XXXX-XXXX" />
        <Input v-else v-model="mfaCode" placeholder="123 456" />
      </div>
      <label class="flex items-center gap-2 text-xs text-muted">
        <input v-model="useRecovery" type="checkbox" />
        {{ t('auth.useRecovery') }}
      </label>
      <label class="flex items-center gap-2 text-xs text-muted">
        <input v-model="rememberDevice" type="checkbox" />
        {{ t('auth.rememberDevice') }}
      </label>
      <Button block size="lg" :loading="auth.loading" @click="onVerifyMfa">{{ t('auth.verify') }}</Button>
    </div>

    <div v-if="auth.isMockMode" class="rounded-2xl bg-surface p-3 text-sm text-muted border border-dashed border-line">
      {{ t('auth.mockLoginNote') }}
    </div>
  </div>
</template>
