import { defineStore } from 'pinia'
import {
  createListing,
  getFavorites,
  getLandlordListings,
  getPopularListings,
  getRecommendedListings,
  isMockApi,
  searchListings,
  updateListing,
  publishListing,
  unpublishListing,
  archiveListing,
  restoreListing,
  markListingAvailable,
  markListingRented,
} from '../services'
import { useAuthStore } from './auth'
import type { Listing, ListingFilters } from '../types'

export const defaultFilters: ListingFilters = {
  category: 'all',
  guests: 1,
  priceRange: [0, 1000000],
  instantBook: false,
  location: '',
  city: '',
  facilities: [],
  amenities: [],
  rating: null,
  rooms: null,
  areaRange: [0, 100000],
  status: 'all',
  centerLat: null,
  centerLng: null,
  radiusKm: 50,
}

const loadFavorites = (): string[] => {
  if (typeof localStorage === 'undefined') return []
  try {
    const stored = localStorage.getItem('ii-favorites')
    return stored ? (JSON.parse(stored) as string[]) : []
  } catch {
    return []
  }
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
  rooms?: number
  area?: number
  images?: string[]
  description?: string
  lat?: number
  lng?: number
  facilities?: string[]
  instantBook?: boolean
  ownerId: string | number
  imagesFiles?: File[]
  keepImages?: { url: string; sortOrder: number; isCover: boolean }[]
  removeImageUrls?: string[]
}

export const useListingsStore = defineStore('listings', {
  state: () => ({
    popular: [] as Listing[],
    recommended: [] as Listing[],
    favoriteListings: [] as Listing[],
    favorites: loadFavorites(),
    landlordListings: [] as Listing[],
    landlordStatusFilter: 'all' as 'all' | 'draft' | 'active' | 'paused' | 'archived' | 'rented' | 'expired',
    filters: { ...defaultFilters },
    searchResults: [] as Listing[],
    searchMeta: null as any,
    searchPage: 1,
    recentSearches: ['Bali', 'Barcelona', 'Lisbon'],
    loading: false,
    loadingMore: false,
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
    persistFavorites() {
      if (typeof localStorage === 'undefined') return
      localStorage.setItem('ii-favorites', JSON.stringify(this.favorites))
    },
    setFilters(partial: Partial<ListingFilters>, options: { fetch?: boolean } = {}) {
      this.filters = { ...this.filters, ...partial }
      if (options.fetch !== false) {
        this.fetchRecommended()
      }
    },
    updateGeoFilters(centerLat: number | null, centerLng: number | null, radiusKm?: number | null) {
      const nextRadius = radiusKm ?? this.filters.radiusKm
      if (
        this.filters.centerLat === centerLat &&
        this.filters.centerLng === centerLng &&
        this.filters.radiusKm === nextRadius
      ) {
        return
      }
      this.filters = {
        ...this.filters,
        centerLat,
        centerLng,
        radiusKm: nextRadius,
      }
    },
    resetFilters() {
      this.filters = { ...defaultFilters }
      this.fetchRecommended()
    },
    async fetchPopular() {
      this.loading = true
      this.error = ''
      try {
        const resp = await getPopularListings(this.filters, 1, 10)
        const list = Array.isArray(resp) ? resp : resp.items
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
        const resp = await getRecommendedListings(this.filters, 1, 10)
        const list = Array.isArray(resp) ? resp : resp.items
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
        if (isMockApi) {
          const favs = await getFavorites()
          this.favorites = favs.map((f: Listing) => f.id)
          this.favoriteListings = favs
        }
        this.recommended = this.syncFavorites(this.recommended)
        this.popular = this.syncFavorites(this.popular)
        this.searchResults = this.syncFavorites(this.searchResults)
        this.landlordListings = this.syncFavorites(this.landlordListings)
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load favorites.'
        if (isMockApi) this.favoriteListings = []
      } finally {
        this.favoritesLoading = false
      }
    },
    async search(query: string, options: { mapMode?: boolean } = {}) {
      this.loading = true
      this.error = ''
      this.searchPage = 1
      try {
        const resp = await searchListings(query, this.filters, this.searchPage, 10, options)
        const list = Array.isArray(resp) ? resp : resp.items
        this.searchMeta = Array.isArray(resp) ? null : resp.meta
        this.searchResults = this.syncFavorites(list)
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
    async loadMoreSearch(query: string | { value: string }, options: { mapMode?: boolean } = {}) {
      const q = typeof query === 'string' ? query : query.value
      if (this.loadingMore) return
      if (this.searchMeta && this.searchMeta.current_page >= this.searchMeta.last_page) return
      this.loadingMore = true
      try {
        const nextPage = (this.searchMeta?.current_page ?? this.searchPage) + 1
        const resp = await searchListings(q, this.filters, nextPage, 10, options)
        const list = Array.isArray(resp) ? resp : resp.items
        this.searchMeta = Array.isArray(resp) ? null : resp.meta
        this.searchPage = nextPage
        this.searchResults = this.syncFavorites([...this.searchResults, ...list])
      } catch (error) {
        this.error = (error as Error).message || 'Search failed.'
      } finally {
        this.loadingMore = false
      }
    },
    async fetchLandlordListings(ownerId?: string | number) {
      this.landlordLoading = true
      this.landlordError = ''
      try {
        const auth = useAuthStore()
        const data = await getLandlordListings(ownerId ?? auth.user.id)
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
        if (isMockApi) {
          this.landlordListings = [created, ...this.landlordListings]
        } else {
          await this.fetchLandlordListings()
        }
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
        if (isMockApi) {
          if (updated) {
            this.landlordListings = this.landlordListings.map((item) => (item.id === id ? updated : item))
          }
        } else {
          await this.fetchLandlordListings()
        }
        return updated
      } catch (error) {
        this.landlordError = (error as Error).message || 'Failed to update listing.'
        throw error
      }
    },
    async publishListingAction(id: string) {
      const updated = await publishListing(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
    },
    async unpublishListingAction(id: string) {
      const updated = await unpublishListing(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
    },
    async archiveListingAction(id: string) {
      const updated = await archiveListing(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
    },
    async restoreListingAction(id: string) {
      const updated = await restoreListing(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
    },
    async markListingRentedAction(id: string) {
      const updated = await markListingRented(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
    },
    async markListingAvailableAction(id: string) {
      const updated = await markListingAvailable(id)
      if (isMockApi) {
        this.landlordListings = this.landlordListings.map((l) => (l.id === id ? updated : l))
      } else {
        await this.fetchLandlordListings()
      }
      return updated
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
      this.persistFavorites()
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
