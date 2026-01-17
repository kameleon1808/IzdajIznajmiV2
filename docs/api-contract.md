# API Contract (frontend-facing sketch)

Base URL: `/api/v1` (auth also available under `/api/auth/*` during the transition)

## Auth (SPA cookie)
- `GET /sanctum/csrf-cookie`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

## Listings
- `GET /api/v1/listings`
  - Query params: `category`, `priceMin`, `priceMax`, `guests`, `instantBook`, `location`, `facilities[]`, `rating`, `page`, `perPage` (default 10, max 50).
  - Only returns `published` listings with processed images.
  - Response (paginated):
    ```json
    {
      "data": [Listing, ...],
      "meta": { "current_page": 1, "last_page": 5, "per_page": 10, "total": 50 },
      "links": { "next": "...", "prev": "..." }
    }
    ```
- `GET /api/v1/listings/:id`
  - Response: `Listing` + related `facilities`, `images`, `description`.
- `GET /api/v1/landlord/listings`
  - Auth: landlord/admin.
  - Response: `Listing[]` filtered by `ownerId`, includes draft/published/archived.
- `POST /api/v1/landlord/listings`
  - Content-Type: `multipart/form-data`
  - Fields: `title, pricePerNight, category, city, country, address, description, beds, baths, lat?, lng?, instantBook?`
  - Arrays: `facilities[]` (names/ids), `images[]` (FILE uploads, image/*, max ~5MB, up to 10)
  - Optional: `coverIndex` (int) to mark cover among new uploads.
  - Behavior: stores originals, enqueues image processing (webp). `coverImage` follows marked cover; `imagesDetailed.processingStatus` shows `pending/done/failed`.
  - Response: created `Listing` with `images` (URLs).
- `PUT /api/v1/landlord/listings/:id`
  - Content-Type: `multipart/form-data`
  - Fields: partial Listing fields as above.
  - Arrays:
    - `keepImages` (JSON string), example `[{"url":"...","sortOrder":0,"isCover":true}]`
    - `removeImageUrls[]` (optional, URLs to delete),
    - `images[]` (new FILE uploads, appended at end order),
    - `facilities[]` (names/ids).
  - Behavior: final image set = keepImages (with ordering/cover) + newly uploaded (appended); removed files are deleted from disk. `coverImage` follows `isCover` flag or first image.
  - Response: updated `Listing`.
- Lifecycle:
  - `PATCH /api/v1/landlord/listings/:id/publish` -> status `published`
  - `PATCH /api/v1/landlord/listings/:id/unpublish` -> status `draft`
  - `PATCH /api/v1/landlord/listings/:id/archive` -> status `archived`
  - `PATCH /api/v1/landlord/listings/:id/restore` -> status `draft`
  - Rules: draft↔published, draft/published→archived, archived→draft (restore), else 422.

## Booking Requests (Inquiry flow)
- `POST /api/v1/booking-requests`
  - Body: `{ listingId, tenantId, landlordId, startDate?, endDate?, guests, message }`
  - Response: `BookingRequest` with `status: 'pending'` and `createdAt`.
- `GET /api/v1/booking-requests?role=seeker`
  - Query params: `tenantId`
  - Response: `BookingRequest[]` for the seeker.
- `GET /api/v1/booking-requests?role=landlord`
  - Query params: `landlordId`
  - Response: `BookingRequest[]` incoming to landlord.
- `PATCH /api/v1/booking-requests/:id`
  - Body: `{ status: 'pending'|'accepted'|'rejected'|'cancelled' }`
  - Response: updated `BookingRequest`.

## Messaging
- `GET /api/v1/conversations` -> `Conversation[]`
- `GET /api/v1/conversations/:id/messages` -> `Message[]`
- Notes: unread/online are placeholders for now; messages are returned newest-first limited to last 50.

## Filtering Notes
- `facilities[]` filter currently matches ANY facility provided (can be tightened to ALL later if needed).

## Types (reference)
- `Listing`: `{ id, title, address?, city, country, lat?, lng?, pricePerNight, rating, reviewsCount, coverImage, images?, imagesDetailed? [{url,sortOrder,isCover,processingStatus?,processingError?}], description?, beds, baths, category ('villa'|'hotel'|'apartment'), isFavorite, instantBook?, facilities?, ownerId?, createdAt?, status ('draft'|'published'|'archived'), publishedAt?, archivedAt? }`
- `BookingRequest`: `{ id, listingId, tenantId (seeker), landlordId, startDate?, endDate?, guests, message, status ('pending'|'accepted'|'rejected'|'cancelled'), createdAt }`
- `Booking`: `{ id, listingId, listingTitle, datesRange, guestsText, pricePerNight, rating, coverImage, status ('booked'|'history') }`
- `Conversation`: `{ id, userName, avatarUrl, lastMessage, time, unreadCount, online }`
- `Message`: `{ id, conversationId, from ('me'|'them'), text, time }`
