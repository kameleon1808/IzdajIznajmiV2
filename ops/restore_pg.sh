#!/usr/bin/env bash
set -euo pipefail

backup_file=${1:-}
if [[ -z "${backup_file}" ]]; then
  echo "Usage: $0 <backup.sql.gz|backup.sql>" >&2
  exit 1
fi

if [[ ! -f "${backup_file}" ]]; then
  echo "Backup file does not exist: ${backup_file}" >&2
  exit 1
fi

if [[ "${CONFIRM_RESTORE:-0}" != "1" ]]; then
  echo "Refusing to restore without CONFIRM_RESTORE=1." >&2
  echo "Set CONFIRM_RESTORE=1 explicitly to continue." >&2
  exit 1
fi

PGHOST=${PGHOST:-127.0.0.1}
PGPORT=${PGPORT:-5432}
PGDATABASE=${PGDATABASE:-}
PGUSER=${PGUSER:-}
PGMAINTENANCE_DB=${PGMAINTENANCE_DB:-postgres}
RECREATE_DB=${RECREATE_DB:-false}
PGCONNECT_TIMEOUT=${PGCONNECT_TIMEOUT:-10}

if [[ -z "${PGDATABASE}" || -z "${PGUSER}" ]]; then
  echo "PGDATABASE and PGUSER are required." >&2
  echo "Example: CONFIRM_RESTORE=1 PGDATABASE=izdaji_staging PGUSER=izdaji ./ops/restore_pg.sh /tmp/backup.sql.gz" >&2
  exit 1
fi

if [[ "${RECREATE_DB}" == "true" ]]; then
  echo "[restore] recreating database ${PGDATABASE}"
  PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT}" dropdb \
    --if-exists \
    --host="${PGHOST}" \
    --port="${PGPORT}" \
    --username="${PGUSER}" \
    "${PGDATABASE}"

  PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT}" createdb \
    --host="${PGHOST}" \
    --port="${PGPORT}" \
    --username="${PGUSER}" \
    --maintenance-db="${PGMAINTENANCE_DB}" \
    "${PGDATABASE}"
fi

echo "[restore] restoring ${backup_file} into ${PGDATABASE}"
if [[ "${backup_file}" == *.gz ]]; then
  gzip -dc "${backup_file}" | PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT}" psql \
    --host="${PGHOST}" \
    --port="${PGPORT}" \
    --username="${PGUSER}" \
    --dbname="${PGDATABASE}" \
    --single-transaction \
    --set=ON_ERROR_STOP=1
else
  PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT}" psql \
    --host="${PGHOST}" \
    --port="${PGPORT}" \
    --username="${PGUSER}" \
    --dbname="${PGDATABASE}" \
    --single-transaction \
    --set=ON_ERROR_STOP=1 \
    < "${backup_file}"
fi

echo "[restore] done"
