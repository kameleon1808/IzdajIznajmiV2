import type {
  Booking,
  BookingRequest,
  Conversation,
  Listing,
  ListingFilters,
  Message,
  Review,
} from '../types'

const makeId = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : Math.random().toString(36).slice(2)

const listings: Listing[] = [
  {
    id: '1',
    title: 'Seaside Villa Aurora',
    address: 'Jadranska 12',
    city: 'Split',
    country: 'Croatia',
    lat: 43.5081,
    lng: 16.4402,
    pricePerNight: 240,
    rating: 4.8,
    reviewsCount: 182,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1501117716987-c8e1ecb210af?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Minimalist coastal escape with private pool, sun decks, and floor-to-ceiling glass framing the sea.',
    beds: 4,
    baths: 3,
    category: 'villa',
    isFavorite: true,
    instantBook: true,
    facilities: ['Pool', 'Wi-Fi', 'Ocean View', 'Kitchen', 'Parking'],
    ownerId: 'landlord-1',
    createdAt: '2025-11-20T10:00:00Z',
  },
  {
    id: '2',
    title: 'Nordic Lights Hotel',
    address: 'Nyhavn 5',
    city: 'Copenhagen',
    country: 'Denmark',
    lat: 55.6761,
    lng: 12.5683,
    pricePerNight: 180,
    rating: 4.6,
    reviewsCount: 140,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1501117716987-c8e1ecb210af?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Scandi-inspired suites with warm wood, soft lighting, and skyline views near the canal.',
    beds: 2,
    baths: 2,
    category: 'hotel',
    isFavorite: false,
    instantBook: true,
    facilities: ['Breakfast', 'Wi-Fi', 'Gym', 'Spa', 'Parking'],
    ownerId: 'landlord-2',
    createdAt: '2025-10-11T14:20:00Z',
  },
  {
    id: '3',
    title: 'Urban Loft Retreat',
    address: 'Rua do Sol 21',
    city: 'Lisbon',
    country: 'Portugal',
    lat: 38.7223,
    lng: -9.1393,
    pricePerNight: 130,
    rating: 4.7,
    reviewsCount: 96,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Airy loft with exposed beams, cozy workspace, and a balcony over Alfama rooftops.',
    beds: 1,
    baths: 1,
    category: 'apartment',
    isFavorite: false,
    instantBook: false,
    facilities: ['Wi-Fi', 'City View', 'Kitchen', 'Workspace'],
    ownerId: 'landlord-1',
    createdAt: '2025-09-02T09:15:00Z',
  },
  {
    id: '4',
    title: 'Lagoon Resort & Spa',
    address: 'Carretera Tulum 14',
    city: 'Tulum',
    country: 'Mexico',
    lat: 20.2115,
    lng: -87.4654,
    pricePerNight: 210,
    rating: 4.9,
    reviewsCount: 201,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1501117716987-c8e1ecb210af?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Jungle resort with lagoon pools, bamboo cabanas, and slow breakfast under palms.',
    beds: 3,
    baths: 2,
    category: 'hotel',
    isFavorite: true,
    instantBook: true,
    facilities: ['Pool', 'Spa', 'Breakfast', 'Wi-Fi', 'Bar'],
    ownerId: 'landlord-3',
    createdAt: '2025-12-05T08:00:00Z',
  },
  {
    id: '5',
    title: 'Hillside Glass House',
    address: 'Crown Range 7',
    city: 'Queenstown',
    country: 'New Zealand',
    lat: -45.0312,
    lng: 168.6626,
    pricePerNight: 320,
    rating: 4.9,
    reviewsCount: 112,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Glass-walled retreat with alpine views, fireplace lounge, and heated floors.',
    beds: 3,
    baths: 3,
    category: 'villa',
    isFavorite: false,
    instantBook: true,
    facilities: ['Mountain View', 'Fireplace', 'Wi-Fi', 'Parking'],
    ownerId: 'landlord-2',
    createdAt: '2025-08-15T17:45:00Z',
  },
  {
    id: '6',
    title: 'Canal Side Studio',
    address: 'Keizersgracht 221',
    city: 'Amsterdam',
    country: 'Netherlands',
    lat: 52.3676,
    lng: 4.9041,
    pricePerNight: 115,
    rating: 4.5,
    reviewsCount: 88,
    coverImage: '',
    images: [
      'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1501117716987-c8e1ecb210af?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
    ],
    description: 'Canal-view micro studio with warm tones, bike rental downstairs, and sunny reading nook.',
    beds: 1,
    baths: 1,
    category: 'apartment',
    isFavorite: true,
    instantBook: false,
    facilities: ['Canal View', 'Wi-Fi', 'Bike Rental'],
    ownerId: 'landlord-3',
    createdAt: '2025-07-01T12:30:00Z',
  },
]

listings.forEach((item) => {
  if (!item.coverImage && item.images?.length) {
    item.coverImage = item.images[0] ?? ''
  }
  ;(item as any).status = (item as any).status ?? 'published'
})

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
    coverImage: listings[1]?.coverImage ?? '',
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
    coverImage: listings[0]?.coverImage ?? '',
    status: 'history',
  },
]

