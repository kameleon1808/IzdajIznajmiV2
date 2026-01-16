import { defineStore } from 'pinia'
import { getBookings } from '../services/mockApi'
import type { Booking } from '../types'

export const useBookingsStore = defineStore('bookings', {
  state: () => ({
    booked: [] as Booking[],
    history: [] as Booking[],
    loading: false,
  }),
  actions: {
    async fetchBookings() {
      this.loading = true
      const [booked, history] = await Promise.all([
        getBookings('booked'),
        getBookings('history'),
      ])
      this.booked = booked
      this.history = history
      this.loading = false
    },
  },
})
