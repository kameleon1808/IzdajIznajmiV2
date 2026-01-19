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
export const applyToListing = pick('applyToListing')
export const getApplicationsForSeeker = pick('getApplicationsForSeeker')
export const getApplicationsForLandlord = pick('getApplicationsForLandlord')
export const updateApplicationStatus = pick('updateApplicationStatus')
export const getConversations = pick('getConversations')
export const getConversationForListing = pick('getConversationForListing')
export const getMessages = pick('getMessages')
export const getMessagesForListing = pick('getMessagesForListing')
export const sendMessageToListing = pick('sendMessageToListing')
export const sendMessageToConversation = pick('sendMessageToConversation')
export const markConversationRead = pick('markConversationRead')
export const getOrCreateConversationForApplication = pick('getOrCreateConversationForApplication')
export const getPublicProfile = pick('getPublicProfile')
export const publishListing = pick('publishListing')
export const unpublishListing = pick('unpublishListing')
export const archiveListing = pick('archiveListing')
export const restoreListing = pick('restoreListing')
export const markListingRented = pick('markListingRented')
export const markListingAvailable = pick('markListingAvailable')

export const isMockApi = useMock
