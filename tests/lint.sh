#!/usr/bin/env bash
# php -l on every module file under each PHP version (uses the images built by setup.sh / docker compose).
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"
rc=0
for V in ${@:-8.2 8.3 8.4}; do
  out="$(docker run --rm -v "$ROOT:/app:ro" "ccenter-test:$V" sh -c \
    'cd /app && find . -name "*.php" -not -path "./tests/.work/*" -not -path "./vendor/*" -not -path "./tools/*" -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "^No syntax errors"' || true)"
  if [ -n "$out" ]; then echo "PHP $V: FAIL"; echo "$out"; rc=1; else echo "PHP $V: all files OK"; fi
done
exit $rc
