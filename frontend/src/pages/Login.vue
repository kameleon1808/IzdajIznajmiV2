<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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

const onSubmit = async () => {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    toast.push({ title: 'Welcome back', type: 'success' })
    const redirect = (route.query.returnUrl as string) || '/'
    router.replace(redirect)
  } catch (err: any) {
    error.value = err.message ?? 'Login failed.'
  }
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-900">Login</h1>
    <p class="text-sm text-muted">Use demo naloge ili vaše kredencijale.</p>

    <ErrorBanner v-if="error" :message="error" />

    <div class="space-y-3 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
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

    <div v-if="auth.isMockMode" class="rounded-2xl bg-surface p-3 text-sm text-muted border border-dashed border-line">
      Dev napomena: u mock modu login samo prebacuje u Tenant ulogu.
    </div>
  </div>
</template>
