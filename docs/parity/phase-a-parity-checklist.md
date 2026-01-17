# Phase A – V1 → V2 Parity Checklist

Legend: ✅ = verified/implemented, ⚠️ = partial, ❌ = missing.

## Auth & identity
- ⚠️ Endpoints: `/api/auth/register`, `/api/auth/login`, `/api/auth/logout`, `/api/auth/me` (Bearer token via Sanctum PAT). Not under `/api/v1/*`; SPA Sanctum cookie flow/CSRF not wired.
- ❌ User fields missing: `full_name`, `phone` (unique), `address_book`, `email_verified`, `phone_verified`, `address_verified`, `is_suspicious`.
- ⚠️ Demo users seeded in `DatabaseSeeder` via `RolesUsersSeeder` (admin/landlord/tenant) with password `password`.

## Roles / permissions
- ❌ Spatie roles/permissions not installed. Current model uses enum `role` (`tenant/landlord/admin`); middleware/policies rely on string comparisons.
- ⚠️ Policies: `ListingPolicy`, `BookingRequestPolicy` exist; applied in `AppServiceProvider::boot()` but not tied to spatie abilities.

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
- ⚠️ Seeds present: roles users (admin/landlord/tenants), facilities, listings (~20), booking requests, one conversation/messages. Missing applications, ratings. Credentials documented in frontend/backend READMEs.

## Frontend essentials
- ⚠️ SPA pages: browse listings (`/`, `/search`, `/listing/:id`), landlord CRUD (`/landlord/listings/*`), favorites (local), booking requests list (`/bookings`), messages UI (`/messages`, `/messages/:id`). No apply-to-listing UI; chat not listing-scoped; ratings UI absent.
- ⚠️ Auth store uses bearer tokens from `/api/auth/*` with `withCredentials:false`; no Sanctum CSRF cookie flow; role guard uses local role (mock switch in mock mode).
