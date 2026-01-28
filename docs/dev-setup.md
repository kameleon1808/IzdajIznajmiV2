# Dev setup (frontend + backend)

## Prerequisites
- Node.js 20+ (Vite 7 needs >=20.19; `.nvmrc` set to 20)
- PHP 8.2+ (CI runs on 8.3)
- Composer, npm

## Backend (Laravel API)
- Lokacija: `/backend`
- Port: `http://localhost:8000`
- Pokretanje (primer): `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate:fresh --seed && php artisan serve`
- Auth: Sanctum SPA cookies (CSRF cookie + session) na `/api/v1/auth/*` uz privremene `/api/auth/*` alias-e; demo korisnici iz seedera (email + `password`).
- CORS/Stateful (dev): `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, `SESSION_DOMAIN=localhost`, `FRONTEND_URLS=http://localhost:5173,http://127.0.0.1:5173`, `supports_credentials=true`.
- Auditing/moderacija: migracije dodaju `audit_logs` i `reports` tabele; admin rute pod `/api/v1/admin/*` koriste `role:admin` middleware.
- Za slike: postavite `APP_URL=http://localhost:8000` i pokrenite `php artisan storage:link` (potrebno za /storage URL-ove).
- Image optimizacija (opciono, default uključeno): `IMAGE_OPTIMIZE=true`, `IMAGE_MAX_WIDTH=1600`, `IMAGE_WEBP_QUALITY=80`.
- Queue:
  - `QUEUE_CONNECTION=database`
  - Pokrenite worker: `php artisan queue:work`
  - Migracije uključuju jobs tabelu (default).
- Scheduler (auto-expire listings + notifications): `php artisan schedule:work` uključuje zadatke:
  - `listings:expire` - dnevno prebacuje stare aktivne oglase u `expired` (02:00)
  - `notifications:digest --frequency=daily` - dnevno digest notifikacije (09:00)
  - `notifications:digest --frequency=weekly` - nedeljni digest notifikacije (ponedeljak 09:00)
  - `saved-searches:match` - matcher za saved searches i in-app alert notifikacije (na 15 min)
- Rate limit pregledi: ključni limiters u `AppServiceProvider`:
  - `chat_messages` 60/min po user/IP (slanje poruka)
  - `applications` 10/h po user/IP (slanje prijava)
  - `listings_search` 60/min IP, `geocode_suggest` 40/min IP; landlord/viewing write limiti ostaju kao ranije
- Observability: struktuisani JSON logovi u `storage/logs/structured-YYYY-MM-DD.log` preko `App\Services\StructuredLogger`.
  - Bitne akcije (listing create/update/publish, prijave, poruke, ocene, prijave ocena) loguju `action`, `user_id`, `listing_id`, `ip`, `user_agent`, bez sadržaja poruka.
  - Neobrađeni 5xx izuzetci se loguju kao `unhandled_exception`; opciono Sentry iza `SENTRY_ENABLED` + `SENTRY_LARAVEL_DSN` (ako je SDK prisutan).
- Saved-search matcher: `php artisan saved-searches:match` koristi cache mutex i obrađuje samo nove ACTIVE oglase od poslednjeg pokretanja.
  - Koristi cache store `CACHE_LOCK_STORE` (default `CACHE_DRIVER`). Za rad zaključavanja mora biti `file`/`redis`/`database` — ne `array`.
  - Notifikacije deep-linkuju na `/search?savedSearchId={id}` i poštuju frekvenciju `instant|daily|weekly`.
- Produkcija: umesto `schedule:work`, podesite cron da pokreće scheduler na svakih minut:
  - `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`
