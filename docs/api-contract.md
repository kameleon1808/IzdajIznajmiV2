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

## Viewings (appointments — separate from bookings/reservations)
- Slots (landlord/admin; listing must be `active`):
  - `GET /api/v1/listings/:listingId/viewing-slots` (landlord sees all; seekers get active/upcoming)
  - `POST /api/v1/listings/:listingId/viewing-slots` (`starts_at`, `ends_at`, `capacity?`, `is_active?`)
  - `PATCH /api/v1/viewing-slots/:id` (owner/admin) to edit times/capacity/activation
  - `DELETE /api/v1/viewing-slots/:id` (fails if active requests exist)
- Requests (seekers request; landlord/admin responds):
  - `POST /api/v1/viewing-slots/:slotId/request` (`message?`) — blocks when slot already has requested/confirmed count >= capacity.
  - `GET /api/v1/seeker/viewing-requests`
  - `GET /api/v1/landlord/viewing-requests?listing_id=`
  - `PATCH /api/v1/viewing-requests/:id/confirm`
  - `PATCH /api/v1/viewing-requests/:id/reject`
  - `PATCH /api/v1/viewing-requests/:id/cancel` (seeker or landlord)
- ICS:
  - `GET /api/v1/viewing-requests/:id/ics` (participants + confirmed only) returns `text/calendar` with filename.
- Statuses: `requested | confirmed | rejected | cancelled`, `cancelledBy` (`seeker|landlord|system`).
- Notifications (new types, distinct from bookings): `viewing.requested` → landlord, `viewing.confirmed` → seeker, `viewing.cancelled` → counterparty. Deep link: `/bookings?tab=viewings&viewingRequestId=:id`.

## Messaging
- `GET /api/v1/conversations` -> `Conversation[]`
- `GET /api/v1/conversations/:id/messages` -> `Message[]`
  - supports incremental fetch: `since_id` (preferred) or `after` (timestamp)
  - supports conditional GET via `ETag` / `If-None-Match` (`304 Not Modified`)
- `POST /api/v1/conversations/:id/messages` (multipart) -> `Message`
  - fields: `body?`, `attachments[]?` (max 5)
  - allowed: images `jpg/jpeg/png/webp`, documents `pdf`, max 10MB/file
  - body required when no attachments
- `GET /api/v1/chat/attachments/:id` (authorized, inline for images, download for pdf)
- `GET /api/v1/chat/attachments/:id/thumb` (authorized, image only)
- `POST /api/v1/conversations/:id/typing` (`{ is_typing: true|false }`)
- `GET /api/v1/conversations/:id/typing` (returns typing users + TTL)
- `POST /api/v1/presence/ping`
- `GET /api/v1/users/:id/presence` (online status)
- `GET /api/v1/presence/users?ids[]=...` (batch online status)
- `GET /api/v1/notifications/unread-count` supports conditional GET via `ETag` / `If-None-Match`
- Notes:
  - Originals are stored privately and served only via authorized `/chat/attachments/*` endpoints.
  - Suggested polling: messages start at ~3s and back off exponentially when idle (reset on activity), typing every ~4s, presence ping every 20-30s, presence check every ~30s.
  - Chat and notifications polling pauses when tab is hidden and resumes on focus/visibility change.
  - Notifications bell polling: unread count every ~15s (+ refresh on tab focus/visibility change).
  - Messages API returns newest-first limited to last 200 (frontend then orders ascending for rendering).

## Filtering Notes
- `facilities[]` filter currently matches ANY facility provided (can be tightened to ALL later if needed).

## Types (reference)
- `Listing`: `{ id, title, address?, city, country, lat?, lng?, pricePerNight, rating, reviewsCount, coverImage, images?, imagesDetailed? [{url,sortOrder,isCover,processingStatus?,processingError?}], description?, beds, baths, category ('villa'|'hotel'|'apartment'), isFavorite, instantBook?, facilities?, ownerId?, createdAt?, status ('draft'|'published'|'archived'), publishedAt?, archivedAt? }`
- `BookingRequest`: `{ id, listingId, tenantId (seeker), landlordId, startDate?, endDate?, guests, message, status ('pending'|'accepted'|'rejected'|'cancelled'), createdAt }`
- `Booking`: `{ id, listingId, listingTitle, datesRange, guestsText, pricePerNight, rating, coverImage, status ('booked'|'history') }`
- `Conversation`: `{ id, userName, avatarUrl, lastMessage, time, unreadCount, online }`
- `Message`: `{ id, conversationId, from ('me'|'them'), text, time, attachments?: [{ id, kind, originalName, mimeType, sizeBytes, url, thumbUrl? }] }`
