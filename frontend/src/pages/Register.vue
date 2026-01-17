<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const phone = ref('')
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
      password: password.value,
      passwordConfirmation: passwordConfirmation.value,
      role: role.value,
    })
    toast.push({ title: 'Account created', type: 'success' })
    router.replace('/')
  } catch (err: any) {
    error.value = err.message ?? 'Registration failed.'
  }
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-900">Register</h1>
    <p class="text-sm text-muted">Kreirajte nalog za seeker ili landlord ulogu.</p>

    <ErrorBanner v-if="error" :message="error" />

    <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Name</p>
        <Input v-model="name" placeholder="Full name" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Email</p>
        <Input v-model="email" placeholder="you@example.com" type="email" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Phone (optional)</p>
        <Input v-model="phone" placeholder="+3859..." type="tel" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Password</p>
        <Input v-model="password" placeholder="••••••" type="password" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Confirm Password</p>
        <Input v-model="passwordConfirmation" placeholder="••••••" type="password" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">Role</p>
        <select
          v-model="role"
          class="w-full rounded-xl border border-line px-3 py-3 text-sm capitalize focus:border-primary focus:outline-none"
        >
          <option value="seeker">Seeker</option>
          <option value="landlord">Landlord</option>
        </select>
      </div>
      <Button block size="lg" :loading="auth.loading" @click="onSubmit">Register</Button>
      <p class="text-center text-sm text-muted">
        Već imate nalog?
        <button class="text-primary font-semibold" @click="router.push('/login')">Login</button>
      </p>
    </div>

    <div v-if="auth.isMockMode" class="rounded-2xl bg-surface p-3 text-sm text-muted border border-dashed border-line">
      Dev napomena: u mock modu registracija samo postavlja ulogu i prebacuje na početni ekran.
    </div>
  </div>
</template>
