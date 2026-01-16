# API Contract (frontend-facing sketch)

Base URL: `/api`

## Listings
- `GET /api/listings`
  - Query params: `category`, `priceMin`, `priceMax`, `guests`, `instantBook`, `location`, `facilities[]`, `rating`.
  - Response: `Listing[]` (see shapes below).
- `GET /api/listings/:id`
  - Response: `Listing` + related `facilities`, `images`, `description`.
- `GET /api/landlord/listings`
  - Auth: landlord/admin.
  - Response: `Listing[]` filtered by `ownerId`.
- `POST /api/landlord/listings`
  - Content-Type: `multipart/form-data`
  - Fields: `title, pricePerNight, category, city, country, address, description, beds, baths, lat?, lng?, instantBook?`
  - Arrays: `facilities[]` (names/ids), `images[]` (FILE uploads, image/*, max ~5MB, up to 10)
  - Behavior: stores files to `/storage/listings/{id}/...`, returns URLs; `coverImage` = first uploaded.
  - Response: created `Listing` with `images` (URLs).
- `PUT /api/landlord/listings/:id`
  - Content-Type: `multipart/form-data`
  - Fields: partial Listing fields as above.
  - Arrays:
    - `keepImageUrls[]` (string URLs to keep),
    - `removeImageUrls[]` (optional, URLs to delete),
    - `images[]` (new FILE uploads),
    - `facilities[]` (names/ids).
  - Behavior: final image set = keepImageUrls + newly uploaded; removed files are deleted from disk. `coverImage` is first remaining.
  - Response: updated `Listing`.

## Booking Requests (Inquiry flow)
- `POST /api/booking-requests`
  - Body: `{ listingId, tenantId, landlordId, startDate?, endDate?, guests, message }`
  - Response: `BookingRequest` with `status: 'pending'` and `createdAt`.
- `GET /api/booking-requests?role=tenant`
  - Query params: `tenantId`
  - Response: `BookingRequest[]` for the tenant.
- `GET /api/booking-requests?role=landlord`
  - Query params: `landlordId`
  - Response: `BookingRequest[]` incoming to landlord.
- `PATCH /api/booking-requests/:id`
  - Body: `{ status: 'pending'|'accepted'|'rejected'|'cancelled' }`
  - Response: updated `BookingRequest`.

## Messaging
- `GET /api/conversations` -> `Conversation[]`
- `GET /api/conversations/:id/messages` -> `Message[]`
- Notes: unread/online are placeholders for now; messages are returned newest-first limited to last 50.

## Filtering Notes
- `facilities[]` filter currently matches ANY facility provided (can be tightened to ALL later if needed).

## Types (reference)
- `Listing`: `{ id, title, address?, city, country, lat?, lng?, pricePerNight, rating, reviewsCount, coverImage, images?, description?, beds, baths, category ('villa'|'hotel'|'apartment'), isFavorite, instantBook?, facilities?, ownerId?, createdAt? }`
- `BookingRequest`: `{ id, listingId, tenantId, landlordId, startDate?, endDate?, guests, message, status ('pending'|'accepted'|'rejected'|'cancelled'), createdAt }`
- `Booking`: `{ id, listingId, listingTitle, datesRange, guestsText, pricePerNight, rating, coverImage, status ('booked'|'history') }`
- `Conversation`: `{ id, userName, avatarUrl, lastMessage, time, unreadCount, online }`
- `Message`: `{ id, conversationId, from ('me'|'them'), text, time }`
