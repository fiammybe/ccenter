#!/usr/bin/env bash
# Build a clean ImpressCMS 2.0.4 + ccenter test stack for one PHP version and run the installer.
#   tests/setup.sh 8.3            -> http://localhost:8083
# Environment:
#   ICMS_SRC   path to an ImpressCMS 2.0.4 checkout (the directory that contains htdocs/)
set -euo pipefail

V="${1:?usage: setup.sh <php-version e.g. 8.3>}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ICMS_SRC="${ICMS_SRC:?set ICMS_SRC to an ImpressCMS 2.0.4 checkout}"
PORT="${WEB_PORT:-808${V#8.}}"
PROJECT="ccenter-${V//./}"
WORK="$HERE/.work/$V"

export PHP_VERSION="$V" WEB_PORT="$PORT" ICMS_WORKDIR="$WORK/htdocs" ICMS_TRUST="$WORK/trust" CCENTER_SRC="$HERE/.."

docker compose -f "$HERE/docker-compose.yml" -p "$PROJECT" down -v --remove-orphans >/dev/null 2>&1 || true
rm -rf "$WORK"; mkdir -p "$WORK/htdocs" "$WORK/trust"
rsync -a "$ICMS_SRC/htdocs/" "$WORK/htdocs/"
mkdir -p "$WORK/htdocs/modules/ccenter"

# Test-only patch of the *copy* of core: the ICMS error handler swallows runtime warnings/notices/deprecations,
# so forward them to the PHP error log where the smoke tests can look for module-originated entries.
LOGGER="$WORK/htdocs/libraries/icms/core/Logger.php"
python3 - "$LOGGER" <<'PY'
import sys
p = sys.argv[1]
s = open(p).read()
needle = "public function handleError($errno, $errstr, $errfile, $errline) {\n"
assert needle in s, "Logger::handleError not found"
s = s.replace(needle, needle + "\t\tif ($errno & error_reporting()) { error_log('PHP E' . $errno . ': ' . $errstr . ' in ' . $errfile . ':' . $errline); }\n", 1)
open(p, "w").write(s)
PY
chmod -R 777 "$WORK"

docker compose -f "$HERE/docker-compose.yml" -p "$PROJECT" up -d --build
for _ in $(seq 1 40); do
  curl -fs -o /dev/null "http://localhost:$PORT/install/index.php" && break; sleep 1
done

( cd "$HERE/e2e" && BASE_URL="http://localhost:$PORT" node install.js )
# Protector (bundled with core) throttles rapid test traffic: its DoS / brute-force counters answer with blank
# pages. It is not what is being tested, so switch its preload off in the test copy. This must happen after the
# installation because installing the Protector module puts the preload back.
rm -f "$WORK/htdocs/plugins/preloads/protector.php"

# ImpressCMS silences PHP diagnostics unless debug mode is on; turn it on so regressions show up in the PHP log.
docker compose -f "$HERE/docker-compose.yml" -p "$PROJECT" exec -T db mariadb -uroot -proot icms \
  -e "UPDATE icms_config SET conf_value='1' WHERE conf_name='debug_mode'"

echo "stack ready: http://localhost:$PORT  (compose project $PROJECT)"
