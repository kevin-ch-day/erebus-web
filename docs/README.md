# Erebus Web Docs

This is the landing page for project documentation. Use it to find operator
guides, architecture notes, and review checklists.

## Start here
- `docs/project_overview.md` - goals, scope, and current operator model.
- `docs/system_architecture_synthesis.md` - current cross-system model for both databases, the CLI, and the web app.
- `docs/knowledge_retention_audit.md` - short retained caveats that still matter during DB/web changes.
- `docs/structure.md` - code structure and web app design patterns.

## Operator guides
- `docs/permissions_analysis.md` - Permission Intel workflow and data model.
- `docs/permissions_pages.md` - how operators use each permissions page.

## Review and verification
- `docs/review_checklist.md` - PM/dev review checklist for web app + DB.
- `docs/phase2_contract.md` - DB invariants and legacy phase notes still relevant to the current schema.
- `docs/smoke_results_template.md` - template for recording current smoke checks.
- `docs/migrations/005_standardize_permission_collations.sql` - standardize permission collations.

Proof artifacts:
- keep dated smoke and lease-verification notes under `docs/ops/`

## Planning notes
- `docs/ops/web_app_scale_audit_2026_05_28.md` - current web app size, naming drift, and restructure plan.

## Notes
- Keep docs concise and aligned with the DB truth model.
- Update docs when workflows change (triage/queue/apply).
