export type ListingCategory = 'villa' | 'hotel' | 'apartment'

export interface Listing {
  id: string
  title: string
  address?: string
  city: string
  country: string
  lat?: number
  lng?: number
  locationSource?: 'geocoded' | 'manual'
  locationAccuracyM?: number | null
  locationOverriddenAt?: string | null
  pricePerNight: number
  rating: number
  reviewsCount: number
  coverImage: string
  images?: string[]
  imagesDetailed?: { url: string; sortOrder: number; isCover: boolean; processingStatus?: string; processingError?: string }[]
  description?: string
  beds: number
  baths: number
  rooms?: number
  area?: number
  category: ListingCategory
  isFavorite: boolean
  instantBook?: boolean
  facilities?: string[]
  distanceKm?: number
  geocodedAt?: string | null
  ownerId?: string | number
  landlord?: {
    id: string | number
    fullName?: string
  }
  createdAt?: string
  status?: 'draft' | 'active' | 'paused' | 'archived' | 'rented' | 'expired'
  publishedAt?: string | null
  archivedAt?: string | null
  expiredAt?: string | null
  warnings?: string[]
}

export type SavedSearchFrequency = 'instant' | 'daily' | 'weekly'

export interface SavedSearch {
  id: string
  name?: string | null
  filters: Record<string, any>
  alertsEnabled: boolean
  frequency: SavedSearchFrequency
  lastAlertedAt?: string | null
  createdAt?: string | null
  updatedAt?: string | null
}

export interface Review {
  id: string
  userName: string
  avatarUrl: string
  rating: number
  text: string
  date: string
}

export interface Booking {
  id: string
  listingId: string
  listingTitle: string
  datesRange: string
  guestsText: string
  pricePerNight: number
  rating: number
  coverImage: string
  status: 'booked' | 'history'
}

export type ViewingRequestStatus = 'requested' | 'confirmed' | 'cancelled' | 'rejected'

export interface ViewingSlot {
  id: string
  listingId: string
  landlordId: string
  startsAt: string
  endsAt: string
  capacity: number
  isActive: boolean
  pattern?: 'everyday' | 'weekdays' | 'weekends' | 'custom' | 'once' | string
  daysOfWeek?: number[]
  timeFrom?: string | null
  timeTo?: string | null
}

export interface ViewingRequest {
  id: string
  status: ViewingRequestStatus
  message?: string | null
  cancelledBy?: 'seeker' | 'landlord' | 'system' | null
  createdAt?: string
  slot: ViewingSlot | null
  listing: {
    id: string
    title?: string
    city?: string
    coverImage?: string
    pricePerNight?: number
    status?: Listing['status']
  } | null
  participants: {
    seekerId: string
    landlordId: string
  }
}

export interface Application {
  id: string
  status: 'submitted' | 'accepted' | 'rejected' | 'withdrawn'
  message?: string | null
  createdAt?: string
  listing: {
    id: string
    title?: string
    city?: string
    coverImage?: string
    pricePerNight?: number
    status?: Listing['status']
  }
  participants: {
    seekerId: string
    landlordId: string
  }
}

export interface Conversation {
  id: string
  listingId: string
  listingTitle?: string
  listingCity?: string
  listingCoverImage?: string
  userName: string
  avatarUrl: string
  lastMessage: string
  time: string
  unreadCount: number
  online: boolean
  participants?: {
    tenantId: string | number
    landlordId: string | number
  }
}

export interface Message {
  id: string
  conversationId: string
  senderId?: string
  from: 'me' | 'them'
  text: string
  createdAt?: string
  time: string
}

export interface PublicProfile {
  id: string
  fullName: string
  joinedAt?: string
  verifications: {
    email: boolean
    phone: boolean
    address: boolean
  }
  ratingStats: {
    average: number
    total: number
    breakdown: Record<string, number>
  }
  recentRatings: Array<{
    raterName?: string
    rating: number
    comment?: string
    createdAt?: string
    listingTitle?: string
  }>
}

export interface Rating {
  id: string
  listingId: string
  rating: number
  comment?: string | null
  createdAt?: string
  rater?: { id?: string | number; name?: string }
  rateeId?: string | number
  listing?: { id?: string | number; title?: string; city?: string }
  reportCount?: number
}

export type ReportType = 'rating' | 'message' | 'listing' | 'other'

export interface Report {
  id: string
  type: ReportType
  status: 'open' | 'resolved' | 'dismissed'
  reason: string
  details?: string | null
  resolution?: string | null
  createdAt?: string
  reviewedAt?: string
  reporter?: { id?: string | number; name?: string }
  target?: Record<string, any>
  totalReports?: number
}

export interface AdminKpiSummary {
  listings: { last24h: number; last7d: number }
  applications: { last24h: number; last7d: number }
  messages: { last24h: number; last7d: number }
  ratings: { last24h: number; last7d: number }
  reports: { last24h: number; last7d: number }
  suspiciousUsers: number
}

export interface AdminConversion {
  browseToApply: { from: number; to: number; rate: number }
  applyToChat: { from: number; to: number; rate: number }
  chatToRating: { from: number; to: number; rate: number }
}

export interface AdminTrendPoint {
  date: string
  listings: number
  applications: number
  messages: number
  ratings: number
  reports: number
}

export interface ListingFilters {
  category: 'all' | ListingCategory
  guests: number
  priceRange: [number, number]
  instantBook: boolean
  location: string
  facilities: string[]
  rating: number | null
  city?: string
  rooms?: number | null
  areaRange?: [number, number] | null
  status?: Listing['status'] | 'all'
  amenities?: string[]
  centerLat?: number | null
  centerLng?: number | null
  radiusKm?: number | null
}
