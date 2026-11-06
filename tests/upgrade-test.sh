#!/usr/bin/env bash
# Upgrade test: install an OLD version of the module, create data with it, switch to the code in this
# working tree, run the module update and check that the data is converted and shows the same.
#   OLD_REF=<git ref of the old version> ICMS_SRC=<ImpressCMS checkout> tests/upgrade-test.sh [php version]
set -euo pipefail
V="${1:-8.3}"
OLD_REF="${OLD_REF:?set OLD_REF to the commit/tag of the version to upgrade from}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"
PORT="${WEB_PORT:-808${V#8.}}"
PROJECT="ccenter-${V//./}"
OLD="$(mktemp -d)"; chmod 755 "$OLD"   # the web server user has to be able to read it
trap 'git -C "$ROOT" worktree remove --force "$OLD" >/dev/null 2>&1 || true' EXIT
git -C "$ROOT" worktree add --detach "$OLD" "$OLD_REF" >/dev/null
export BASE_URL="http://localhost:$PORT" COMPOSE_PROJECT="$PROJECT"

echo "== install $OLD_REF on PHP $V"
CCENTER_SRC="$OLD" "$HERE/setup.sh" "$V" >/dev/null
( cd "$HERE/e2e" && node upgrade.js seed )

echo "== switch to the current code"
export PHP_VERSION="$V" WEB_PORT="$PORT" ICMS_WORKDIR="$HERE/.work/$V/htdocs" ICMS_TRUST="$HERE/.work/$V/trust" CCENTER_SRC="$ROOT"
docker compose -f "$HERE/docker-compose.yml" -p "$PROJECT" up -d --force-recreate web >/dev/null
for _ in $(seq 1 30); do curl -fs -o /dev/null "$BASE_URL/" && break; sleep 1; done
( cd "$HERE/e2e" && node upgrade.js verify )
