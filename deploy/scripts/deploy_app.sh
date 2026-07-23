#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

bash "${ROOT_DIR}/deploy/scripts/rotate_logs.sh"
bash "${ROOT_DIR}/deploy/scripts/clear_cache.sh"
apachectl -t

echo "Deploy checks completados. Ejecuta: systemctl reload apache2"
