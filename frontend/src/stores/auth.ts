import { defineStore } from 'pinia'
import { apiClient, ensureCsrfCookie } from '../services/apiClient'

export type Role = 'guest' | 'seeker' | 'landlord' | 'admin'

interface User {
  id: string
  name: string
  fullName?: string
  email?: string
  role: Role
  roles: Role[]
}

interface PersistedState {
  user: User
  impersonating?: boolean
  impersonator?: User | null
}

const STORAGE_KEY = 'ii-auth-state'
const defaultUser: User = { id: 'guest', name: 'Guest', role: 'guest', roles: ['guest'] }

const loadState = (): PersistedState => {
  if (typeof localStorage === 'undefined') return { user: { ...defaultUser } }
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored) {
    try {
      return JSON.parse(stored) as PersistedState
    } catch (e) {
      console.error(e)
    }
  }
  return { user: { ...defaultUser } }
}

const storedState = loadState()

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: storedState.user,
    isAuthenticated: storedState.user.role !== 'guest',
    loading: false,
    initialized: false,
    impersonating: storedState.impersonating ?? false,
    impersonator: storedState.impersonator ?? null,
    isMockMode: (import.meta.env.VITE_USE_MOCK_API ?? 'true') !== 'false',
  }),
  getters: {
    primaryRole: (state): Role => state.user.roles[0] ?? state.user.role ?? 'guest',
    hasRole: (state) => (role: Role) => state.user.roles.includes(role) || state.user.role === role,
  },
  actions: {
    mapUser(user: any): User {
      const roles = (user?.roles as Role[] | undefined)?.map(this.normalizeRole) ?? []
      const primary = roles[0] ?? this.normalizeRole(user?.role) ?? 'guest'
      const normalizedRoles = roles.length ? roles : [primary]
      return {
        id: String(user?.id ?? 'guest'),
        name: user?.name ?? user?.fullName ?? user?.full_name ?? 'User',
        fullName: user?.fullName ?? user?.full_name,
        email: user?.email,
        role: primary,
        roles: normalizedRoles,
      }
    },
    setSession(payload: { user?: any; impersonating?: boolean; impersonator?: any }) {
      const nextUser = payload.user ? this.mapUser(payload.user) : { ...defaultUser }
      this.user = nextUser
      this.isAuthenticated = nextUser.role !== 'guest'
      this.impersonating = Boolean(payload.impersonating) && nextUser.role !== 'guest'
      this.impersonator = payload.impersonator ? this.mapUser(payload.impersonator) : null
      this.persist()
    },
    setUser(user: any) {
      this.setSession({ user, impersonating: false, impersonator: null })
    },
    normalizeRole(role?: string): Role {
      if (!role) return 'guest'
      if (role === 'tenant') return 'seeker'
      return (['seeker', 'landlord', 'admin'] as const).includes(role as any) ? (role as Role) : 'guest'
    },
    persist() {
      if (typeof localStorage === 'undefined') return
      const payload: PersistedState = {
        user: this.user,
        impersonating: this.impersonating,
        impersonator: this.impersonator,
      }
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
    },
    async initialize() {
      if (this.initialized) return
      if (this.isMockMode) {
        this.isAuthenticated = this.user.role !== 'guest'
        this.initialized = true
        return
      }
      try {
        await this.fetchMe()
      } catch {
        this.clearSession()
      } finally {
        this.initialized = true
      }
    },
    async register(payload: {
      name: string
      email: string
      password: string
      passwordConfirmation?: string
      role?: Role
      phone?: string
      fullName?: string
    }) {
      if (this.isMockMode) {
        this.loginAs(payload.role ?? 'seeker')
        return
      }
      this.loading = true
      try {
        await ensureCsrfCookie()
        const { data } = await apiClient.post('/auth/register', {
          name: payload.name,
          full_name: payload.fullName ?? payload.name,
          email: payload.email,
          phone: payload.phone,
          password: payload.password,
          password_confirmation: payload.passwordConfirmation ?? payload.password,
          role: payload.role ?? 'seeker',
        })
        this.setUser(data.user)
      } finally {
        this.loading = false
      }
    },
    async login(email: string, password: string) {
      if (this.isMockMode) {
        this.loginAs('seeker')
        return
      }
      this.loading = true
      try {
        await ensureCsrfCookie()
        const { data } = await apiClient.post('/auth/login', { email, password })
        this.setUser(data.user)
      } finally {
        this.loading = false
      }
    },
    async fetchMe() {
      if (this.isMockMode) return
      const { data } = await apiClient.get('/auth/me')
      this.setSession({
        user: data.user,
        impersonating: data.impersonating,
        impersonator: data.impersonator,
      })
    },
    async logout() {
      try {
        if (!this.isMockMode) {
          await ensureCsrfCookie()
          await apiClient.post('/auth/logout')
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.clearSession()
      }
    },
    async startImpersonation(userId: string | number) {
      if (this.isMockMode) {
        this.impersonator = { ...this.user }
        this.user = { id: String(userId), name: `Impersonated ${userId}`, role: 'seeker', roles: ['seeker'] }
        this.impersonating = true
        this.isAuthenticated = true
        this.persist()
        return
      }
      await ensureCsrfCookie()
      const { data } = await apiClient.post(`/admin/impersonate/${userId}`)
      this.setSession({
        user: data.user ?? data.data ?? data,
        impersonating: data.impersonating ?? true,
        impersonator: data.impersonator,
      })
    },
    async stopImpersonation() {
      if (this.isMockMode) {
        this.impersonating = false
        this.impersonator = null
        this.user = { ...defaultUser }
        this.isAuthenticated = false
        this.persist()
        return
      }
      await ensureCsrfCookie()
      const { data } = await apiClient.post('/admin/impersonate/stop')
      this.setSession({
        user: data.user ?? defaultUser,
        impersonating: false,
        impersonator: null,
      })
      await this.fetchMe()
    },
    loginAs(role: Role) {
      if (!this.isMockMode) return
      const names: Record<Role, string> = {
        guest: 'Guest',
        seeker: 'Sara Seeker',
        landlord: 'Lana Landlord',
        admin: 'Admin',
      }
      this.user = { id: `${role}-1`, name: names[role] ?? 'User', role, roles: [role] }
      this.isAuthenticated = role !== 'guest'
      this.persist()
    },
    clearSession() {
      this.user = { ...defaultUser }
      this.isAuthenticated = false
      this.impersonating = false
      this.impersonator = null
      this.persist()
    },
    async handleUnauthorized() {
      this.clearSession()
    },
  },
})