const bookingRequests: BookingRequest[] = [
  {
    id: 'br1',
    listingId: '1',
    tenantId: 'tenant-1',
    landlordId: 'landlord-1',
    startDate: '2026-03-02',
    endDate: '2026-03-07',
    guests: 3,
    message: 'We would love a quiet family stay. Can we check in early?',
    status: 'pending',
    createdAt: '2026-01-05T10:00:00Z',
  },
  {
    id: 'br2',
    listingId: '2',
    tenantId: 'tenant-1',
    landlordId: 'landlord-2',
    startDate: '2026-02-18',
    endDate: '2026-02-20',
    guests: 2,
    message: 'Celebrating anniversary, need late checkout.',
    status: 'accepted',
    createdAt: '2026-01-08T12:30:00Z',
  },
  {
    id: 'br3',
    listingId: '3',
    tenantId: 'tenant-2',
    landlordId: 'landlord-1',
    guests: 1,
    message: 'Workcation with stable Wi-Fi, flexible dates.',
    status: 'rejected',
    createdAt: '2026-01-10T09:20:00Z',
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

const maybeFail = () => {
  const failChance = 0.08
  if (Math.random() < failChance) {
    throw new Error('Network error. Please try again.')
  }
}

async function simulate<T>(data: T): Promise<T> {
  await delay()
  maybeFail()
  return JSON.parse(JSON.stringify(data)) as T
}

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
  return simulate(listings.slice(0, 3))
}

export async function getRecommendedListings(filters?: Partial<ListingFilters>): Promise<Listing[]> {
  return simulate(applyFilters(listings, filters))
}

export async function searchListings(query: string, filters?: Partial<ListingFilters>): Promise<Listing[]> {
  const filtered = applyFilters(listings, filters)
  return simulate(filtered.filter((item) => item.title.toLowerCase().includes(query.toLowerCase())))
}

export async function getListingById(id: string): Promise<Listing | null> {
  const found = listings.find((item) => item.id === id)
  return simulate(found ?? null)
}

export async function getListingFacilities(id: string): Promise<{ title: string; items: string[] }[]> {
  const group = facilityGroups[id] ?? facilityGroups.default
  return simulate(group ?? [])
}

export async function getListingReviews(id: string): Promise<Review[]> {
  return simulate(reviews[id] ?? [])
}

export async function getFavorites(): Promise<Listing[]> {
  return simulate(listings.filter((item) => item.isFavorite))
}

export async function getBookings(status: Booking['status']): Promise<Booking[]> {
  return simulate(bookings.filter((b) => b.status === status))
}

export async function getConversations(): Promise<Conversation[]> {
  return simulate(conversations)
}

export async function getMessages(conversationId: string): Promise<Message[]> {
  return simulate(messages[conversationId] ?? [])
}

export async function getLandlordListings(ownerId: string | number): Promise<Listing[]> {
  return simulate(listings.filter((item) => item.ownerId === ownerId))
}

type ListingInput = {
  title: string
  pricePerNight: number
  category: Listing['category']
  address: string
  city: string
  country: string
  beds: number
  baths: number
  images?: string[]
  description?: string
  lat?: number
  lng?: number
  facilities?: string[]
}

export async function createListing(payload: ListingInput & { ownerId: string | number }): Promise<Listing> {
  await delay()
  maybeFail()
  const id = makeId()
  const coverImage = payload.images?.[0] ?? 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80'
  const listing: Listing = {
    id,
    title: payload.title,
    address: payload.address,
    city: payload.city,
    country: payload.country,
    lat: payload.lat,
    lng: payload.lng,
    pricePerNight: payload.pricePerNight,
    rating: 4.7,
    reviewsCount: 0,
    coverImage,
    images: payload.images ?? [coverImage],
    description: payload.description,
    beds: payload.beds,
    baths: payload.baths,
    category: payload.category,
    isFavorite: false,
    instantBook: true,
    facilities: payload.facilities ?? [],
    ownerId: payload.ownerId,
    createdAt: new Date().toISOString(),
  }
  listings.unshift(listing)
  return JSON.parse(JSON.stringify(listing))
}

export async function updateListing(id: string, payload: Partial<ListingInput>): Promise<Listing | null> {
  await delay()
  maybeFail()
  const index = listings.findIndex((item) => item.id === id)
  if (index === -1) return null
  const current = listings[index]!
  const updated: Listing = {
    ...current,
    ...payload,
    coverImage: payload.images?.[0] ?? current.coverImage,
    images: payload.images ?? current.images,
  }
  listings[index] = updated
  return JSON.parse(JSON.stringify(updated))
}

export async function publishListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'published'
  return JSON.parse(JSON.stringify(found))
}

export async function unpublishListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'draft'
  return JSON.parse(JSON.stringify(found))
}

export async function archiveListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'archived'
  return JSON.parse(JSON.stringify(found))
}

export async function restoreListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'draft'
  return JSON.parse(JSON.stringify(found))
}

export async function createBookingRequest(
  payload: Omit<BookingRequest, 'id' | 'status' | 'createdAt'>,
): Promise<BookingRequest> {
  await delay()
  maybeFail()
  const request: BookingRequest = {
    ...payload,
    id: makeId(),
    status: 'pending',
    createdAt: new Date().toISOString(),
  }
  bookingRequests.unshift(request)
  return JSON.parse(JSON.stringify(request))
}

export async function getBookingRequestsForTenant(tenantId: string): Promise<BookingRequest[]> {
  return simulate(bookingRequests.filter((r) => r.tenantId === tenantId))
}

export async function getBookingRequestsForLandlord(landlordId: string): Promise<BookingRequest[]> {
  return simulate(bookingRequests.filter((r) => r.landlordId === landlordId))
}

export async function updateBookingRequestStatus(
  id: string,
  status: BookingRequest['status'],
): Promise<BookingRequest | null> {
  await delay()
  maybeFail()
  const index = bookingRequests.findIndex((r) => r.id === id)
  if (index === -1) return null
  const current = bookingRequests[index]
  if (!current) return null
  bookingRequests[index] = { ...current, status }
  return JSON.parse(JSON.stringify(bookingRequests[index]))
}
