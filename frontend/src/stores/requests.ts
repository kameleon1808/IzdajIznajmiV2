import { defineStore } from 'pinia'
import {
  createBookingRequest,
  getBookingRequestsForLandlord,
  getBookingRequestsForTenant,
  isMockApi,
  updateBookingRequestStatus,
} from '../services'
import { useAuthStore } from './auth'
import type { BookingRequest } from '../types'

export const useRequestsStore = defineStore('requests', {
  state: () => ({
    tenantRequests: [] as BookingRequest[],
    landlordRequests: [] as BookingRequest[],
    loading: false,
    error: '',
  }),
  actions: {
    clearError() {
      this.error = ''
    },
    async fetchTenantRequests(tenantId?: string) {
      this.loading = true
      this.error = ''
      try {
        const auth = useAuthStore()
        this.tenantRequests = await getBookingRequestsForTenant(tenantId ?? auth.user.id)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load requests.'
        this.tenantRequests = []
      } finally {
        this.loading = false
      }
    },
    async fetchLandlordRequests(landlordId?: string) {
      this.loading = true
      this.error = ''
      try {
        const auth = useAuthStore()
        this.landlordRequests = await getBookingRequestsForLandlord(landlordId ?? auth.user.id)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load incoming requests.'
        this.landlordRequests = []
      } finally {
        this.loading = false
      }
    },
    async sendRequest(
      payload: Omit<BookingRequest, 'id' | 'status' | 'createdAt' | 'tenantId'> & { tenantId?: string },
    ) {
      this.error = ''
      try {
        const auth = useAuthStore()
        const body = { ...payload } as any
        if (isMockApi) body.tenantId = payload.tenantId ?? auth.user.id
        const created = await createBookingRequest(body)
        this.tenantRequests = [created, ...this.tenantRequests]
        return created
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send request.'
        throw error
      }
    },
    async updateStatus(id: string, status: BookingRequest['status']) {
      this.error = ''
      try {
        const updated = await updateBookingRequestStatus(id, status)
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
