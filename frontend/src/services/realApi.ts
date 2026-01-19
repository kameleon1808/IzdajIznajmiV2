import { apiClient } from './apiClient'
import type { BookingRequest, Conversation, Listing, ListingFilters, Message } from '../types'
import { useAuthStore } from '../stores/auth'

const mapListing = (data: any): Listing => {
  const detailed = data.imagesDetailed ?? data.images ?? data.listing_images ?? []
  const detailedArr = Array.isArray(detailed) ? detailed : Object.values(detailed)
  const imagesDetailed = detailedArr.map((img: any) => ({
    url: typeof img === 'string' ? img : img.url,
    sortOrder: Number(img.sortOrder ?? img.sort_order ?? 0),
    isCover: Boolean(img.isCover ?? img.is_cover ?? false),
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
    createdAt: data.createdAt ?? data.created_at,
    status: data.status,
    publishedAt: data.publishedAt ?? data.published_at,
    archivedAt: data.archivedAt ?? data.archived_at,
    expiredAt: data.expiredAt ?? data.expired_at,
    warnings: data.warnings ?? [],
  }
}

const mapBookingRequest = (data: any): BookingRequest => ({
  id: String(data.id),
  listingId: String(data.listingId ?? data.listing_id),
  tenantId: String(data.tenantId ?? data.tenant_id),
  landlordId: String(data.landlordId ?? data.landlord_id),
  startDate: data.startDate ?? data.start_date ?? undefined,
  endDate: data.endDate ?? data.end_date ?? undefined,
  guests: Number(data.guests ?? 1),
  message: data.message ?? '',
  status: data.status,
  createdAt: data.createdAt ?? data.created_at ?? '',
})

const mapConversation = (data: any): Conversation => ({
  id: String(data.id),
  userName: data.userName ?? data.tenant?.name ?? data.landlord?.name ?? 'Conversation',
  avatarUrl: data.avatarUrl ?? data.avatar_url ?? '',
  lastMessage: data.lastMessage ?? data.last_message ?? '',
  time: data.time ?? data.updated_at ?? '',
  unreadCount: Number(data.unreadCount ?? data.unread_count ?? 0),
  online: Boolean(data.online ?? false),
})

const mapMessage = (data: any): Message => {
  const auth = useAuthStore()
  const senderId = String(data.sender_id ?? data.senderId ?? '')
  return {
    id: String(data.id),
    conversationId: String(data.conversation_id ?? data.conversationId ?? ''),
    from: auth.user.id && senderId === String(auth.user.id) ? 'me' : 'them',
    text: data.body ?? data.text ?? '',
    time: data.created_at ?? data.time ?? '',
  }
}

const applyListingFilters = (filters?: ListingFilters) => {
  if (!filters) return {}
  const params: Record<string, any> = {}
  if (filters.category && filters.category !== 'all') params.category = filters.category
  if (filters.priceRange?.length) {
    params.priceMin = filters.priceRange[0]
    params.priceMax = filters.priceRange[1]
  }
  if (filters.guests) params.guests = filters.guests
  if (filters.instantBook) params.instantBook = filters.instantBook
  if (filters.location) params.location = filters.location
  if (filters.city) params.city = filters.city
  if (filters.rooms) params.rooms = filters.rooms
  if (filters.areaRange?.length) {
    params.areaMin = filters.areaRange[0]
    params.areaMax = filters.areaRange[1]
  }
  if (filters.facilities?.length) params.facilities = filters.facilities
  if (filters.amenities?.length) params.amenities = filters.amenities
  if (filters.rating) params.rating = filters.rating
  if (filters.status && filters.status !== 'all') params.status = filters.status
  return params
}

const appendIfValue = (form: FormData, key: string, value: any) => {
  if (value === undefined || value === null || value === '') return
  form.append(key, value as any)
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

export const searchListings = async (query: string, filters?: ListingFilters, page = 1, perPage = 10) => {
  const params = { ...applyListingFilters(filters), location: query || filters?.location, page, perPage }
  const { data } = await apiClient.get('/listings', { params })
  return mapPaginated(data)
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
  appendIfValue(form, 'instantBook', payload.instantBook)
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
  if (payload.instantBook !== undefined) form.append('instantBook', payload.instantBook)
  payload.facilities?.forEach((f: any) => form.append('facilities[]', f))
  if (payload.keepImages?.length) {
    form.append('keepImages', JSON.stringify(payload.keepImages))
  }
  payload.removeImageUrls?.forEach((url: string) => form.append('removeImageUrls[]', url))
  payload.imagesFiles?.forEach((file: File) => form.append('images[]', file))

  const { data } = await apiClient.post(`/landlord/listings/${id}?_method=PUT`, form)
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

export const createBookingRequest = async (
  payload: Omit<BookingRequest, 'id' | 'tenantId' | 'status' | 'createdAt'>,
): Promise<BookingRequest> => {
  const body = {
    listingId: payload.listingId,
    landlordId: payload.landlordId,
    startDate: payload.startDate,
    endDate: payload.endDate,
    guests: payload.guests,
    message: payload.message,
  }
  const { data } = await apiClient.post('/booking-requests', body)
  return mapBookingRequest(data.data ?? data)
}

export const getBookingRequestsForTenant = async (): Promise<BookingRequest[]> => {
  const { data } = await apiClient.get('/booking-requests', { params: { role: 'seeker' } })
  const list = (data.data ?? data) as any[]
  return list.map(mapBookingRequest)
}

export const getBookingRequestsForLandlord = async (): Promise<BookingRequest[]> => {
  const { data } = await apiClient.get('/booking-requests', { params: { role: 'landlord' } })
  const list = (data.data ?? data) as any[]
  return list.map(mapBookingRequest)
}

export const updateBookingRequestStatus = async (
  id: string,
  status: BookingRequest['status'],
): Promise<BookingRequest> => {
  const { data } = await apiClient.patch(`/booking-requests/${id}`, { status })
  return mapBookingRequest(data.data ?? data)
}

export const getConversations = async (): Promise<Conversation[]> => {
  const { data } = await apiClient.get('/conversations')
  const list = (data.data ?? data) as any[]
  return list.map(mapConversation)
}

export const getMessages = async (conversationId: string): Promise<Message[]> => {
  const { data } = await apiClient.get(`/conversations/${conversationId}/messages`)
  const list = (data.data ?? data) as any[]
  return list.map(mapMessage)
}

export const getBookings = async () => {
  return []
}
