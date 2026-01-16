import * as mockApi from './mockApi'
import * as realApi from './realApi'

const useMock = (import.meta.env.VITE_USE_MOCK_API ?? 'true') !== 'false'
const api = useMock ? mockApi : realApi

const pick = <T extends keyof typeof mockApi>(key: T) => {
  const realFn = (api as any)[key]
  if (typeof realFn === 'function') return realFn
  return (mockApi as any)[key]
}

export const getPopularListings = pick('getPopularListings')
export const getRecommendedListings = pick('getRecommendedListings')
export const searchListings = pick('searchListings')
export const getListingById = pick('getListingById')
export const getListingFacilities = pick('getListingFacilities')
export const getListingReviews = pick('getListingReviews')
export const getFavorites = pick('getFavorites')
export const getBookings = pick('getBookings')
export const getLandlordListings = pick('getLandlordListings')
export const createListing = pick('createListing')
export const updateListing = pick('updateListing')
export const createBookingRequest = pick('createBookingRequest')
export const getBookingRequestsForTenant = pick('getBookingRequestsForTenant')
export const getBookingRequestsForLandlord = pick('getBookingRequestsForLandlord')
export const updateBookingRequestStatus = pick('updateBookingRequestStatus')
export const getConversations = pick('getConversations')
export const getMessages = pick('getMessages')

export const isMockApi = useMock
