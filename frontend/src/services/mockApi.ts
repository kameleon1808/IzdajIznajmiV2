import type {
  Booking,
  Conversation,
  Listing,
  ListingFilters,
  Message,
  Review,
} from '../types'

const listings: Listing[] = [
  {
    id: '1',
    title: 'Seaside Villa Aurora',
    city: 'Split',
    country: 'Croatia',
    pricePerNight: 240,
    rating: 4.8,
    reviewsCount: 182,
    coverImage:
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1200&q=80',
    beds: 4,
    baths: 3,
    category: 'villa',
    isFavorite: true,
    instantBook: true,
    facilities: ['Pool', 'Wi-Fi', 'Ocean View', 'Kitchen', 'Parking'],
  },
  {
    id: '2',
    title: 'Nordic Lights Hotel',
    city: 'Copenhagen',
    country: 'Denmark',
    pricePerNight: 180,
    rating: 4.6,
    reviewsCount: 140,
    coverImage:
      'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1200&q=80',
    beds: 2,
    baths: 2,
    category: 'hotel',
    isFavorite: false,
    instantBook: true,
    facilities: ['Breakfast', 'Wi-Fi', 'Gym', 'Spa', 'Parking'],
  },
  {
    id: '3',
    title: 'Urban Loft Retreat',
    city: 'Lisbon',
    country: 'Portugal',
    pricePerNight: 130,
    rating: 4.7,
    reviewsCount: 96,
    coverImage:
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1200&q=80',
    beds: 1,
    baths: 1,
    category: 'apartment',
    isFavorite: false,
    instantBook: false,
    facilities: ['Wi-Fi', 'City View', 'Kitchen', 'Workspace'],
  },
  {
    id: '4',
    title: 'Lagoon Resort & Spa',
    city: 'Tulum',
    country: 'Mexico',
    pricePerNight: 210,
    rating: 4.9,
    reviewsCount: 201,
    coverImage:
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1200&q=80',
    beds: 3,
    baths: 2,
    category: 'hotel',
    isFavorite: true,
    instantBook: true,
    facilities: ['Pool', 'Spa', 'Breakfast', 'Wi-Fi', 'Bar'],
  },
  {
    id: '5',
    title: 'Hillside Glass House',
    city: 'Queenstown',
    country: 'New Zealand',
    pricePerNight: 320,
    rating: 4.9,
    reviewsCount: 112,
    coverImage:
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1200&q=80',
    beds: 3,
    baths: 3,
    category: 'villa',
    isFavorite: false,
    instantBook: true,
    facilities: ['Mountain View', 'Fireplace', 'Wi-Fi', 'Parking'],
  },
  {
    id: '6',
    title: 'Canal Side Studio',
    city: 'Amsterdam',
    country: 'Netherlands',
    pricePerNight: 115,
    rating: 4.5,
    reviewsCount: 88,
    coverImage:
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
    beds: 1,
    baths: 1,
    category: 'apartment',
    isFavorite: true,
    instantBook: false,
    facilities: ['Canal View', 'Wi-Fi', 'Bike Rental'],
  },
]

const reviews: Record<string, Review[]> = {
  '1': [
    {
      id: 'r1',
      userName: 'Sofia Mendes',
      avatarUrl: 'https://i.pravatar.cc/100?img=1',
      rating: 5,
      text: 'Loved the sea breeze and the cozy interiors. Perfect family stay.',
      date: '2025-12-02',
    },
    {
      id: 'r2',
      userName: 'Luka Horvat',
      avatarUrl: 'https://i.pravatar.cc/100?img=6',
      rating: 4.5,
      text: 'Amazing views and staff. The pool could be warmer.',
      date: '2025-11-12',
    },
  ],
}

const bookings: Booking[] = [
  {
    id: 'b1',
    listingId: '2',
    listingTitle: 'Nordic Lights Hotel',
    datesRange: '12 - 16 Feb 2026',
    guestsText: '2 Guests',
    pricePerNight: 180,
    rating: 4.6,
    coverImage:
      'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1200&q=80',
    status: 'booked',
  },
  {
    id: 'b2',
    listingId: '1',
    listingTitle: 'Seaside Villa Aurora',
    datesRange: '23 - 28 Jan 2026',
    guestsText: '4 Guests',
    pricePerNight: 240,
    rating: 4.8,
    coverImage:
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1200&q=80',
    status: 'history',
  },
]

