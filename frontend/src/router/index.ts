import { createRouter, createWebHistory } from 'vue-router'
import Bookings from '../pages/Bookings.vue'
import Chat from '../pages/Chat.vue'
import Facilities from '../pages/Facilities.vue'
import Favorites from '../pages/Favorites.vue'
import Home from '../pages/Home.vue'
import ListingDetail from '../pages/ListingDetail.vue'
import MapPage from '../pages/Map.vue'
import Messages from '../pages/Messages.vue'
import Profile from '../pages/Profile.vue'
import Reviews from '../pages/Reviews.vue'
import Search from '../pages/Search.vue'
import SettingsLanguage from '../pages/SettingsLanguage.vue'
import SettingsLegal from '../pages/SettingsLegal.vue'
import SettingsPersonalInfo from '../pages/SettingsPersonalInfo.vue'

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
    { path: '/favorites', name: 'favorites', component: Favorites, meta: { topBar: { type: 'title', title: 'My Favorite' }, showTabs: true } },
    { path: '/bookings', name: 'bookings', component: Bookings, meta: { topBar: { type: 'title', title: 'My Booking' }, showTabs: true } },
    { path: '/messages', name: 'messages', component: Messages, meta: { topBar: { type: 'title', title: 'Messages' }, showTabs: true } },
    {
      path: '/messages/:id',
      name: 'chat',
      component: Chat,
      meta: { topBar: { type: 'chat' }, showTabs: false, contentClass: 'p-0 pb-20' },
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
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

export default router
