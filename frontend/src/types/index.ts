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
    verificationStatus?: 'none' | 'pending' | 'approved' | 'rejected'
    verifiedAt?: string | null
    badges?: string[]
  }
  createdAt?: string
  status?: 'draft' | 'active' | 'paused' | 'archived' | 'rented' | 'expired'
  publishedAt?: string | null
  archivedAt?: string | null
  expiredAt?: string | null
  warnings?: string[]
  why?: string[]
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
  scheduledAt?: string | null
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
  attachments?: ChatAttachment[]
}

export interface ChatAttachment {
  id: string
  kind: 'image' | 'document'
  originalName: string
  mimeType: string
  sizeBytes: number
  url: string
  thumbUrl?: string | null
}

export interface PublicProfile {
  id: string
  fullName: string
  joinedAt?: string
  badges?: string[]
  verifications: {
    email: boolean
    phone: boolean
    address: boolean
  }
  landlordVerification?: {
    status: 'none' | 'pending' | 'approved' | 'rejected'
    verifiedAt?: string | null
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

export type TransactionStatus =
  | 'initiated'
  | 'contract_generated'
  | 'seeker_signed'
  | 'landlord_signed'
  | 'deposit_paid'
  | 'move_in_confirmed'
  | 'completed'
  | 'cancelled'
  | 'disputed'

export interface ContractSignature {
  id: string
  userId: string
  role: 'seeker' | 'landlord'
  signedAt?: string | null
  signatureMethod?: string
  signatureData?: Record<string, any>
}

export interface Contract {
  id: string
  version: number
  templateKey: string
  status: 'draft' | 'final'
  contractHash?: string
  pdfUrl?: string
  createdAt?: string
  signatures: ContractSignature[]
}

export interface Payment {
  id: string
  provider: string
  type: 'deposit' | 'rent'
  amount: number
  currency: string
  status: 'pending' | 'succeeded' | 'failed' | 'refunded' | 'cancelled'
  receiptUrl?: string | null
  createdAt?: string
}

export interface RentalTransaction {
  id: string
  status: TransactionStatus
  depositAmount?: number | null
  rentAmount?: number | null
  currency: string
  startedAt?: string | null
  completedAt?: string | null
  createdAt?: string
  updatedAt?: string
  listing: {
    id: string
    title?: string
    address?: string
    city?: string
    coverImage?: string
    status?: Listing['status']
  } | null
  participants: {
    landlordId: string
    seekerId: string
  }
  contract: Contract | null
  payments: Payment[]
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
  priceBucket?: string | null
  instantBook: boolean
  location: string
  facilities: string[]
  rating: number | null
  city?: string
  rooms?: number | null
  areaRange?: [number, number] | null
  areaBucket?: string | null
  status?: Listing['status'] | 'all'
  amenities?: string[]
  centerLat?: number | null
  centerLng?: number | null
  radiusKm?: number | null
}

export type KycStatus = 'none' | 'pending' | 'approved' | 'rejected' | 'withdrawn'

export interface KycDocument {
  id: string
  docType: 'id_front' | 'id_back' | 'selfie' | 'proof_of_address'
  originalName: string
  mimeType: string
  sizeBytes: number
  createdAt?: string
  downloadUrl?: string | null
}

export interface KycSubmission {
  id: string
  userId?: string | number
  status: KycStatus
  submittedAt?: string
  reviewedAt?: string | null
  reviewerId?: string | number | null
  reviewerNote?: string | null
  user?: { id: string | number; fullName?: string; email?: string }
  reviewer?: { id: string | number; fullName?: string }
  documents?: KycDocument[]
  createdAt?: string
  updatedAt?: string
}

export interface FacetOption {
  value: string
  count: number
}

export interface ListingSearchFacets {
  city: FacetOption[]
  status: FacetOption[]
  rooms: FacetOption[]
  amenities: FacetOption[]
  price_bucket: FacetOption[]
  area_bucket: FacetOption[]
}

export interface SearchSuggestion {
  label: string
  type: 'query' | 'city' | 'amenity'
  value: string
}

export interface SecuritySession {
  id: string
  sessionId?: string
  deviceLabel?: string | null
  ipTruncated?: string | null
  userAgent?: string | null
  lastActiveAt?: string | null
  createdAt?: string | null
  isCurrent?: boolean
}

export interface FraudSignal {
  id: string
  signalKey: string
  weight: number
  meta?: Record<string, any> | null
  createdAt?: string | null
}

export interface FraudScore {
  score: number
  lastCalculatedAt?: string | null
}

export interface AdminUserSecurityPayload {
  user: any
  fraudScore: FraudScore
  fraudSignals: FraudSignal[]
  sessions: SecuritySession[]
  landlordMetrics?: {
    avgRating30d?: number | null
    allTimeAvgRating?: number | null
    ratingsCount?: number
    medianResponseTimeMinutes?: number | null
    completedTransactionsCount?: number
    updatedAt?: string | null
  } | null
  landlordBadges?: {
    badges: string[]
    override?: Record<string, boolean> | null
    suppressed?: boolean
  } | null
}
