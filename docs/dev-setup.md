# Dev setup (frontend + backend)

## Backend (Laravel API)
- Lokacija: `/backend`
- Port: `http://localhost:8000`
- Pokretanje (primer): `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate:fresh --seed && php artisan serve`
- Auth: Sanctum Bearer token; demo korisnici iz seedera (email + `password`).
- CORS: dozvoljen `http://localhost:5173` za SPA.

## Frontend (Vue 3 + Vite)
- Lokacija: `/frontend`
- Port: `http://localhost:5173`
- Env primer (`frontend/.env.example`):
  - `VITE_API_BASE_URL=http://localhost:8000`
  - `VITE_USE_MOCK_API=true` (default za bezbedan start)
- Pokretanje: `npm install && cp .env.example .env && npm run dev`
- Build: `npm run build`

## API modovi
- Mock mod (`VITE_USE_MOCK_API=true`): koristi `services/mockApi.ts`, role switch na Profilu ostaje vidljiv.
- Real API (`VITE_USE_MOCK_API=false`): koristi Laravel, zahteva login/register; route guard vraća na `/login` ako nema tokena.

## Reference
- API ugovor: `docs/api-contract.md`
- Primer cURL zahteva: `docs/api-examples.md`
- UI smernice: `docs/ui.md`, referentne slike u `docs/ui-reference/`
