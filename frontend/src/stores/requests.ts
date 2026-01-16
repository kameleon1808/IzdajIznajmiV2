import { defineStore } from 'pinia'
import {
  createBookingRequest,
  getBookingRequestsForLandlord,
  getBookingRequestsForTenant,
  updateBookingRequestStatus,
} from '../services/mockApi'
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
    async fetchTenantRequests(tenantId: string) {
      this.loading = true
      this.error = ''
      try {
        this.tenantRequests = await getBookingRequestsForTenant(tenantId)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load requests.'
        this.tenantRequests = []
      } finally {
        this.loading = false
      }
    },
    async fetchLandlordRequests(landlordId: string) {
      this.loading = true
      this.error = ''
      try {
        this.landlordRequests = await getBookingRequestsForLandlord(landlordId)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load incoming requests.'
        this.landlordRequests = []
      } finally {
        this.loading = false
      }
    },
    async sendRequest(payload: Omit<BookingRequest, 'id' | 'status' | 'createdAt'>) {
      this.error = ''
      try {
        const created = await createBookingRequest(payload)
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
