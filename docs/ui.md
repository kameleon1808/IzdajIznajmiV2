# UI Tokens & Routes

## Color Tokens
- `primary`: #2F80ED (cta, active chips, icons)
- `primary.dark`: #1F63C9 (pressed state)
- `primary.light`: #E8F1FF (backgrounds)
- `surface`: #F7F9FC (page background)
- `muted`: #6B7280 (secondary text)
- `line`: #E5E7EB (subtle borders)
- `card-shadow`: rgba(47, 128, 237, 0.08) spread for soft depth

## Spacing & Radius
- Spacing scale: 4 / 8 / 12 / 16 / 20 / 24 px. Sections breathe with 16–24 px.
- Corners: cards & inputs 18–20 px; pills full (`rounded-pill`); hero/map containers 24–28 px.
- Shadows: `shadow-soft` (0 8px 24px rgba(0,0,0,0.06)), `shadow-card` (0 10px 30px rgba(47,128,237,0.08)).
- Safe-area padding on bottom nav (`safe-bottom`) for devices with insets.

## Routes
- `/` Home
- `/search` Search + filter sheet
- `/map` Map view
- `/listing/:id` ListingDetail
- `/listing/:id/facilities` Facilities
- `/listing/:id/reviews` Reviews
- `/favorites` Favorites grid
- `/bookings` My Booking tabs
- `/messages` Messages list
- `/messages/:id` Chat thread
- `/profile` Profile & settings
- `/settings/personal` Personal Info
- `/settings/legal` Legal & Policies
- `/settings/language` Language selector
- `/landlord/listings` My Listings (landlord)
- `/landlord/listings/new` Create Listing
- `/landlord/listings/:id/edit` Edit Listing

## Mock API Shapes
- `Listing`: `{ id, title, address?, city, country, lat?, lng?, pricePerNight, rating, reviewsCount, coverImage, images?, description?, beds, baths, category ('villa'|'hotel'|'apartment'), isFavorite, instantBook?, facilities?, ownerId?, createdAt? }`
- `Review`: `{ id, userName, avatarUrl, rating, text, date }`
- `Booking`: `{ id, listingId, listingTitle, datesRange, guestsText, pricePerNight, rating, coverImage, status ('booked'|'history') }`
- `BookingRequest`: `{ id, listingId, tenantId, landlordId, startDate?, endDate?, guests, message, status ('pending'|'accepted'|'rejected'|'cancelled'), createdAt }`
- `Conversation`: `{ id, userName, avatarUrl, lastMessage, time, unreadCount, online }`
- `Message`: `{ id, conversationId, from ('me'|'them'), text, time }`
- `ListingFilters`: `{ category: 'all'|ListingCategory, guests, priceRange [min,max], instantBook, location, facilities[], rating }`
