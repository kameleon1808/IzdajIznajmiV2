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
- ✅ Applications domain live: `/api/v1/listings/{listing}/apply`, seeker `/seeker/applications`, landlord `/landlord/applications`, status update `/applications/{id}` with apply-once + active-listing guard.

## Chat (listing-scoped + anti-spam + read)
- ✅ Conversations scoped to listing + participants with spam guard (3 seeker messages until landlord reply), read markers, participant-only access, endpoints for listing + conversation threads.
- ✅ Seeds include listing-scoped conversations with reply + spam-block scenario.

## Ratings & trust
- ✅ Ratings domain implemented: one per pair+listing, 5/24h limit, verification + chat preconditions, IP/User-Agent captured, reporting, admin delete/review, suspicious flagging.

## Seeds / demo data
- ⚠️ Seeds now include applications and listing-scoped conversations/messages plus landlord with active listings; ratings still missing. Credentials documented in frontend/backend READMEs.

## Frontend essentials
- ✅ SPA pages: browse listings (`/`, `/search`, `/listing/:id`), landlord CRUD (`/landlord/listings/*`), favorites (local), applications list (`/bookings` tab), apply-from-detail button, listing-scoped chat (`/messages`, `/messages/:id`). Ratings UI still absent.
- ✅ Auth store uses Sanctum cookie flow (`/sanctum/csrf-cookie` + `/api/v1/auth/*`, withCredentials on), role guard uses backend roles; mock switch only in mock mode.
