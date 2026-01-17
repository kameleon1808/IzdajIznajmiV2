# IzdajIznajmi V2 – Frontend

Vue 3 + Vite SPA (mobile-first) sa Tailwind-om, Pinia store-ovima i dualnim API slojem (mock ili realni Laravel backend).

## Quick start
```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

- Backend default: `http://localhost:8000` (Laravel API u /backend).
- Frontend dev: `http://localhost:5173` (Vite proxy preusmerava `/api` i `/sanctum` na backend radi cookie auth-a)

## Environment
- `VITE_API_BASE_URL` – baza za API (ostavite prazno za dev proxy; postavite na backend URL u produkciji)
- `VITE_USE_MOCK_API` – `true` (default) koristi lokalni mock; `false` koristi realni backend.

Promenite `.env` i restartujte `npm run dev` kad menjate mod.

## Auth (real API)
- Sanctum cookie/session flow: klijent automatski zove `/sanctum/csrf-cookie`, šalje `withCredentials: true`, i koristi `/api/v1/auth/*` rute (legacy `/api/auth/*` ostaje privremeno).
- Demo nalozi (password `password`): `admin@example.com`, `lana@demo.com`, `leo@demo.com`, `tena@demo.com`, `tomas@demo.com`, `tara@demo.com`.
- Login/Register stranice su dostupne na `/login` i `/register`. Zaštićene rute preusmeravaju na login sa `returnUrl`.

## Mock mod (dev)
- Ako je `VITE_USE_MOCK_API=true`, role switch na Profile stranici radi za brzo testiranje, a svi podaci se učitavaju iz `services/mockApi.ts`.

## Build
```bash
npm run build
```
