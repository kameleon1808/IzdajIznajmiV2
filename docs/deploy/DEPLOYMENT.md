# Deployment Guide (Phase C2)

This app runs as a Laravel API (`backend/`) plus a built SPA (`frontend/`). Target stack: Ubuntu 22.04+, Nginx, PHP-FPM 8.2/8.3, MySQL/MariaDB/PostgreSQL, Supervisor for queues, cron for the scheduler.

## Prerequisites
- Packages: `php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-gd php8.3-curl php8.3-zip git unzip curl supervisor nginx`
- Composer installed globally; Node.js 20+ only if building the frontend on the server.
- Database ready (MySQL/MariaDB/PostgreSQL); create a user with least privileges.
- DNS + TLS: terminate TLS on Nginx (not covered here).

## Environments
- **Staging:** use `backend/.env.example.staging` as a template; set `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, DB creds, and `APP_KEY` (`php artisan key:generate --show` then paste).
- **Production:** use `backend/.env.example.production`; same keys as staging with production domains.
- Keep `.env` files only on the servers; never commit secrets.

Key environment variables to verify:
- `APP_KEY`, `APP_URL`, `APP_VERSION`
- `DB_*`
- `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URL`, `FRONTEND_URLS`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`
- `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`
- `GEOCODER_DRIVER` (`nominatim` in prod/staging, `fake` locally), `SENTRY_DSN` (optional)
- `QUEUE_FAILED_JOBS_ALERT_ENABLED`, `QUEUE_FAILED_JOBS_ALERT_THRESHOLD`, `QUEUE_FAILED_JOBS_ALERT_COOLDOWN_SECONDS`
- `SECURITY_HEADERS_ENABLED`, `SECURITY_HSTS_ENABLED`, `SECURITY_CSP_ENABLED`, `SECURITY_CSP_REPORT_ONLY`

## Directory layout on server
```
/var/www/izdaji        # repo root (git clone)
├─ backend/.env        # per-environment
├─ frontend/dist       # built SPA assets (copied or built on server)
└─ ops/                # deploy scripts/configs
```

## Nginx
Use `ops/nginx-site.conf` as a starting point. Points SPA root to `frontend/dist`, proxies `/api` and `/sanctum` to Laravel (php-fpm), serves `/storage` from `backend/public/storage`. Set `server_name`, TLS, and PHP-FPM socket path.
If running behind another load balancer, forward `X-Forwarded-Proto` and keep `APP_URL` set to the public https URL so Sanctum cookies stay secure.

## Queues & Scheduler
- Queue worker via Supervisor: copy `ops/supervisor-queue.conf` to `/etc/supervisor/conf.d/`, adjust paths/user, then `supervisorctl reread && supervisorctl update && supervisorctl restart izdaji-queue:*`.
- Scheduler via cron: copy `ops/cron.txt` line into `crontab -e` (root or web user). Runs `schedule:run` every minute.
- Scheduled tasks:
  - `listings:expire` daily 02:00
  - `notifications:digest --frequency=daily` daily 09:00
  - `notifications:digest --frequency=weekly` weekly Monday 09:00
  - `saved-searches:match` every 15 minutes
  - Geocode backfill (`listings:geocode --missing`) is manual or staging-only warmup.
- Failed jobs ops and queue observability runbook: `docs/ops/QUEUE-OPS.md`.

## Backups (PostgreSQL)
- Backup script: `ops/backup_pg.sh`
- Restore script: `ops/restore_pg.sh`
- Runbook: `docs/ops/BACKUPS.md`
- Cron sample is in `ops/cron.txt`.
- Systemd sample units are in `ops/systemd/pg-backup.service` and `ops/systemd/pg-backup.timer`.

## Deploy script (manual or CI)
`ops/deploy.sh` is idempotent. It runs: composer install (no-dev), migrate --force, config/route/view cache, queue restart, optional staging geocode warmup, single `schedule:run`, and a health ping. Required env vars:
- `ENVIRONMENT=staging|production` (defaults to production)
- `APP_DIR=/var/www/izdaji` if the repo is elsewhere
- `HEALTH_URL` override for remote health (default `http://127.0.0.1/api/v1/health`)
- `FRONTEND_BUILD=1` to build SPA on the server (Node 20+ required)
- `APP_VERSION` is auto-derived from `git rev-parse --short HEAD` unless explicitly overridden.

