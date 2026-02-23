<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, Bookmark, ChevronRight, CreditCard, HelpCircle, Languages, LogOut, Shield, Store, FileText } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import ImageLightbox from '../components/ui/ImageLightbox.vue'
import ModalSheet from '../components/ui/ModalSheet.vue'
import AvatarPlaceholder from '../components/ui/AvatarPlaceholder.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useLanguageStore } from '../stores/language'

const router = useRouter()
const showLogout = ref(false)
const auth = useAuthStore()
const toast = useToastStore()
const languageStore = useLanguageStore()
const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
const profileAvatarUrl = computed(() => auth.user.avatarUrl ?? null)
const profileAvatarImages = computed(() => (profileAvatarUrl.value ? [profileAvatarUrl.value] : []))
const avatarLightboxOpen = ref(false)
const avatarLightboxIndex = ref(0)
const selectedRole = ref<Role>(auth.primaryRole)
const showRoleSwitch = computed(() => auth.isMockMode)
const roleLabel = (role: Role | string) => {
  if (role === 'seeker') return t('auth.roles.seeker')
  if (role === 'landlord') return t('auth.roles.landlord')
  if (role === 'admin') return t('auth.roles.admin')
  if (role === 'guest') return t('auth.roles.guest')
  return role
}

const baseItems = computed(() => [
  { label: t('profile.menu.card'), icon: CreditCard, action: () => {} },
  { label: t('profile.menu.security'), icon: Shield, action: () => router.push('/settings/security') },
  { label: t('profile.menu.notifications'), icon: Bell, action: () => router.push('/settings/notifications') },
  { label: t('profile.menu.languages'), icon: Languages, action: () => router.push('/settings/language') },
  { label: t('profile.menu.support'), icon: HelpCircle, action: () => router.push('/settings/legal') },
])

const menuItems = computed(() => {
  const extras = []
  if (auth.hasRole('landlord') || auth.hasRole('seeker')) {
    extras.push({ label: t('profile.menu.verification'), icon: Shield, action: () => router.push('/profile/verification') })
  }
  if (auth.hasRole('landlord')) {
    extras.push({ label: t('profile.menu.myListings'), icon: Store, action: () => router.push('/landlord/listings') })
    extras.push({ label: t('profile.menu.transactions'), icon: FileText, action: () => router.push('/transactions') })
  }
  if (auth.hasRole('admin')) {
    extras.push({ label: t('profile.menu.kycReview'), icon: Shield, action: () => router.push('/admin/kyc') })
    extras.push({ label: t('profile.menu.transactions'), icon: FileText, action: () => router.push('/admin/transactions') })
    extras.push({ label: t('profile.menu.users'), icon: FileText, action: () => router.push('/admin/users') })
  }
  if (auth.hasRole('seeker')) {
    extras.push({ label: t('profile.menu.savedSearches'), icon: Bookmark, action: () => router.push('/saved-searches') })
    extras.push({ label: t('profile.menu.transactions'), icon: FileText, action: () => router.push('/transactions') })
  }
  return [...extras, ...baseItems.value]
})

const switchRole = (role: Role) => {
  selectedRole.value = role
  auth.loginAs(role)
  toast.push({ title: `${t('profile.roleLabel')}: ${role}`, type: 'info' })
}

const handleLogout = async () => {
  await auth.logout()
  toast.push({ title: t('profile.loggedOut'), type: 'info' })
  showLogout.value = false
  router.push('/')
}

const openAvatarLightbox = () => {
  if (!profileAvatarUrl.value) return
  avatarLightboxIndex.value = 0
  avatarLightboxOpen.value = true
}
</script>

<template>
  <div class="space-y-5">
    <div
      v-if="!auth.isAuthenticated && !auth.isMockMode"
      class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60"
    >
      <p class="text-sm text-muted">{{ t('profile.notSignedIn') }}</p>
      <div class="flex gap-2">
        <Button class="flex-1" @click="router.push('/login')">{{ t('auth.login') }}</Button>
        <Button class="flex-1" variant="secondary" @click="router.push('/register')">{{ t('auth.register') }}</Button>
      </div>
    </div>

    <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <button
        v-if="profileAvatarUrl"
        type="button"
        class="cursor-zoom-in rounded-2xl"
        :aria-label="t('common.avatarAlt')"
        @click="openAvatarLightbox"
      >
        <img
          :src="profileAvatarUrl"
          :alt="t('common.avatarAlt')"
          class="h-12 w-12 rounded-2xl object-cover shadow-soft"
        />
      </button>
      <AvatarPlaceholder v-else :alt="t('common.avatarAlt')" />
      <div>
        <p class="text-lg font-semibold text-slate-900">{{ auth.user.name }}</p>
        <Badge variant="pending" class="mt-1 inline-block capitalize">{{ roleLabel(auth.primaryRole) }}</Badge>
      </div>
      <div class="ml-auto">
        <Button variant="secondary" size="sm" @click="router.push(`/users/${auth.user.id}`)">{{ t('profile.viewProfile') }}</Button>
      </div>
    </div>

    <div v-if="showRoleSwitch" class="rounded-2xl bg-surface p-2 shadow-soft border border-white/60">
      <p class="px-2 text-xs font-semibold text-muted">{{ t('profile.switchRoleDev') }}</p>
      <div class="mt-2 grid grid-cols-4 gap-2">
        <button
          v-for="role in ['guest', 'seeker', 'landlord', 'admin']"
          :key="role"
          class="rounded-xl px-3 py-2 text-sm font-semibold capitalize shadow-soft"
          :class="selectedRole === role ? 'bg-primary text-white' : 'bg-white text-slate-800'"
          @click="switchRole(role as Role)"
        >
          {{ roleLabel(role as Role) }}
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
      <span class="flex-1 text-sm font-semibold">{{ t('auth.logout') }}</span>
      <ChevronRight class="h-4 w-4" />
    </button>
  </div>

  <ModalSheet v-model="showLogout" :title="t('profile.logoutConfirmTitle')">
    <p class="text-sm text-muted">{{ t('profile.logoutConfirmMessage') }}</p>
    <div class="mt-4 flex gap-2">
      <Button variant="secondary" class="flex-1" @click="showLogout = false">{{ t('common.cancel') }}</Button>
      <Button variant="danger" class="flex-1" @click="handleLogout">{{ t('auth.logout') }}</Button>
    </div>
  </ModalSheet>

  <ImageLightbox
    :images="profileAvatarImages"
    :open="avatarLightboxOpen"
    :index="avatarLightboxIndex"
    :alt="t('common.avatarAlt')"
    @update:open="avatarLightboxOpen = $event"
    @update:index="avatarLightboxIndex = $event"
  />
</template>
