export type ListingCategory = 'villa' | 'hotel' | 'apartment'

export interface Listing {
  id: string
  title: string
  city: string
  country: string
  pricePerNight: number
  rating: number
  reviewsCount: number
  coverImage: string
  beds: number
  baths: number
  category: ListingCategory
  isFavorite: boolean
  instantBook?: boolean
  facilities?: string[]
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
