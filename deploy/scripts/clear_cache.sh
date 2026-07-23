#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

find "${ROOT_DIR}/assets/cache/img" -type f -name '*.*' -delete || true

php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "OPcache reset\n"; } else { echo "OPcache no disponible\n"; }'

echo "Cache limpiada"
