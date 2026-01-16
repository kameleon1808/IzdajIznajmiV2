import { apiClient } from './apiClient'
import type { BookingRequest, Conversation, Listing, ListingFilters, Message } from '../types'
import { useAuthStore } from '../stores/auth'

const mapListing = (data: any): Listing => ({
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
  coverImage: data.coverImage ?? data.cover_image ?? data.images?.[0]?.url ?? '',
  images: (data.images ?? data.listing_images ?? [])
    .map((img: any) => (typeof img === 'string' ? img : img.url))
    .filter(Boolean),
  description: data.description ?? '',
  beds: Number(data.beds ?? 0),
  baths: Number(data.baths ?? 0),
  category: data.category,
  isFavorite: Boolean(data.isFavorite ?? false),
  instantBook: Boolean(data.instantBook ?? data.instant_book ?? false),
  facilities:
    data.facilities?.map((f: any) => (typeof f === 'string' ? f : f.name)) ??
    data.facilities ??
    [],
  ownerId: data.ownerId ?? data.owner_id,
  createdAt: data.createdAt ?? data.created_at,
})

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
  if (filters.facilities?.length) params.facilities = filters.facilities
  if (filters.rating) params.rating = filters.rating
  return params
}

export const getPopularListings = async (filters?: ListingFilters): Promise<Listing[]> => {
  const params = applyListingFilters(filters)
  const { data } = await apiClient.get('/listings', { params })
  const list = (data.data ?? data) as any[]
  return list.map(mapListing)
}

export const getRecommendedListings = async (filters?: ListingFilters): Promise<Listing[]> =>
  getPopularListings(filters)

export const searchListings = async (query: string, filters?: ListingFilters): Promise<Listing[]> => {
  const params = { ...applyListingFilters(filters), location: query || filters?.location }
  const { data } = await apiClient.get('/listings', { params })
  const list = (data.data ?? data) as any[]
  return list.map(mapListing)
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

export const createListing = async (payload: Partial<Listing>): Promise<Listing> => {
  const body = {
    title: payload.title,
    pricePerNight: payload.pricePerNight,
    category: payload.category,
    address: payload.address,
    city: payload.city,
    country: payload.country,
    description: payload.description,
    beds: payload.beds,
    baths: payload.baths,
    images: payload.images,
    facilities: payload.facilities,
    lat: payload.lat,
    lng: payload.lng,
    instantBook: payload.instantBook,
  }
  const { data } = await apiClient.post('/landlord/listings', body)
  return mapListing(data.data ?? data)
}

export const updateListing = async (id: string, payload: Partial<Listing>): Promise<Listing> => {
  const body = {
    title: payload.title,
    pricePerNight: payload.pricePerNight,
    category: payload.category,
    address: payload.address,
    city: payload.city,
    country: payload.country,
    description: payload.description,
    beds: payload.beds,
    baths: payload.baths,
    images: payload.images,
    facilities: payload.facilities,
    lat: payload.lat,
    lng: payload.lng,
    instantBook: payload.instantBook,
  }
  const { data } = await apiClient.put(`/landlord/listings/${id}`, body)
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
  const { data } = await apiClient.get('/booking-requests', { params: { role: 'tenant' } })
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
