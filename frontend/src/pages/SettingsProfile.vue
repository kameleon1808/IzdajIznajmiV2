<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { changeMyPassword, updateMyProfile } from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const profileForm = reactive({
  fullName: auth.user.fullName ?? auth.user.name ?? '',
  phone: auth.user.phone ?? '',
  address: typeof auth.user.addressBook === 'string'
    ? auth.user.addressBook
    : auth.user.addressBook?.primary ?? '',
})

const initialProfile = ref({ ...profileForm })
const profileDirty = computed(
  () =>
    profileForm.fullName !== initialProfile.value.fullName ||
    profileForm.phone !== initialProfile.value.phone ||
    profileForm.address !== initialProfile.value.address,
)

const profileSaving = ref(false)
const profileError = ref('')

const saveProfile = async () => {
  if (!profileDirty.value) return
  profileSaving.value = true
  profileError.value = ''
  try {
    const payload = {
      fullName: profileForm.fullName,
      phone: profileForm.phone ? profileForm.phone : null,
      addressBook: profileForm.address ? { primary: profileForm.address } : null,
    }
    const user = await updateMyProfile(payload)
    auth.setUser(user)
    initialProfile.value = { ...profileForm }
    toast.push({ title: t('common.success'), message: t('settings.profile.saved'), type: 'success' })
  } catch (err) {
    profileError.value = (err as Error).message || t('settings.profile.saveFailed')
  } finally {
    profileSaving.value = false
  }
}

const passwordForm = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
})
const passwordSaving = ref(false)
const passwordError = ref('')
const canSavePassword = computed(
  () => passwordForm.currentPassword && passwordForm.newPassword && passwordForm.confirmPassword,
)

const updatePassword = async () => {
  if (!canSavePassword.value) return
  if (passwordForm.newPassword !== passwordForm.confirmPassword) {
    passwordError.value = t('settings.profile.passwordMismatch')
    return
  }
  passwordSaving.value = true
  passwordError.value = ''
  try {
    await changeMyPassword({
      currentPassword: passwordForm.currentPassword,
      newPassword: passwordForm.newPassword,
      newPasswordConfirmation: passwordForm.confirmPassword,
    })
    passwordForm.currentPassword = ''
    passwordForm.newPassword = ''
    passwordForm.confirmPassword = ''
    toast.push({ title: t('common.success'), message: t('settings.profile.passwordUpdated'), type: 'success' })
  } catch (err) {
    passwordError.value = (err as Error).message || t('settings.profile.passwordUpdateFailed')
  } finally {
    passwordSaving.value = false
  }
}

watch(
  () => auth.user,
  (next) => {
    profileForm.fullName = next.fullName ?? next.name ?? ''
    profileForm.phone = next.phone ?? ''
    profileForm.address = typeof next.addressBook === 'string'
      ? next.addressBook
      : next.addressBook?.primary ?? ''
    initialProfile.value = { ...profileForm }
  },
)
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
      <h2 class="text-base font-semibold text-slate-900">{{ t('settings.profile.title') }}</h2>
      <ErrorBanner v-if="profileError" :message="profileError" />
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.fullName') }}
        <input v-model="profileForm.fullName" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.phone') }}
        <input v-model="profileForm.phone" type="tel" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.location') }}
        <input v-model="profileForm.address" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <Button block size="lg" :variant="profileDirty ? 'primary' : 'secondary'" :disabled="!profileDirty || profileSaving" @click="saveProfile">
        {{ profileSaving ? t('common.saving') : t('settings.profile.save') }}
      </Button>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
      <h2 class="text-base font-semibold text-slate-900">{{ t('settings.profile.passwordTitle') }}</h2>
      <ErrorBanner v-if="passwordError" :message="passwordError" />
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.security.currentPassword') }}
        <input v-model="passwordForm.currentPassword" type="password" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.profile.newPassword') }}
        <input v-model="passwordForm.newPassword" type="password" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.profile.confirmPassword') }}
        <input v-model="passwordForm.confirmPassword" type="password" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <Button block size="lg" :variant="canSavePassword ? 'primary' : 'secondary'" :disabled="!canSavePassword || passwordSaving" @click="updatePassword">
        {{ passwordSaving ? t('common.saving') : t('settings.profile.updatePassword') }}
      </Button>
    </div>
  </div>
</template>
