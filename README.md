# Erebus Web (Internal Operator Console)

Erebus Web is an internal operator console for Android permission intelligence,
VT state visibility, and artifact workflow support.

## Current scope

The live web app currently provides:
- Permission triage, review, queue visibility, evidence, and catalog pages
- VT health, VT key visibility, run history, and snapshot inventory
- Artifact hash lookup and artifact intake helpers
- Cross-database Analysis Fusion and VT confidence review surfaces

Planned VT admin surfaces remain visible only when feature flags enable them.

## Quick start (Fedora Apache/PHP-FPM)

1. Clone the repository **outside** Apache's document root, for example:
   - `/srv/erebus-web`
2. Configure Apache to serve only `/srv/erebus-web/public` (for example, map
   `/erebus-web` to that directory). Do not expose the repository root, `.git`,
   `app/`, or `.env` through Apache.
3. Ensure `httpd`, `php-fpm`, and `mariadb` are running.
4. Set your `BASE_URL` to the resulting public path:
   - `BASE_URL=/erebus-web`
5. Open the app:
   - `http://127.0.0.1/erebus-web/`

For Fedora/SELinux deployments, ensure the web user can read `.env` without
making it world-readable, and label only the cache directory writable by
`httpd` (for example `storage/cache` as `httpd_sys_rw_content_t`).

