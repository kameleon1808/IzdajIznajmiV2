# Docker Manual (Project: IzdajIznajmiV2)

Quick reference for running, controlling, and troubleshooting Docker environments.

## Basic Commands
- Start (first run or after image changes):
```bash
docker compose up --build
```
- Start in background:
```bash
docker compose up -d --build
```
- Stop and remove containers:
```bash
docker compose down
```

## Status and Logs
- Service status (from repo root):
```bash
docker compose ps
```
- Running containers:
```bash
docker ps
```
- All containers (including stopped):
```bash
docker ps -a
```
- All service logs:
```bash
docker compose logs
```
- Logs for a specific service (example backend):
```bash
docker compose logs --tail=200 backend
```
- Follow logs live:
```bash
docker compose logs -f
```

## Entering Containers
- Backend shell:
```bash
docker compose exec backend sh
```
- One-off backend command:
```bash
docker compose exec -T backend php artisan migrate:fresh --seed
```

## Initial Backend Tasks
- Migrations + seed:
```bash
docker compose run --rm backend php artisan migrate:fresh --seed
```
- MeiliSearch reindex:
```bash
docker compose run --rm backend php artisan search:listings:reindex --reset
```

## Restart and Recovery
- Restart selected services:
```bash
docker compose restart queue scheduler reverb
```
- Start only selected services:
```bash
docker compose up -d queue scheduler reverb
```

## Cleanup
- Stop and remove containers/networks:
```bash
docker compose down
```
- Remove volumes too (deletes DB/storage/node_modules data):
```bash
docker compose down -v
```

## Ports
- API: `http://localhost:8000`
- Frontend: `http://localhost:5173`
- MeiliSearch: `http://localhost:7700`

## Production Compose (separate from local dev)
- Compose file: `docker-compose.production.yml`
- Env template: `.env.production.compose.example`
- Push/PWA release notes: `docs/releases/notifications/web-push-pwa-rollout-2026-02-20.md`

### Docker Projects — Overview

Two Docker Compose projects use the same `docker-compose.production.yml`.
**Important: they cannot run at the same time — both bind port 80.**

| Project | Name | Purpose |
|---|---|---|
| `izdajiznajmiv2` | main production | publicly available at `izdajiznajmi.com` (named tunnel) |
| `izdaji_prod` | development/testing | local at `localhost`, start on demand |

---

### Main Production Stack (`izdajiznajmiv2`)

> Uses named Cloudflare Tunnel → `izdajiznajmi.com`

Optional alias:
```bash
DC="docker compose -f docker-compose.production.yml"
```

- Start (with tunnel):
```bash
docker compose -f docker-compose.production.yml --profile public up -d
```
- Stop:
```bash
docker compose -f docker-compose.production.yml --profile public down
```
- Migrations:
```bash
docker compose -f docker-compose.production.yml exec backend php artisan migrate --force
```
- Health check:
```bash
curl -f http://localhost/api/v1/health
```
- Tunnel logs:
```bash
docker compose -f docker-compose.production.yml logs tunnel --tail=50
```

---

### Development/Testing Stack (`izdaji_prod`)

> Locally available at `http://localhost`. Start on demand — stop the production stack first.

Optional alias:
```bash
DC="docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml"
```

- Prepare env:
```bash
cp .env.production.compose.example .env.production.compose
```
- Start (local only, no tunnel):
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --build
```
- Initial migrations + seed:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan migrate:fresh --seed
```
- Health check:
```bash
curl -f http://localhost/api/v1/health
```
- Stop:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml down
```

---

### Cloudflare Named Tunnel — One-time Setup

> Must be done once on every machine that hosts the application.

**Prerequisites:** domain must be added to the Cloudflare account (nameservers active).

1. Install `cloudflared` on WSL:
```bash
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb
```

2. Fix permissions and authenticate:
```bash
sudo chown $USER:$USER ~/.cloudflared
cloudflared tunnel login
```
_(open the URL in your browser and authorize the domain)_

3. Create tunnel and DNS record:
```bash
cloudflared tunnel create izdajiznajmi
cloudflared tunnel route dns izdajiznajmi izdajiznajmi.com
```

4. Create `~/.cloudflared/config.yml` (replace UUID):
```yaml
tunnel: <TUNNEL-UUID>
credentials-file: /home/nonroot/.cloudflared/<TUNNEL-UUID>.json

ingress:
  - hostname: izdajiznajmi.com
    service: http://gateway:80
  - service: http_status:404
```

5. Set permissions for the Docker container:
```bash
chmod o+r ~/.cloudflared/<TUNNEL-UUID>.json
chmod o+rx ~/.cloudflared
```

After this, starting with `--profile public` automatically uses `izdajiznajmi.com`.

## Env Priority in Production (`.env.production` vs `.env`)
- If `APP_ENV=production`, Laravel loads `backend/.env.production` when it exists.
- In that case values from `backend/.env` can be ignored at runtime.
- `.env.production` takes precedence over Docker Compose environment variable defaults — keep it up to date when domains change.

Practical rule:
- Local/dev: maintain `backend/.env`
- Production compose: maintain `backend/.env.production`

Quick runtime verification:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan tinker --execute="dump(config('mail.default')); dump(config('mail.mailers.smtp.host')); dump(config('mail.mailers.smtp.port'));"
```

If mail still shows `log/127.0.0.1/2525`:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan optimize:clear
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --force-recreate backend queue scheduler reverb
```

## Important: How File Changes Become Visible
- Production test stack uses bind mounts for `./backend` and `./frontend`.
- Local file edits are immediately visible to those running containers.
- Result: the same source tree can affect both local and production-test URLs.
- For full isolation, use a separate clone or image-only deployment without bind mounts.

## When Extra Actions Are Required
- Backend `.php` changes: usually visible immediately after refresh.
- Frontend changes in production stack: rebuild frontend service (no HMR in production mode):
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --build frontend
```
- Alternative if frontend container is already running:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec frontend npm run build
```
- When changing `.env`, `config/*`, routes, or middleware:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan optimize:clear
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan event:clear
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --force-recreate backend queue scheduler reverb
```
- When changing event/listener wiring (for example notifications/chat events), verify registrations:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan event:list
```
- When changing migrations:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan migrate --force
```

## Docker Compose Watch
- `watch` can sync/rebuild on file changes.
- It is not required in this project because bind mounts are already used.
- If an interactive watch menu appears, detach with `d`.
