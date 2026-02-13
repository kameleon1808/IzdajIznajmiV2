<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)

const name = ref('')
const email = ref('')
const phone = ref('')
const dateOfBirth = ref('')
const gender = ref<'muski' | 'zenski' | ''>('')
const residentialAddress = ref('')
const employmentStatus = ref<'zaposlen' | 'nezaposlen' | 'student' | ''>('')
const password = ref('')
const passwordConfirmation = ref('')
const role = ref<Role>('seeker')
const error = ref('')

const onSubmit = async () => {
  error.value = ''
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      phone: phone.value || undefined,
      dateOfBirth: dateOfBirth.value || undefined,
      gender: gender.value || undefined,
      residentialAddress: residentialAddress.value || undefined,
      employmentStatus: employmentStatus.value || undefined,
      password: password.value,
      passwordConfirmation: passwordConfirmation.value,
      role: role.value,
    })
    toast.push({ title: t('auth.accountCreated'), type: 'success' })
    router.replace('/')
  } catch (err: any) {
    error.value = err.message ?? t('auth.registerFailed')
  }
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-900">{{ t('auth.register') }}</h1>
    <p class="text-sm text-muted">{{ t('auth.registerHint') }}</p>

    <ErrorBanner v-if="error" :message="error" />

    <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.name') }}</p>
        <Input v-model="name" :placeholder="t('auth.namePlaceholder')" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.email') }}</p>
        <Input v-model="email" :placeholder="t('auth.emailPlaceholder')" type="email" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.phoneOptional') }}</p>
        <Input v-model="phone" :placeholder="t('auth.phonePlaceholder')" type="tel" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.dateOfBirth') }}</p>
        <Input v-model="dateOfBirth" type="date" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.gender') }}</p>
        <select
          v-model="gender"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
        >
          <option value="">{{ t('common.notProvided') }}</option>
          <option value="muski">{{ t('common.gender.male') }}</option>
          <option value="zenski">{{ t('common.gender.female') }}</option>
        </select>
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.residentialAddress') }}</p>
        <Input v-model="residentialAddress" :placeholder="t('auth.residentialAddressPlaceholder')" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.employmentStatus') }}</p>
        <select
          v-model="employmentStatus"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
        >
          <option value="">{{ t('common.notProvided') }}</option>
          <option value="zaposlen">{{ t('common.employmentStatus.employed') }}</option>
          <option value="nezaposlen">{{ t('common.employmentStatus.unemployed') }}</option>
          <option value="student">{{ t('common.employmentStatus.student') }}</option>
        </select>
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.password') }}</p>
        <Input v-model="password" :placeholder="t('auth.passwordPlaceholder')" type="password" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.confirmPassword') }}</p>
        <Input v-model="passwordConfirmation" :placeholder="t('auth.passwordPlaceholder')" type="password" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ t('auth.role') }}</p>
        <select
          v-model="role"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm capitalize focus:border-primary focus:outline-none"
        >
          <option value="seeker">{{ t('auth.roles.seeker') }}</option>
          <option value="landlord">{{ t('auth.roles.landlord') }}</option>
        </select>
      </div>
      <Button block size="lg" :loading="auth.loading" @click="onSubmit">{{ t('auth.register') }}</Button>
      <p class="text-center text-sm text-muted">
        {{ t('auth.hasAccount') }}
        <button class="text-primary font-semibold" @click="router.push('/login')">{{ t('auth.login') }}</button>
      </p>
    </div>

    <div v-if="auth.isMockMode" class="rounded-2xl bg-surface p-3 text-sm text-muted border border-dashed border-line">
      {{ t('auth.mockRegisterNote') }}
    </div>
  </div>
</template>
