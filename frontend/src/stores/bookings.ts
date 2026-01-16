import { defineStore } from 'pinia'
import { getBookings } from '../services'
import type { Booking } from '../types'

export const useBookingsStore = defineStore('bookings', {
  state: () => ({
    booked: [] as Booking[],
    history: [] as Booking[],
    loading: false,
    error: '',
  }),
  actions: {
    async fetchBookings() {
      this.loading = true
      this.error = ''
      try {
        const [booked, history] = await Promise.all([getBookings('booked'), getBookings('history')])
        this.booked = booked
        this.history = history
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load bookings.'
        this.booked = []
        this.history = []
      } finally {
        this.loading = false
      }
    },
  },
})
