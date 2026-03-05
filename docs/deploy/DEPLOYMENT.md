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
- `SECURITY_HEADERS_ENABLED`, `SECURITY_PERMISSIONS_POLICY`, `SECURITY_HSTS_ENABLED`, `SECURITY_HSTS_PRELOAD`, `SECURITY_CSP_ENABLED`, `SECURITY_CSP_REPORT_ONLY`, `SECURITY_CSP_POLICY`

## Directory layout on server
```
/var/www/izdaji        # repo root (git clone)
├─ backend/.env        # per-environment
├─ frontend/dist       # built SPA assets (copied or built on server)
└─ ops/                # deploy scripts/configs
```

## Nginx
Use `ops/nginx-site.conf` as a starting point. Points SPA root to `frontend/dist`, proxies `/api` and `/sanctum` to Laravel (php-fpm), serves `/storage` from `backend/public/storage`. Set `server_name`, TLS certificate paths, and PHP-FPM socket path.

TLS is pre-configured for TLS 1.2/1.3 only with ECDHE cipher suites, OCSP stapling, and disabled session tickets. HTTP is redirected to HTTPS. See `docs/security/TRANSPORT-SECURITY.md` for full details and verification commands.

For Docker deployments (`docker-compose.production.yml`), use `ops/nginx-docker-production.conf`. TLS is terminated upstream (load balancer or Cloudflare Tunnel); the gateway only handles HTTP internally.

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
Headers are emitted at two levels: Nginx (edge) and Laravel's `SecurityHeadersMiddleware` (API responses). See `docs/security/TRANSPORT-SECURITY.md` for the full reference including CSP policy, nonce usage, HSTS configuration, and verification curl commands.

Summary of production defaults (from `backend/.env.example.production`):
- `SECURITY_HEADERS_ENABLED=true` — emits `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` on every response
- `SECURITY_HSTS_ENABLED=true`, `SECURITY_HSTS_PRELOAD=true` — HSTS with 1-year max-age, includeSubDomains, preload (only on HTTPS + `APP_ENV=production`)
- `SECURITY_CSP_ENABLED=true`, `SECURITY_CSP_REPORT_ONLY=false` — CSP enforced; policy includes `'nonce-{nonce}'` placeholder replaced per-request
- `SECURITY_PERMISSIONS_POLICY` — disables camera, microphone, payment, USB; allows geolocation for self

Cookie baseline:
- `SESSION_SECURE_COOKIE=true` in production HTTPS
- `SESSION_SAME_SITE=lax`
- `SESSION_HTTP_ONLY=true`

## Promotion checklist (staging → production)
1) Deploy to staging branch; wait for queues/scheduler healthy.
2) Run smoke: health endpoints, login, create listing draft, send message, run saved-search match (`php artisan saved-searches:match`), confirm notification digest job enqueues.
3) Tag release (`git tag vX.Y.Z && git push origin vX.Y.Z`).
4) Deploy production workflow; verify health and key flows; monitor logs for 15 minutes.

## Local Hosting via Cloudflare Tunnel

An alternative to VPS deployment: run the full stack on a local machine and expose it publicly via Cloudflare Tunnel. No port forwarding required.

### How it works

```
Browser → Cloudflare (izdajiznajmi.com) → cloudflared tunnel → Docker gateway (port 80)
```

The `docker-compose.production.yml` includes a `tunnel` service (profile `public`) that runs `cloudflared` inside Docker and routes traffic from `izdajiznajmi.com` to the `gateway` container.

### One-time setup (per machine)

Requires: domain managed by Cloudflare (nameservers pointing to Cloudflare).

```bash
# Install cloudflared (WSL/Linux)
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb

# Fix directory ownership if needed (Docker may have created it as root)
sudo chown $USER:$USER ~/.cloudflared

# Authenticate and create tunnel
cloudflared tunnel login
cloudflared tunnel create izdajiznajmi
cloudflared tunnel route dns izdajiznajmi izdajiznajmi.com
```

