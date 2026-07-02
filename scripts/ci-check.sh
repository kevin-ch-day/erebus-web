#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${BASE_URL:-http://localhost/erebus-web/public}"

cd "$ROOT_DIR"

echo "==> Typecheck and frontend build"
npm run check

echo "==> API contract smoke tests"
for test in tests/api/*_contract.php; do
  [[ -f "$test" ]] || continue
  echo "  php $test"
  BASE_URL="$BASE_URL" php "$test"
done

echo "CI check passed."
