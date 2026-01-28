import { apiClient } from './apiClient'
import { defaultFilters } from '../stores/listings'
import type {
  AdminConversion,
  AdminKpiSummary,
  AdminTrendPoint,
  Application,
  Conversation,
  Listing,
  ListingFilters,
  Message,
  PublicProfile,
  Rating,
  Report,
  ViewingRequest,
  ViewingSlot,
  SavedSearch,
  SearchSuggestion,
} from '../types'
import { useAuthStore } from '../stores/auth'

const mapListing = (data: any): Listing => {
  const detailed = data.imagesDetailed ?? data.images ?? data.listing_images ?? []
  const detailedArr = Array.isArray(detailed) ? detailed : Object.values(detailed)
  const imagesDetailed = detailedArr.map((img: any) => ({
    url: typeof img === 'string' ? img : img.url,
    sortOrder: Number(img.sortOrder ?? img.sort_order ?? 0),
    isCover: Boolean(img.isCover ?? img.is_cover ?? false),
    processingStatus: img.processingStatus ?? img.processing_status ?? 'done',
    processingError: img.processingError ?? img.processing_error,
  }))
  imagesDetailed.sort((a, b) => a.sortOrder - b.sortOrder)
  const imagesSimple = imagesDetailed.map((i) => i.url).filter(Boolean)
  const coverFromDetailed = imagesDetailed.find((i) => i.isCover)?.url ?? imagesSimple[0] ?? ''

  return {
    id: String(data.id),
    title: data.title,
    address: data.address ?? '',
    city: data.city,
    country: data.country,
    lat: data.lat != null ? Number(data.lat) : undefined,
    lng: data.lng != null ? Number(data.lng) : undefined,
    locationSource: data.locationSource ?? data.location_source ?? 'geocoded',
    locationAccuracyM: data.locationAccuracyM ?? data.location_accuracy_m ?? null,
    locationOverriddenAt: data.locationOverriddenAt ?? data.location_overridden_at ?? null,
    distanceKm: data.distanceKm != null ? Number(data.distanceKm) : data.distance_km != null ? Number(data.distance_km) : undefined,
    geocodedAt: data.geocodedAt ?? data.geocoded_at ?? null,
    pricePerNight: Number(data.pricePerNight ?? data.price_per_night ?? data.price ?? 0),
    rating: Number(data.rating ?? 0),
    reviewsCount: Number(data.reviewsCount ?? data.reviews_count ?? 0),
    coverImage: data.coverImage ?? data.cover_image ?? coverFromDetailed ?? '',
    images: imagesSimple,
    imagesDetailed,
    description: data.description ?? '',
    beds: Number(data.beds ?? 0),
    baths: Number(data.baths ?? 0),
    rooms: data.rooms != null ? Number(data.rooms) : undefined,
    area: data.area != null ? Number(data.area) : undefined,
    category: data.category,
    isFavorite: Boolean(data.isFavorite ?? false),
    instantBook: Boolean(data.instantBook ?? data.instant_book ?? false),
  facilities:
    data.facilities?.map((f: any) => (typeof f === 'string' ? f : f.name)) ??
    data.facilities ??
      [],
  ownerId: data.ownerId ?? data.owner_id,
  landlord: data.landlord
    ? {
        id: data.landlord.id ?? data.landlordId,
        fullName: data.landlord.fullName ?? data.landlord.full_name ?? data.landlord.name,
      }
    : undefined,
  createdAt: data.createdAt ?? data.created_at,
  status: data.status,
  publishedAt: data.publishedAt ?? data.published_at,
  archivedAt: data.archivedAt ?? data.archived_at,
  expiredAt: data.expiredAt ?? data.expired_at,
    warnings: data.warnings ?? [],
  }
}

