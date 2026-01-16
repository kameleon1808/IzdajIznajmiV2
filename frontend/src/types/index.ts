export type ListingCategory = 'villa' | 'hotel' | 'apartment'

export interface Listing {
  id: string
  title: string
  address?: string
  city: string
  country: string
  lat?: number
  lng?: number
  pricePerNight: number
  rating: number
  reviewsCount: number
  coverImage: string
  images?: string[]
  imagesDetailed?: { url: string; sortOrder: number; isCover: boolean }[]
  description?: string
  beds: number
  baths: number
  category: ListingCategory
  isFavorite: boolean
  instantBook?: boolean
  facilities?: string[]
  ownerId?: string | number
  createdAt?: string
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

export interface BookingRequest {
  id: string
  listingId: string
  tenantId: string
  landlordId: string
  startDate?: string
  endDate?: string
  guests: number
  message: string
  status: 'pending' | 'accepted' | 'rejected' | 'cancelled'
  createdAt: string
}

export interface Conversation {
  id: string
  userName: string
  avatarUrl: string
  lastMessage: string
  time: string
  unreadCount: number
  online: boolean
}

export interface Message {
  id: string
  conversationId: string
  from: 'me' | 'them'
  text: string
  time: string
}

export interface ListingFilters {
  category: 'all' | ListingCategory
  guests: number
  priceRange: [number, number]
  instantBook: boolean
  location: string
  facilities: string[]
  rating: number | null
}
