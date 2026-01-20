# Dev setup (frontend + backend)

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
- Scheduler (auto-expire listings): `php artisan schedule:work` uključuje zadatak `listings:expire` koji dnevno prebacuje stare aktivne oglase u `expired`.

## Frontend (Vue 3 + Vite)
- Lokacija: `/frontend`
- Port: `http://localhost:5173`
- Env primer (`frontend/.env.example`):
  - `VITE_API_BASE_URL=` (prazno koristi dev proxy ka backend-u)
  - `VITE_USE_MOCK_API=true` (default za bezbedan start)
- Pokretanje: `npm install && cp .env.example .env && npm run dev`
- Build: `npm run build` (zahteva Node 20.19+ ili 22.12+; dev server radi na Node 18 ali produkcioni build ne).
- Admin UI: `/admin` (KPI), `/admin/moderation` (prijave), `/admin/ratings` (ocene). Impersonacija prikazuje žuti baner sa tasterom „Stop“.

## API modovi
- Mock mod (`VITE_USE_MOCK_API=true`): koristi `services/mockApi.ts`, role switch na Profilu ostaje vidljiv.
- Real API (`VITE_USE_MOCK_API=false`): koristi Laravel, zahteva login/register; route guard vraća na `/login` ako nema aktivne sesije (cookie auth).

## Reference
- API ugovor: `docs/api-contract.md`
- Primer cURL zahteva: `docs/api-examples.md`
- UI smernice: `docs/ui.md`, referentne slike u `docs/ui-reference/`