const mapApplication = (data: any): Application => ({
  id: String(data.id),
  status: data.status,
  message: data.message ?? null,
  createdAt: data.createdAt ?? data.created_at ?? '',
  listing: {
    id: String(data.listing?.id ?? data.listingId ?? data.listing_id ?? ''),
    title: data.listing?.title ?? data.listingTitle,
    city: data.listing?.city,
    coverImage: data.listing?.coverImage ?? data.listing?.cover_image,
    pricePerNight: data.listing?.pricePerNight ?? data.listing?.price_per_night,
    status: data.listing?.status,
  },
  participants: {
    seekerId: String(data.participants?.seekerId ?? data.seekerId ?? data.seeker_id ?? ''),
    landlordId: String(data.participants?.landlordId ?? data.landlordId ?? data.landlord_id ?? ''),
  },
})

const mapConversation = (data: any): Conversation => {
  const rawTime = data.time ?? data.updated_at ?? ''
  const formattedTime =
    rawTime && typeof rawTime === 'string' && rawTime.includes('T')
      ? new Date(rawTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      : rawTime

  return {
    id: String(data.id),
    listingId: String(data.listingId ?? data.listing_id ?? data.listing?.id ?? ''),
    listingTitle: data.listingTitle ?? data.listing?.title,
    listingCity: data.listingCity ?? data.listing?.city,
    listingCoverImage:
      data.listingCoverImage ?? data.listing?.coverImage ?? data.listing?.cover_image ?? data.listing?.images?.[0] ?? '',
    userName: data.userName ?? data.tenant?.name ?? data.landlord?.name ?? 'Conversation',
    avatarUrl: data.avatarUrl ?? data.avatar_url ?? '',
    lastMessage: data.lastMessage ?? data.last_message ?? '',
    time: formattedTime || '',
    unreadCount: Number(data.unreadCount ?? data.unread_count ?? 0),
    online: Boolean(data.online ?? false),
    participants: data.participants
      ? data.participants
      : undefined,
  }
}

const mapMessage = (data: any): Message => {
  const auth = useAuthStore()
  const senderId = String(data.sender_id ?? data.senderId ?? '')
  const rawTime = data.createdAt ?? data.created_at ?? data.time ?? ''
  return {
    id: String(data.id),
    conversationId: String(data.conversation_id ?? data.conversationId ?? ''),
    senderId,
    from: auth.user.id && senderId === String(auth.user.id) ? 'me' : 'them',
    text: data.body ?? data.text ?? '',
    createdAt: rawTime,
    time: rawTime ? new Date(rawTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : data.time ?? '',
  }
}

const mapViewingSlot = (data: any): ViewingSlot => ({
  id: String(data.id),
  listingId: String(data.listingId ?? data.listing_id ?? ''),
  landlordId: String(data.landlordId ?? data.landlord_id ?? ''),
  startsAt: data.startsAt ?? data.starts_at ?? '',
  endsAt: data.endsAt ?? data.ends_at ?? '',
  capacity: Number(data.capacity ?? 1),
  isActive: Boolean(data.isActive ?? data.is_active ?? true),
  pattern: data.pattern ?? undefined,
  daysOfWeek: data.daysOfWeek ?? data.days_of_week ?? [],
  timeFrom: data.timeFrom ?? data.time_from ?? null,
  timeTo: data.timeTo ?? data.time_to ?? null,
})

const mapViewingRequest = (data: any): ViewingRequest => ({
  id: String(data.id),
  status: data.status,
  message: data.message ?? null,
  cancelledBy: data.cancelledBy ?? data.cancelled_by ?? null,
  createdAt: data.createdAt ?? data.created_at ?? null,
  scheduledAt: data.scheduledAt ?? data.scheduled_at ?? null,
  slot: data.slot ? mapViewingSlot(data.slot) : null,
  listing: data.listing
    ? {
        id: String(data.listing.id ?? data.listingId ?? data.listing_id ?? ''),
        title: data.listing.title ?? data.listingTitle,
        city: data.listing.city,
        coverImage: data.listing.coverImage ?? data.listing.cover_image,
        pricePerNight: data.listing.pricePerNight ?? data.listing.price_per_night,
        status: data.listing.status,
      }
    : null,
  participants: {
    seekerId: String(data.participants?.seekerId ?? data.seekerId ?? data.seeker_id ?? ''),
    landlordId: String(data.participants?.landlordId ?? data.landlordId ?? data.landlord_id ?? ''),
  },
})

const mapSavedSearch = (data: any): SavedSearch => ({
  id: String(data.id),
  name: data.name ?? null,
  filters: data.filters ?? {},
  alertsEnabled: Boolean(data.alertsEnabled ?? data.alerts_enabled ?? false),
  frequency: data.frequency ?? 'instant',
  lastAlertedAt: data.lastAlertedAt ?? data.last_alerted_at ?? null,
  createdAt: data.createdAt ?? data.created_at ?? null,
  updatedAt: data.updatedAt ?? data.updated_at ?? null,
})

const applyListingFilters = (filters?: ListingFilters) => {
  if (!filters) return {}
  const params: Record<string, any> = {}
  if (filters.category && filters.category !== 'all') params.category = filters.category
  if (filters.priceRange?.length && (filters.priceRange[0] !== defaultFilters.priceRange[0] || filters.priceRange[1] !== defaultFilters.priceRange[1])) {
    params.priceMin = filters.priceRange[0]
    params.priceMax = filters.priceRange[1]
  }
  if (filters.guests) params.guests = filters.guests
  if (filters.instantBook) params.instantBook = filters.instantBook
  if (filters.location) params.location = filters.location
  if (filters.city) params.city = filters.city
  if (filters.rooms) params.rooms = filters.rooms
  if (
    filters.areaRange?.length &&
    (filters.areaRange[0] !== defaultFilters.areaRange?.[0] || filters.areaRange[1] !== defaultFilters.areaRange?.[1])
  ) {
    params.areaMin = filters.areaRange[0]
    params.areaMax = filters.areaRange[1]
  }
  if (filters.facilities?.length) params.facilities = filters.facilities
  if (filters.amenities?.length) params.amenities = filters.amenities
  if (filters.rating) params.rating = filters.rating
  if (filters.status && filters.status !== 'all') params.status = filters.status
  if (filters.centerLat != null && filters.centerLng != null) {
    params.centerLat = filters.centerLat
    params.centerLng = filters.centerLng
  }
  if (filters.radiusKm) params.radiusKm = filters.radiusKm
  return params
}

const applySearchV2Filters = (filters?: ListingFilters) => {
  if (!filters) return {}
  const params = applyListingFilters(filters)
  if (filters.priceBucket) params.price_bucket = filters.priceBucket
  if (filters.areaBucket) params.area_bucket = filters.areaBucket
  return params
}

const appendIfValue = (form: FormData, key: string, value: any) => {
  if (value === undefined || value === null || value === '') return
  form.append(key, value as any)
}

const appendBoolean = (form: FormData, key: string, value: boolean | null | undefined) => {
  if (value === undefined || value === null) return
  form.append(key, value ? '1' : '0')
}

const mapPaginated = (payload: any) => {
  const data = payload.data ?? payload
  if (Array.isArray(data)) {
    return { items: data.map(mapListing), meta: null }
  }
  return {
    items: (payload.data ?? []).map(mapListing),
    meta: payload.meta ?? null,
  }
}

export const getPopularListings = async (filters?: ListingFilters, page = 1, perPage = 10) => {
  const params = { ...applyListingFilters(filters), page, perPage }
  const { data } = await apiClient.get('/listings', { params })
  return mapPaginated(data)
}

export const getRecommendedListings = async (filters?: ListingFilters, page = 1, perPage = 10) =>
  getPopularListings(filters, page, perPage)

export const searchListings = async (
  query: string,
  filters?: ListingFilters,
  page = 1,
  perPage = 10,
  options: { mapMode?: boolean } = {},
) => {
  const params: Record<string, any> = { ...applyListingFilters(filters), location: query || filters?.location, page, perPage }
  if (options.mapMode) params.mapMode = true
  const { data } = await apiClient.get('/listings', { params })
  return mapPaginated(data)
}

export const searchListingsV2 = async (
  query: string,
  filters?: ListingFilters,
  page = 1,
  perPage = 10,
): Promise<{ items: Listing[]; meta: any; facets: any }> => {
  const params: Record<string, any> = { ...applySearchV2Filters(filters), q: query || filters?.location, page, perPage }
  const { data } = await apiClient.get('/search/listings', { params })
  return {
    items: (data.data ?? []).map(mapListing),
    meta: data.meta ?? null,
    facets: data.facets ?? {},
  }
}

export const geocodeLocation = async (query: string): Promise<{ lat: number; lng: number }> => {
  const { data } = await apiClient.get('/geocode', { params: { q: query } })
  return { lat: Number(data.lat), lng: Number(data.lng) }
}

export type GeocodeSuggestion = { label: string; lat: number; lng: number; type: string }

export const suggestLocations = async (query: string, limit = 5): Promise<GeocodeSuggestion[]> => {
  const { data } = await apiClient.get('/geocode/suggest', { params: { q: query, limit } })
  return data as GeocodeSuggestion[]
}

export const suggestSearch = async (query: string, limit = 8): Promise<SearchSuggestion[]> => {
  const { data } = await apiClient.get('/search/suggest', { params: { q: query, limit } })
  return data as SearchSuggestion[]
}

export const getListingById = async (id: string): Promise<Listing | null> => {
  const { data } = await apiClient.get(`/listings/${id}`)
  const item = (data.data ?? data) as any
  return item ? mapListing(item) : null
}

export const getListingFacilities = async (id: string): Promise<{ group: string; items: string[] }[]> => {
  const listing = await getListingById(id)
  if (!listing?.facilities?.length) return []
  return [{ group: 'Facilities', items: listing.facilities }]
}

export const getListingReviews = async (): Promise<any[]> => {
  return []
}

export const getFavorites = async (): Promise<Listing[]> => {
  return []
}

export const getLandlordListings = async (): Promise<Listing[]> => {
  const { data } = await apiClient.get('/landlord/listings')
  const list = (data.data ?? data) as any[]
  return list.map(mapListing)
}

export const createListing = async (payload: any): Promise<Listing> => {
  const form = new FormData()
  appendIfValue(form, 'title', payload.title)
  appendIfValue(form, 'pricePerNight', payload.pricePerNight)
  appendIfValue(form, 'category', payload.category)
  appendIfValue(form, 'address', payload.address)
  appendIfValue(form, 'city', payload.city)
  appendIfValue(form, 'country', payload.country)
  appendIfValue(form, 'description', payload.description)
  appendIfValue(form, 'beds', payload.beds)
  appendIfValue(form, 'baths', payload.baths)
  appendIfValue(form, 'rooms', payload.rooms)
  appendIfValue(form, 'area', payload.area)
  appendIfValue(form, 'lat', payload.lat)
  appendIfValue(form, 'lng', payload.lng)
  appendBoolean(form, 'instantBook', payload.instantBook)
  payload.facilities?.forEach((f: any) => form.append('facilities[]', f))
  payload.imagesFiles?.forEach((file: File) => form.append('images[]', file))
  if (payload.coverIndex !== undefined) form.append('coverIndex', payload.coverIndex)

  const { data } = await apiClient.post('/landlord/listings', form)
  return mapListing(data.data ?? data)
}

export const updateListing = async (id: string, payload: any): Promise<Listing> => {
  const form = new FormData()
  if (payload.title !== undefined) form.append('title', payload.title)
  if (payload.pricePerNight !== undefined) form.append('pricePerNight', payload.pricePerNight)
  if (payload.category !== undefined) form.append('category', payload.category)
  if (payload.address !== undefined) form.append('address', payload.address)
  if (payload.city !== undefined) form.append('city', payload.city)
  if (payload.country !== undefined) form.append('country', payload.country)
  if (payload.description !== undefined) form.append('description', payload.description)
  if (payload.beds !== undefined) form.append('beds', payload.beds)
  if (payload.baths !== undefined) form.append('baths', payload.baths)
  if (payload.rooms !== undefined) form.append('rooms', payload.rooms)
  if (payload.area !== undefined) form.append('area', payload.area)
  if (payload.lat !== undefined) form.append('lat', payload.lat)
  if (payload.lng !== undefined) form.append('lng', payload.lng)
  appendBoolean(form, 'instantBook', payload.instantBook)
  payload.facilities?.forEach((f: any) => form.append('facilities[]', f))
  if (payload.keepImages?.length) {
    form.append('keepImages', JSON.stringify(payload.keepImages))
  }
  payload.removeImageUrls?.forEach((url: string) => form.append('removeImageUrls[]', url))
  payload.imagesFiles?.forEach((file: File) => form.append('images[]', file))

  const { data } = await apiClient.post(`/landlord/listings/${id}?_method=PUT`, form)
  return mapListing(data.data ?? data)
}

export const updateListingLocation = async (
  listingId: string,
  payload: { latitude: number; longitude: number },
): Promise<Listing> => {
  const { data } = await apiClient.patch(`/listings/${listingId}/location`, payload)
  return mapListing(data.data ?? data)
}

export const resetListingLocation = async (listingId: string): Promise<Listing> => {
  const { data } = await apiClient.post(`/listings/${listingId}/location/reset`)
  return mapListing(data.data ?? data)
}

export const publishListing = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/publish`)
  return mapListing(data.data ?? data)
}

export const unpublishListing = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/unpublish`)
  return mapListing(data.data ?? data)
}

