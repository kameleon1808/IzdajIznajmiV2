# IzdajIznajmiV2 Backend API (Laravel)

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan schedule:work   # runs listings:expire auto-expiry, saved-searches:match, + notification digests (daily 09:00, weekly Monday 09:00)
# optional for image pipeline:
# php artisan queue:work
php artisan serve
```

- SPA dev origin: `http://localhost:5173`
- Auth: Sanctum SPA cookies (`/sanctum/csrf-cookie` + session) with canonical routes under `/api/v1/auth/*` (legacy `/api/auth/*` kept temporarily)
- Stateful dev defaults: `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, `SESSION_DOMAIN=localhost`, CORS `supports_credentials=true`
- Demo users (password `password`):
  - admin@example.com (admin)
  - lana@demo.com, leo@demo.com (landlords)
  - tena@demo.com, tomas@demo.com, tara@demo.com (seekers)
- Listings parity: statuses `draft/active/paused/archived/rented/expired`, duplicate-address guard rails (block same-landlord active duplicates; warn cross-landlord), discovery filters include city/rooms/area/amenities/status, `listings:expire` auto-expires active listings after 30 days.
- Notifications: in-app notification system with preferences and digest support. Run `php artisan notifications:digest --frequency=daily|weekly` manually or via scheduler (daily at 09:00, weekly Monday at 09:00). Notification types: `listing.new_match`, `application.created`, `application.status_changed`, `message.received`, `rating.received`, `report.update`, `admin.notice`, `digest.daily`, `digest.weekly`.
- Saved searches: matcher runs via scheduler (`php artisan schedule:work` in dev). In production, configure a cron entry to run the scheduler every minute: `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`.
- Notification deep links (SPA, cookie auth):
  - Chat: `/chat?conversationId={id}` (all participants), `/chat?listingId={id}` (seekers) or `/chat?applicationId={id}` (landlords); resolves to `/chat/{conversationId}` after hydration.
  - Applications: `/applications?applicationId={id}` (seekers) and `/applications?applicationId={id}` for landlords (opens requests tab).
  - Listings: `/listing/{id}` always resolves and hydrates.
  - Admin moderation: `/admin/moderation/reports/{id}` (admins only).

## Docs
- Contract: `docs/api-contract.md`
- cURL snippets: `docs/api-examples.md`

## Tests
```bash
php artisan test
```