Manual run:
```bash
cd /var/www/izdaji
ENVIRONMENT=staging HEALTH_URL=https://api.staging.izdaji.example/api/v1/health ./ops/deploy.sh
```

## Rollback
`ops/rollback.sh` checks out a previous ref (default `HEAD~1`), reinstalls composer deps, rebuilds caches, restarts queues, and pings health. Safe migrations are **off** by default; set `ALLOW_MIGRATE=1` only if the schema change is backward compatible.
`APP_VERSION` is auto-derived from the rollback ref hash unless explicitly overridden.
```bash
cd /var/www/izdaji
ROLLBACK_REF=v1.2.3 ./ops/rollback.sh           # no migrations
ALLOW_MIGRATE=1 ROLLBACK_REF=v1.2.3 ./ops/rollback.sh
```

## GitHub Actions deploy (SSH)
- Workflows: `.github/workflows/deploy-staging.yml` and `deploy-production.yml`.
- Secrets needed:
  - Staging: `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_KEY` (private key), `STAGING_DEPLOY_PATH` (e.g., `/var/www/izdaji`), `STAGING_BRANCH` (default `staging`), optional `STAGING_HEALTH_URL`.
  - Production: `PROD_SSH_HOST`, `PROD_SSH_USER`, `PROD_SSH_KEY`, `PROD_DEPLOY_PATH`, `PROD_BRANCH` (default `main`), optional `PROD_HEALTH_URL`.
- The workflow SSH-es into the server, pulls the branch, and runs `ops/deploy.sh` with the right `ENVIRONMENT`. A post-deploy `curl --fail` hits `/api/v1/health`.

## Frontend build/serve
- Build locally or in CI: `cd frontend && npm ci && npm run build`, then sync `frontend/dist/` to the server path used by Nginx.
- If building on the server, set `FRONTEND_BUILD=1` when running `ops/deploy.sh` and ensure Node 20+ is installed.
- Frontend env: `frontend/.env.production` (`VITE_API_BASE_URL=https://api.izdaji.example`, `VITE_USE_MOCK_API=false`). For same-origin deployments you can leave `VITE_API_BASE_URL` blank and rely on `/api` relative paths.

## Health & smoke checks
- Liveness: `GET /api/v1/health` (returns status + app version + DB check).
- Readiness: `GET /api/v1/health/ready` (DB, cache, queue driver).
- Queue health: `GET /api/v1/health/queue` (queue connectivity + `failed_jobs` count + alert state).
- After deploy, expect HTTP 200; failures return 500 with minimal error strings.

## Security Headers and Cookies
- Backend emits `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy` by default (`SECURITY_HEADERS_ENABLED=true`).
- HSTS is env-gated and off by default (`SECURITY_HSTS_ENABLED=false`); enable only on HTTPS production hosts.
- CSP is env-gated and starts in report-only mode by default (`SECURITY_CSP_ENABLED=false`, `SECURITY_CSP_REPORT_ONLY=true`).
- Frontend static header examples are included in `ops/nginx-site.conf`.
- Cookie baseline:
  - `SESSION_SECURE_COOKIE=true` in production HTTPS
  - `SESSION_SAME_SITE=lax` (or `none` only with strict cross-site requirements + secure)
  - `SESSION_HTTP_ONLY=true`

## Promotion checklist (staging → production)
1) Deploy to staging branch; wait for queues/scheduler healthy.
2) Run smoke: health endpoints, login, create listing draft, send message, run saved-search match (`php artisan saved-searches:match`), confirm notification digest job enqueues.
3) Tag release (`git tag vX.Y.Z && git push origin vX.Y.Z`).
4) Deploy production workflow; verify health and key flows; monitor logs for 15 minutes.

## Troubleshooting
- 502/504: check php-fpm service and socket path in Nginx; ensure `storage/` is writable by `www-data`.
- Cookies/auth: align `SANCTUM_STATEFUL_DOMAINS` with the SPA host, `SESSION_DOMAIN` with the parent domain, enable HTTPS and `SESSION_SECURE_COOKIE=true`.
- Migrations blocked: `php artisan queue:work` should be stopped during long migrations; run `php artisan down` if necessary, then `php artisan up` after deploy.
- CORS: `FRONTEND_URLS` must include the exact scheme+host+port; cache may need `php artisan config:clear` before `config:cache`.
