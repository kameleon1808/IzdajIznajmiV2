import { defineStore } from 'pinia'
import {
  getFavorites,
  getPopularListings,
  getRecommendedListings,
  searchListings,
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

export const useListingsStore = defineStore('listings', {
  state: () => ({
    popular: [] as Listing[],
    recommended: [] as Listing[],
    favoriteListings: [] as Listing[],
    favorites: [] as string[],
    filters: { ...defaultFilters },
    searchResults: [] as Listing[],
    recentSearches: ['Bali', 'Barcelona', 'Lisbon'],
    loading: false,
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
      const list = await getPopularListings()
      this.popular = this.syncFavorites(list)
      this.loading = false
    },
    async fetchRecommended() {
      const list = await getRecommendedListings(this.filters)
      this.recommended = this.syncFavorites(list)
    },
    async fetchFavorites() {
      const favs = await getFavorites()
      this.favorites = favs.map((f) => f.id)
      this.favoriteListings = favs
      this.recommended = this.syncFavorites(this.recommended)
      this.popular = this.syncFavorites(this.popular)
      this.searchResults = this.syncFavorites(this.searchResults)
    },
    async search(query: string) {
      this.loading = true
      const results = await searchListings(query, this.filters)
      this.searchResults = this.syncFavorites(results)
      this.loading = false
      if (query.trim() && !this.recentSearches.includes(query)) {
        this.recentSearches = [query, ...this.recentSearches].slice(0, 5)
      }
    },
    toggleFavorite(id: string) {
      if (this.favorites.includes(id)) {
        this.favorites = this.favorites.filter((item) => item !== id)
        this.favoriteListings = this.favoriteListings.filter((item) => item.id !== id)
      } else {
        this.favorites.push(id)
        const found =
          [...this.recommended, ...this.popular, ...this.searchResults].find((item) => item.id === id) ||
          this.favoriteListings.find((item) => item.id === id)
        if (found && !this.favoriteListings.find((f) => f.id === id)) {
          this.favoriteListings.push({ ...found, isFavorite: true })
        }
      }
      this.recommended = this.syncFavorites(this.recommended)
      this.popular = this.syncFavorites(this.popular)
      this.searchResults = this.syncFavorites(this.searchResults)
    },
    syncFavorites(list: Listing[]) {
      return list.map((item) => ({
        ...item,
        isFavorite: this.favorites.includes(item.id) || item.isFavorite,
      }))
    },
  },
})
