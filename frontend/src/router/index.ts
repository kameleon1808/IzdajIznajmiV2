import { createRouter, createWebHistory } from 'vue-router'
import Bookings from '../pages/Bookings.vue'
import Chat from '../pages/Chat.vue'
import Facilities from '../pages/Facilities.vue'
import Favorites from '../pages/Favorites.vue'
import Home from '../pages/Home.vue'
import LandlordListings from '../pages/LandlordListings.vue'
import ListingDetail from '../pages/ListingDetail.vue'
import ListingForm from '../pages/ListingForm.vue'
import MapPage from '../pages/Map.vue'
import Messages from '../pages/Messages.vue'
import Profile from '../pages/Profile.vue'
import Reviews from '../pages/Reviews.vue'
import Search from '../pages/Search.vue'
import SettingsLanguage from '../pages/SettingsLanguage.vue'
import SettingsLegal from '../pages/SettingsLegal.vue'
import SettingsPersonalInfo from '../pages/SettingsPersonalInfo.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import Login from '../pages/Login.vue'
import Register from '../pages/Register.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: Home, meta: { topBar: { type: 'home' }, showTabs: true } },
    { path: '/search', name: 'search', component: Search, meta: { topBar: { type: 'search' }, showTabs: true } },
    { path: '/map', name: 'map', component: MapPage, meta: { topBar: null, showTabs: true, contentClass: 'p-0 pb-24' } },
    {
      path: '/listing/:id',
      name: 'listing-detail',
      component: ListingDetail,
      meta: { topBar: { type: 'detail' }, showTabs: false, contentClass: 'p-0 pb-28' },
    },
    {
      path: '/listing/:id/facilities',
      name: 'listing-facilities',
      component: Facilities,
      meta: { topBar: { type: 'back', title: 'Facilities' }, showTabs: false },
    },
    {
      path: '/listing/:id/reviews',
      name: 'listing-reviews',
      component: Reviews,
      meta: { topBar: { type: 'back', title: 'Reviews' }, showTabs: false },
    },
    {
      path: '/favorites',
      name: 'favorites',
      component: Favorites,
      meta: { topBar: { type: 'title', title: 'My Favorite' }, showTabs: true, roles: ['seeker'] },
    },
    {
      path: '/bookings',
      name: 'bookings',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'My Booking' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/messages',
      name: 'messages',
      component: Messages,
      meta: { topBar: { type: 'title', title: 'Messages' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/messages/:id',
      name: 'chat',
      component: Chat,
      meta: { topBar: { type: 'chat' }, showTabs: false, contentClass: 'p-0 pb-20', roles: ['seeker', 'landlord'] },
    },
    { path: '/profile', name: 'profile', component: Profile, meta: { topBar: { type: 'title', title: 'Profile' }, showTabs: true } },
    {
      path: '/settings/personal',
      name: 'settings-personal',
      component: SettingsPersonalInfo,
      meta: { topBar: { type: 'back', title: 'Personal Info' }, showTabs: false },
    },
    {
      path: '/settings/legal',
      name: 'settings-legal',
      component: SettingsLegal,
      meta: { topBar: { type: 'back', title: 'Legal & Policies' }, showTabs: false },
    },
    {
      path: '/settings/language',
      name: 'settings-language',
      component: SettingsLanguage,
      meta: { topBar: { type: 'back', title: 'Language' }, showTabs: false },
    },
    {
      path: '/landlord/listings',
      name: 'landlord-listings',
      component: LandlordListings,
      meta: { topBar: { type: 'title', title: 'My Listings' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/landlord/listings/new',
      name: 'landlord-listing-new',
      component: ListingForm,
      meta: { topBar: { type: 'back', title: 'New Listing' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/landlord/listings/:id/edit',
      name: 'landlord-listing-edit',
      component: ListingForm,
      meta: { topBar: { type: 'back', title: 'Edit Listing' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    { path: '/login', name: 'login', component: Login, meta: { topBar: { type: 'title', title: 'Login' }, showTabs: false } },
    {
      path: '/register',
      name: 'register',
      component: Register,
      meta: { topBar: { type: 'title', title: 'Register' }, showTabs: false },
    },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to, _from, next) => {
  const auth = useAuthStore()
  const toast = useToastStore()
  const allowedRoles = (to.meta.roles as Role[] | undefined) || undefined

  await auth.initialize()

  if (allowedRoles) {
    if (!auth.isAuthenticated && !auth.isMockMode) {
      return next({ path: '/login', query: { returnUrl: to.fullPath } })
    }
    if (!allowedRoles.some((role) => auth.hasRole(role))) {
      toast.push({ title: 'Access denied', message: 'Switch role to continue.', type: 'error' })
      return next('/')
    }
  }
  next()
})

export default router
