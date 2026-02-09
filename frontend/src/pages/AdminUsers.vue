<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { getAdminUsers } from '../services'
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const loading = ref(true)
const error = ref('')
const users = ref<any[]>([])
const query = ref('')
const role = ref('')
const suspicious = ref('')

const roleLabel = (value: string) => {
  if (value === 'admin') return t('auth.roles.admin')
  if (value === 'landlord') return t('auth.roles.landlord')
  if (value === 'seeker') return t('auth.roles.seeker')
  return value
}
const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const params: { q?: string; role?: string; suspicious?: boolean } = {}
    if (query.value.trim()) params.q = query.value.trim()
    if (role.value) params.role = role.value
    if (suspicious.value) params.suspicious = suspicious.value === 'true'
    users.value = await getAdminUsers(params)
  } catch (err) {
    error.value = (err as Error).message || t('admin.users.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch([query, role, suspicious], () => load())

const goToUser = (id: string | number) => {
  router.push(`/admin/users/${id}`)
}
</script>

<template>
  <div class="space-y-5">
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 to-slate-700 px-5 py-6 text-white shadow-lg">
      <p class="text-sm opacity-80">{{ t('admin.header.operations') }}</p>
      <h1 class="text-2xl font-semibold leading-tight">{{ t('admin.users.title') }}</h1>
      <p class="mt-1 text-sm opacity-75">{{ t('admin.users.subtitle') }}</p>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">{{ t('admin.nav.dashboard') }}</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">{{ t('admin.nav.moderation') }}</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">{{ t('admin.nav.ratings') }}</router-link>
      <router-link to="/admin/transactions" class="opacity-80 hover:opacity-100">{{ t('admin.nav.transactions') }}</router-link>
      <router-link to="/admin/users">{{ t('admin.nav.users') }}</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">{{ t('admin.nav.kyc') }}</router-link>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <input
        v-model="query"
        type="text"
        class="flex-1 rounded-xl border border-line px-3 py-2 text-sm"
        :placeholder="t('admin.users.searchPlaceholder')"
      />
      <select v-model="role" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">{{ t('admin.users.allRoles') }}</option>
        <option value="admin">{{ t('auth.roles.admin') }}</option>
        <option value="landlord">{{ t('auth.roles.landlord') }}</option>
        <option value="seeker">{{ t('auth.roles.seeker') }}</option>
      </select>
      <select v-model="suspicious" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">{{ t('admin.users.allUsers') }}</option>
        <option value="true">{{ t('admin.users.suspicious') }}</option>
        <option value="false">{{ t('admin.users.normal') }}</option>
      </select>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="3" />

    <div v-else class="space-y-3">
      <div
        v-for="user in users"
        :key="user.id"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white p-4 shadow-soft"
      >
        <div>
          <p class="text-base font-semibold text-slate-900">{{ user.fullName || user.name || user.id }}</p>
          <p class="text-xs text-muted">{{ user.email || '—' }} · #{{ user.id }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Badge :variant="user.isSuspicious ? 'rejected' : 'accepted'">{{ user.isSuspicious ? t('admin.users.suspicious') : t('admin.users.ok') }}</Badge>
          <Badge variant="info" class="capitalize">{{ roleLabel(user.role) }}</Badge>
          <Button size="sm" variant="secondary" @click="goToUser(user.id)">{{ t('common.open') }}</Button>
        </div>
      </div>
      <p v-if="!users.length" class="text-sm text-muted">{{ t('admin.users.empty') }}</p>
    </div>
  </div>
</template>
