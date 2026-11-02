#!/usr/bin/env bash
# Remove the test stack for one PHP version (or all when no argument is given).
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
for V in ${1:-8.2 8.3 8.4}; do
  PHP_VERSION="$V" ICMS_WORKDIR=/tmp ICMS_TRUST=/tmp docker compose -f "$HERE/docker-compose.yml" -p "ccenter-${V//./}" down -v --remove-orphans || true
done
