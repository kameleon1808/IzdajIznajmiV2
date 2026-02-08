<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { getAdminUsers } from '../services'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const users = ref<any[]>([])
const query = ref('')
const role = ref('')
const suspicious = ref('')

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
    error.value = (err as Error).message || 'Failed to load users.'
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
      <p class="text-sm opacity-80">Admin Operations</p>
      <h1 class="text-2xl font-semibold leading-tight">Users</h1>
      <p class="mt-1 text-sm opacity-75">Search and review user security status.</p>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-semibold text-indigo-600">
      <router-link to="/admin" class="opacity-80 hover:opacity-100">Dashboard</router-link>
      <router-link to="/admin/moderation" class="opacity-80 hover:opacity-100">Moderacija</router-link>
      <router-link to="/admin/ratings" class="opacity-80 hover:opacity-100">Ocene</router-link>
      <router-link to="/admin/transactions" class="opacity-80 hover:opacity-100">Transakcije</router-link>
      <router-link to="/admin/users">Korisnici</router-link>
      <router-link to="/admin/kyc" class="opacity-80 hover:opacity-100">KYC</router-link>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <input
        v-model="query"
        type="text"
        class="flex-1 rounded-xl border border-line px-3 py-2 text-sm"
        placeholder="Search by name, email, or ID"
      />
      <select v-model="role" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">All roles</option>
        <option value="admin">Admin</option>
        <option value="landlord">Landlord</option>
        <option value="seeker">Seeker</option>
      </select>
      <select v-model="suspicious" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
        <option value="">All users</option>
        <option value="true">Suspicious</option>
        <option value="false">Normal</option>
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
          <Badge :variant="user.isSuspicious ? 'rejected' : 'accepted'">{{ user.isSuspicious ? 'Suspicious' : 'OK' }}</Badge>
          <Badge variant="info" class="capitalize">{{ user.role }}</Badge>
          <Button size="sm" variant="secondary" @click="goToUser(user.id)">Open</Button>
        </div>
      </div>
      <p v-if="!users.length" class="text-sm text-muted">No users found.</p>
    </div>
  </div>
</template>
