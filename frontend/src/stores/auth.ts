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
  mfaEnabled?: boolean
  mfaConfirmedAt?: string | null
  mfaRequired?: boolean
  isSuspicious?: boolean
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
    mfaRequired: false,
    mfaChallengeId: null as string | null,
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
        mfaEnabled: Boolean(user?.mfaEnabled ?? user?.mfa_enabled ?? false),
        mfaConfirmedAt: user?.mfaConfirmedAt ?? user?.mfa_confirmed_at ?? null,
        mfaRequired: Boolean(user?.mfaRequired ?? user?.mfa_required ?? false),
        isSuspicious: Boolean(user?.isSuspicious ?? user?.is_suspicious ?? false),
      }
    },
    setSession(payload: { user?: any; impersonating?: boolean; impersonator?: any }) {
      const nextUser = payload.user ? this.mapUser(payload.user) : { ...defaultUser }
      this.user = nextUser
      this.isAuthenticated = nextUser.role !== 'guest'
      this.impersonating = Boolean(payload.impersonating) && nextUser.role !== 'guest'
      this.impersonator = payload.impersonator ? this.mapUser(payload.impersonator) : null
      this.clearMfaChallenge()
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
        return { mfaRequired: false }
      }
      this.clearMfaChallenge()
      this.loading = true
      try {
        await ensureCsrfCookie()
        const response = await apiClient.post('/auth/login', { email, password })
        const data = response.data
        if (data?.mfa_required) {
          this.setMfaChallenge(data.challenge_id)
          return { mfaRequired: true, challengeId: data.challenge_id }
        }
        this.setUser(data.user)
        return { mfaRequired: false }
      } finally {
        this.loading = false
      }
    },
    async fetchMe() {
      if (this.isMockMode) return
      const response = await apiClient.get('/auth/me')
      const data = response.data
      if (data?.mfa_required) {
        if (this.mfaChallengeId && data.challenge_id !== this.mfaChallengeId) {
          this.clearMfaChallenge()
          return
        }
        this.setMfaChallenge(data.challenge_id)
        return
      }
      this.setSession({
        user: data.user,
        impersonating: data.impersonating,
        impersonator: data.impersonator,
      })
    },
    async verifyMfa(payload: { challengeId: string; code?: string; recoveryCode?: string; rememberDevice?: boolean }) {
      if (this.isMockMode) {
        this.loginAs('seeker')
        this.clearMfaChallenge()
        return
      }
      const { data } = await apiClient.post('/security/mfa/verify', {
        challenge_id: payload.challengeId,
        code: payload.code,
        recovery_code: payload.recoveryCode,
        remember_device: payload.rememberDevice,
      })
      this.setUser(data.user)
      this.clearMfaChallenge()
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
      this.clearMfaChallenge()
      this.persist()
    },
    setMfaChallenge(challengeId: string) {
      this.mfaRequired = true
      this.mfaChallengeId = challengeId
      this.isAuthenticated = false
      this.user = { ...defaultUser }
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem('ii-mfa-challenge', challengeId)
      }
    },
    clearMfaChallenge() {
      this.mfaRequired = false
      this.mfaChallengeId = null
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.removeItem('ii-mfa-challenge')
      }
    },
    async handleUnauthorized() {
      this.clearSession()
    },
  },
})
