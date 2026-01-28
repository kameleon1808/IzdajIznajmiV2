import { defineStore } from 'pinia'
import {
  cancelViewingRequest,
  confirmViewingRequest,
  createViewingSlot,
  deleteViewingSlot,
  downloadViewingRequestIcs,
  getViewingRequestsForLandlord,
  getViewingRequestsForSeeker,
  getViewingSlots,
  isMockApi,
  rejectViewingRequest,
  requestViewingSlot,
  updateViewingSlot,
} from '../services'
import { useAuthStore } from './auth'
import type { ViewingRequest, ViewingSlot } from '../types'

export const useViewingsStore = defineStore('viewings', {
  state: () => ({
    slotsByListing: {} as Record<string, ViewingSlot[]>,
    seekerRequests: [] as ViewingRequest[],
    landlordRequests: [] as ViewingRequest[],
    loadingSlots: false,
    loadingRequests: false,
    error: '',
  }),
  actions: {
    async fetchSlots(listingId: string) {
      this.loadingSlots = true
      this.error = ''
      try {
        const slots = await getViewingSlots(listingId)
        this.slotsByListing[listingId] = slots
        return slots
      } catch (error) {
        this.slotsByListing[listingId] = []
        this.error = (error as Error).message || 'Failed to load viewing slots.'
        throw error
      } finally {
        this.loadingSlots = false
      }
    },
    async createSlot(
      listingId: string,
      payload: {
        startsAt: string
        endsAt: string
        capacity?: number
        isActive?: boolean
        pattern?: ViewingSlot['pattern']
        daysOfWeek?: number[]
        timeFrom?: string
        timeTo?: string
      },
    ) {
      this.error = ''
      try {
        const slot = await createViewingSlot(listingId, payload)
        const existing = this.slotsByListing[listingId] ?? []
        this.slotsByListing[listingId] = [slot, ...existing]
        return slot
      } catch (error) {
        this.error = (error as Error).message || 'Failed to create slot.'
        throw error
      }
    },
    async updateSlot(slotId: string, payload: Partial<Pick<ViewingSlot, 'startsAt' | 'endsAt' | 'capacity' | 'isActive'>>) {
      this.error = ''
      try {
        const slot = await updateViewingSlot(slotId, payload)
        Object.keys(this.slotsByListing).forEach((listingId) => {
          this.slotsByListing[listingId] = (this.slotsByListing[listingId] ?? []).map((s) => (s.id === slotId ? { ...s, ...slot } : s))
        })
        this.syncRequestSlot(slot)
        return slot
      } catch (error) {
        this.error = (error as Error).message || 'Failed to update slot.'
        throw error
      }
    },
    async deleteSlot(slotId: string) {
      this.error = ''
      try {
        await deleteViewingSlot(slotId)
        Object.keys(this.slotsByListing).forEach((listingId) => {
          this.slotsByListing[listingId] = (this.slotsByListing[listingId] ?? []).filter((s) => s.id !== slotId)
        })
      } catch (error) {
        this.error = (error as Error).message || 'Failed to delete slot.'
        throw error
      }
    },
    async requestSlot(slotId: string, message?: string, scheduledAt?: string) {
      this.error = ''
      try {
        const auth = useAuthStore()
        const seekerId = isMockApi ? auth.user.id : undefined
        const request = await requestViewingSlot(slotId, message, scheduledAt, seekerId)
        this.seekerRequests = [request, ...this.seekerRequests.filter((r) => r.id !== request.id)]
        return request
      } catch (error) {
        this.error = (error as Error).message || 'Failed to request viewing.'
        throw error
      }
    },
    async fetchSeekerRequests() {
      this.loadingRequests = true
      this.error = ''
      try {
        const auth = useAuthStore()
        this.seekerRequests = await getViewingRequestsForSeeker(isMockApi ? auth.user.id : undefined)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load viewings.'
        this.seekerRequests = []
      } finally {
        this.loadingRequests = false
      }
    },
    async fetchLandlordRequests(listingId?: string) {
      this.loadingRequests = true
      this.error = ''
      try {
        const auth = useAuthStore()
        this.landlordRequests = await getViewingRequestsForLandlord(listingId, isMockApi ? auth.user.id : undefined)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load viewings.'
        this.landlordRequests = []
      } finally {
        this.loadingRequests = false
      }
    },
    async confirmRequest(id: string) {
      this.error = ''
      try {
        const updated = await confirmViewingRequest(id)
        this.syncRequest(updated)
        return updated
      } catch (error) {
        this.error = (error as Error).message || 'Failed to confirm viewing.'
        throw error
      }
    },
    async rejectRequest(id: string) {
      this.error = ''
      try {
        const updated = await rejectViewingRequest(id)
        this.syncRequest(updated)
        return updated
      } catch (error) {
        this.error = (error as Error).message || 'Failed to reject viewing.'
        throw error
      }
    },
    async cancelRequest(id: string) {
      this.error = ''
      try {
        const auth = useAuthStore()
        const isSeeker = [...this.seekerRequests, ...this.landlordRequests].some(
          (r) => r.id === id && r.participants.seekerId === auth.user.id,
        )
        const cancelledBy = isSeeker ? 'seeker' : 'landlord'
        const updated = await cancelViewingRequest(id, isMockApi ? (cancelledBy as any) : undefined)
        this.syncRequest(updated)
        return updated
      } catch (error) {
        this.error = (error as Error).message || 'Failed to cancel viewing.'
        throw error
      }
    },
    async downloadIcs(id: string) {
      return downloadViewingRequestIcs(id)
    },
    syncRequestSlot(slot: ViewingSlot) {
      const merge = (requests: ViewingRequest[]) =>
        requests.map((req) => (req.slot?.id === slot.id ? { ...req, slot: { ...req.slot, ...slot } } : req))
      this.seekerRequests = merge(this.seekerRequests)
      this.landlordRequests = merge(this.landlordRequests)
    },
    syncRequest(updated: ViewingRequest) {
      const merge = (requests: ViewingRequest[]) => requests.map((req) => (req.id === updated.id ? updated : req))
      const nextSeeker = merge(this.seekerRequests)
      const nextLandlord = merge(this.landlordRequests)
      this.seekerRequests = nextSeeker.some((r) => r.id === updated.id) ? nextSeeker : [updated, ...nextSeeker]
      this.landlordRequests = nextLandlord.some((r) => r.id === updated.id) ? nextLandlord : [updated, ...nextLandlord]
    },
  },
})
