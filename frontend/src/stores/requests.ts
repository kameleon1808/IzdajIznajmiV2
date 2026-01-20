import { defineStore } from 'pinia'
import {
  applyToListing,
  getApplicationsForLandlord,
  getApplicationsForSeeker,
  isMockApi,
  updateApplicationStatus,
} from '../services'
import { useAuthStore } from './auth'
import type { Application } from '../types'

export const useRequestsStore = defineStore('requests', {
  state: () => ({
    tenantRequests: [] as Application[],
    landlordRequests: [] as Application[],
    loading: false,
    error: '',
  }),
  actions: {
    clearError() {
      this.error = ''
    },
    async fetchTenantRequests() {
      this.loading = true
      this.error = ''
      try {
        this.tenantRequests = await getApplicationsForSeeker()
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load requests.'
        this.tenantRequests = []
      } finally {
        this.loading = false
      }
    },
    async fetchLandlordRequests(listingId?: string) {
      this.loading = true
      this.error = ''
      try {
        this.landlordRequests = await getApplicationsForLandlord(listingId)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load incoming requests.'
        this.landlordRequests = []
      } finally {
        this.loading = false
      }
    },
    async sendRequest(payload: { listingId: string; message?: string; tenantId?: string }) {
      this.error = ''
      try {
        const auth = useAuthStore()
        const body = { ...payload } as any
        if (isMockApi) body.tenantId = payload.tenantId ?? auth.user.id
        const created = await applyToListing(body.listingId, body.message)
        this.tenantRequests = [created, ...this.tenantRequests]
        return created
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send request.'
        throw error
      }
    },
    async updateStatus(id: string, status: Application['status']) {
      this.error = ''
      try {
        const updated = await updateApplicationStatus(id, status)
        if (updated) {
          this.tenantRequests = this.tenantRequests.map((r) => (r.id === id ? updated : r))
          this.landlordRequests = this.landlordRequests.map((r) => (r.id === id ? updated : r))
        }
        return updated
      } catch (error) {
        this.error = (error as Error).message || 'Failed to update request.'
        throw error
      }
    },
  },
})