const conversations: Conversation[] = [
  {
    id: 'c1',
    userName: 'Evelyn Hunt',
    avatarUrl: 'https://i.pravatar.cc/100?img=12',
    lastMessage: 'See you at 4PM for check-in.',
    time: '09:20',
    unreadCount: 2,
    online: true,
  },
  {
    id: 'c2',
    userName: 'Marco Silva',
    avatarUrl: 'https://i.pravatar.cc/100?img=9',
    lastMessage: 'Thanks for booking!',
    time: 'Yesterday',
    unreadCount: 0,
    online: false,
  },
]

const messages: Record<string, Message[]> = {
  c1: [
    { id: 'm1', conversationId: 'c1', from: 'them', text: 'Welcome to Aurora!', time: '09:15' },
    { id: 'm2', conversationId: 'c1', from: 'me', text: 'Excited to arrive.', time: '09:17' },
  ],
  c2: [
    { id: 'm3', conversationId: 'c2', from: 'them', text: 'Let me know if you need anything.', time: 'Yesterday' },
  ],
}

const facilityGroups: Record<string, { title: string; items: string[] }[]> = {
  default: [
    { title: 'Food & Drink', items: ['Restaurant', 'Room service', 'Bar'] },
    { title: 'Transportation', items: ['Airport shuttle', 'Free parking', 'Bike rental'] },
    { title: 'Wellness', items: ['Spa', 'Gym', 'Yoga deck'] },
    { title: 'Connectivity', items: ['High-speed Wi-Fi', 'Co-working space'] },
  ],
}

const delay = (min = 150, max = 300) =>
  new Promise<void>((resolve) => setTimeout(resolve, Math.floor(Math.random() * (max - min + 1)) + min))

const applyFilters = (items: Listing[], filters?: Partial<ListingFilters>) => {
  if (!filters) return items
  return items.filter((item) => {
    const matchCategory = filters.category && filters.category !== 'all' ? item.category === filters.category : true
    const matchGuests = filters.guests ? item.beds >= filters.guests : true
    const matchPrice = filters.priceRange ? item.pricePerNight >= filters.priceRange[0] && item.pricePerNight <= filters.priceRange[1] : true
    const matchInstant = filters.instantBook ? item.instantBook : true
    const matchLocation = filters.location
      ? `${item.city} ${item.country}`.toLowerCase().includes(filters.location.toLowerCase())
      : true
    const matchRating = filters.rating ? item.rating >= filters.rating : true
    const matchFacilities = filters.facilities && filters.facilities.length
    ? filters.facilities.every((f) => item.facilities?.includes(f))
    : true

    return matchCategory && matchGuests && matchPrice && matchInstant && matchLocation && matchRating && matchFacilities
  })
}

export async function getPopularListings(): Promise<Listing[]> {
  await delay()
  return listings.slice(0, 3)
}

export async function getRecommendedListings(filters?: Partial<ListingFilters>): Promise<Listing[]> {
  await delay()
  return applyFilters(listings, filters)
}

export async function searchListings(query: string, filters?: Partial<ListingFilters>): Promise<Listing[]> {
  await delay()
  const filtered = applyFilters(listings, filters)
  return filtered.filter((item) => item.title.toLowerCase().includes(query.toLowerCase()))
}

export async function getListingById(id: string): Promise<Listing | null> {
  await delay()
  return listings.find((item) => item.id === id) ?? null
}

export async function getListingFacilities(id: string): Promise<{ title: string; items: string[] }[]> {
  await delay()
  const group = facilityGroups[id] ?? facilityGroups.default
  return group as { title: string; items: string[] }[]
}

export async function getListingReviews(id: string): Promise<Review[]> {
  await delay()
  return reviews[id] ?? []
}

export async function getFavorites(): Promise<Listing[]> {
  await delay()
  return listings.filter((item) => item.isFavorite)
}

export async function getBookings(status: Booking['status']): Promise<Booking[]> {
  await delay()
  return bookings.filter((b) => b.status === status)
}

export async function getConversations(): Promise<Conversation[]> {
  await delay()
  return conversations
}

export async function getMessages(conversationId: string): Promise<Message[]> {
  await delay()
  return messages[conversationId] ?? []
}