An Apache example is included at
[`deploy/httpd/erebus-web.conf.example`](deploy/httpd/erebus-web.conf.example).
After reviewing its path and URL prefix, install it as
`/etc/httpd/conf.d/erebus-web.conf`, then label the checkout and cache on
Fedora:

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/srv/erebus-web(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/srv/erebus-web/storage/cache(/.*)?'
sudo restorecon -Rv /srv/erebus-web
sudo systemctl reload httpd
```

Review the existing `semanage fcontext` rules before adding them; use `-m`
instead of `-a` if the target rule already exists.

Before switching traffic to a receiver, run the read-only preflight:

```bash
bash scripts/deployment-preflight.sh
```

## Environment configuration

The web app reads config from environment variables and repo-local `.env` files.
For Apache/PHP-FPM deployments, do not rely on a developer shell `~/.my.cnf`:
the web-server user usually cannot read it. Use a repo-local `.env` copied from
`.env.example`, or provide equivalent process-level env vars.

The web app and Erebus Engine share the canonical `EREBUS_DB_*` configuration
names. The web app can optionally read Permission Intel tables from a separate
catalog with `EREBUS_PERMISSION_INTEL_DB_NAME`. Legacy `DB_*` and
`PERMISSION_INTEL_DB_*` names remain accepted for existing Web-only hosts, but
the canonical names take precedence when both are present.

The Health API reports this without exposing values: `canonical`,
`legacy_compatibility`, `mixed_precedence`, or `defaults_only`. Treat
`legacy_compatibility` and `mixed_precedence` as receiver-migration cleanup
items; the new host should use only canonical names.

**Required DB env vars (example):**

```env
EREBUS_DB_HOST=127.0.0.1
EREBUS_DB_PORT=3306
EREBUS_DB_NAME=erebus_threat_intel_prod
EREBUS_DB_USER=erebus_web
EREBUS_DB_PASSWORD=your_password
EREBUS_PERMISSION_INTEL_DB_NAME=android_permission_intel
```

Copy `.env.example` to `.env` for local/server deployment, or use
`app/database/db_config.example.php` as a reference sheet. Do not commit secrets.

**App environment flags:**

```env
APP_ENV=prod
BASE_URL=/erebus-web
FEATURE_PHASE2B_READONLY=1
FEATURE_PHASE3_OPS=0
```

`FEATURE_PHASE3_OPS=0` is enforced by every write endpoint as well as the UI.
Keep it disabled on a receiver until its database role, access controls, and
operator workflow have been deliberately validated.

- `APP_ENV=dev` enables verbose error output; use it only for local developer
  checkouts.
- `APP_ENV=prod` hides stack traces and logs errors server-side.

## Required PHP extensions

- PDO MySQL (`pdo_mysql`)

## Frontend build (Milestone 1 foundation)

The web app now supports a small modern shell bundle built with `Vite` and
`Alpine.js`, with `TypeScript` available for incremental migration. The production
PHP views still work without it, but when the build artifact exists the app will
use it for shared shell behavior before falling back to legacy global scripts.

```bash
npm install
npm run check
```

To prune generated dependencies, build output, caches, logs, and local audit
artifacts from the repo checkout:

```bash
npm run clean
```

Preview what would be removed without deleting it:

```bash
npm run clean:dry-run
```

This emits:

- `public/assets/build/app-shell.js`
- typed page-controller outputs such as:
  - `public/assets/js/pages/stack_audit_page.js`
  - `public/assets/js/pages/family_taxonomy_queue_page.js`

Milestone 1 delivers:

- Vite build support
- TypeScript frontend foundation
- Alpine-powered shell interactions
- typed shared frontend modules
- migrated workflow pages (`Permission Review`, `Analysis Fusion`, `VT Confidence`)

Recommended migration path:

- keep PHP as the runtime/backend
- use `Vite` + `TypeScript` for new frontend work
- convert shared JS first, then the largest page controllers

See:

- `frontend/README.md`
- `docs/system_architecture_synthesis.md`

## Database import

Import the primary catalog into your local DB:

```bash
mysql -u root -p erebus_threat_intel_prod < docs/erebus_database_dev.sql
```

If you received the dump separately, place it at `docs/erebus_database_dev.sql` before importing.
If Permission Intel is split, load the `android_permission_*` tables into
`android_permission_intel` and set
`EREBUS_PERMISSION_INTEL_DB_NAME=android_permission_intel`.

### Migrations (schema alignment)

Apply migrations in order to ensure queue/audit tables and indexes exist:

```bash
mysql -u root -p android_permission_intel < docs/migrations/001_create_permission_queue.sql
mysql -u root -p android_permission_intel < docs/migrations/002_create_triage_audit.sql
mysql -u root -p android_permission_intel < docs/migrations/003_add_indexes.sql
mysql -u root -p android_permission_intel < docs/migrations/004_align_permission_queue_schema.sql
mysql -u root -p android_permission_intel < docs/migrations/005_standardize_permission_collations.sql
```

If you are running a unified catalog instead of split mode, run those migrations
against `EREBUS_DB_NAME` instead.

## Tests

Run the API health contract against a running app:

```bash
BASE_URL=http://localhost/erebus-web/public php tests/api/health_contract.php
```

The current legacy checkout under `/var/www/html/erebus-web` still uses
`/erebus-web/public` until Apache is remapped to serve only `public/`. A new
receiver deployment following the steps above should instead use
`/erebus-web`; the generic `http://localhost` root is correct only when a
separate vhost maps directly to this repo's `public/` directory.

## CLI visibility helpers

The web app now includes a small CLI surface for faster family-taxonomy and
stack-upgrade inspection from the shell:

```bash
php bin/erebus_console.php family:summary --format=table
php bin/erebus_console.php family:export --decision-mode=repair_after_alias_review --format=csv > /tmp/erebus_family_alias_review.csv
php bin/erebus_console.php family:apply-plan --decision-mode=repair_after_alias_review --format=sql
php bin/erebus_console.php family:pairs --format=table
php bin/erebus_console.php family:drivers --format=table
php bin/erebus_console.php family:governance --format=table
php bin/erebus_console.php family:opportunities --limit=25 --format=table
php bin/erebus_console.php family:rows --decision-mode=ask_why_first --limit=25 --format=table
php bin/erebus_console.php stack:audit --format=json
```

Use these when you want the same family-debt or stack-audit intelligence the web
app shows, but in a repeatable terminal-friendly form for offline review,
automation, or upgrade planning.

## OpenAPI contract

Erebus Web now ships a minimal machine-readable contract for the core operator APIs:

```bash
curl http://localhost/erebus-web/public/api.php/openapi.php | jq '.info,.paths'
```

The source document lives at:

- `openapi.json`

The served endpoint is:

- `/api.php/openapi.php`

This contract currently covers the main operator endpoints used by the web app:

- `health.php`
- `samples_list.php`
- `family_taxonomy_check.php`
- `stack_audit.php`

## Artifact intake catch-up

The operator web app now supports:

- bulk artifact queue submit
- CSV paste/import on `Submit Artifact`
- Beacon/LAMDA-style packet column mapping into the bulk table
- updated artifact source vocabulary aligned with current Erebus research/import lanes

This is still a queueing surface, not a direct LAMDA staging/promotion workflow.
It helps operators reshape and queue artifacts, but does not replace the CLI-side
Beacon/LAMDA dry-run and staging policy pipeline.

## Operational assumptions

- No auth yet beyond deployment/network boundaries.
- Write endpoints exist (triage update, queue update, sample update).
- Use deployment-local controls and DB audit visibility for write tracing.

### Queue apply (Python consumer)

Queue apply is Python-only. There is no public HTTP endpoint for apply in v1.
The consumer reads `android_permission_dict_queue`, applies changes, and updates
status fields directly in the database.

## Permission workflow loop

1. Permission observations and VT evidence land in Permission Intel tables.
2. The web app shows evidence-first triage, review, evidence, and fusion surfaces.
3. Operator triage updates the unknown lifecycle ledger.
4. Operator queue updates are optional maintenance intent, not evidence truth.
5. Python applies queued dictionary changes and writes audit/applied state.

This loop is intentionally split: web records review/intent, Python applies
dictionary maintenance, and the databases remain the source of truth.
