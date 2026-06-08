#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DRY_RUN=0

if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

remove_path() {
  local path="$1"
  if [[ ! -e "$path" && ! -L "$path" ]]; then
    return 0
  fi

  if [[ "$DRY_RUN" -eq 1 ]]; then
    printf 'would remove %s\n' "$path"
    return 0
  fi

  rm -rf -- "$path"
  printf 'removed %s\n' "$path"
}

remove_glob_matches() {
  local pattern="$1"
  local matched=0

  while IFS= read -r -d '' path; do
    matched=1
    remove_path "$path"
  done < <(find "$ROOT_DIR" -path "$ROOT_DIR/.git" -prune -o -name "$pattern" -print0 2>/dev/null)

  return 0
}

cd "$ROOT_DIR"

DIRS_TO_REMOVE=(
  "node_modules"
  "vendor"
  "public/assets/build"
  "test-results"
  "playwright-report"
  "coverage"
  "dist"
  "build"
  ".phpstan"
  ".phpunit.cache"
)

FILES_TO_REMOVE=(
  "repo_file_inventory.txt"
  "repo_size_inventory.txt"
  "repo_git_status.txt"
  "npm-debug.log"
  "yarn-debug.log"
  "yarn-error.log"
)

for path in "${DIRS_TO_REMOVE[@]}"; do
  remove_path "$ROOT_DIR/$path"
done

for path in "${FILES_TO_REMOVE[@]}"; do
  remove_path "$ROOT_DIR/$path"
done

remove_glob_matches ".DS_Store"
remove_glob_matches "Thumbs.db"
remove_glob_matches "*.swp"
remove_glob_matches "*~"
remove_glob_matches "*.tsbuildinfo"
remove_glob_matches ".phpunit.result.cache"
remove_glob_matches ".php-cs-fixer.cache"
remove_glob_matches ".eslintcache"
remove_glob_matches "npm-debug.log*"
remove_glob_matches "yarn-debug.log*"
remove_glob_matches "yarn-error.log*"

while IFS= read -r -d '' path; do
  remove_path "$path"
done < <(
  find "$ROOT_DIR/logs" \
    \( -name "*.log" -o -name "*.pid" -o -path "$ROOT_DIR/logs/cache/*.json" \) \
    -print0 2>/dev/null
)

printf 'repo cleanup complete\n'
