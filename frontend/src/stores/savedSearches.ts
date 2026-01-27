import { defineStore } from 'pinia'
import { createSavedSearch, deleteSavedSearch, getSavedSearches, updateSavedSearch } from '../services'
import type { SavedSearch } from '../types'

export const useSavedSearchesStore = defineStore('savedSearches', {
  state: () => ({
    savedSearches: [] as SavedSearch[],
    loading: false,
    error: '',
  }),
  getters: {
    byId: (state) => (id: string) => state.savedSearches.find((item) => item.id === id) ?? null,
  },
  actions: {
    async fetchSavedSearches() {
      this.loading = true
      this.error = ''
      try {
        this.savedSearches = await getSavedSearches()
        return this.savedSearches
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load saved searches.'
        throw error
      } finally {
        this.loading = false
      }
    },
    async createSavedSearch(payload: {
      name?: string | null
      filters: Record<string, any>
      alertsEnabled?: boolean
      frequency?: SavedSearch['frequency']
    }) {
      this.error = ''
      try {
        const saved = await createSavedSearch(payload)
        this.savedSearches = [saved, ...this.savedSearches]
        return saved
      } catch (error) {
        this.error = (error as Error).message || 'Failed to save search.'
        throw error
      }
    },
    async updateSavedSearch(id: string, payload: {
      name?: string | null
      filters?: Record<string, any>
      alertsEnabled?: boolean
      frequency?: SavedSearch['frequency']
    }) {
      this.error = ''
      try {
        const updated = await updateSavedSearch(id, payload)
        this.savedSearches = this.savedSearches.map((item) => (item.id === id ? updated : item))
        return updated
      } catch (error) {
        this.error = (error as Error).message || 'Failed to update saved search.'
        throw error
      }
    },
    async deleteSavedSearch(id: string) {
      this.error = ''
      try {
        await deleteSavedSearch(id)
        this.savedSearches = this.savedSearches.filter((item) => item.id !== id)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to delete saved search.'
        throw error
      }
    },
  },
})
