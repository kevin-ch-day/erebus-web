# Review Checklist (PM + Dev)

Use this checklist to review the current web app, DB state, and documentation.
It is designed for fast, repeatable verification before demos or enrichments.

## Docs inventory (what exists)
- `docs/README.md` - docs landing page.
- `docs/project_overview.md` - goals + milestones.
- `docs/structure.md` - code structure + design principles.
- `docs/permissions_analysis.md` - Permission Intel workflow/data model.
- `docs/permissions_pages.md` - operator workflow by permissions page.
- `docs/migrations/004_align_permission_queue_schema.sql` - queue schema alignment.
- `docs/migrations/005_standardize_permission_collations.sql` - permission collation standardization.

## Web app smoke checks
- **Permission Overview**: status + buckets render; no 500s.
- **Permission Drift**: namespace list renders; no 500s.
- **Permission Triage**:
  - "Queued" badge appears for queued items.
  - Queue status card shows counts + timestamps.
  - Active/Governed/Ledger lanes render from current evidence and lifecycle state.
- **Permission Review**:
  - Save decision returns to triage with banner.
  - Queue update creates or updates queued row.
  - Updating notes/metadata does not reset applied/error queue outcomes.
  - Notes guardrail triggers for OEM/Ignore queue actions.
- **Permission Queue**:
  - Fallback mode shows the same queue actions/status values as the API (no semantic remap).
- **Permission Evidence**: loads rows or shows empty-state explanation.
- **Analysis Fusion**: buckets render or schema-unavailable state is explicit.
- **VT Confidence**: confidence buckets render or schema-unavailable state is explicit.
- **Catalog pages**: AOSP, Google, OEM Registry, OEM Permissions load with copy.

## DB checks (core tables)
Run these from MySQL CLI against the active Erebus catalog. In most live setups:
- primary DB: `erebus_threat_intel_prod`
- PI DB: `android_permission_intel`

**Queue table exists + status counts**
```sql
SELECT status, COUNT(*) AS n
FROM android_permission_dict_queue
GROUP BY status;
```

**Queued items show expected fields**
```sql
SELECT queue_id, permission_string, queue_action,
       proposed_bucket, proposed_classification, status, updated_at_utc
FROM android_permission_dict_queue
ORDER BY updated_at_utc DESC
LIMIT 5;
```

**No duplicate queued rows**
```sql
SELECT permission_string, COUNT(*) AS n
FROM android_permission_dict_queue
GROUP BY permission_string
HAVING COUNT(*) > 1;
```

**Unknown permissions still present**
```sql
SELECT COUNT(*) AS unknown_count
FROM android_permission_dict_unknown;
```

**Scan summary rows present**
```sql
SELECT COUNT(*) AS scan_rows
FROM virustotal_sample_scan_summary;
```

**VT key status fields present**
```sql
SELECT api_key_id, is_enabled, is_visible, cooldown_until_utc, last_429_at_utc
FROM virustotal_api_keys
ORDER BY api_key_id ASC
LIMIT 5;
```

## API checks (quick)
- `GET /public/api.php/android_permission_intelligence.php?limit=100`
- `GET /public/api.php/android_permission_review.php?permission=ANDROID.PERMISSION.READ_EXTERNAL_STORAGE`
- `GET /public/api.php/schema_inventory.php`
- `GET /public/api.php/android_permission_classification_gaps.php?limit=25`
- `GET /public/api.php/vt_confidence.php?limit=25`
- `GET /public/api.php/analysis_fusion.php?limit=25`
- `POST /public/api.php/android_permission_queue_update.php` (returns `operation: created|updated`)
- VT/queue read paths:
  - `GET /public/api.php/fallback_vt_status.php`
  - `GET /public/api.php/fallback_permission_queue.php?status=queued`
  - `GET /public/api.php/health.php`

## Known dependencies
- Python queue consumer is required to apply queued rows to dictionaries.
- AOSP curated overrides table (if approved) is not created yet.
- Queue apply is Python-only; there is no public HTTP endpoint in v1.

## When something fails
- Check `logs/api.log` for SQL errors.
- Ensure DB schema has required columns (schema guard on Health page).
- Confirm the deployed app can reach `/public/api.php/...` through the same origin as the page.
