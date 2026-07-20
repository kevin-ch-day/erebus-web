#!/usr/bin/env bash
# Read-only receiver preflight for Erebus Web.
set -u

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
env_file="$repo_root/.env"
cache_dir="$repo_root/storage/cache"
warnings=0
failures=0

warn() {
  printf 'WARN  %s\n' "$1"
  warnings=$((warnings + 1))
}

fail() {
  printf 'FAIL  %s\n' "$1"
  failures=$((failures + 1))
}

ok() {
  printf 'OK    %s\n' "$1"
}

has_env_key() {
  local key="$1"
  [[ -f "$env_file" ]] && grep -Eq "^[[:space:]]*(export[[:space:]]+)?${key}=" "$env_file"
}

printf 'Erebus Web deployment preflight\n'
printf 'Repository: %s\n\n' "$repo_root"

if [[ "$repo_root" == /var/www/* || "$repo_root" == /var/www ]]; then
  warn 'Repository is under /var/www. New deployments should keep the checkout outside the web root and expose only public/.'
else
  ok 'Repository is outside /var/www.'
fi

if [[ -f "$repo_root/public/index.php" && -f "$repo_root/public/api.php" ]]; then
  ok 'Public front controllers are present.'
else
  fail 'public/index.php or public/api.php is missing.'
fi

if [[ -f "$env_file" ]]; then
  mode="$(stat -c '%a' "$env_file" 2>/dev/null || true)"
  other_digit="${mode: -1}"
  if [[ "$other_digit" == '0' ]]; then
    ok ".env is not world-readable (mode ${mode})."
  else
    fail ".env is world-accessible (mode ${mode:-unknown}); use owner/group-only permissions such as 0640."
  fi

  canonical=0
  legacy=0
  has_env_key 'EREBUS_DB_NAME' && canonical=1
  has_env_key 'EREBUS_PERMISSION_INTEL_DB_NAME' && canonical=1
  has_env_key 'DB_NAME' && legacy=1
  has_env_key 'PERMISSION_INTEL_DB_NAME' && legacy=1
  case "${canonical}${legacy}" in
    10) ok 'Database configuration uses canonical EREBUS_* names.' ;;
    01) warn 'Database configuration uses legacy aliases; migrate to EREBUS_DB_* and EREBUS_PERMISSION_INTEL_DB_NAME.' ;;
    11) warn 'Both canonical and legacy database names are set; canonical values win, but remove duplicates.' ;;
    *) warn 'No database catalog name was found in .env; confirm process-level environment configuration.' ;;
  esac

  if has_env_key 'FEATURE_PHASE3_OPS' && grep -Eq '^[[:space:]]*FEATURE_PHASE3_OPS=(0|false|FALSE|no|NO)[[:space:]]*$' "$env_file"; then
    ok 'Write operations are disabled.'
  else
    warn 'Write operations may be enabled; validate database privileges and API access controls before serving this host.'
  fi
else
  warn '.env is absent; confirm process-level environment variables are configured for PHP-FPM.'
fi

if command -v php >/dev/null 2>&1 && php -m 2>/dev/null | grep -qx 'pdo_mysql'; then
  ok 'PHP PDO MySQL extension is available.'
else
  fail 'PHP PDO MySQL extension is unavailable.'
fi

if [[ -d "$cache_dir" && -w "$cache_dir" ]]; then
  ok 'Shared cache directory exists and is writable by the current user.'
else
  warn 'storage/cache is absent or not writable by the current user; validate web-user ownership and SELinux context.'
fi

printf '\nResult: %d failure(s), %d warning(s). No changes were made.\n' "$failures" "$warnings"
[[ "$failures" -eq 0 ]]
