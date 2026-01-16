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
- Auth: Sanctum personal access tokens (send `Authorization: Bearer <token>`)
- Demo users (password `password`):
  - admin@example.com (admin)
  - lana@demo.com, leo@demo.com (landlords)
  - tena@demo.com, tomas@demo.com, tara@demo.com (tenants)

## Docs
- Contract: `docs/api-contract.md`
- cURL snippets: `docs/api-examples.md`

## Tests
```bash
php artisan test
```
