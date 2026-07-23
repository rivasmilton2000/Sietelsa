#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-sietelsa}"
DB_USER="${DB_USER:-sietelsa}"
DB_PASS="${DB_PASS:-}"

OUT_DIR="${ROOT_DIR}/database"
mkdir -p "${OUT_DIR}"
OUT_FILE="${OUT_DIR}/backup_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"

if [[ -z "${DB_PASS}" ]]; then
  mysqldump --single-transaction --routines --triggers -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" "${DB_NAME}" > "${OUT_FILE}"
else
  MYSQL_PWD="${DB_PASS}" mysqldump --single-transaction --routines --triggers -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" "${DB_NAME}" > "${OUT_FILE}"
fi

echo "Backup creado: ${OUT_FILE}"
