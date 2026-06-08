`docs/migrations` is intentionally small.

Keep only reusable bootstrap/schema-alignment migrations here:

- `001_create_permission_queue.sql`
- `002_create_triage_audit.sql`
- `003_add_indexes.sql`
- `004_align_permission_queue_schema.sql`
- `005_standardize_permission_collations.sql`

Do not keep one-off Erebus Web data-repair tranches in this folder after they have
been applied and recorded in `schema_migrations`. Those are historical
operations, not fresh-environment setup steps.
