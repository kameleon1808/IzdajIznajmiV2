<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, Bookmark, ChevronRight, CreditCard, HelpCircle, Languages, LogOut, Shield, Store, FileText } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const router = useRouter()
const showLogout = ref(false)
const auth = useAuthStore()
const toast = useToastStore()
const selectedRole = ref<Role>(auth.primaryRole)
const showRoleSwitch = computed(() => auth.isMockMode)

const baseItems = [
  { label: 'Your Card', icon: CreditCard, action: () => {} },
  { label: 'Security', icon: Shield, action: () => router.push('/settings/security') },
  { label: 'Notification', icon: Bell, action: () => router.push('/settings/notifications') },
  { label: 'Languages', icon: Languages, action: () => router.push('/settings/language') },
  { label: 'Help & Support', icon: HelpCircle, action: () => router.push('/settings/legal') },
]

const menuItems = computed(() => {
  const extras = []
  if (auth.hasRole('landlord')) {
    extras.push({ label: 'Verification', icon: Shield, action: () => router.push('/profile/verification') })
    extras.push({ label: 'My Listings', icon: Store, action: () => router.push('/landlord/listings') })
    extras.push({ label: 'Transactions', icon: FileText, action: () => router.push('/transactions') })
  }
  if (auth.hasRole('admin')) {
    extras.push({ label: 'KYC Review', icon: Shield, action: () => router.push('/admin/kyc') })
    extras.push({ label: 'Transactions', icon: FileText, action: () => router.push('/admin/transactions') })
    extras.push({ label: 'Users', icon: FileText, action: () => router.push('/admin/users') })
  }
  if (auth.hasRole('seeker')) {
    extras.push({ label: 'Saved Searches', icon: Bookmark, action: () => router.push('/saved-searches') })
    extras.push({ label: 'Transactions', icon: FileText, action: () => router.push('/transactions') })
  }
  return [...extras, ...baseItems]
})

const switchRole = (role: Role) => {
  selectedRole.value = role
  auth.loginAs(role)
  toast.push({ title: `Role: ${role}`, type: 'info' })
}

const handleLogout = async () => {
  await auth.logout()
  toast.push({ title: 'Logged out', type: 'info' })
  showLogout.value = false
  router.push('/')
}
</script>

<template>
  <div class="space-y-5">
    <div
      v-if="!auth.isAuthenticated && !auth.isMockMode"
      class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60"
    >
      <p class="text-sm text-muted">Niste prijavljeni. Ulogujte se da vidite rezervacije i poruke.</p>
      <div class="flex gap-2">
        <Button class="flex-1" @click="router.push('/login')">Login</Button>
        <Button class="flex-1" variant="secondary" @click="router.push('/register')">Register</Button>
      </div>
    </div>

    <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <img
        src="https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=300&q=80"
        alt="avatar"
        class="h-16 w-16 rounded-3xl object-cover"
      />
      <div>
        <p class="text-lg font-semibold text-slate-900">{{ auth.user.name }}</p>
        <p class="text-sm text-muted">@{{ auth.user.id }}</p>
        <Badge variant="pending" class="mt-1 inline-block capitalize">{{ auth.primaryRole }}</Badge>
      </div>
      <div class="ml-auto">
        <Button variant="secondary" size="sm" @click="router.push(`/users/${auth.user.id}`)">View profile</Button>
      </div>
    </div>

    <div v-if="showRoleSwitch" class="rounded-2xl bg-surface p-2 shadow-soft border border-white/60">
      <p class="px-2 text-xs font-semibold text-muted">Switch role (dev only)</p>
      <div class="mt-2 grid grid-cols-4 gap-2">
        <button
          v-for="role in ['guest', 'seeker', 'landlord', 'admin']"
          :key="role"
          class="rounded-xl px-3 py-2 text-sm font-semibold capitalize shadow-soft"
          :class="selectedRole === role ? 'bg-primary text-white' : 'bg-white text-slate-800'"
          @click="switchRole(role as Role)"
        >
          {{ role }}
        </button>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-2 shadow-soft border border-white/60">
      <button
        v-for="item in menuItems"
        :key="item.label"
        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-surface"
        @click="item.action()"
      >
        <component :is="item.icon" class="h-5 w-5 text-primary" />
        <span class="flex-1 text-sm font-semibold text-slate-900">{{ item.label }}</span>
        <ChevronRight class="h-4 w-4 text-muted" />
      </button>
    </div>

    <button
      v-if="auth.isAuthenticated || auth.isMockMode"
      class="flex w-full items-center gap-3 rounded-xl bg-white px-3 py-3 text-left text-red-500 shadow-soft border border-white/60"
      @click="showLogout = true"
    >
      <LogOut class="h-5 w-5" />
      <span class="flex-1 text-sm font-semibold">Logout</span>
      <ChevronRight class="h-4 w-4" />
    </button>
  </div>

  <ModalSheet v-model="showLogout" title="Are you sure?">
    <p class="text-sm text-muted">You will be logged out of this device. Continue?</p>
    <div class="mt-4 flex gap-2">
      <Button variant="secondary" class="flex-1" @click="showLogout = false">Cancel</Button>
      <Button variant="danger" class="flex-1" @click="handleLogout">Logout</Button>
    </div>
  </ModalSheet>
</template>
