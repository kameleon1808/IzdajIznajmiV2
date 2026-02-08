import { createRouter, createWebHistory } from 'vue-router'
import Bookings from '../pages/Bookings.vue'
import Chat from '../pages/Chat.vue'
import ChatDeepLink from '../pages/ChatDeepLink.vue'
import Facilities from '../pages/Facilities.vue'
import Favorites from '../pages/Favorites.vue'
import Home from '../pages/Home.vue'
import LandlordListings from '../pages/LandlordListings.vue'
import ListingDetail from '../pages/ListingDetail.vue'
import ListingForm from '../pages/ListingForm.vue'
import MapPage from '../pages/Map.vue'
import Messages from '../pages/Messages.vue'
import Profile from '../pages/Profile.vue'
import PublicProfile from '../pages/PublicProfile.vue'
import AdminRatings from '../pages/AdminRatings.vue'
import AdminDashboard from '../pages/AdminDashboard.vue'
import AdminModeration from '../pages/AdminModeration.vue'
import AdminKyc from '../pages/AdminKyc.vue'
import AdminTransactions from '../pages/AdminTransactions.vue'
import AdminTransactionDetail from '../pages/AdminTransactionDetail.vue'
import AdminUserSecurity from '../pages/AdminUserSecurity.vue'
import AdminUsers from '../pages/AdminUsers.vue'
import Reviews from '../pages/Reviews.vue'
import Search from '../pages/Search.vue'
import SavedSearches from '../pages/SavedSearches.vue'
import SettingsLanguage from '../pages/SettingsLanguage.vue'
import SettingsLegal from '../pages/SettingsLegal.vue'
import SettingsPersonalInfo from '../pages/SettingsPersonalInfo.vue'
import SettingsNotifications from '../pages/SettingsNotifications.vue'
import SettingsSecurity from '../pages/SettingsSecurity.vue'
import Notifications from '../pages/Notifications.vue'
import { useAuthStore, type Role } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import Login from '../pages/Login.vue'
import Register from '../pages/Register.vue'
import KycVerification from '../pages/KycVerification.vue'
import TransactionDetail from '../pages/TransactionDetail.vue'
import Transactions from '../pages/Transactions.vue'

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
      path: '/saved-searches',
      name: 'saved-searches',
      component: SavedSearches,
      meta: { topBar: { type: 'title', title: 'Saved Searches' }, showTabs: true, roles: ['seeker'] },
    },
    {
      path: '/bookings',
      name: 'bookings',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'My Booking' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/viewings',
      name: 'viewings',
      redirect: { path: '/bookings', query: { tab: 'viewings' } },
      meta: { topBar: { type: 'title', title: 'Viewings' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/applications',
      name: 'applications',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'Applications' }, showTabs: true, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/landlord/applications',
      name: 'landlord-applications',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'Applications' }, showTabs: true, roles: ['landlord', 'admin'] },
    },
    {
      path: '/messages',
      name: 'messages',
      component: Messages,
      meta: { topBar: { type: 'title', title: 'Messages' }, showTabs: true, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/chat',
      name: 'chat-entry',
      component: ChatDeepLink,
      meta: { topBar: { type: 'title', title: 'Chat' }, showTabs: false, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/chat/:id',
      name: 'chat',
      component: Chat,
      meta: { topBar: { type: 'chat' }, showTabs: false, contentClass: 'p-0 pb-20', roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/messages/:id',
      redirect: (to) => ({ path: `/chat/${to.params.id}`, query: to.query }),
    },
    {
      path: '/transactions/:id',
      name: 'transaction-detail',
      component: TransactionDetail,
      meta: { topBar: { type: 'back', title: 'Transaction' }, showTabs: false, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/transactions',
      name: 'transactions',
      component: Transactions,
      meta: { topBar: { type: 'title', title: 'Transactions' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    { path: '/profile', name: 'profile', component: Profile, meta: { topBar: { type: 'title', title: 'Profile' }, showTabs: true } },
    {
      path: '/profile/verification',
      name: 'profile-verification',
      component: KycVerification,
      meta: { topBar: { type: 'back', title: 'Verification' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/users/:id',
      name: 'public-profile',
      component: PublicProfile,
      meta: { topBar: { type: 'back', title: 'Profile' }, showTabs: false },
    },
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
      path: '/settings/security',
      name: 'settings-security',
      component: SettingsSecurity,
      meta: { topBar: { type: 'back', title: 'Security' }, showTabs: false },
    },
    {
      path: '/settings/notifications',
      name: 'settings-notifications',
      component: SettingsNotifications,
      meta: { topBar: { type: 'back', title: 'Notifications' }, showTabs: false },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: Notifications,
      meta: { topBar: null, showTabs: false },
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
    {
      path: '/admin/ratings',
      name: 'admin-ratings',
      component: AdminRatings,
      meta: { topBar: { type: 'title', title: 'Ratings Admin' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: AdminDashboard,
      meta: { topBar: { type: 'title', title: 'Admin Ops' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/moderation',
      name: 'admin-moderation',
      component: AdminModeration,
      meta: { topBar: { type: 'title', title: 'Moderation' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/kyc',
      name: 'admin-kyc',
      component: AdminKyc,
      meta: { topBar: { type: 'title', title: 'KYC Review' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/transactions',
      name: 'admin-transactions',
      component: AdminTransactions,
      meta: { topBar: { type: 'title', title: 'Transactions' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/transactions/:id',
      name: 'admin-transaction-detail',
      component: AdminTransactionDetail,
      meta: { topBar: { type: 'back', title: 'Transaction Detail' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: AdminUsers,
      meta: { topBar: { type: 'title', title: 'Users' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/users/:id',
      name: 'admin-user-security',
      component: AdminUserSecurity,
      meta: { topBar: { type: 'back', title: 'User Security' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/moderation/reports/:id',
      name: 'admin-report-detail',
      component: () => import('../pages/AdminReportDetail.vue'),
      meta: { topBar: { type: 'back', title: 'Report Detail' }, showTabs: false, roles: ['admin'] },
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

  if (to.path === '/applications' && to.query.role === 'landlord') {
    const { role, ...rest } = to.query
    return next({ path: '/landlord/applications', query: rest })
  }

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
