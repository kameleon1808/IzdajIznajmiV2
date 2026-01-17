# IzdajIznajmiV2 Backend API (Laravel)

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- SPA dev origin: `http://localhost:5173`
- Auth: Sanctum SPA cookies (`/sanctum/csrf-cookie` + session) with canonical routes under `/api/v1/auth/*` (legacy `/api/auth/*` kept temporarily)
- Stateful dev defaults: `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, `SESSION_DOMAIN=localhost`, CORS `supports_credentials=true`
- Demo users (password `password`):
  - admin@example.com (admin)
  - lana@demo.com, leo@demo.com (landlords)
  - tena@demo.com, tomas@demo.com, tara@demo.com (seekers)

## Docs
- Contract: `docs/api-contract.md`
- cURL snippets: `docs/api-examples.md`

## Tests
```bash
php artisan test
```
