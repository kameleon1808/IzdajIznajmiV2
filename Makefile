.PHONY: install build dev migrate cache up down up-prod down-prod restart-prod deploy

# ── Dependencies (runs on host) ────────────────────────────────────────────────
install:
	cd backend && composer install
	cd frontend && npm ci

# ── Frontend (runs on host) ────────────────────────────────────────────────────
build:
	npm run build --prefix frontend

dev:
	npm run dev --prefix frontend

# ── Backend artisan ────────────────────────────────────────────────────────────
migrate:
	docker compose exec backend php artisan migrate

cache:
	docker compose exec backend php artisan config:cache
	docker compose exec backend php artisan route:cache
	docker compose exec backend php artisan view:cache

# ── Dev stack (postgres, backend, queue, scheduler, reverb, meilisearch) ───────
up:
	docker compose up -d

down:
	docker compose down

# ── Production stack ───────────────────────────────────────────────────────────
up-prod:
	docker compose -f docker-compose.production.yml up -d

down-prod:
	docker compose -f docker-compose.production.yml down

restart-prod:
	docker compose -f docker-compose.production.yml restart

# ── Full deploy (migrates + builds frontend + health check) ────────────────────
deploy:
	./ops/deploy.sh
