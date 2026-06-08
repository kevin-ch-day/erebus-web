# Project Structure (Erebus Web)

Purpose: keep the codebase readable and predictable as features grow.

Status note: the app has grown beyond the original small-shell assumption.
This file captures the intended structure, but the live footprint now also
needs the scale audit in `docs/ops/web_app_scale_audit_2026_05_28.md`.

## Top-level
- `app/` PHP application code (views, APIs, and library helpers).
- `public/` public web root (index router, CSS, JS, assets).
- `docs/` project notes and operator-facing guidance.
- `logs/` runtime logs (local only).

## app/ layout
- `app/views/` HTML/PHP pages rendered by `public/index.php`.
  - Naming: `permissions_*` for page views under Android Permissions.
- `app/api/` JSON endpoints (read-only or update actions).
  - Naming: `android_permission_*` for permissions APIs.
- `app/lib/` shared helpers (URL building, input handling, permissions LOVs).
- `app/database/`
  - `queries/` SQL strings (single responsibility, no logic).
  - `services/` query composition + DB access.
  - `db_conn.php`, `db_engine.php` are DB plumbing only.

## public/ layout
- `public/index.php` router (allowlist routes only).
- `public/assets/css/`
  - `app_colors.css` tokens
  - `style.css` global layout + utilities
  - `sidebar_style.css`, `table_style.css` scoped styles
- `public/assets/js/`
  - `app_core.js` shared helpers (format, fetch, copy)
  - `pages/` page entry scripts (`*_page.js`)
  - `modules/` reusable components (`perm_triage`, `samples`, etc)

## Naming conventions
- **Pages:** `permissions_*` for views, `permissions_*_page.js` for JS.
- **APIs:** `android_permission_*` for permissions endpoints.
- **Services/queries:** `*_service.php`, `*_queries.php`.

## Current scale pressure

The current app footprint is materially larger than when this guide was first
written:

- 33 views
- 35 APIs
- 13 DB service files
- 29 page JS controllers
- 10 TypeScript page sources

The largest active pressure points are oversized service files and mixed page
controller ownership. See `docs/ops/web_app_scale_audit_2026_05_28.md` for the
measured hotspots and the recommended staged redesign.

## Data path flow (standard)
`View (app/views) -> API (app/api) -> Service (app/database/services) -> Query (app/database/queries)`

## Keep it simple
- Avoid deep nesting (no new subfolders unless it removes duplication).
- Prefer small helper files over 1,000+ line files.
- Keep naming aligned between view, JS, API, and service.
- When a domain already has multiple pages and workflow-specific APIs, prefer
  a domain folder over adding more top-level files.

## Design + UX Principles
- DB truth is authoritative; UI does not infer state.
- Workflow pages mutate state; catalog pages are read-only.
- Navigation and naming are consistent across view, API, and JS.
- Empty states explain why data is missing (no silent failure).

## Related Documentation
- `docs/README.md` is the landing page for project docs.
- `docs/project_overview.md` covers goals and milestones.
- `docs/permissions_analysis.md` explains the Permission Intel model.
- `docs/permissions_pages.md` explains operator workflows for permissions pages.
