# Development Setup (Frontend + Backend)

This guide is the primary onboarding reference for running the project locally and in Docker-based development environments.

## Prerequisites
- Node.js 20+ (Vite 7 requires >=20.19; `.nvmrc` is set to 20)
- PHP 8.2+ (CI runs on 8.3)
- Composer and npm
- Docker Engine or Docker Desktop (for Docker-based setups)

## Quick Start (native)
1. Backend:
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```
2. Frontend:
```bash
cd frontend
npm install
cp .env.example .env
npm run dev -- --host --port 5173
```
3. Background workers (new terminal in `backend/`):
```bash
php artisan queue:work
php artisan schedule:work
```

## Docker Development (Windows / Docker Desktop)
- Configuration is in root `docker-compose.yml`.
- Services started: backend (`artisan serve`), queue (`queue:work`), scheduler (`schedule:work`), frontend (`npm run dev`), and MeiliSearch.

Commands:
- First start: `docker compose up --build`
- Initial migrations and seed: `docker compose run --rm backend php artisan migrate:fresh --seed`
- Optional Meili reindex: `docker compose run --rm backend php artisan search:listings:reindex --reset`
- Stop: `docker compose down`

Ports:
- API: `http://localhost:8000`
- Frontend: `http://localhost:5173`
- MeiliSearch: `http://localhost:7700`

Compose bootstrap behavior:
- Copies `backend/.env.example` to `backend/.env` if missing
- Generates `APP_KEY`
- Creates the SQLite file if needed
- Runs `storage:link`

## Docker Development (Windows + WSL2 terminal)
- Keep Docker Desktop as the engine and run commands from WSL.
- Docker Desktop -> Settings -> Resources -> WSL Integration: enable the distro.
- In WSL distro, install Docker CLI (not a second engine). Example for Ubuntu/Debian:
  - `sudo apt update && sudo apt install -y docker.io docker-compose-plugin`
- Verify: `docker version` (must see Docker Desktop engine)
- Start from repo root: `docker compose up --build`
- Do not run a separate Linux Docker Engine in parallel with Docker Desktop.

## Docker Development (Linux native)
- Install Docker Engine + Compose plugin.
- Add your user to the `docker` group and re-login:
  - `sudo usermod -aG docker $USER`
  - `newgrp docker`
- Verify: `docker version` and `docker compose version`
- Start from repo root: `docker compose up --build`

## Backend (Laravel API)
- Location: `/backend`
- Default local URL: `http://localhost:8000`
- Auth flow: Sanctum SPA cookies (`/sanctum/csrf-cookie`, then `/api/v1/auth/*`)
- Legacy aliases under `/api/auth/*` remain temporarily for compatibility.

Important local config:
- `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`
- `SESSION_DOMAIN=localhost`
- `FRONTEND_URLS=http://localhost:5173,http://127.0.0.1:5173`
- `supports_credentials=true` in CORS config

Images and media:
- Set `APP_URL=http://localhost:8000`
- Run `php artisan storage:link`
- Optional image optimization:
  - `IMAGE_OPTIMIZE=true`
  - `IMAGE_MAX_WIDTH=1600`
  - `IMAGE_WEBP_QUALITY=80`

Chat attachments:
- Private path: `storage/app/private/chat/{conversation_id}`
- Allowed types: `jpg`, `jpeg`, `png`, `webp`, `pdf`
- Max file size: 10MB per file (configurable via `CHAT_ATTACHMENT_*` env)

Queues and scheduler:
- `QUEUE_CONNECTION=database`
- Worker: `php artisan queue:work`
- Dev scheduler worker: `php artisan schedule:work`
- In production, prefer cron + `schedule:run` every minute.

Scheduled jobs currently used:
- `listings:expire` daily at 02:00
- `notifications:digest --frequency=daily` daily at 09:00
- `notifications:digest --frequency=weekly` Mondays at 09:00
- `saved-searches:match` every 15 minutes

