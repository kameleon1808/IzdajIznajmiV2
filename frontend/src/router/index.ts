import { createRouter, createWebHistory } from 'vue-router'
import Bookings from '../pages/Bookings.vue'
import Chat from '../pages/Chat.vue'
import ChatDeepLink from '../pages/ChatDeepLink.vue'
import Facilities from '../pages/Facilities.vue'
import Favorites from '../pages/Favorites.vue'
import Home from '../pages/Home.vue'
import HomeSidebarCard from '../components/home/HomeSidebarCard.vue'
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
import { useLanguageStore } from '../stores/language'
import { useToastStore } from '../stores/toast'
import Login from '../pages/Login.vue'
import Register from '../pages/Register.vue'
import KycVerification from '../pages/KycVerification.vue'
import TransactionDetail from '../pages/TransactionDetail.vue'
import Transactions from '../pages/Transactions.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: Home,
      meta: { topBar: { type: 'home' }, showTabs: true, shellClass: 'lg:max-w-7xl lg:px-6', sidebar: HomeSidebarCard },
    },
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
      meta: { topBar: { type: 'back', title: 'Facilities', titleKey: 'titles.facilities' }, showTabs: false },
    },
    {
      path: '/listing/:id/reviews',
      name: 'listing-reviews',
      component: Reviews,
      meta: { topBar: { type: 'back', title: 'Reviews', titleKey: 'titles.reviews' }, showTabs: false },
    },
    {
      path: '/favorites',
      name: 'favorites',
      component: Favorites,
      meta: { topBar: { type: 'title', title: 'My Favorite', titleKey: 'titles.favorites' }, showTabs: true, roles: ['seeker'] },
    },
    {
      path: '/saved-searches',
      name: 'saved-searches',
      component: SavedSearches,
      meta: { topBar: { type: 'title', title: 'Saved Searches', titleKey: 'titles.savedSearches' }, showTabs: true, roles: ['seeker'] },
    },
    {
      path: '/bookings',
      name: 'bookings',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'My Booking', titleKey: 'nav.bookings' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/viewings',
      name: 'viewings',
      redirect: { path: '/bookings', query: { tab: 'viewings' } },
      meta: { topBar: { type: 'title', title: 'Viewings', titleKey: 'titles.viewings' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    {
      path: '/applications',
      name: 'applications',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'Applications', titleKey: 'titles.applications' }, showTabs: true, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/landlord/applications',
      name: 'landlord-applications',
      component: Bookings,
      meta: { topBar: { type: 'title', title: 'Applications', titleKey: 'titles.applications' }, showTabs: true, roles: ['landlord', 'admin'] },
    },
    {
      path: '/messages',
      name: 'messages',
      component: Messages,
      meta: { topBar: { type: 'title', title: 'Messages', titleKey: 'nav.messages' }, showTabs: true, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/chat',
      name: 'chat-entry',
      component: ChatDeepLink,
      meta: { topBar: { type: 'title', title: 'Chat', titleKey: 'titles.chat' }, showTabs: false, roles: ['seeker', 'landlord', 'admin'] },
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
      meta: { topBar: { type: 'back', title: 'Transaction', titleKey: 'titles.transaction' }, showTabs: false, roles: ['seeker', 'landlord', 'admin'] },
    },
    {
      path: '/transactions',
      name: 'transactions',
      component: Transactions,
      meta: { topBar: { type: 'title', title: 'Transactions', titleKey: 'titles.transactions' }, showTabs: true, roles: ['seeker', 'landlord'] },
    },
    { path: '/profile', name: 'profile', component: Profile, meta: { topBar: { type: 'title', title: 'Profile', titleKey: 'titles.profile' }, showTabs: true } },
    {
      path: '/profile/verification',
      name: 'profile-verification',
      component: KycVerification,
      meta: { topBar: { type: 'back', title: 'Verification', titleKey: 'titles.verification' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/users/:id',
      name: 'public-profile',
      component: PublicProfile,
      meta: { topBar: { type: 'back', title: 'Profile', titleKey: 'titles.profile' }, showTabs: false },
    },
    {
      path: '/settings/personal',
      name: 'settings-personal',
      component: SettingsPersonalInfo,
      meta: { topBar: { type: 'back', title: 'Personal Info', titleKey: 'titles.personalInfo' }, showTabs: false },
    },
    {
      path: '/settings/legal',
      name: 'settings-legal',
      component: SettingsLegal,
      meta: { topBar: { type: 'back', title: 'Legal & Policies', titleKey: 'titles.legalPolicies' }, showTabs: false },
    },
    {
      path: '/settings/language',
      name: 'settings-language',
      component: SettingsLanguage,
      meta: { topBar: { type: 'back', title: 'Language', titleKey: 'titles.language' }, showTabs: false },
    },
    {
      path: '/settings/security',
      name: 'settings-security',
      component: SettingsSecurity,
      meta: { topBar: { type: 'back', title: 'Security', titleKey: 'titles.security' }, showTabs: false },
    },
    {
      path: '/settings/notifications',
      name: 'settings-notifications',
      component: SettingsNotifications,
      meta: { topBar: { type: 'back', title: 'Notifications', titleKey: 'titles.notifications' }, showTabs: false },
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
      meta: { topBar: { type: 'title', title: 'My Listings', titleKey: 'titles.myListings' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/landlord/listings/new',
      name: 'landlord-listing-new',
      component: ListingForm,
      meta: { topBar: { type: 'back', title: 'New Listing', titleKey: 'titles.newListing' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/landlord/listings/:id/edit',
      name: 'landlord-listing-edit',
      component: ListingForm,
      meta: { topBar: { type: 'back', title: 'Edit Listing', titleKey: 'titles.editListing' }, showTabs: false, roles: ['landlord', 'admin'] },
    },
    {
      path: '/admin/ratings',
      name: 'admin-ratings',
      component: AdminRatings,
      meta: { topBar: { type: 'title', title: 'Ratings Admin', titleKey: 'titles.ratingsAdmin' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: AdminDashboard,
      meta: { topBar: { type: 'title', title: 'Admin Ops', titleKey: 'titles.adminOps' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/moderation',
      name: 'admin-moderation',
      component: AdminModeration,
      meta: { topBar: { type: 'title', title: 'Moderation', titleKey: 'titles.moderation' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/kyc',
      name: 'admin-kyc',
      component: AdminKyc,
      meta: { topBar: { type: 'title', title: 'KYC Review', titleKey: 'titles.kycReview' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/transactions',
      name: 'admin-transactions',
      component: AdminTransactions,
      meta: { topBar: { type: 'title', title: 'Transactions', titleKey: 'titles.transactions' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/transactions/:id',
      name: 'admin-transaction-detail',
      component: AdminTransactionDetail,
      meta: { topBar: { type: 'back', title: 'Transaction Detail', titleKey: 'titles.transactionDetail' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: AdminUsers,
      meta: { topBar: { type: 'title', title: 'Users', titleKey: 'titles.users' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/users/:id',
      name: 'admin-user-security',
      component: AdminUserSecurity,
      meta: { topBar: { type: 'back', title: 'User Security', titleKey: 'titles.userSecurity' }, showTabs: false, roles: ['admin'] },
    },
    {
      path: '/admin/moderation/reports/:id',
      name: 'admin-report-detail',
      component: () => import('../pages/AdminReportDetail.vue'),
      meta: { topBar: { type: 'back', title: 'Report Detail', titleKey: 'titles.reportDetail' }, showTabs: false, roles: ['admin'] },
    },
    { path: '/login', name: 'login', component: Login, meta: { topBar: { type: 'title', title: 'Login', titleKey: 'titles.login' }, showTabs: false } },
    {
      path: '/register',
      name: 'register',
      component: Register,
      meta: { topBar: { type: 'title', title: 'Register', titleKey: 'titles.register' }, showTabs: false },
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
      const languageStore = useLanguageStore()
      const t = (key: Parameters<typeof languageStore.t>[0]) => languageStore.t(key)
      toast.push({ title: t('router.accessDenied'), message: t('router.switchRole'), type: 'error' })
      return next('/')
    }
  }
  next()
})

export default router
