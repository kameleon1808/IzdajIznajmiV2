#!/usr/bin/env bash
set -euo pipefail

PGHOST=${PGHOST:-127.0.0.1}
PGPORT=${PGPORT:-5432}
PGDATABASE=${PGDATABASE:-}
PGUSER=${PGUSER:-}
BACKUP_DIR=${BACKUP_DIR:-/var/backups/izdaji/postgres}
BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-14}
PGCONNECT_TIMEOUT=${PGCONNECT_TIMEOUT:-10}

if [[ -z "${PGDATABASE}" || -z "${PGUSER}" ]]; then
  echo "PGDATABASE and PGUSER are required." >&2
  echo "Example: PGDATABASE=izdaji PGUSER=izdaji ./ops/backup_pg.sh" >&2
  exit 1
fi

mkdir -p "${BACKUP_DIR}"

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
backup_file="${BACKUP_DIR}/${PGDATABASE}_${timestamp}.sql.gz"
checksum_file="${backup_file}.sha256"

echo "[backup] creating ${backup_file}"
PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT}" pg_dump \
  --host="${PGHOST}" \
  --port="${PGPORT}" \
  --username="${PGUSER}" \
  --format=plain \
  --no-owner \
  --no-privileges \
  "${PGDATABASE}" | gzip -c > "${backup_file}"

sha256sum "${backup_file}" > "${checksum_file}"

echo "[backup] pruning backups older than ${BACKUP_RETENTION_DAYS} days in ${BACKUP_DIR}"
find "${BACKUP_DIR}" -type f -name "${PGDATABASE}_*.sql.gz" -mtime +"${BACKUP_RETENTION_DAYS}" -delete
find "${BACKUP_DIR}" -type f -name "${PGDATABASE}_*.sql.gz.sha256" -mtime +"${BACKUP_RETENTION_DAYS}" -delete

echo "[backup] done ${backup_file}"