Rate limiters (defined in `AppServiceProvider`):
- `chat_messages`: 30/min per user/thread
- `chat_attachments`: 10 uploads/10min per user/thread
- `applications`: 10/hour per user/IP
- `listings_search`: 60/min per IP
- `geocode_suggest`: 40/min per IP

Realtime behavior:
- Chat and notification bell use polling (not mandatory WebSocket dependencies)
- Typing/presence signals use cache + periodic API calls
- Event discovery is disabled (`withEvents(discover: false)`) to prevent duplicate listener registration

If you change events/listeners:
```bash
php artisan event:clear
php artisan optimize:clear
```

Support runbook: `docs/ops/CHAT-REALTIME-SUPPORT.md`

Observability:
- Structured logs: `storage/logs/structured-YYYY-MM-DD.log`
- API responses include `X-Request-Id`
- Error payloads include `request_id` for backend log correlation
- Queue health endpoint: `GET /api/v1/health/queue`

Saved search matcher notes:
- Command: `php artisan saved-searches:match`
- Uses cache mutex and processes only new active listings since the last run
- Locking requires `file`, `redis`, or `database` cache store (not `array`)

Geocoding and geo search:
- Default: `GEOCODER_DRIVER=fake`
- Optional real adapter: `GEOCODER_DRIVER=nominatim`
- Suggest API: `GET /api/v1/geocode/suggest?q=...&limit=...`
- Search geo params: `centerLat`, `centerLng`, `radiusKm`
- Backfill command: `php artisan listings:geocode --missing`
- Manual pin endpoints:
  - `PATCH /api/v1/listings/{id}/location`
  - `POST /api/v1/listings/{id}/location/reset`

Search v2 (MeiliSearch):
- Local start:
```bash
docker run --rm -p 7700:7700 -e MEILI_MASTER_KEY=masterKey getmeili/meilisearch:v1.8
```
- Backend env:
  - `SEARCH_DRIVER=meili`
  - `MEILISEARCH_HOST=http://localhost:7700`
  - `MEILISEARCH_KEY=masterKey`
  - `MEILISEARCH_INDEX=listings`
- Reindex:
  - `php artisan search:listings:reindex`
  - `php artisan search:listings:reindex --reset`
- Frontend flag: `VITE_SEARCH_V2=true`

## Frontend (Vue 3 + Vite)
- Location: `/frontend`
- Default local URL: `http://localhost:5173`

Env example (`frontend/.env.example`):
- `VITE_API_BASE_URL=` (empty -> uses dev proxy)
- `VITE_USE_MOCK_API=true` (safe default)
- `VITE_ENABLE_WEB_PUSH=false` (enable for local push testing)
- `VITE_VAPID_PUBLIC_KEY=` (must match backend VAPID public key)

Commands:
- Start: `npm install && cp .env.example .env && npm run dev`
- Build: `npm run build`
- Unit tests: `npm run test`
- E2E smoke: `npm run test:e2e`
- First Playwright install: `npx playwright install --with-deps chromium`

Admin pages:
- `/admin`
- `/admin/moderation`
- `/admin/ratings`

## API Modes
- Mock mode (`VITE_USE_MOCK_API=true`): uses `services/mockApi.ts`; profile role switch is visible.
- Real API (`VITE_USE_MOCK_API=false`): uses Laravel backend and cookie auth; route guards redirect to `/login` if session is missing.

## Release Notes and References
- API contract: `docs/api-contract.md`
- cURL examples: `docs/api-examples.md`
- UI guide: `docs/ui.md`
- UI reference images: `docs/ui-reference/`
- Chat release notes: `docs/releases/chat/chat-realtime-phase-g-and-hotfixes-2026-02-13.md`
- Push/PWA release notes: `docs/releases/notifications/web-push-pwa-rollout-2026-02-20.md`
- Verification release notes: `docs/releases/verification/email-only-verification-fix-2026-02-21.md`