export const archiveListing = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/archive`)
  return mapListing(data.data ?? data)
}

export const restoreListing = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/restore`)
  return mapListing(data.data ?? data)
}

export const markListingRented = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/mark-rented`)
  return mapListing(data.data ?? data)
}

export const markListingAvailable = async (id: string): Promise<Listing> => {
  const { data } = await apiClient.patch(`/landlord/listings/${id}/mark-available`)
  return mapListing(data.data ?? data)
}

export const getSavedSearches = async (): Promise<SavedSearch[]> => {
  const { data } = await apiClient.get('/saved-searches')
  const list = (data.data ?? data) as any[]
  return list.map(mapSavedSearch)
}

export const createSavedSearch = async (payload: {
  name?: string | null
  filters: Record<string, any>
  alertsEnabled?: boolean
  frequency?: SavedSearch['frequency']
}): Promise<SavedSearch> => {
  const body = {
    name: payload.name ?? null,
    filters: payload.filters,
    alerts_enabled: payload.alertsEnabled ?? true,
    frequency: payload.frequency ?? 'instant',
  }
  const { data } = await apiClient.post('/saved-searches', body)
  return mapSavedSearch(data.data ?? data)
}

export const updateSavedSearch = async (
  id: string,
  payload: {
    name?: string | null
    filters?: Record<string, any>
    alertsEnabled?: boolean
    frequency?: SavedSearch['frequency']
  },
): Promise<SavedSearch> => {
  const body: Record<string, any> = {}
  if (payload.name !== undefined) body.name = payload.name
  if (payload.filters !== undefined) body.filters = payload.filters
  if (payload.alertsEnabled !== undefined) body.alerts_enabled = payload.alertsEnabled
  if (payload.frequency !== undefined) body.frequency = payload.frequency
  const { data } = await apiClient.put(`/saved-searches/${id}`, body)
  return mapSavedSearch(data.data ?? data)
}

export const deleteSavedSearch = async (id: string): Promise<void> => {
  await apiClient.delete(`/saved-searches/${id}`)
}

export const applyToListing = async (listingId: string, message?: string | null): Promise<Application> => {
  const { data } = await apiClient.post(`/listings/${listingId}/apply`, { message })
  return mapApplication(data.data ?? data)
}

export const getApplicationsForSeeker = async (): Promise<Application[]> => {
  const { data } = await apiClient.get('/seeker/applications')
  const list = (data.data ?? data) as any[]
  return list.map(mapApplication)
}

export const getApplicationsForLandlord = async (listingId?: string): Promise<Application[]> => {
  const params: Record<string, any> = {}
  if (listingId) params.listing_id = listingId
  const { data } = await apiClient.get('/landlord/applications', { params })
  const list = (data.data ?? data) as any[]
  return list.map(mapApplication)
}

export const updateApplicationStatus = async (id: string, status: Application['status']): Promise<Application> => {
  const { data } = await apiClient.patch(`/applications/${id}`, { status })
  return mapApplication(data.data ?? data)
}

export const getConversations = async (): Promise<Conversation[]> => {
  const { data } = await apiClient.get('/conversations')
  const list = (data.data ?? data) as any[]
  return list.map(mapConversation)
}

export const getConversationForListing = async (listingId: string, seekerId?: string): Promise<Conversation> => {
  const { data } = await apiClient.get(`/listings/${listingId}/conversation`, {
    params: seekerId ? { seeker_id: seekerId } : undefined,
  })
  return mapConversation(data.data ?? data)
}

export const getConversationById = async (conversationId: string): Promise<Conversation> => {
  const { data } = await apiClient.get(`/conversations/${conversationId}`)
  return mapConversation(data.data ?? data)
}

export const getMessages = async (conversationId: string): Promise<Message[]> => {
  const { data } = await apiClient.get(`/conversations/${conversationId}/messages`)
  const list = (data.data ?? data) as any[]
  return list.map(mapMessage)
}

export const getMessagesForListing = async (listingId: string): Promise<Message[]> => {
  const { data } = await apiClient.get(`/listings/${listingId}/messages`)
  const list = (data.data ?? data) as any[]
  return list.map(mapMessage)
}

export const sendMessageToListing = async (listingId: string, message: string): Promise<Message> => {
  const { data } = await apiClient.post(`/listings/${listingId}/messages`, { message })
  return mapMessage(data.data ?? data)
}

export const sendMessageToConversation = async (conversationId: string, message: string): Promise<Message> => {
  const { data } = await apiClient.post(`/conversations/${conversationId}/messages`, { message })
  return mapMessage(data.data ?? data)
}

export const markConversationRead = async (conversationId: string): Promise<void> => {
  await apiClient.post(`/conversations/${conversationId}/read`)
}

export const getOrCreateConversationForApplication = async (applicationId: string): Promise<Conversation> => {
  const { data } = await apiClient.post(`/applications/${applicationId}/conversation`)
  return mapConversation(data.data ?? data)
}

const mapProfile = (data: any): PublicProfile => ({
  id: String(data.id),
  fullName: data.fullName ?? data.full_name ?? data.name ?? '',
  joinedAt: data.joinedAt ?? data.joined_at ?? data.created_at,
  verifications: {
    email: Boolean(data.verifications?.email),
    phone: Boolean(data.verifications?.phone),
    address: Boolean(data.verifications?.address),
  },
  ratingStats: {
    average: Number(data.ratingStats?.average ?? 0),
    total: Number(data.ratingStats?.total ?? 0),
    breakdown: data.ratingStats?.breakdown ?? {},
  },
  recentRatings:
    data.recentRatings?.map((r: any) => ({
      raterName: r.raterName ?? r.rater_name,
      rating: Number(r.rating ?? 0),
      comment: r.comment,
      createdAt: r.createdAt ?? r.created_at,
      listingTitle: r.listingTitle ?? r.listing_title,
    })) ?? [],
})

export const getPublicProfile = async (userId: string): Promise<PublicProfile> => {
  const { data } = await apiClient.get(`/users/${userId}`)
  return mapProfile(data.data ?? data)
}

const mapRating = (data: any): Rating => ({
  id: String(data.id),
  listingId: String(data.listingId ?? data.listing_id ?? data.listing?.id ?? ''),
  rating: Number(data.rating ?? 0),
  comment: data.comment ?? null,
  createdAt: data.createdAt ?? data.created_at,
  rater: data.rater
    ? { id: data.rater.id, name: data.rater.fullName ?? data.rater.name }
    : data.rater_id
    ? { id: data.rater_id, name: data.rater_name }
    : undefined,
  rateeId: data.rateeId ?? data.ratee_id,
  listing: data.listing
    ? { id: data.listing.id, title: data.listing.title, city: data.listing.city }
    : undefined,
  reportCount: data.reportCount ?? data.report_count,
})

const mapReportType = (targetType?: string): Report['type'] => {
  if (!targetType) return 'other'
  if (targetType.includes('Rating')) return 'rating'
  if (targetType.includes('Message')) return 'message'
  if (targetType.includes('Listing')) return 'listing'
  return 'other'
}

const mapReport = (data: any): Report => ({
  id: String(data.id),
  type: data.type ?? mapReportType(data.target_type),
  status: data.status,
  reason: data.reason,
  details: data.details ?? null,
  resolution: data.resolution ?? null,
  createdAt: data.createdAt ?? data.created_at,
  reviewedAt: data.reviewedAt ?? data.reviewed_at,
  reporter: data.reporter
    ? { id: data.reporter.id, name: data.reporter.fullName ?? data.reporter.name }
    : undefined,
  target: data.target ?? data.target_summary,
  totalReports: Number(data.totalReports ?? data.total_reports ?? 1),
})

export const leaveRating = async (
  listingId: string,
  rateeUserId: string | number,
  payload: { rating: number; comment?: string },
): Promise<Rating> => {
  const { data } = await apiClient.post(`/listings/${listingId}/ratings`, {
    ratee_user_id: rateeUserId,
    rating: payload.rating,
    comment: payload.comment,
  })
  return mapRating(data.data ?? data)
}

export const getUserRatings = async (userId: string): Promise<Rating[]> => {
  const { data } = await apiClient.get(`/users/${userId}/ratings`)
  const list = (data.data ?? data) as any[]
  return list.map(mapRating)
}

export const reportRating = async (ratingId: string, reason: string, details?: string) => {
  const { data } = await apiClient.post(`/ratings/${ratingId}/report`, { reason, details })
  return mapRating(data.data ?? data)
}

export const getAdminRatings = async (params?: { reported?: boolean }): Promise<Rating[]> => {
  const { data } = await apiClient.get('/admin/ratings', { params })
  const list = (data.data ?? data) as any[]
  return list.map(mapRating)
}

export const deleteAdminRating = async (ratingId: string) => {
  await apiClient.delete(`/admin/ratings/${ratingId}`)
}

export const flagUserSuspicious = async (userId: string | number, isSuspicious: boolean) => {
  const { data } = await apiClient.patch(`/admin/users/${userId}/flag-suspicious`, { is_suspicious: isSuspicious })
  return data
}

export const getAdminReports = async (params?: {
  type?: 'rating' | 'message' | 'listing'
  status?: 'open' | 'resolved' | 'dismissed'
  q?: string
}): Promise<Report[]> => {
  const { data } = await apiClient.get('/admin/moderation/queue', { params })
  const list = (data.data ?? data) as any[]
  return list.map(mapReport)
}

export const getAdminReport = async (id: string): Promise<Report> => {
  const { data } = await apiClient.get(`/admin/moderation/reports/${id}`)
  return mapReport(data.data ?? data)
}

export const updateAdminReport = async (
  id: string,
  payload: { action: 'dismiss' | 'resolve'; resolution?: string; deleteTarget?: boolean; flagUserId?: string | number },
): Promise<Report> => {
  const body: any = {
    action: payload.action,
    resolution: payload.resolution,
    delete_target: payload.deleteTarget,
    flag_user_id: payload.flagUserId,
  }
  const { data } = await apiClient.patch(`/admin/moderation/reports/${id}`, body)
  return mapReport(data.data ?? data)
}

export const getAdminKpiSummary = async (): Promise<AdminKpiSummary> => {
  const { data } = await apiClient.get('/admin/kpi/summary')
  return data
}

export const getAdminKpiConversion = async (): Promise<AdminConversion> => {
  const { data } = await apiClient.get('/admin/kpi/conversion')
  return data
}

export const getAdminKpiTrends = async (range: '7d' | '30d' = '7d'): Promise<AdminTrendPoint[]> => {
  const { data } = await apiClient.get('/admin/kpi/trends', { params: { range } })
  const list = (data.data ?? data) as any[]
  return list.map((item: any) => ({
    date: item.date,
    listings: Number(item.listings ?? 0),
    applications: Number(item.applications ?? 0),
    messages: Number(item.messages ?? 0),
    ratings: Number(item.ratings ?? 0),
    reports: Number(item.reports ?? 0),
  }))
}

export const startImpersonation = async (userId: string | number) => {
  const { data } = await apiClient.post(`/admin/impersonate/${userId}`)
  return data
}

export const stopImpersonation = async () => {
  const { data } = await apiClient.post('/admin/impersonate/stop')
  return data
}

export const getBookings = async () => {
  return []
}

const mapSlotPayload = (payload: {
  startsAt?: string
  endsAt?: string
  capacity?: number
  isActive?: boolean
  pattern?: ViewingSlot['pattern']
  daysOfWeek?: number[]
  timeFrom?: string
  timeTo?: string
}) => {
  const body: Record<string, any> = {}
  if (payload.startsAt !== undefined) body.starts_at = payload.startsAt
  if (payload.endsAt !== undefined) body.ends_at = payload.endsAt
  if (payload.capacity !== undefined) body.capacity = payload.capacity
  if (payload.isActive !== undefined) body.is_active = payload.isActive
  if (payload.pattern) body.pattern = payload.pattern
  if (payload.daysOfWeek) body.days_of_week = payload.daysOfWeek
  if (payload.timeFrom) body.time_from = payload.timeFrom
  if (payload.timeTo) body.time_to = payload.timeTo
  return body
}

export const getViewingSlots = async (listingId: string): Promise<ViewingSlot[]> => {
  const { data } = await apiClient.get(`/listings/${listingId}/viewing-slots`)
  const items = (data.data ?? data) as any[]
  return items.map(mapViewingSlot)
}

export const createViewingSlot = async (
  listingId: string,
  payload: { startsAt: string; endsAt: string; capacity?: number; isActive?: boolean },
): Promise<ViewingSlot> => {
  const { data } = await apiClient.post(`/listings/${listingId}/viewing-slots`, mapSlotPayload(payload))
  return mapViewingSlot(data.data ?? data)
}

export const updateViewingSlot = async (
  slotId: string,
  payload: Partial<{ startsAt: string; endsAt: string; capacity?: number; isActive?: boolean }>,
): Promise<ViewingSlot> => {
  const { data } = await apiClient.patch(`/viewing-slots/${slotId}`, mapSlotPayload(payload as any))
  return mapViewingSlot(data.data ?? data)
}

export const deleteViewingSlot = async (slotId: string): Promise<void> => {
  await apiClient.delete(`/viewing-slots/${slotId}`)
}

export const requestViewingSlot = async (
  slotId: string,
  message?: string,
  scheduledAt?: string,
  _seekerId?: string,
): Promise<ViewingRequest> => {
  const payload: Record<string, any> = { message }
  if (scheduledAt) {
    payload.scheduledAt = scheduledAt
  }
  const { data } = await apiClient.post(`/viewing-slots/${slotId}/request`, payload)
  return mapViewingRequest(data.data ?? data)
}

export const getViewingRequestsForSeeker = async (): Promise<ViewingRequest[]> => {
  const { data } = await apiClient.get('/seeker/viewing-requests')
  const list = (data.data ?? data) as any[]
  return list.map(mapViewingRequest)
}

export const getViewingRequestsForLandlord = async (listingId?: string): Promise<ViewingRequest[]> => {
  const { data } = await apiClient.get('/landlord/viewing-requests', { params: listingId ? { listing_id: listingId } : {} })
  const list = (data.data ?? data) as any[]
  return list.map(mapViewingRequest)
}

export const confirmViewingRequest = async (id: string): Promise<ViewingRequest> => {
  const { data } = await apiClient.patch(`/viewing-requests/${id}/confirm`)
  return mapViewingRequest(data.data ?? data)
}

export const rejectViewingRequest = async (id: string): Promise<ViewingRequest> => {
  const { data } = await apiClient.patch(`/viewing-requests/${id}/reject`)
  return mapViewingRequest(data.data ?? data)
}

export const cancelViewingRequest = async (id: string): Promise<ViewingRequest> => {
  const { data } = await apiClient.patch(`/viewing-requests/${id}/cancel`)
  return mapViewingRequest(data.data ?? data)
}

export const downloadViewingRequestIcs = async (id: string): Promise<Blob> => {
  const { data } = await apiClient.get(`/viewing-requests/${id}/ics`, { responseType: 'blob' })
  return data as Blob
}