Create `~/.cloudflared/config.yml` (replace UUID with output from `tunnel create`):
```yaml
tunnel: <TUNNEL-UUID>
credentials-file: /home/nonroot/.cloudflared/<TUNNEL-UUID>.json

ingress:
  - hostname: izdajiznajmi.com
    service: http://gateway:80
  - service: http_status:404
```

Set file permissions so Docker's `nonroot` user can read the credentials:
```bash
chmod o+r ~/.cloudflared/<TUNNEL-UUID>.json
chmod o+rx ~/.cloudflared
```

### Running

> **Important:** Always include `--env-file .env.production.compose`. Omitting it causes the gateway to use wrong ports and the frontend to start in dev mode (port 5173 instead of the expected 4173), resulting in 502 errors.

```bash
# Recommended: set an alias to avoid mistakes
alias dc-prod='docker compose --env-file .env.production.compose -f docker-compose.production.yml --profile public'

# Start full stack with public tunnel (builds frontend — takes several minutes on first run)
dc-prod up -d --build

# Run migrations (first run or after schema changes)
dc-prod exec backend php artisan migrate --force

# Check tunnel status
dc-prod logs tunnel --tail=20

# Monitor frontend build progress
dc-prod logs -f frontend
# Wait for: "➜  Network: http://172.18.x.x:4173/" — site is live when this appears
```

A healthy tunnel log shows:
```
INF Registered tunnel connection connIndex=0 ...
INF Registered tunnel connection connIndex=1 ...
```

### Dev vs production compose

The repo contains two compose files:

| File | Frontend | Use case |
|---|---|---|
| `docker-compose.yml` | `npm run dev` on port **5173** (no build) | Local development only |
| `docker-compose.production.yml` | `npm run build && npm run preview` on port **4173** | Production / Cloudflare tunnel |

The nginx gateway (`ops/nginx-docker-production.conf`) always proxies to `frontend:4173`. Starting the stack with `docker-compose.yml` by mistake will cause 502 on the frontend because port 4173 is not listening.

**Always use `docker-compose.production.yml --env-file .env.production.compose` for any environment that goes through the gateway.**

### Docker projects

Two Compose projects use the same `docker-compose.production.yml`. They can run simultaneously — production uses port 80 (via Cloudflare Tunnel), dev uses port 8090.

| Project name | Command flag | Purpose | Gateway port |
|---|---|---|---|
| `izdajiznajmiv2` | _(no `-p` flag)_ | Main production — public on `izdajiznajmi.com` | 80 |
| `izdaji_dev` | `-p izdaji_dev` | Development/testing — local at `http://localhost:8090` | 8090 |

### Caveats

- The machine must remain on and connected for the site to be accessible.
- Cloudflare Tunnel credentials (`~/.cloudflared/`) must be preserved; re-run setup if they are lost.
- Domain propagation after nameserver change can take up to 48 hours.

## Troubleshooting
- **502 on Docker stack:** most likely cause is the frontend running in dev mode (wrong compose file used). Check with `docker inspect <frontend-container> --format '{{.Config.Cmd}}'` — it must show `npm run build && npm run preview` and port 4173, not `npm run dev` and port 5173. Fix: `dc-prod down && dc-prod up -d --build`.
- 502/504: check php-fpm service and socket path in Nginx; ensure `storage/` is writable by `www-data`.
- Cookies/auth: align `SANCTUM_STATEFUL_DOMAINS` with the SPA host, `SESSION_DOMAIN` with the parent domain, enable HTTPS and `SESSION_SECURE_COOKIE=true`.
- Migrations blocked: `php artisan queue:work` should be stopped during long migrations; run `php artisan down` if necessary, then `php artisan up` after deploy.
- CORS: `FRONTEND_URLS` must include the exact scheme+host+port; cache may need `php artisan config:clear` before `config:cache`.
