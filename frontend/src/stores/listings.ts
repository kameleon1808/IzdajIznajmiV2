import { defineStore } from 'pinia'
import {
  createListing,
  getFavorites,
  getLandlordListings,
  getPopularListings,
  getRecommendedListings,
  searchListings,
  updateListing,
} from '../services/mockApi'
import type { Listing, ListingFilters } from '../types'

const defaultFilters: ListingFilters = {
  category: 'all',
  guests: 1,
  priceRange: [50, 400],
  instantBook: false,
  location: '',
  facilities: [],
  rating: null,
}

type ListingFormInput = {
  title: string
  pricePerNight: number
  category: Listing['category']
  address: string
  city: string
  country: string
  beds: number
  baths: number
  images?: string[]
  description?: string
  lat?: number
  lng?: number
  facilities?: string[]
  ownerId: string | number
}

export const useListingsStore = defineStore('listings', {
  state: () => ({
    popular: [] as Listing[],
    recommended: [] as Listing[],
    favoriteListings: [] as Listing[],
    favorites: [] as string[],
    landlordListings: [] as Listing[],
    filters: { ...defaultFilters },
    searchResults: [] as Listing[],
    recentSearches: ['Bali', 'Barcelona', 'Lisbon'],
    loading: false,
    favoritesLoading: false,
    landlordLoading: false,
    error: '',
    landlordError: '',
  }),
  getters: {
    filteredRecommended(state) {
      return state.recommended.map((item) => ({
        ...item,
        isFavorite: state.favorites.includes(item.id) || item.isFavorite,
      }))
    },
  },
  actions: {
    setFilters(partial: Partial<ListingFilters>) {
      this.filters = { ...this.filters, ...partial }
      this.fetchRecommended()
    },
    resetFilters() {
      this.filters = { ...defaultFilters }
      this.fetchRecommended()
    },
    async fetchPopular() {
      this.loading = true
      this.error = ''
      try {
        const list = await getPopularListings()
        this.popular = this.syncFavorites(list)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load popular listings.'
        this.popular = []
      } finally {
        this.loading = false
      }
    },
    async fetchRecommended() {
      this.loading = true
      this.error = ''
      try {
        const list = await getRecommendedListings(this.filters)
        this.recommended = this.syncFavorites(list)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load listings.'
        this.recommended = []
      } finally {
        this.loading = false
      }
    },
    async fetchFavorites() {
      this.favoritesLoading = true
      this.error = ''
      try {
        const favs = await getFavorites()
        this.favorites = favs.map((f) => f.id)
        this.favoriteListings = favs
        this.recommended = this.syncFavorites(this.recommended)
        this.popular = this.syncFavorites(this.popular)
        this.searchResults = this.syncFavorites(this.searchResults)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load favorites.'
        this.favoriteListings = []
      } finally {
        this.favoritesLoading = false
      }
    },
    async search(query: string) {
      this.loading = true
      this.error = ''
      try {
        const results = await searchListings(query, this.filters)
        this.searchResults = this.syncFavorites(results)
        if (query.trim() && !this.recentSearches.includes(query)) {
          this.recentSearches = [query, ...this.recentSearches].slice(0, 5)
        }
      } catch (error) {
        this.error = (error as Error).message || 'Search failed.'
        this.searchResults = []
      } finally {
        this.loading = false
      }
    },
    async fetchLandlordListings(ownerId: string | number) {
      this.landlordLoading = true
      this.landlordError = ''
      try {
        const data = await getLandlordListings(ownerId)
        this.landlordListings = this.syncFavorites(data)
      } catch (error) {
        this.landlordError = (error as Error).message || 'Failed to load landlord listings.'
        this.landlordListings = []
      } finally {
        this.landlordLoading = false
      }
    },
    async createListing(payload: ListingFormInput) {
      this.landlordError = ''
      try {
        const created = await createListing(payload)
        this.landlordListings = [created, ...this.landlordListings]
        return created
      } catch (error) {
        this.landlordError = (error as Error).message || 'Failed to create listing.'
        throw error
      }
    },
    async updateListingAction(id: string, payload: Partial<ListingFormInput>) {
      this.landlordError = ''
      try {
        const updated = await updateListing(id, payload)
        if (updated) {
          this.landlordListings = this.landlordListings.map((item) => (item.id === id ? updated : item))
        }
        return updated
      } catch (error) {
        this.landlordError = (error as Error).message || 'Failed to update listing.'
        throw error
      }
    },
    toggleFavorite(id: string) {
      if (this.favorites.includes(id)) {
        this.favorites = this.favorites.filter((item) => item !== id)
        this.favoriteListings = this.favoriteListings.filter((item) => item.id !== id)
      } else {
        this.favorites.push(id)
        const found =
          [...this.recommended, ...this.popular, ...this.searchResults, ...this.landlordListings].find(
            (item) => item.id === id,
          ) || this.favoriteListings.find((item) => item.id === id)
        if (found && !this.favoriteListings.find((f) => f.id === id)) {
          this.favoriteListings.push({ ...found, isFavorite: true })
        }
      }
      this.recommended = this.syncFavorites(this.recommended)
      this.popular = this.syncFavorites(this.popular)
      this.searchResults = this.syncFavorites(this.searchResults)
      this.landlordListings = this.syncFavorites(this.landlordListings)
    },
    syncFavorites(list: Listing[]) {
      return list.map((item) => ({
        ...item,
        isFavorite: this.favorites.includes(item.id) || item.isFavorite,
      }))
    },
  },
})
