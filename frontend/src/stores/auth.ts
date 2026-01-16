import { defineStore } from 'pinia'

export type Role = 'guest' | 'tenant' | 'landlord' | 'admin'

interface User {
  id: string
  name: string
  role: Role
}

const STORAGE_KEY = 'ii-auth'

const defaultUser: User = { id: 'guest', name: 'Guest', role: 'guest' }

const loadUser = (): User => {
  if (typeof localStorage === 'undefined') return { ...defaultUser }
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored) {
    try {
      return JSON.parse(stored) as User
    } catch (e) {
      console.error(e)
    }
  }
  return { ...defaultUser }
}

const initialUser = loadUser()

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: initialUser,
    isAuthenticated: initialUser.role !== 'guest',
  }),
  actions: {
    loginAs(role: Role) {
      const names: Record<Role, string> = {
        guest: 'Guest',
        tenant: 'Tena Tenant',
        landlord: 'Lana Landlord',
        admin: 'Admin',
      }
      this.user = { id: `${role}-1`, name: names[role] ?? 'User', role }
      this.isAuthenticated = role !== 'guest'
      this.persist()
    },
    logout() {
      this.user = { ...defaultUser }
      this.isAuthenticated = false
      this.persist()
    },
    persist() {
      if (typeof localStorage === 'undefined') return
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.user))
    },
  },
})
