import type {
  AdminConversion,
  AdminKpiSummary,
  AdminTrendPoint,
  Application,
  Booking,
  Conversation,
  Listing,
  ListingFilters,
  ListingSearchFacets,
  Message,
  PublicProfile,
  Rating,
  Report,
  Review,
  SavedSearch,
  SearchSuggestion,
  ViewingRequest,
  ViewingSlot,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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
    locationSource: 'geocoded',
    geocodedAt: '2025-01-01T00:00:00Z',
    locationOverriddenAt: null,
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

const savedSearches: SavedSearch[] = []

listings.forEach((item) => {
  if (!item.coverImage && item.images?.length) {
    item.coverImage = item.images[0] ?? ''
  }
  ;(item as any).status = (item as any).status ?? 'active'
  ;(item as any).rooms = (item as any).rooms ?? item.beds
  ;(item as any).area = (item as any).area ?? 80 + Math.floor(Math.random() * 70)
  ;(item as any).warnings = []
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

const viewingSlots: ViewingSlot[] = [
  {
    id: 'vs1',
    listingId: '1',
    landlordId: 'landlord-1',
    startsAt: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString(),
    endsAt: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000 + 45 * 60 * 1000).toISOString(),
    capacity: 1,
    isActive: true,
    pattern: 'once',
    timeFrom: '17:00',
    timeTo: '17:45',
  },
  {
    id: 'vs2',
    listingId: '2',
    landlordId: 'landlord-2',
    startsAt: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toISOString(),
    endsAt: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000 + 60 * 60 * 1000).toISOString(),
    capacity: 2,
    isActive: true,
    pattern: 'weekdays',
    timeFrom: '18:00',
    timeTo: '19:00',
  },
]

const viewingRequests: ViewingRequest[] = [
  {
    id: 'vr1',
    status: 'confirmed',
    message: 'Can we see the basement?',
    cancelledBy: null,
    createdAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
    slot: viewingSlots[0] ?? null,
    listing: {
      id: '1',
      title: listings[0]?.title ?? 'Listing',
      city: listings[0]?.city,
      coverImage: listings[0]?.coverImage,
      pricePerNight: listings[0]?.pricePerNight,
      status: (listings[0] as any)?.status,
    },
    participants: { seekerId: 'tenant-1', landlordId: 'landlord-1' },
  },
  {
    id: 'vr2',
    status: 'requested',
    message: 'Evening slot preferred',
    cancelledBy: null,
    createdAt: new Date().toISOString(),
    slot: viewingSlots[1] ?? null,
    listing: {
      id: '2',
      title: listings[1]?.title ?? 'Listing',
      city: listings[1]?.city,
      coverImage: listings[1]?.coverImage,
      pricePerNight: listings[1]?.pricePerNight,
      status: (listings[1] as any)?.status,
    },
    participants: { seekerId: 'tenant-2', landlordId: 'landlord-2' },
  },
]

const toAppListing = (listingId: string) => {
  const listing = listings.find((l) => l.id === listingId)
  return {
    id: listingId,
    title: listing?.title ?? 'Listing',
    city: listing?.city,
    coverImage: listing?.coverImage ?? listing?.images?.[0],
    pricePerNight: listing?.pricePerNight,
    status: (listing as any)?.status ?? 'active',
  }
}

const applications: Application[] = [
  {
    id: 'app1',
    status: 'submitted',
    message: 'We would love a quiet family stay. Can we check in early?',
    createdAt: '2026-01-05T10:00:00Z',
    listing: toAppListing('1'),
    participants: { seekerId: 'tenant-1', landlordId: 'landlord-1' },
  },
  {
    id: 'app2',
    status: 'accepted',
    message: 'Celebrating anniversary, need late checkout.',
    createdAt: '2026-01-08T12:30:00Z',
    listing: toAppListing('2'),
    participants: { seekerId: 'tenant-1', landlordId: 'landlord-2' },
  },
  {
    id: 'app3',
    status: 'rejected',
    message: 'Workcation with stable Wi-Fi, flexible dates.',
    createdAt: '2026-01-10T09:20:00Z',
    listing: toAppListing('3'),
    participants: { seekerId: 'tenant-2', landlordId: 'landlord-1' },
  },
]

const conversations: Conversation[] = [
  {
    id: 'c1',
    listingId: '1',
    listingTitle: listings[0]?.title ?? 'Listing',
    listingCity: listings[0]?.city,
    listingCoverImage: listings[0]?.coverImage ?? '',
    userName: 'Evelyn Hunt',
    avatarUrl: 'https://i.pravatar.cc/100?img=12',
    lastMessage: 'See you at 4PM for check-in.',
    time: '09:20',
    unreadCount: 2,
    online: true,
  },
  {
    id: 'c2',
    listingId: '2',
    listingTitle: listings[1]?.title ?? 'Listing',
    listingCity: listings[1]?.city,
    listingCoverImage: listings[1]?.coverImage ?? '',
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
    { id: 'm1', conversationId: 'c1', senderId: 'landlord-1', from: 'them', text: 'Welcome to Aurora!', time: '09:15' },
    { id: 'm2', conversationId: 'c1', senderId: 'tenant-1', from: 'me', text: 'Excited to arrive.', time: '09:17' },
  ],
  c2: [
    { id: 'm3', conversationId: 'c2', senderId: 'landlord-2', from: 'them', text: 'Let me know if you need anything.', time: 'Yesterday' },
  ],
}

const moderationReports: Report[] = [
  {
    id: 'rpt-1',
    type: 'rating',
    status: 'open',
    reason: 'abuse',
    details: 'Contains offensive language',
    createdAt: '2026-01-15T10:00:00Z',
    reporter: { id: 'tenant-1', name: 'Tenant One' },
    target: { id: 'rating-1', rating: 1, comment: 'Rude host', listingTitle: listings[0]?.title },
    totalReports: 2,
  },
  {
    id: 'rpt-2',
    type: 'message',
    status: 'open',
    reason: 'spam',
    details: 'Repeated unsolicited offers',
    createdAt: '2026-01-16T12:00:00Z',
    reporter: { id: 'landlord-2', name: 'Landlord Two' },
    target: { id: 'm3', body: 'Check my other listings', listingTitle: listings[1]?.title },
    totalReports: 1,
  },
  {
    id: 'rpt-3',
    type: 'listing',
    status: 'dismissed',
    reason: 'fake',
    details: 'Photos look AI-generated',
    createdAt: '2026-01-12T08:00:00Z',
    reporter: { id: 'tenant-2', name: 'Tenant Two' },
    target: { id: listings[2]?.id, title: listings[2]?.title, city: listings[2]?.city, status: 'active' },
    resolution: 'Verified photos',
    reviewedAt: '2026-01-13T09:00:00Z',
    totalReports: 1,
  },
]

const adminKpiSummary: AdminKpiSummary = {
  listings: { last24h: 3, last7d: 12 },
  applications: { last24h: 8, last7d: 24 },
  messages: { last24h: 45, last7d: 180 },
  ratings: { last24h: 6, last7d: 18 },
  reports: { last24h: 4, last7d: 9 },
  suspiciousUsers: 2,
}

const adminConversion: AdminConversion = {
  browseToApply: { from: 120, to: 30, rate: 0.25 },
  applyToChat: { from: 30, to: 20, rate: 0.67 },
  chatToRating: { from: 20, to: 8, rate: 0.4 },
}

const adminTrends: AdminTrendPoint[] = Array.from({ length: 14 }).map((_, idx) => {
  const date = new Date()
  date.setDate(date.getDate() - (13 - idx))
  return {
    date: date.toISOString().slice(0, 10),
    listings: Math.max(0, Math.floor(Math.random() * 5)),
    applications: Math.max(1, Math.floor(Math.random() * 6)),
    messages: Math.max(5, Math.floor(Math.random() * 30)),
    ratings: Math.max(0, Math.floor(Math.random() * 4)),
    reports: Math.floor(Math.random() * 3),
  }
})

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

const toRadians = (deg: number) => (deg * Math.PI) / 180
const distanceBetween = (lat1: number, lng1: number, lat2: number, lng2: number) => {
  const earthRadiusKm = 6371
  const dLat = toRadians(lat2 - lat1)
  const dLng = toRadians(lng2 - lng1)
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return earthRadiusKm * c
}

const fakeGeocode = (address: string) => {
  const normalized = address.trim().toLowerCase()
  if (!normalized) return null
  const baseLat = 45
  const baseLng = 15
  const spreadKm = 120
  const hashOffset = (seed: string) => {
    const sub = parseInt(hashString(seed).slice(0, 8), 16)
    return (sub / 0xffffffff) * 2 - 1
  }
  const latOffset = hashOffset(normalized + 'lat')
  const lngOffset = hashOffset(normalized + 'lng')
  const kmPerDegreeLat = 111.045
  const kmPerDegreeLng = kmPerDegreeLat * Math.max(Math.cos(toRadians(baseLat)), 0.1)
  return {
    lat: Number((baseLat + (latOffset * (spreadKm / kmPerDegreeLat))).toFixed(6)),
    lng: Number((baseLng + (lngOffset * (spreadKm / kmPerDegreeLng))).toFixed(6)),
  }
}

const hashString = (value: string) => {
  const hex = Array.from(new TextEncoder().encode(value)).map((b) => b.toString(16).padStart(2, '0')).join('')
  return hex.padEnd(32, '0')
}

const priceBuckets = [
  { label: '0-300', min: 0, max: 300 },
  { label: '300-600', min: 300, max: 600 },
  { label: '600-1000', min: 600, max: 1000 },
  { label: '1000+', min: 1000, max: Infinity },
]

const areaBuckets = [
  { label: '0-30', min: 0, max: 30 },
  { label: '30-60', min: 30, max: 60 },
  { label: '60-100', min: 60, max: 100 },
  { label: '100+', min: 100, max: Infinity },
]

const bucketFor = (value: number | undefined, buckets: { label: string; min: number; max: number }[]) => {
  if (value == null) return null
  return buckets.find((bucket) => value >= bucket.min && value < bucket.max)?.label ?? null
}

const applyFilters = (items: Listing[], filters?: Partial<ListingFilters>) => {
  if (!filters) return items.map((item) => ({ ...item }))
  let filtered = items.map((item) => ({ ...item }))
  filtered = filtered.filter((item) => {
    const matchCategory = filters.category && filters.category !== 'all' ? item.category === filters.category : true
    const matchGuests = filters.guests ? item.beds >= filters.guests : true
    const matchPrice = filters.priceRange ? item.pricePerNight >= filters.priceRange[0] && item.pricePerNight <= filters.priceRange[1] : true
    const matchPriceBucket = filters.priceBucket ? bucketFor(item.pricePerNight, priceBuckets) === filters.priceBucket : true
    const matchInstant = filters.instantBook ? item.instantBook : true
    const matchLocation = filters.location
      ? `${item.city} ${item.country}`.toLowerCase().includes(filters.location.toLowerCase())
      : true
    const matchCity = filters.city ? item.city.toLowerCase().includes(filters.city.toLowerCase()) : true
    const matchRooms = filters.rooms ? (item.rooms ?? item.beds) >= filters.rooms : true
    const matchArea = filters.areaRange
      ? (item.area ?? 0) >= (filters.areaRange[0] ?? 0) && (item.area ?? 0) <= (filters.areaRange[1] ?? Infinity)
      : true
    const matchAreaBucket = filters.areaBucket ? bucketFor(item.area ?? 0, areaBuckets) === filters.areaBucket : true
    const matchRating = filters.rating ? item.rating >= filters.rating : true
    const amenities = (filters.amenities?.length ? filters.amenities : filters.facilities) ?? []
    const matchFacilities = amenities.length ? amenities.some((f) => item.facilities?.includes(f)) : true
    const matchStatus = filters.status && filters.status !== 'all' ? item.status === filters.status : true

    return (
      matchCategory &&
      matchGuests &&
      matchPrice &&
      matchPriceBucket &&
      matchInstant &&
      matchLocation &&
      matchCity &&
      matchRooms &&
      matchArea &&
      matchAreaBucket &&
      matchRating &&
      matchFacilities &&
      matchStatus
    )
  })

  if (filters.centerLat != null && filters.centerLng != null) {
    const radius = filters.radiusKm ?? 15
    filtered = filtered
      .map((item) => {
        if (item.lat == null || item.lng == null) return { ...item, distanceKm: undefined }
        const distanceKm = distanceBetween(filters.centerLat!, filters.centerLng!, item.lat, item.lng)
        return { ...item, distanceKm }
      })
      .filter((item) => item.distanceKm != null && item.distanceKm <= radius)
      .sort((a, b) => (a.distanceKm ?? 0) - (b.distanceKm ?? 0))
  }

  return filtered
}

const buildFacets = (items: Listing[]): ListingSearchFacets => {
  const tally = (map: Map<string, number>, value: string | null | undefined) => {
    if (!value) return
    map.set(value, (map.get(value) ?? 0) + 1)
  }

  const city = new Map<string, number>()
  const status = new Map<string, number>()
  const rooms = new Map<string, number>()
  const amenities = new Map<string, number>()
  const priceBucket = new Map<string, number>()
  const areaBucket = new Map<string, number>()

  items.forEach((item) => {
    tally(city, item.city)
    tally(status, item.status)
    tally(rooms, String(item.rooms ?? item.beds ?? ''))
    item.facilities?.forEach((facility) => tally(amenities, facility))
    tally(priceBucket, bucketFor(item.pricePerNight, priceBuckets))
    tally(areaBucket, bucketFor(item.area ?? 0, areaBuckets))
  })

  const mapToArray = (map: Map<string, number>) =>
    Array.from(map.entries()).map(([value, count]) => ({ value, count }))

  return {
    city: mapToArray(city).sort((a, b) => b.count - a.count),
    status: mapToArray(status).sort((a, b) => b.count - a.count),
    rooms: mapToArray(rooms).sort((a, b) => Number(a.value) - Number(b.value)),
    amenities: mapToArray(amenities).sort((a, b) => b.count - a.count),
    price_bucket: mapToArray(priceBucket),
    area_bucket: mapToArray(areaBucket),
  }
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

export async function searchListingsV2(
  query: string,
  filters?: Partial<ListingFilters>,
): Promise<{ items: Listing[]; meta: any; facets: ListingSearchFacets }> {
  const filtered = applyFilters(listings, filters)
  const q = query.trim().toLowerCase()
  const items = q ? filtered.filter((item) => item.title.toLowerCase().includes(q)) : filtered
  return simulate({
    items,
    meta: { page: 1, perPage: items.length, total: items.length, lastPage: 1 },
    facets: buildFacets(items),
  })
}

export async function suggestLocations(query: string, limit = 5): Promise<{ label: string; lat: number; lng: number; type: string }[]> {
  const base = [
    { label: 'Belgrade, Serbia', lat: 44.8125, lng: 20.4612, type: 'city' },
    { label: 'Novi Sad, Serbia', lat: 45.2671, lng: 19.8335, type: 'city' },
    { label: 'Niš, Serbia', lat: 43.3209, lng: 21.8958, type: 'city' },
    { label: 'Zagreb, Croatia', lat: 45.815, lng: 15.9819, type: 'city' },
    { label: 'Split, Croatia', lat: 43.5081, lng: 16.4402, type: 'city' },
  ]

  const normalized = query.trim().toLowerCase()
  if (!normalized) return simulate([])
  const matched = base.filter((item) => item.label.toLowerCase().includes(normalized)).slice(0, limit)
  return simulate(matched)
}

export async function suggestSearch(query: string, limit = 8): Promise<SearchSuggestion[]> {
  const normalized = query.trim().toLowerCase()
  if (!normalized) return simulate([])
  const suggestions: SearchSuggestion[] = [{ label: query, type: 'query', value: query }]
  const cityMatches = listings
    .map((item) => item.city)
    .filter((city) => city.toLowerCase().includes(normalized))
    .slice(0, limit)
    .map((city) => ({ label: city, type: 'city' as const, value: city }))
  const amenityMatches = listings
    .flatMap((item) => item.facilities ?? [])
    .filter((facility) => facility.toLowerCase().includes(normalized))
    .slice(0, limit)
    .map((facility) => ({ label: facility, type: 'amenity' as const, value: facility }))
  const merged = [...suggestions, ...cityMatches, ...amenityMatches]
  const unique = Array.from(new Map(merged.map((item) => [`${item.type}:${item.value}`, item])).values())
  return simulate(unique.slice(0, limit))
}

export async function geocodeLocation(query: string): Promise<{ lat: number; lng: number }> {
  const result = fakeGeocode(query)
  if (!result) throw new Error('Location not found')
  return simulate(result)
}

export async function updateListingLocation(
  listingId: string,
  payload: { latitude: number; longitude: number },
): Promise<Listing> {
  const index = listings.findIndex((item) => item.id === listingId)
  if (index === -1) throw new Error('Listing not found')

  const base = listings[index]!

  listings[index] = {
    ...base,
    lat: payload.latitude,
    lng: payload.longitude,
    locationSource: 'manual',
    locationOverriddenAt: new Date().toISOString(),
    geocodedAt: null,
  }

  return simulate(listings[index]!)
}

export async function resetListingLocation(listingId: string): Promise<Listing> {
  const index = listings.findIndex((item) => item.id === listingId)
  if (index === -1) throw new Error('Listing not found')

  const listing = listings[index]!
  const geocoded = fakeGeocode(`${listing.address ?? ''} ${listing.city ?? ''} ${listing.country ?? ''}`) ?? {
    lat: listing.lat ?? 45,
    lng: listing.lng ?? 15,
  }

  const base = listings[index]!

  listings[index] = {
    ...base,
    lat: geocoded.lat,
    lng: geocoded.lng,
    locationSource: 'geocoded',
    locationOverriddenAt: null,
    geocodedAt: new Date().toISOString(),
  }

  return simulate(listings[index]!)
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

export async function getViewingSlots(listingId: string): Promise<ViewingSlot[]> {
  return simulate(viewingSlots.filter((slot) => slot.listingId === listingId))
}

export async function createViewingSlot(
  listingId: string,
  payload: {
    startsAt: string
    endsAt: string
    capacity?: number
    isActive?: boolean
    pattern?: ViewingSlot['pattern']
    daysOfWeek?: number[]
    timeFrom?: string
    timeTo?: string
  },
): Promise<ViewingSlot> {
  const slot: ViewingSlot = {
    id: makeId(),
    listingId,
    landlordId: String(listings.find((l) => l.id === listingId)?.ownerId ?? 'landlord-1'),
    startsAt: payload.startsAt,
    endsAt: payload.endsAt,
    capacity: payload.capacity ?? 1,
    isActive: payload.isActive ?? true,
    pattern: payload.pattern ?? 'once',
    daysOfWeek: payload.daysOfWeek ?? [],
    timeFrom: payload.timeFrom ?? null,
    timeTo: payload.timeTo ?? null,
  }
  viewingSlots.unshift(slot)
  return simulate(slot)
}

export async function updateViewingSlot(
  slotId: string,
  payload: Partial<Pick<ViewingSlot, 'startsAt' | 'endsAt' | 'capacity' | 'isActive'>>,
): Promise<ViewingSlot> {
  const idx = viewingSlots.findIndex((s) => s.id === slotId)
  if (idx === -1) throw new Error('Slot not found')
  const previous = viewingSlots[idx]!
  const updated: ViewingSlot = {
    ...previous,
    id: previous.id,
    listingId: previous.listingId,
    landlordId: previous.landlordId,
    startsAt: payload.startsAt ?? previous.startsAt,
    endsAt: payload.endsAt ?? previous.endsAt,
    capacity: payload.capacity ?? previous.capacity,
    isActive: payload.isActive ?? previous.isActive,
  }
  viewingSlots[idx] = updated
  viewingRequests.forEach((req) => {
    if (req.slot?.id === slotId) {
      req.slot = { ...req.slot, ...updated }
    }
  })
  return simulate(updated)
}

export async function deleteViewingSlot(slotId: string): Promise<void> {
  const hasActive = viewingRequests.some(
    (req) => req.slot?.id === slotId && ['requested', 'confirmed'].includes(req.status),
  )
  if (hasActive) {
    throw new Error('Cannot delete slot with active requests')
  }
  const idx = viewingSlots.findIndex((s) => s.id === slotId)
  if (idx >= 0) {
    viewingSlots.splice(idx, 1)
  }
  return simulate(undefined)
}

const cloneSlot = (slot: ViewingSlot | null): ViewingSlot | null => (slot ? { ...slot } : null)
const makeListingSummary = (listingId: string) => {
  const listing = listings.find((l) => l.id === listingId)
  return {
    id: listingId,
    title: listing?.title ?? 'Listing',
    city: listing?.city,
    coverImage: listing?.coverImage ?? listing?.images?.[0],
    pricePerNight: listing?.pricePerNight,
    status: (listing as any)?.status,
  }
}

export async function requestViewingSlot(
  slotId: string,
  message?: string,
  scheduledAt?: string,
  seekerId?: string,
): Promise<ViewingRequest> {
  const slot = viewingSlots.find((s) => s.id === slotId)
  if (!slot || !slot.isActive) {
    throw new Error('Viewing slot unavailable')
  }
  const activeCount = viewingRequests.filter(
    (req) => req.slot?.id === slotId && ['requested', 'confirmed'].includes(req.status),
  ).length
  if (activeCount >= (slot.capacity ?? 1)) {
    throw new Error('Slot already booked')
  }
  const request: ViewingRequest = {
    id: makeId(),
    status: 'requested',
    message: message ?? '',
    cancelledBy: null,
    createdAt: new Date().toISOString(),
    scheduledAt: scheduledAt ?? null,
    slot: cloneSlot(slot) as ViewingSlot,
    listing: makeListingSummary(slot.listingId),
    participants: {
      seekerId: seekerId ?? 'tenant-1',
      landlordId: slot.landlordId,
    },
  }
  viewingRequests.unshift(request)
  return simulate(request)
}

export async function getViewingRequestsForSeeker(seekerId?: string): Promise<ViewingRequest[]> {
  const id = seekerId ?? 'tenant-1'
  return simulate(viewingRequests.filter((req) => req.participants.seekerId === id))
}

export async function getViewingRequestsForLandlord(listingId?: string, landlordId?: string): Promise<ViewingRequest[]> {
  const id = landlordId ?? 'landlord-1'
  const filtered = viewingRequests.filter((req) => req.participants.landlordId === id)
  const narrowed = listingId ? filtered.filter((req) => req.listing?.id === listingId) : filtered
  return simulate(narrowed)
}

const updateViewingRequestStatus = async (
  id: string,
  status: ViewingRequest['status'],
  cancelledBy?: ViewingRequest['cancelledBy'],
): Promise<ViewingRequest> => {
  const idx = viewingRequests.findIndex((req) => req.id === id)
  if (idx === -1) throw new Error('Viewing not found')
  const previous = viewingRequests[idx]
  if (!previous) throw new Error('Viewing not found')
  const updated: ViewingRequest = {
    ...previous,
    status,
    cancelledBy: cancelledBy ?? previous.cancelledBy ?? null,
    id: previous.id,
    slot: previous.slot ?? null,
    listing: previous.listing ?? null,
    participants: previous.participants,
  }
  viewingRequests[idx] = updated
  return simulate(updated)
}

export async function confirmViewingRequest(id: string): Promise<ViewingRequest> {
  return updateViewingRequestStatus(id, 'confirmed')
}

export async function rejectViewingRequest(id: string): Promise<ViewingRequest> {
  return updateViewingRequestStatus(id, 'rejected')
}

export async function cancelViewingRequest(id: string, cancelledBy?: ViewingRequest['cancelledBy']): Promise<ViewingRequest> {
  return updateViewingRequestStatus(id, 'cancelled', cancelledBy ?? 'seeker')
}

export async function downloadViewingRequestIcs(id: string): Promise<Blob> {
  const req = viewingRequests.find((r) => r.id === id)
  const text = `BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//IzdajIznajmi//Mock//EN
BEGIN:VEVENT
SUMMARY:Viewing ${req?.listing?.title ?? ''}
DTSTART:${req?.slot?.startsAt ?? ''}
DTEND:${req?.slot?.endsAt ?? ''}
END:VEVENT
END:VCALENDAR`
  return simulate(new Blob([text], { type: 'text/calendar' }))
}

export async function getConversations(): Promise<Conversation[]> {
  return simulate(conversations)
}

export async function getConversationById(conversationId: string): Promise<Conversation> {
  const found = conversations.find((c) => c.id === conversationId)
  if (!found) throw new Error('Conversation not found')
  return simulate(found)
}

export async function getMessages(conversationId: string): Promise<Message[]> {
  return simulate(messages[conversationId] ?? [])
}

export async function getConversationForListing(listingId: string, _seekerId?: string): Promise<Conversation> {
  let conversation = conversations.find((c) => c.listingId === listingId)
  if (!conversation) {
    const listing = listings.find((l) => l.id === listingId)
    conversation = {
      id: makeId(),
      listingId,
      listingTitle: listing?.title ?? 'Listing',
      listingCity: listing?.city,
      listingCoverImage: listing?.coverImage ?? '',
      userName: listing?.ownerId ? `Landlord ${listing.ownerId}` : 'Host',
      avatarUrl: '',
      lastMessage: 'Start chatting',
      time: new Date().toISOString(),
      unreadCount: 0,
      online: false,
    }
    conversations.unshift(conversation)
    messages[conversation.id] = []
  }

  return simulate(conversation)
}

export async function getMessagesForListing(listingId: string): Promise<Message[]> {
  const convo = conversations.find((c) => c.listingId === listingId)
  if (!convo) return simulate([])
  return getMessages(convo.id)
}

export async function sendMessageToListing(
  listingId: string,
  message: string,
  attachments?: File[],
): Promise<Message> {
  const convo = (await getConversationForListing(listingId)) as Conversation
  return sendMessageToConversation(convo.id, message, attachments)
}

export async function sendMessageToConversation(
  conversationId: string,
  message: string,
  attachments?: File[],
): Promise<Message> {
  await delay()
  const mappedAttachments =
    attachments?.map((file) => {
      const isImage = file.type.startsWith('image/')
      const kind: 'image' | 'document' = isImage ? 'image' : 'document'
      return {
        id: makeId(),
        kind,
        originalName: file.name,
        mimeType: file.type,
        sizeBytes: file.size,
        url: URL.createObjectURL(file),
        thumbUrl: isImage ? URL.createObjectURL(file) : null,
      }
    }) ?? []
  const msg: Message = {
    id: makeId(),
    conversationId,
    senderId: 'mock-seeker',
    from: 'me',
    text: message,
    time: new Date().toISOString(),
    attachments: mappedAttachments,
  }
  messages[conversationId] = [...(messages[conversationId] ?? []), msg]
  const convo = conversations.find((c) => c.id === conversationId)
  if (convo) {
    convo.lastMessage = message?.trim() ? message : mappedAttachments.length ? 'Sent an attachment' : ''
    convo.time = msg.time
    convo.unreadCount = 0
  }
  return JSON.parse(JSON.stringify(msg))
}

export async function markConversationRead(_conversationId: string): Promise<void> {
  await delay()
}

export async function getOrCreateConversationForApplication(applicationId: string): Promise<Conversation> {
  await delay()
  const app = applications.find((a) => a.id === applicationId)
  if (!app) throw new Error('Application not found')
  let convo = conversations.find(
    (c) => c.listingId === app.listing.id && c.userName === app.participants.seekerId.toString(),
  )
  if (!convo) {
    convo = {
      id: makeId(),
      listingId: app.listing.id,
      listingTitle: app.listing.title,
      listingCity: app.listing.city,
      listingCoverImage: app.listing.coverImage,
      userName: `Seeker ${app.participants.seekerId}`,
      avatarUrl: '',
      lastMessage: 'Start chatting',
      time: new Date().toISOString(),
      unreadCount: 0,
      online: false,
    }
    conversations.unshift(convo)
    messages[convo.id] = []
  }
  return JSON.parse(JSON.stringify(convo))
}

export async function getPublicProfile(userId: string): Promise<PublicProfile> {
  await delay()
  const profile: PublicProfile = {
    id: userId,
    fullName: `Landlord ${userId}`,
    joinedAt: new Date().toISOString(),
    verifications: { email: true, phone: false, address: false },
    ratingStats: { average: 0, total: 0, breakdown: {} },
    recentRatings: [],
  }
  return JSON.parse(JSON.stringify(profile))
}

const mapRating = (data: any): Rating => ({
  id: String(data.id),
  listingId: String(data.listingId ?? ''),
  rating: Number(data.rating ?? 0),
  comment: data.comment ?? null,
  createdAt: data.createdAt ?? new Date().toISOString(),
  rater: data.rater,
  rateeId: data.rateeId,
  listing: data.listing,
  reportCount: data.reportCount ?? 0,
})

export async function leaveRating(
  listingId: string,
  rateeUserId: string | number,
  payload: { rating: number; comment?: string },
) {
  await delay()
  const rating = mapRating({
    id: makeId(),
    listingId,
    rating: payload.rating,
    comment: payload.comment,
    createdAt: new Date().toISOString(),
    rater: { id: 'mock-seeker', name: 'Mock Seeker' },
    rateeId: rateeUserId,
  })
  return rating
}

export async function getUserRatings(_userId: string): Promise<Rating[]> {
  await delay()
  return []
}

export async function reportRating(_ratingId: string, _reason: string, _details?: string) {
  await delay()
  return null
}

export async function getAdminRatings(): Promise<Rating[]> {
  await delay()
  return []
}

export async function deleteAdminRating(_ratingId: string) {
  await delay()
}

export async function flagUserSuspicious(_userId: string | number, _isSuspicious: boolean) {
  await delay()
  return { isSuspicious: _isSuspicious }
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
  rooms?: number
  area?: number
  images?: string[]
  description?: string
  lat?: number
  lng?: number
  facilities?: string[]
  instantBook?: boolean
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
    rooms: (payload as any).rooms ?? payload.beds,
    area: (payload as any).area ?? 90,
    category: payload.category,
    isFavorite: false,
    instantBook: payload.instantBook ?? true,
    facilities: payload.facilities ?? [],
    ownerId: payload.ownerId,
    createdAt: new Date().toISOString(),
    status: 'draft',
    warnings: [],
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
    rooms: (payload as any).rooms ?? current.rooms,
    area: (payload as any).area ?? current.area,
  }
  listings[index] = updated
  return JSON.parse(JSON.stringify(updated))
}

export async function publishListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'active'
  return JSON.parse(JSON.stringify(found))
}

export async function unpublishListing(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'paused'
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

export async function markListingRented(id: string): Promise<Listing | null> {
  await delay()
  const found = listings.find((l) => l.id === id)
  if (!found) return null
  ;(found as any).status = 'rented'
  return JSON.parse(JSON.stringify(found))
}

export async function markListingAvailable(id: string): Promise<Listing | null> {
  return publishListing(id)
}

export async function applyToListing(listingId: string, message?: string | null): Promise<Application> {
  await delay()
  maybeFail()
  const listing = listings.find((l) => l.id === listingId)
  const application: Application = {
    id: makeId(),
    status: 'submitted',
    message: message ?? '',
    createdAt: new Date().toISOString(),
    listing: toAppListing(listingId),
    participants: {
      seekerId: 'mock-seeker',
      landlordId: (listing as any)?.ownerId ?? 'mock-landlord',
    },
  }
  applications.unshift(application)
  return JSON.parse(JSON.stringify(application))
}

export async function getApplicationsForSeeker(): Promise<Application[]> {
  return simulate(applications)
}

export async function getApplicationsForLandlord(listingId?: string): Promise<Application[]> {
  const filtered = listingId ? applications.filter((a) => a.listing.id === listingId) : applications
  return simulate(filtered)
}

export async function updateApplicationStatus(id: string, status: Application['status']): Promise<Application | null> {
  await delay()
  maybeFail()
  const index = applications.findIndex((a) => a.id === id)
  if (index === -1) return null
  const current = applications[index]!
  applications[index] = { ...current, status }
  return JSON.parse(JSON.stringify(applications[index]))
}

export async function getAdminReports(params?: {
  type?: 'rating' | 'message' | 'listing'
  status?: 'open' | 'resolved' | 'dismissed'
  q?: string
}): Promise<Report[]> {
  await delay()
  let list = [...moderationReports]
  if (params?.type) list = list.filter((r) => r.type === params.type)
  if (params?.status) list = list.filter((r) => r.status === params.status)
  if (params?.q) {
    const q = params.q.toLowerCase()
    list = list.filter((r) => r.reason.toLowerCase().includes(q) || (r.details ?? '').toLowerCase().includes(q))
  }
  return JSON.parse(JSON.stringify(list))
}

export async function getAdminReport(id: string): Promise<Report> {
  await delay()
  const found = moderationReports.find((r) => r.id === id)
  if (!found) throw new Error('Not found')
  return JSON.parse(JSON.stringify(found))
}

export async function updateAdminReport(
  id: string,
  payload: { action: 'dismiss' | 'resolve'; resolution?: string; deleteTarget?: boolean; flagUserId?: string | number },
): Promise<Report> {
  await delay()
  const index = moderationReports.findIndex((r) => r.id === id)
  if (index === -1) throw new Error('Not found')
  const current = moderationReports[index]!
  const updated: Report = {
    ...current,
    status: payload.action === 'dismiss' ? 'dismissed' : 'resolved',
    resolution: payload.resolution ?? payload.action,
    reviewedAt: new Date().toISOString(),
  }
  moderationReports[index] = updated
  return JSON.parse(JSON.stringify(updated))
}

export async function getAdminKpiSummary(): Promise<AdminKpiSummary> {
  return simulate(adminKpiSummary)
}

export async function getAdminKpiConversion(): Promise<AdminConversion> {
  return simulate(adminConversion)
}

export async function getAdminKpiTrends(range: '7d' | '30d' = '7d'): Promise<AdminTrendPoint[]> {
  await delay()
  const slice = range === '30d' ? adminTrends.concat(adminTrends).slice(-30) : adminTrends.slice(-7)
  return JSON.parse(JSON.stringify(slice))
}

export async function startImpersonation(userId: string | number) {
  await delay()
  return {
    user: { id: userId, name: `Impersonated ${userId}`, role: 'seeker', roles: ['seeker'] },
    impersonating: true,
    impersonator: { id: 'admin-1', name: 'Admin', role: 'admin', roles: ['admin'] },
  }
}

export async function stopImpersonation() {
  await delay()
  return {
    user: { id: 'admin-1', name: 'Admin', role: 'admin', roles: ['admin'] },
    impersonating: false,
  }
}

export async function getSavedSearches(): Promise<SavedSearch[]> {
  await delay()
  return JSON.parse(JSON.stringify(savedSearches))
}

export async function createSavedSearch(payload: {
  name?: string | null
  filters: Record<string, any>
  alertsEnabled?: boolean
  frequency?: SavedSearch['frequency']
}): Promise<SavedSearch> {
  await delay()
  const now = new Date().toISOString()
  const saved: SavedSearch = {
    id: makeId(),
    name: payload.name ?? null,
    filters: payload.filters ?? {},
    alertsEnabled: payload.alertsEnabled ?? true,
    frequency: payload.frequency ?? 'instant',
    lastAlertedAt: null,
    createdAt: now,
    updatedAt: now,
  }
  savedSearches.unshift(saved)
  return JSON.parse(JSON.stringify(saved))
}

export async function updateSavedSearch(
  id: string,
  payload: {
    name?: string | null
    filters?: Record<string, any>
    alertsEnabled?: boolean
    frequency?: SavedSearch['frequency']
  },
): Promise<SavedSearch> {
  await delay()
  const index = savedSearches.findIndex((item) => item.id === id)
  if (index === -1) throw new Error('Not found')
  const current = savedSearches[index]!
  const updated: SavedSearch = {
    ...current,
    name: payload.name !== undefined ? payload.name : current.name,
    filters: payload.filters !== undefined ? payload.filters : current.filters,
    alertsEnabled: payload.alertsEnabled !== undefined ? payload.alertsEnabled : current.alertsEnabled,
    frequency: payload.frequency !== undefined ? payload.frequency : current.frequency,
    updatedAt: new Date().toISOString(),
  }
  savedSearches[index] = updated
  return JSON.parse(JSON.stringify(updated))
}

export async function deleteSavedSearch(id: string): Promise<void> {
  await delay()
  const index = savedSearches.findIndex((item) => item.id === id)
  if (index >= 0) {
    savedSearches.splice(index, 1)
  }
}

export async function setTypingStatus(_conversationId: string, _isTyping: boolean): Promise<void> {
  await delay()
}

export async function getTypingStatus(
  _conversationId: string,
): Promise<{ users: Array<{ id: string; name: string; expiresIn: number }>; ttlSeconds: number }> {
  await delay()
  return { users: [], ttlSeconds: 8 }
}

export async function pingPresence(): Promise<void> {
  await delay()
}

export async function getUserPresence(
  userId: string,
): Promise<{ userId: string; online: boolean; expiresIn: number }> {
  await delay()
  return { userId, online: true, expiresIn: 60 }
}