- Notifikacije: sistem za in-app notifikacije sa preferencama i digest podrškom. Tipovi: `application.created`, `application.status_changed`, `message.received`, `rating.received`, `report.update`, `admin.notice`, `digest.daily`, `digest.weekly`. Korisnici mogu da konfigurišu tipove i digest frekvenciju (none/daily/weekly) kroz `/settings/notifications`.
- Saved searches API: `POST/GET/PUT/DELETE /api/v1/saved-searches`; deep-link format: `/search?savedSearchId={id}`.
- Geokodiranje & geo pretraga:
  - Default koristi `FakeGeocoder` (determinističan lat/lng na osnovu adrese) — vidi `GEOCODER_DRIVER=fake`, `GEOCODER_CACHE_TTL`, `FAKE_GEOCODER_*` u `.env.example`.
  - Opcioni Nominatim adapter iza `GEOCODER_DRIVER=nominatim` sa `GEOCODER_NOMINATIM_URL`, `GEOCODER_NOMINATIM_EMAIL`, `GEOCODER_NOMINATIM_RATE_LIMIT_MS`.
  - Autocomplete suggeri: `GET /api/v1/geocode/suggest?q=...&limit=...` koristi `GEOCODER_SUGGEST_DRIVER` (`fake` ili `nominatim`) i keš `GEOCODER_SUGGEST_CACHE_TTL` (minuti); rate limit key `geocode_suggest` (40/min IP).
  - Geo parametri u pretrazi i deep linkovima: `centerLat`, `centerLng`, `radiusKm` (km, max `SEARCH_MAX_RADIUS_KM`, default 50). Map pin payload u map modu ograničen na `SEARCH_MAX_MAP_RESULTS` (default 300) uz `mapMode=true` query param.
  - Backfill komanda za postojeće zapise: `php artisan listings:geocode --missing` (koristi queue sync).
  - API: `/api/v1/geocode?q=...` (GET) za frontend centriranje mape; pretraga oglasa prihvata `centerLat`, `centerLng`, `radiusKm` (km) uz ostale filtere i vraća `distanceKm` kada je geo filter aktivan.
  - Verifikacija lokacije: detalj oglasa ima "View on map" link + Leaflet preview sa pinom. Vlasnik/admin mogu da ukljuce "Adjust pin" (draggable marker) i sacuvaju rucne koordinate preko `PATCH /api/v1/listings/{id}/location`; reset na automatsko geokodiranje ide kroz `POST /api/v1/listings/{id}/location/reset`.
  - Manual override pravilo: kada je `location_source=manual` geokoder preskace osvjezavanje dok se ne promeni adresa (sto automatski vraca `location_source` na `geocoded`) ili dok se ne pozove reset endpoint. U DEV modu ispod mape se ispisuju lat/lng radi debug-a.
- Search v2 (MeiliSearch):
  - Pokretanje lokalno (Docker): `docker run --rm -p 7700:7700 -e MEILI_MASTER_KEY=masterKey getmeili/meilisearch:v1.8`.
  - Env: `SEARCH_DRIVER=meili`, `MEILISEARCH_HOST=http://localhost:7700`, `MEILISEARCH_KEY=masterKey`, `MEILISEARCH_INDEX=listings`.
  - Reindex: `php artisan search:listings:reindex`.
  - Clean rebuild (drop + reindex): `php artisan search:listings:reindex --reset`.
  - Frontend flag: `VITE_SEARCH_V2=true` (koristi `/api/v1/search/listings` + `/api/v1/search/suggest`).

## Frontend (Vue 3 + Vite)
- Lokacija: `/frontend`
- Port: `http://localhost:5173`
- Env primer (`frontend/.env.example`):
  - `VITE_API_BASE_URL=` (prazno koristi dev proxy ka backend-u)
  - `VITE_USE_MOCK_API=true` (default za bezbedan start)
- Pokretanje: `npm install && cp .env.example .env && npm run dev`
- Build: `npm run build` (zahteva Node 20.19+ ili 22.12+; dev server radi, ali CI i build treba pokretati na Node 20+)
- Unit testovi: `npm run test`
- E2E smoke (mock API): `npm run test:e2e` (prvo pokretanje: `npx playwright install --with-deps chromium`)
- Admin UI: `/admin` (KPI), `/admin/moderation` (prijave), `/admin/ratings` (ocene). Impersonacija prikazuje žuti baner sa tasterom „Stop“.

## API modovi
- Mock mod (`VITE_USE_MOCK_API=true`): koristi `services/mockApi.ts`, role switch na Profilu ostaje vidljiv.
- Real API (`VITE_USE_MOCK_API=false`): koristi Laravel, zahteva login/register; route guard vraća na `/login` ako nema aktivne sesije (cookie auth).

## Reference
- API ugovor: `docs/api-contract.md`
- Primer cURL zahteva: `docs/api-examples.md`
- UI smernice: `docs/ui.md`, referentne slike u `docs/ui-reference/`
