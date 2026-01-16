import { defineStore } from 'pinia'
import { apiClient } from '../services/apiClient'

export type Role = 'guest' | 'tenant' | 'landlord' | 'admin'

interface User {
  id: string
  name: string
  email?: string
  role: Role
}

interface PersistedState {
  token: string | null
  user: User
}

const STORAGE_KEY = 'ii-auth-state'
const defaultUser: User = { id: 'guest', name: 'Guest', role: 'guest' }

const loadState = (): PersistedState => {
  if (typeof localStorage === 'undefined') return { token: null, user: { ...defaultUser } }
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored) {
    try {
      return JSON.parse(stored) as PersistedState
    } catch (e) {
      console.error(e)
    }
  }
  return { token: null, user: { ...defaultUser } }
}

const storedState = loadState()

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: storedState.user,
    token: storedState.token as string | null,
    isAuthenticated: storedState.user.role !== 'guest',
    loading: false,
    initialized: false,
    isMockMode: (import.meta.env.VITE_USE_MOCK_API ?? 'true') !== 'false',
  }),
  actions: {
    setSession(user: any, token: string | null) {
      this.user = {
        id: String(user.id),
        name: user.name ?? 'User',
        email: user.email,
        role: (user.role as Role) ?? 'tenant',
      }
      this.token = token
      this.isAuthenticated = !!token && this.user.role !== 'guest'
      this.persist()
    },
    persist() {
      if (typeof localStorage === 'undefined') return
      const payload: PersistedState = { token: this.token, user: this.user }
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
    },
    async initialize() {
      if (this.initialized) return
      if (this.token && !this.isMockMode) {
        try {
          await this.fetchMe()
        } catch {
          await this.logout()
        }
      }
      this.initialized = true
    },
    async register(payload: { name: string; email: string; password: string; passwordConfirmation?: string; role?: Role }) {
      if (this.isMockMode) {
        this.loginAs(payload.role ?? 'tenant')
        return
      }
      this.loading = true
      try {
        const { data } = await apiClient.post('/auth/register', {
          name: payload.name,
          email: payload.email,
          password: payload.password,
          password_confirmation: payload.passwordConfirmation ?? payload.password,
          role: payload.role ?? 'tenant',
        })
        this.setSession(data.user, data.token)
      } finally {
        this.loading = false
      }
    },
    async login(email: string, password: string) {
      if (this.isMockMode) {
        this.loginAs('tenant')
        return
      }
      this.loading = true
      try {
        const { data } = await apiClient.post('/auth/login', { email, password })
        this.setSession(data.user, data.token)
      } finally {
        this.loading = false
      }
    },
    async fetchMe() {
      if (this.isMockMode || !this.token) return
      const { data } = await apiClient.get('/auth/me')
      this.setSession(data.user, this.token)
    },
    async logout() {
      try {
        if (!this.isMockMode && this.token) {
          await apiClient.post('/auth/logout')
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.clearSession()
      }
    },
    loginAs(role: Role) {
      if (!this.isMockMode) return
      const names: Record<Role, string> = {
        guest: 'Guest',
        tenant: 'Tena Tenant',
        landlord: 'Lana Landlord',
        admin: 'Admin',
      }
      this.user = { id: `${role}-1`, name: names[role] ?? 'User', role }
      this.isAuthenticated = role !== 'guest'
      this.token = null
      this.persist()
    },
    clearSession() {
      this.user = { ...defaultUser }
      this.token = null
      this.isAuthenticated = false
      this.persist()
    },
    async handleUnauthorized() {
      this.clearSession()
    },
  },
})
