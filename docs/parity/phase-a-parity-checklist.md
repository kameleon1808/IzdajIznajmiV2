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
- ⚠️ Endpoints: `/api/listings` (public browse), `/api/listings/{id}`, `/api/landlord/listings` CRUD + publish/unpublish/archive/restore (all under `/api/*`, auth:sanctum).
- ❌ Status set is `draft/published/archived` only (no `active/paused/rented/expired`); no auto-expire after 30 days.
- ❌ Guard rails: no duplicate active listing prevention by landlord+address; no warning when address exists for another landlord.

## Discovery filters
- ⚠️ Supported filters in `ListingController@index`: `category`, `priceMin`, `priceMax`, `guests` (beds), `instantBook`, `location` (city/country contains), `facilities[]`, `rating`, pagination (`page`, `perPage`). Missing `city` dedicated field filter, `rooms`, `area`, `status`.

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
