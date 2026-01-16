# API Contract (frontend-facing sketch)

Base URL: `/api`

## Listings
- `GET /api/listings`
  - Query params: `category`, `priceMin`, `priceMax`, `guests`, `instantBook`, `location`, `facilities[]`, `rating`, `page`, `perPage` (default 10, max 50).
  - Response (paginated):
    ```json
    {
      "data": [Listing, ...],
      "meta": { "current_page": 1, "last_page": 5, "per_page": 10, "total": 50 },
      "links": { "next": "...", "prev": "..." }
    }
    ```
- `GET /api/listings/:id`
  - Response: `Listing` + related `facilities`, `images`, `description`.
- `GET /api/landlord/listings`
  - Auth: landlord/admin.
  - Response: `Listing[]` filtered by `ownerId`.
- `POST /api/landlord/listings`
  - Content-Type: `multipart/form-data`
  - Fields: `title, pricePerNight, category, city, country, address, description, beds, baths, lat?, lng?, instantBook?`
  - Arrays: `facilities[]` (names/ids), `images[]` (FILE uploads, image/*, max ~5MB, up to 10)
  - Optional: `coverIndex` (int) to mark cover among new uploads.
  - Behavior: stores files to `/storage/listings/{id}/...`, converts to webp (best effort), returns URLs; `coverImage` = marked cover or first uploaded.
  - Response: created `Listing` with `images` (URLs).
- `PUT /api/landlord/listings/:id`
  - Content-Type: `multipart/form-data`
  - Fields: partial Listing fields as above.
  - Arrays:
    - `keepImages` (JSON string), example `[{"url":"...","sortOrder":0,"isCover":true}]`
    - `removeImageUrls[]` (optional, URLs to delete),
    - `images[]` (new FILE uploads, appended at end order),
    - `facilities[]` (names/ids).
  - Behavior: final image set = keepImages (with ordering/cover) + newly uploaded (appended); removed files are deleted from disk. `coverImage` follows `isCover` flag or first image.
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
- `Listing`: `{ id, title, address?, city, country, lat?, lng?, pricePerNight, rating, reviewsCount, coverImage, images?, imagesDetailed? [{url,sortOrder,isCover}], description?, beds, baths, category ('villa'|'hotel'|'apartment'), isFavorite, instantBook?, facilities?, ownerId?, createdAt? }`
- `BookingRequest`: `{ id, listingId, tenantId, landlordId, startDate?, endDate?, guests, message, status ('pending'|'accepted'|'rejected'|'cancelled'), createdAt }`
- `Booking`: `{ id, listingId, listingTitle, datesRange, guestsText, pricePerNight, rating, coverImage, status ('booked'|'history') }`
- `Conversation`: `{ id, userName, avatarUrl, lastMessage, time, unreadCount, online }`
- `Message`: `{ id, conversationId, from ('me'|'them'), text, time }`
