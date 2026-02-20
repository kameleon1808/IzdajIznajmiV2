# IzdajIznajmiV2
[![Backend CI](https://github.com/kameleon1808/IzdajIznajmiV2/actions/workflows/backend.yml/badge.svg)](https://github.com/kameleon1808/IzdajIznajmiV2/actions/workflows/backend.yml)

## Project Overview
IzdajIznajmiV2 is a UI-first marketplace for short- and mid-term stays, built as a modern SPA with a contract-driven Laravel API. It covers the full listing lifecycle from draft to publish/archive, focuses on quick discovery with polished mobile UX, and keeps the backend explicit about roles, policies, and rate limits.

The repo is organized as a monorepo (frontend + backend + docs) to keep product, design, and implementation in sync for portfolio review and client onboarding.

## Why this project?
The goal is to demonstrate a UX-forward SPA backed by a clean, well-documented API surface: dual API modes for rapid prototyping, a predictable Laravel contract with policies and rate limiting, and a realistic media pipeline (async image processing) suitable for production hardening.

## Key Features
- Marketplace & roles: guest browsing; seeker favorites and booking inquiries; landlord listing CRUD and publishing; admin oversight. Listing statuses: draft → active/paused → rented/archived/expired (auto after 30d) with duplicate-address guard rails.
- Discovery: browsing, search/filters (city/location, price range, rooms/guests, area, instant book, amenities/status/rating), pagination, and listing detail. Map view is a visual placeholder hero (no live map yet).
- Saved searches + alerts: seekers can save search filters, manage alert frequency (instant/daily/weekly), and receive in-app notifications for new matching listings.
- Favorites: client-side (frontend local) favorites with quick toggle.
- Booking Requests (inquiry flow): tenant creates; landlord accepts/rejects; tenant can cancel while pending. Statuses surface in UI and API.
- Viewings: separate appointment scheduling for in-person visits (slots per listing, seeker requests, landlord confirms/rejects/cancels, ICS download, deep-linked notifications) surfaced in a new "Viewings" tab under `/bookings`.
- Messaging skeleton: conversations and messages list; unread/online indicators are placeholders; newest messages limited to latest 50.
- Listing images: multipart upload, ordering, cover selection, and async processing queue (WebP resize/convert). Processing status per image: pending/done/failed; cover auto-updates when processed.
- Rate limiting: 429 protection on auth (10/min/IP), listings search (60/min/IP), booking requests (20/min/user or IP), landlord writes (30/min/user or IP).
- Dual-mode frontend: `VITE_USE_MOCK_API=true` uses local mock data; `false` hits real Laravel API with route guards redirecting to login.
- Ops readiness: request correlation (`X-Request-Id`), queue health endpoint (`/api/v1/health/queue` with failed jobs count), env-gated security headers, Postgres backup/restore scripts, and baseline k6 load test scripts under `ops/loadtest`.

## Tech Stack
- Frontend: Vue 3, Vite, TypeScript, Tailwind CSS, Pinia, Vue Router.
- Backend: Laravel 12 API, Sanctum SPA cookie auth, database queues, Intervention Image for media processing.
- Database: SQLite by default (file included), compatible with MySQL/PostgreSQL.
- Tooling: PHP Unit tests, npm scripts for dev/build, queue worker for async jobs.

## Architecture
- Monorepo with isolated `frontend/`, `backend/`, and `docs/`.
- Frontend SPA toggles between mock services and real API via env flag; route guards enforce role access when using real API.
- Backend exposes `/api/v1` endpoints (with temporary `/api/auth/*` aliases), enforces policies per role, processes images asynchronously, and rate-limits sensitive routes.
- Docs folder contains API contract/examples, UI notes, and test/UAT plans kept close to code.

## Repo map
```
.
├─ frontend/          # Vue 3 + Vite SPA (mock/real API switch)
├─ backend/           # Laravel 12 API, Sanctum, queues, image pipeline
├─ docs/
│  ├─ api-contract.md
│  ├─ api-examples.md
│  ├─ dev-setup.md
│  ├─ ui.md
│  ├─ test-plan-sr.md
│  ├─ uat-test-plan-sr.md
│  └─ ui-reference/   # reference screenshots
└─ README.md          # this file
```

## Screens / UX
- `"/"` Home highlights recommended/popular listings; CTA to inquiry.
- `"/search"` Filter sheet for category/price/guests/instant book/facilities/rating; paginated results.
- `"/map"` Visual map hero placeholder (stylized background, no live map data yet).
- `"/listing/:id"` Detail with gallery, facilities, reviews, inquiry CTA; `"/facilities"` and `"/reviews"` sub-routes.
- `"/favorites"` Favorites grid (local-only); `"/bookings"` now split into Reservations vs Viewings tabs; `"/messages"` conversations and `"/messages/:id"` chat (unread/online placeholders).
- `"/saved-searches"` Manage saved searches (alerts, frequency, run/delete); notifications deep-link to `"/search?savedSearchId={id}"` and hydrate filters.
- `"/profile"` with mock role switch when in mock mode; settings pages for personal info/legal/language.
- Landlord: `"/landlord/listings"` index, `"/landlord/listings/new"` create, `"/landlord/listings/:id/edit"` edit; publish/unpublish/archive actions surface from API states.
- Auth: `"/login"` and `"/register"`; protected routes redirect to login when real API is enabled.

## Getting Started (Local Development)

### Prerequisites
- Node.js 20+ (CI uses 20.x; Vite 7 requires 20.19+)
- PHP 8.2+ (CI uses 8.3), Composer
- SQLite (default) or another DB; database queue driver enabled
- Image processing requires GD (via Intervention Image)

### Backend setup
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan queue:work    # keep running for image processing
php artisan schedule:work # runs listings:expire auto-expiry, saved-searches:match, and digests on schedule
php artisan saved-searches:match # on-demand matcher for saved searches + alerts
php artisan serve --port=8000
```
- API base: `/api/v1` (auth also available at `/api/auth/*` during the transition); SPA cookie auth via `/sanctum/csrf-cookie`.
- Stateful dev defaults: `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, `SESSION_DOMAIN=localhost`, CORS `supports_credentials=true`.
- Image opts (default on): `IMAGE_OPTIMIZE=true`, `IMAGE_MAX_WIDTH=1600`, `IMAGE_WEBP_QUALITY=80`.

### Frontend setup
```bash
cd frontend
npm install
cp .env.example .env
# Toggle mock vs real API
# VITE_USE_MOCK_API=true  # mock data, fastest start
# VITE_USE_MOCK_API=false # real backend; leave VITE_API_BASE_URL blank to use the dev proxy (or point to http://localhost:8000)
npm run dev -- --host --port=5173
```
- Vite dev proxy forwards `/api` and `/sanctum` to the backend for cookie auth; withCredentials is enabled in the client.
- Mock mode keeps role switch visible on Profile; real mode enforces login and role guards.

### Realtime (Reverb)
Reverb is enabled for local/dev only and uses the Pusher protocol.

Backend env (see `backend/.env.example`):
- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` (where Laravel sends events)
- `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT` (where the Reverb server listens)

Frontend env (see `frontend/.env.example`):
- `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`

Run locally (non-docker):
```bash
cd backend
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

Run with Docker Compose:
```bash
docker compose up -d
# or, if you prefer explicit services:
docker compose up -d backend frontend queue reverb
```

Troubleshooting:
- If `/broadcasting/auth` fails for SPA, ensure CORS includes `broadcasting/auth` and `SANCTUM_STATEFUL_DOMAINS` includes `localhost:5173`.
- For Docker, set `REVERB_HOST=reverb` (service name) in the backend/queue containers; for local dev use `REVERB_HOST=localhost`.
- If the browser console shows origin errors, add your dev origin to `config/reverb.php` (`allowed_origins`).

### Demo Accounts (password `password`)
- Admin: `admin@example.com`
- Landlords: `lana@demo.com`, `leo@demo.com`
- Seekers: `tena@demo.com`, `tomas@demo.com`, `tara@demo.com`

## Environment Variables
- Frontend: `VITE_API_BASE_URL` (blank to use dev proxy), `VITE_USE_MOCK_API`, `VITE_SEARCH_V2` (enable Search v2 UI).
- Backend (minimum): `APP_URL` (e.g., http://localhost:8000), `FRONTEND_URL` (http://localhost:5173), `FRONTEND_URLS` (comma list for CORS), `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, `SESSION_DOMAIN=localhost`, `DB_CONNECTION` (sqlite by default), `QUEUE_CONNECTION=database`, `IMAGE_OPTIMIZE`, `IMAGE_MAX_WIDTH`, `IMAGE_WEBP_QUALITY`.
- Search v2 (optional): `SEARCH_DRIVER=meili`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, `MEILISEARCH_INDEX`.

## API Documentation
- Contract: `docs/api-contract.md`
- Examples (cURL): `docs/api-examples.md`
- UI notes/tokens: `docs/ui.md`

## Security & Permissions
- Roles: guest (browse), seeker (favorites, inquiries), landlord (listing CRUD/publish), admin (override).
- Auth: Laravel Sanctum SPA cookies (`/sanctum/csrf-cookie` + session) on `/api/v1/auth/*`; legacy `/api/auth/*` kept temporarily. Route guards block protected pages in real API mode.
- Listings safety: duplicate-address guard (blocks same landlord active duplicates, warns on cross-landlord conflicts) and scheduled auto-expire after 30 days of being active (`php artisan listings:expire`).
- Saved searches: `/api/v1/saved-searches` (POST/GET/PUT/DELETE). Alerts link to `/search?savedSearchId={id}` and matcher runs via scheduler (every 15 minutes).
- Policies: listings view published or owner/admin; updates require owner/admin (archived immutable except admin). Booking requests: seeker can cancel pending own; landlord can accept/reject pending for own listing; admin bypasses.
- Rate limiting (429): auth 10/min/IP; listings search 60/min/IP; booking requests 20/min/user or IP; landlord writes 30/min/user or IP.
- Storage & media: uploads via multipart, stored to `public`; queue processes WebP conversions and updates cover/ordering with `processing_status` (`pending/done/failed`).

## Testing
- Backend: `cd backend && composer install && php artisan test`
- Frontend (unit): `cd frontend && npm ci && npm run test`
- Frontend (build/type-check): `cd frontend && npm ci && npm run build`
- E2E smoke (mock API): `cd frontend && npm ci && npm run test:e2e` (first run: `npx playwright install --with-deps chromium`)
- UAT reference: `docs/uat-test-plan-sr.md`; test plan: `docs/test-plan-sr.md`

## Deploy
- Deployment guide, env templates, and ops scripts live in `docs/deploy/DEPLOYMENT.md`.
- Ready-to-run scripts: `ops/deploy.sh` (idempotent deploy), `ops/rollback.sh`.
- Ops runbooks: `docs/ops/BACKUPS.md`, `docs/ops/QUEUE-OPS.md`, `docs/ops/LOAD-TESTING.md`, `docs/ops/PERFORMANCE.md`.
- Chat/realtime support runbook: `docs/ops/CHAT-REALTIME-SUPPORT.md`.
- GitHub Actions deploy workflows: `.github/workflows/deploy-staging.yml` and `deploy-production.yml` (SSH-based).

## Roadmap
- Production deploy (containers/CI, env hardening, object storage/CDN for media).
- Optional upgrade path: WebSockets for chat/notifications (current production flow uses polling-based realtime).
- Payments and booking confirmation flow (Stripe/PayPal), availability calendar.
- Geo search with real maps and location biasing; richer filters (amenities, policies).
- Observability (logs/metrics/traces) and admin dashboards.

## License
TBD

## Contact / Hiring
Open to collaboration / freelance.

## Quick Note (SR)
Ovaj projekat je UI-prioritet marketplace sa čistim Laravel API slojem i jasnim ugovorima. Dualni mod (mock/real) omogućava brzo testiranje bez backend-a ili rad sa Sanctum cookie authom (`/sanctum/csrf-cookie` → session). Upload slika ide preko reda za obradu (WebP) sa statusima, a rate limit štiti auth/pretragu/booking/landlord radnje. Uloge i politike su striktne (seeker inquiry, landlord CRUD/publish, admin override), uz lokalne “favorites”. Ako koristite real API, ne zaboravite `queue:work`, `storage:link` i demo naloge (password `password`) za brzo testiranje.
