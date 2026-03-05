#!/usr/bin/env bash
# ops/sync.sh — sync local changes into a running Docker environment
#
# Usage:
#   ops/sync.sh [dev|prod|all] [OPTIONS]
#
# Targets:
#   dev   — izdaji_dev container  (docker compose -p izdaji_dev --env-file .env.production.compose)
#   prod  — production container   (docker compose, default project name)
#   all   — both dev and prod
#
# Options:
#   --frontend-only   skip backend artisan steps
#   --backend-only    skip frontend rebuild
#   --skip-migrate    skip database migrations
#   --skip-cache      skip config/route/view cache rebuild
#   --skip-queue      skip queue:restart
#
# Examples:
#   ops/sync.sh dev                  # frontend rebuild + artisan → izdaji_dev
#   ops/sync.sh prod --backend-only  # only artisan commands on prod
#   ops/sync.sh all --skip-migrate   # sync both stacks, skip migrations
#   ops/sync.sh dev --frontend-only  # only rebuild frontend in dev

set -euo pipefail

BOLD='\033[1m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; RESET='\033[0m'
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

log()  { echo -e "${GREEN}[sync]${RESET} $*"; }
warn() { echo -e "${YELLOW}[sync]${RESET} $*"; }
err()  { echo -e "${RED}[sync] ERROR:${RESET} $*" >&2; }

# ── defaults ──────────────────────────────────────────────────────────────────
TARGET="${1:-dev}"
shift || true

FRONTEND_ONLY=0
BACKEND_ONLY=0
SKIP_MIGRATE=0
SKIP_CACHE=0
SKIP_QUEUE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --frontend-only) FRONTEND_ONLY=1 ;;
    --backend-only)  BACKEND_ONLY=1  ;;
    --skip-migrate)  SKIP_MIGRATE=1  ;;
    --skip-cache)    SKIP_CACHE=1    ;;
    --skip-queue)    SKIP_QUEUE=1    ;;
    *)
      err "Unknown flag: $1"
      echo "Usage: $0 [dev|prod|all] [--frontend-only] [--backend-only] [--skip-migrate] [--skip-cache] [--skip-queue]" >&2
      exit 1
      ;;
  esac
  shift
done

# ── Docker Compose aliases ────────────────────────────────────────────────────
DEV_COMPOSE="docker compose -p izdaji_dev --env-file ${APP_DIR}/.env.production.compose -f ${APP_DIR}/docker-compose.production.yml"
PROD_COMPOSE="docker compose -f ${APP_DIR}/docker-compose.production.yml"

# ── frontend rebuild (inside container) ──────────────────────────────────────
frontend_build() {
  local compose_cmd="$1"
  local env_name="$2"

  log "Rebuilding frontend [${env_name}]..."
  $compose_cmd exec -T frontend npm run build
  log "Frontend rebuild done [${env_name}]."
}

# ── backend artisan sync ──────────────────────────────────────────────────────
backend_sync() {
  local compose_cmd="$1"
  local env_name="$2"

  log "Backend sync [${env_name}]..."

  if [[ "$SKIP_CACHE" != "1" ]]; then
    log "  Clearing and rebuilding config/route/view cache..."
    $compose_cmd exec -T backend php artisan config:clear
    $compose_cmd exec -T backend php artisan cache:clear
    $compose_cmd exec -T backend php artisan config:cache
    $compose_cmd exec -T backend php artisan route:cache
    $compose_cmd exec -T backend php artisan view:cache
  fi

  if [[ "$SKIP_MIGRATE" != "1" ]]; then
    log "  Running migrations..."
    $compose_cmd exec -T backend php artisan migrate --force
  fi

  if [[ "$SKIP_QUEUE" != "1" ]]; then
    log "  Restarting queue workers..."
    $compose_cmd exec -T backend php artisan queue:restart || true
  fi

  log "Backend sync done [${env_name}]."
}

# ── sync one target ───────────────────────────────────────────────────────────
sync_target() {
  local compose_cmd="$1"
  local env_name="$2"

  if [[ "$BACKEND_ONLY" != "1" ]]; then
    frontend_build "$compose_cmd" "$env_name"
  fi

  if [[ "$FRONTEND_ONLY" != "1" ]]; then
    backend_sync "$compose_cmd" "$env_name"
  fi
}

# ── main ──────────────────────────────────────────────────────────────────────
if [[ "$TARGET" != "dev" && "$TARGET" != "prod" && "$TARGET" != "all" ]]; then
  err "Unknown target: ${TARGET}. Use: dev | prod | all"
  exit 1
fi

flags=""
[[ "$FRONTEND_ONLY" == "1" ]] && flags+=" frontend-only"
[[ "$BACKEND_ONLY"  == "1" ]] && flags+=" backend-only"
[[ "$SKIP_MIGRATE"  == "1" ]] && flags+=" skip-migrate"
[[ "$SKIP_CACHE"    == "1" ]] && flags+=" skip-cache"
[[ "$SKIP_QUEUE"    == "1" ]] && flags+=" skip-queue"

echo -e "${BOLD}[sync] target=${TARGET}${flags}${RESET}"

case "$TARGET" in
  dev)
    sync_target "$DEV_COMPOSE" "dev"
    ;;
  prod)
    sync_target "$PROD_COMPOSE" "prod"
    ;;
  all)
    sync_target "$DEV_COMPOSE"  "dev"
    sync_target "$PROD_COMPOSE" "prod"
    ;;
esac

echo -e "${BOLD}${GREEN}[sync] All done!${RESET}"
