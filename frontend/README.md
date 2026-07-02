# Frontend Modernization

This directory is the modern frontend entry layer for Erebus Web.

## Milestone 1 scope

Milestone 1 establishes the frontend foundation without replacing the PHP
runtime or forcing a SPA rewrite.

What is included:

- `Vite` as the build tool
- `TypeScript` for typed frontend code
- `Alpine.js` for lightweight shell interactivity
- a built shell bundle at `public/assets/build/app-shell.js`
- typed shared modules for:
  - global `App` helpers
  - topbar DB health pill
  - topbar clock
  - shared Permission Intel helper layer
- migrated workflow pages:
  - `permissions-review`
  - `analysis-fusion`
  - `vt-confidence`
  - `sample-detail`
  - `samples`
  - `time-reference`

What is intentionally not included yet:

- React/Vue/Svelte SPA architecture
- Node.js server runtime
- backend framework migration
- full page-by-page TS migration

## Directory layout

- `app-shell.ts`
  - shell bundle entrypoint
- `shared/`
  - typed shared utilities and page-independent browser logic
- `pages/`
  - page-level workflow controllers migrated into the bundle
- `types/`
  - local type declarations for browser globals and third-party packages

## Rules for new frontend work

1. Keep PHP as the runtime and routing layer.
2. Put new shared browser logic in `frontend/shared/`.
3. Put migrated workflow pages in `frontend/pages/`.
4. Prefer TypeScript for all new frontend code.
5. Do not add a second frontend runtime or parallel app shell.
6. When the shell bundle owns a script, suppress the legacy page/global script
   through `app/lib/assets.php` instead of loading both.

## Build and verification

```bash
npm install
npm run check
```

`npm run check` runs:

- `npm run typecheck`
- `npm run build`

## Current migration status

Shared typed modules:

- `shared/app-core.ts`
- `shared/db-status-pill.ts`
- `shared/topbar-clock.ts`
- `shared/permission-intel.ts`

Migrated page modules:

- `pages/permissions-review.ts`
- `pages/analysis-fusion.ts`
- `pages/vt-confidence.ts`
- `pages/sample-detail-page.ts`
- `pages/samples-page.ts`
- `pages/time-reference-page.ts`

## Best next steps

1. Migrate `permissions-triage`
2. Migrate `permissions-overview`
3. Migrate `settings` / remaining workspace utility pages
4. Replace remaining legacy page globals with typed namespaces or shared modules
5. Decide whether to keep progressive enhancement or later adopt a fuller PHP
   framework such as Laravel for backend structure
