<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import Badge from '../components/ui/Badge.vue'
import { useAuthStore } from '../stores/auth'
import {
  confirmMfaSetup,
  disableMfa,
  getSecuritySessions,
  regenerateMfaRecoveryCodes,
  revokeSecuritySession,
  revokeOtherSessions,
  setupMfa,
} from '../services'

const auth = useAuthStore()

const loading = ref(false)
const error = ref('')
const mfaSetupPayload = ref<{ secret: string; otpauthUrl: string; qrSvg: string } | null>(null)
const recoveryCodes = ref<string[]>([])
const confirmCode = ref('')
const recoveryRegenerateCode = ref('')
const disablePassword = ref('')
const disableCode = ref('')
const sessions = ref<any[]>([])

const isMfaEnabled = computed(() => Boolean(auth.user?.mfaEnabled))
const mfaStatusLabel = computed(() => (isMfaEnabled.value ? 'Enabled' : 'Disabled'))

const loadSessions = async () => {
  try {
    const data = await getSecuritySessions()
    sessions.value = data.sessions ?? []
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno učitavanje sesija.'
  }
}

const startMfaSetup = async () => {
  error.value = ''
  loading.value = true
  try {
    const data = await setupMfa()
    mfaSetupPayload.value = {
      secret: data.secret,
      otpauthUrl: data.otpauth_url ?? data.otpauthUrl,
      qrSvg: data.qr_svg ?? data.qrSvg,
    }
    recoveryCodes.value = data.recovery_codes ?? data.recoveryCodes ?? []
  } catch (err: any) {
    error.value = err.message ?? 'MFA setup nije uspeo.'
  } finally {
    loading.value = false
  }
}

const confirmMfa = async () => {
  error.value = ''
  loading.value = true
  try {
    await confirmMfaSetup(confirmCode.value)
    await auth.fetchMe()
    mfaSetupPayload.value = null
    recoveryCodes.value = []
    confirmCode.value = ''
  } catch (err: any) {
    error.value = err.message ?? 'MFA potvrda nije uspela.'
  } finally {
    loading.value = false
  }
}

const regenerateRecoveryCodes = async () => {
  error.value = ''
  loading.value = true
  try {
    const data = await regenerateMfaRecoveryCodes(recoveryRegenerateCode.value)
    recoveryCodes.value = data.recovery_codes ?? data.recoveryCodes ?? []
    recoveryRegenerateCode.value = ''
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno osvežavanje recovery kodova.'
  } finally {
    loading.value = false
  }
}

const disableMfaNow = async () => {
  error.value = ''
  loading.value = true
  try {
    await disableMfa({ password: disablePassword.value, code: disableCode.value })
    await auth.fetchMe()
    disablePassword.value = ''
    disableCode.value = ''
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno isključivanje MFA.'
  } finally {
    loading.value = false
  }
}

const revokeSession = async (id: string | number) => {
  error.value = ''
  try {
    await revokeSecuritySession(id)
    await loadSessions()
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno opozivanje sesije.'
  }
}

const revokeOthers = async () => {
  error.value = ''
  try {
    await revokeOtherSessions()
    await loadSessions()
  } catch (err: any) {
    error.value = err.message ?? 'Neuspešno odjavljivanje drugih uređaja.'
  }
}

onMounted(() => {
  loadSessions()
})
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-muted">Multi-factor authentication</p>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold">MFA status</h2>
            <Badge :variant="isMfaEnabled ? 'accepted' : 'pending'">{{ mfaStatusLabel }}</Badge>
          </div>
        </div>
        <Button v-if="!isMfaEnabled" size="sm" :loading="loading" @click="startMfaSetup">Enable MFA</Button>
      </div>

      <ErrorBanner v-if="error" :message="error" class="mt-3" />

      <div v-if="mfaSetupPayload" class="mt-4 space-y-3">
        <p class="text-sm font-semibold">Scan QR code</p>
        <div class="rounded-xl border border-line bg-surface p-3">
          <div class="max-w-[220px]" v-html="mfaSetupPayload.qrSvg" />
        </div>
        <p class="text-xs text-muted">Secret: <span class="font-mono">{{ mfaSetupPayload.secret }}</span></p>
        <div class="space-y-2">
          <p class="text-sm font-semibold">Recovery codes (one-time)</p>
          <div class="grid grid-cols-2 gap-2 text-xs font-mono">
            <div v-for="code in recoveryCodes" :key="code" class="rounded-lg bg-slate-50 px-2 py-1">
              {{ code }}
            </div>
          </div>
        </div>
        <div class="space-y-2">
          <p class="text-sm font-semibold">Confirm MFA</p>
          <Input v-model="confirmCode" placeholder="123 456" />
          <Button size="sm" :loading="loading" @click="confirmMfa">Confirm</Button>
        </div>
      </div>

      <div v-if="isMfaEnabled" class="mt-4 space-y-4">
        <div class="space-y-2">
          <p class="text-sm font-semibold">Regenerate recovery codes</p>
          <p class="text-xs text-muted">Potrebna je potvrda sa autentikator koda.</p>
          <div class="flex flex-col gap-2">
            <Input v-model="recoveryRegenerateCode" placeholder="123 456" />
            <Button size="sm" variant="secondary" :loading="loading" @click="regenerateRecoveryCodes">
              Regenerate codes
            </Button>
          </div>
        </div>
        <div v-if="recoveryCodes.length" class="space-y-2">
          <p class="text-sm font-semibold">New recovery codes</p>
          <div class="grid grid-cols-2 gap-2 text-xs font-mono">
            <div v-for="code in recoveryCodes" :key="code" class="rounded-lg bg-slate-50 px-2 py-1">
              {{ code }}
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-sm font-semibold">Disable MFA</p>
          <div class="grid gap-2">
            <Input v-model="disablePassword" type="password" placeholder="Current password" />
            <Input v-model="disableCode" placeholder="123 456" />
            <Button size="sm" variant="danger" :loading="loading" @click="disableMfaNow">Disable MFA</Button>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">Sessions & Devices</h2>
          <p class="text-xs text-muted">Pregled aktivnih uređaja i poslednjih aktivnosti.</p>
        </div>
        <Button size="sm" variant="secondary" @click="revokeOthers">Logout other devices</Button>
      </div>

      <div v-if="!sessions.length" class="mt-4 text-sm text-muted">Nema aktivnih sesija.</div>
      <div v-else class="mt-4 space-y-3">
        <div
          v-for="session in sessions"
          :key="session.id"
          class="flex flex-col gap-2 rounded-xl border border-line bg-surface px-3 py-3"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900">
                {{ session.deviceLabel || session.userAgent?.slice(0, 48) || 'Unknown device' }}
              </p>
              <p class="text-xs text-muted">IP: {{ session.ipTruncated ?? 'N/A' }}</p>
            </div>
            <Badge :variant="session.isCurrent ? 'accepted' : 'pending'">{{ session.isCurrent ? 'Current' : 'Active' }}</Badge>
          </div>
          <div class="flex items-center justify-between text-xs text-muted">
            <span>Last active: {{ session.lastActiveAt ?? '—' }}</span>
            <span>Created: {{ session.createdAt ?? '—' }}</span>
          </div>
          <div v-if="!session.isCurrent">
            <Button size="sm" variant="danger" @click="revokeSession(session.id)">Revoke</Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
