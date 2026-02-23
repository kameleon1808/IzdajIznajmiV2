<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { changeMyPassword, updateMyProfile, uploadMyAvatar } from '../services'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const profileForm = reactive({
  fullName: auth.user.fullName ?? auth.user.name ?? '',
  dateOfBirth: auth.user.dateOfBirth ?? '',
  gender: auth.user.gender ?? '',
  residentialAddress: auth.user.residentialAddress ?? '',
  employmentStatus: auth.user.employmentStatus ?? '',
  phone: auth.user.phone ?? '',
  address: typeof auth.user.addressBook === 'string'
    ? auth.user.addressBook
    : auth.user.addressBook?.primary ?? '',
})

const initialProfile = ref({ ...profileForm })
const profileDirty = computed(
  () =>
    profileForm.fullName !== initialProfile.value.fullName ||
    profileForm.dateOfBirth !== initialProfile.value.dateOfBirth ||
    profileForm.gender !== initialProfile.value.gender ||
    profileForm.residentialAddress !== initialProfile.value.residentialAddress ||
    profileForm.employmentStatus !== initialProfile.value.employmentStatus ||
    profileForm.phone !== initialProfile.value.phone ||
    profileForm.address !== initialProfile.value.address,
)

const profileSaving = ref(false)
const profileError = ref('')
const avatarInputRef = ref<HTMLInputElement | null>(null)
const avatarFile = ref<File | null>(null)
const avatarPreviewUrl = ref<string | null>(null)
const avatarUploading = ref(false)
const avatarError = ref('')
const avatarImageUrl = computed(() => avatarPreviewUrl.value || auth.user.avatarUrl || null)

const saveProfile = async () => {
  if (!profileDirty.value) return
  profileSaving.value = true
  profileError.value = ''
  try {
    const payload = {
      fullName: profileForm.fullName,
      dateOfBirth: profileForm.dateOfBirth ? profileForm.dateOfBirth : null,
      gender: profileForm.gender ? profileForm.gender : null,
      residentialAddress: profileForm.residentialAddress ? profileForm.residentialAddress : null,
      employmentStatus: profileForm.employmentStatus ? profileForm.employmentStatus : null,
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

const resetAvatarSelection = () => {
  if (avatarPreviewUrl.value) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
  }
  avatarFile.value = null
  avatarPreviewUrl.value = null
  if (avatarInputRef.value) {
    avatarInputRef.value.value = ''
  }
}

const openAvatarPicker = () => {
  avatarInputRef.value?.click()
}

const onAvatarChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return
  avatarError.value = ''
  avatarFile.value = file
  if (avatarPreviewUrl.value) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
  }
  avatarPreviewUrl.value = URL.createObjectURL(file)
}

const saveAvatar = async () => {
  if (!avatarFile.value) return
  avatarUploading.value = true
  avatarError.value = ''
  try {
    const user = await uploadMyAvatar(avatarFile.value)
    auth.setUser(user)
    resetAvatarSelection()
    toast.push({ title: t('common.success'), message: t('settings.profile.avatarUpdated'), type: 'success' })
  } catch (err) {
    avatarError.value = (err as Error).message || t('settings.profile.avatarUpdateFailed')
  } finally {
    avatarUploading.value = false
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
    profileForm.dateOfBirth = next.dateOfBirth ?? ''
    profileForm.gender = next.gender ?? ''
    profileForm.residentialAddress = next.residentialAddress ?? ''
    profileForm.employmentStatus = next.employmentStatus ?? ''
    profileForm.phone = next.phone ?? ''
    profileForm.address = typeof next.addressBook === 'string'
      ? next.addressBook
      : next.addressBook?.primary ?? ''
    initialProfile.value = { ...profileForm }
  },
)

onBeforeUnmount(() => {
  if (avatarPreviewUrl.value) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-2xl bg-white p-4 shadow-soft border border-white/60 space-y-3">
      <h2 class="text-base font-semibold text-slate-900">{{ t('settings.profile.title') }}</h2>
      <ErrorBanner v-if="profileError" :message="profileError" />
      <ErrorBanner v-if="avatarError" :message="avatarError" />
      <div class="rounded-2xl border border-line bg-slate-50 p-3">
        <p class="text-sm font-semibold text-slate-900">{{ t('settings.profile.avatarTitle') }}</p>
        <div class="mt-3 flex items-center gap-3">
          <img
            v-if="avatarImageUrl"
            :src="avatarImageUrl"
            :alt="t('common.avatarAlt')"
            class="h-12 w-12 rounded-2xl object-cover shadow-soft"
          />
          <div
            v-else
            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 px-1 text-center text-[10px] font-semibold leading-tight text-slate-600 shadow-soft"
          >
            Blank profile picture
          </div>
          <div class="min-w-0 flex-1 space-y-2">
            <input
              ref="avatarInputRef"
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              class="hidden"
              @change="onAvatarChange"
            />
            <p v-if="avatarFile" class="truncate text-xs text-muted">{{ avatarFile.name }}</p>
            <div class="flex gap-2">
              <Button size="sm" variant="secondary" @click="openAvatarPicker">
                {{ t('settings.profile.chooseAvatar') }}
              </Button>
              <Button size="sm" :disabled="!avatarFile || avatarUploading" @click="saveAvatar">
                {{ avatarUploading ? t('common.saving') : t('settings.profile.uploadAvatar') }}
              </Button>
            </div>
          </div>
        </div>
      </div>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.fullName') }}
        <input v-model="profileForm.fullName" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.dateOfBirth') }}
        <input v-model="profileForm.dateOfBirth" type="date" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.gender') }}
        <select
          v-model="profileForm.gender"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
        >
          <option value="">{{ t('common.notProvided') }}</option>
          <option value="muski">{{ t('common.gender.male') }}</option>
          <option value="zenski">{{ t('common.gender.female') }}</option>
          <option value="ne_zelim_da_kazem">{{ t('common.gender.ratherNotToSay') }}</option>
        </select>
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.residentialAddress') }}
        <input v-model="profileForm.residentialAddress" type="text" class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none" />
      </label>
      <label class="space-y-1 text-sm font-semibold text-slate-900">
        {{ t('settings.personal.employmentStatus') }}
        <select
          v-model="profileForm.employmentStatus"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
        >
          <option value="">{{ t('common.notProvided') }}</option>
          <option value="zaposlen">{{ t('common.employmentStatus.employed') }}</option>
          <option value="nezaposlen">{{ t('common.employmentStatus.unemployed') }}</option>
          <option value="student">{{ t('common.employmentStatus.student') }}</option>
          <option value="penzioner">{{ t('common.employmentStatus.retired') }}</option>
        </select>
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
