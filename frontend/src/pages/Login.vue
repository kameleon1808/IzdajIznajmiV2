<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
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
    toast.push({ title: 'Welcome back', type: 'success' })
    const redirect = (route.query.returnUrl as string) || '/'
    router.replace(redirect)
  } catch (err: any) {
    error.value = err.message ?? 'Login failed.'
  }
}

const onVerifyMfa = async () => {
  mfaError.value = ''
  if (!auth.mfaChallengeId) {
    mfaError.value = 'Missing MFA challenge. Please log in again.'
    return
  }
  try {
    await auth.verifyMfa({
      challengeId: auth.mfaChallengeId,
      code: useRecovery.value ? undefined : mfaCode.value,
      recoveryCode: useRecovery.value ? recoveryCode.value : undefined,
      rememberDevice: rememberDevice.value,
    })
    toast.push({ title: 'MFA verified', type: 'success' })
    const redirect = (route.query.returnUrl as string) || '/'
    router.replace(redirect)
  } catch (err: any) {
    mfaError.value = err.message ?? 'Verification failed.'
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
    <h1 class="text-xl font-semibold text-slate-900">Login</h1>
    <p class="text-sm text-muted">Use demo naloge ili vaše kredencijale.</p>

    <ErrorBanner v-if="error" :message="error" />

    <div v-if="!auth.mfaRequired" class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Email</p>
        <Input v-model="email" placeholder="you@example.com" type="email" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Password</p>
        <Input v-model="password" placeholder="••••••" type="password" />
      </div>
      <Button block size="lg" :loading="auth.loading" @click="onSubmit">Login</Button>
      <p class="text-center text-sm text-muted">
        Nemate nalog?
        <button class="text-primary font-semibold" @click="router.push('/register')">Register</button>
      </p>
    </div>

    <div v-else class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-900">MFA verification</p>
        <button class="text-xs text-muted hover:text-slate-700" @click="resetMfa">Back</button>
      </div>
      <p class="text-xs text-muted">Unesite kod iz autentikatora ili iskoristite recovery kod.</p>
      <ErrorBanner v-if="mfaError" :message="mfaError" />
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ useRecovery ? 'Recovery code' : 'Authenticator code' }}</p>
        <Input v-if="useRecovery" v-model="recoveryCode" placeholder="XXXX-XXXX" />
        <Input v-else v-model="mfaCode" placeholder="123 456" />
      </div>
      <label class="flex items-center gap-2 text-xs text-muted">
        <input v-model="useRecovery" type="checkbox" />
        Use recovery code
      </label>
      <label class="flex items-center gap-2 text-xs text-muted">
        <input v-model="rememberDevice" type="checkbox" />
        Remember this device
      </label>
      <Button block size="lg" :loading="auth.loading" @click="onVerifyMfa">Verify</Button>
    </div>

    <div v-if="auth.isMockMode" class="rounded-2xl bg-surface p-3 text-sm text-muted border border-dashed border-line">
      Dev napomena: u mock modu login samo prebacuje u Tenant ulogu.
    </div>
  </div>
</template>
