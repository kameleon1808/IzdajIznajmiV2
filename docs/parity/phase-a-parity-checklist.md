# Phase A – V1 → V2 Parity Checklist

Legend: ✅ = verified/implemented, ⚠️ = partial, ❌ = missing.

## Auth & identity
- ✅ Endpoints: `/api/v1/auth/register|login|logout|me` (stateful Sanctum cookie/CSRF), legacy `/api/auth/*` aliases kept.
- ✅ User fields present: `full_name`, `phone` (unique), `address_book`, `email_verified`, `phone_verified`, `address_verified`, `is_suspicious`.
- ⚠️ Demo users seeded in `DatabaseSeeder` via `RolesUsersSeeder` (admin/landlord/seeker) with password `password`.

## Roles / permissions
- ✅ Spatie roles/permissions installed and applied (`admin/landlord/seeker`); controllers/policies use `hasRole`/`hasAnyRole` with role column fallback.
- ⚠️ Policies: `ListingPolicy`, `BookingRequestPolicy` exist; applied in `AppServiceProvider::boot()` (basic checks only).

## Listings guard rails + statuses + auto-expire
- ✅ Endpoints: `/api/v1/listings` (public browse with filters), `/api/v1/listings/{id}`, `/api/v1/landlord/listings` CRUD + activate/pause/archive/restore + mark-rented (aliases under `/api/*`, auth:sanctum).
- ✅ Status set expanded to `draft/active/paused/archived/rented/expired`; publish/activate maps to `active`, pause maps to `paused`, archive/restore intact; auto-expire command marks active listings older than 30 days as `expired`.
- ✅ Guard rails: normalized `address_key` with block for same landlord active duplicates (409) and warning when another landlord has an active listing at that address; warnings returned in payload.

## Discovery filters
- ✅ Filters include `city`, `location` (contains), `priceMin/priceMax`, `rooms`, `areaMin/areaMax`, `guests`, `instantBook`, `facilities`/`amenities`, `rating`, `status`, and pagination (`page`, `perPage`).

## Applications (apply once + separate lists)
- ❌ No applications model/endpoints. Booking Requests exist instead: `/api/booking-requests` (POST create, GET list by role query param, PATCH status). No `/api/v1/listings/{listing}/apply` or apply-once enforcement; no seeker/landlord list separation beyond query param.

## Chat (listing-scoped + anti-spam + read)
- ⚠️ Conversations/messages endpoints: `/api/conversations`, `/api/conversations/{conversation}/messages` (auth:sanctum). Not listing-scoped; no anti-spam rule; no read markers; access control not participant-scoped.
- ⚠️ Seeded one conversation/messages via `ConversationsSeeder` (listing_id null).

## Ratings & trust
- ❌ No ratings models/endpoints; no limits (1 per pair/listing, 5/24h), no verification checks, no IP/User-Agent capture, no reporting/admin actions, no suspicious flagging.

## Seeds / demo data
- ⚠️ Seeds present: roles users (admin/landlord/seekers), facilities, listings (~20), booking requests, one conversation/messages. Missing applications, ratings. Credentials documented in frontend/backend READMEs.

## Frontend essentials
- ⚠️ SPA pages: browse listings (`/`, `/search`, `/listing/:id`), landlord CRUD (`/landlord/listings/*`), favorites (local), booking requests list (`/bookings`), messages UI (`/messages`, `/messages/:id`). No apply-to-listing UI; chat not listing-scoped; ratings UI absent.
- ✅ Auth store uses Sanctum cookie flow (`/sanctum/csrf-cookie` + `/api/v1/auth/*`, withCredentials on), role guard uses backend roles; mock switch only in mock mode.
