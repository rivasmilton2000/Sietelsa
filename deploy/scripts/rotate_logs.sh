#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOG_DIR="${ROOT_DIR}/logs"
LOG_FILE="${LOG_DIR}/app.log"
MAX_SIZE_BYTES=$((10 * 1024 * 1024))

mkdir -p "${LOG_DIR}"
touch "${LOG_FILE}"

SIZE=$(stat -c%s "${LOG_FILE}" 2>/dev/null || echo 0)
if [[ "${SIZE}" -lt "${MAX_SIZE_BYTES}" ]]; then
  echo "Sin rotacion: tamaño actual ${SIZE} bytes"
  exit 0
fi

STAMP=$(date +%Y%m%d_%H%M%S)
ROTATED="${LOG_DIR}/app.log.${STAMP}"

mv "${LOG_FILE}" "${ROTATED}"
touch "${LOG_FILE}"

gzip -f "${ROTATED}" || true

echo "Log rotado: ${ROTATED}.gz"
