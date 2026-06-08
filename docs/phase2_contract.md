# Current DB Contract and Invariants

This page defines what the UI can rely on today. Older phase names remain in
some flags and files, but this document describes the current database and web
contract.

## DB invariants (UI can rely on now)
- `android_permission_obs_sample` is current per-sample observation evidence, upserted by `(sample_id, permission_string)`.
- `android_permission_enrich_vt_event` is the enrichment history.
- `android_permission_enrich_vt_current` is a cache/rollup; it can drift.
- `android_permission_dict_unknown` is an intake/triage lifecycle ledger. It is not only "currently unknown" rows.
- `android_permission_dict_queue` is dictionary maintenance intent plus apply status.
- The UI never rewrites evidence tables.
- Permission string columns use case-insensitive collation (utf8mb4_unicode_ci); display stored case, but comparisons are case-insensitive.
- `android_permission_dict_queue` is current state (one row per permission_string); history lives in audit tables.
- Queue apply is Python-only; no public HTTP endpoint in v1.
- `android_permission_dict_unknown.seen_count` is historical ledger context, not live prevalence.

## Rollup drift guard (vt_current vs vt_event)
Stale is defined as any of:
- `vt_current.last_seen_at_utc` < max event time for a permission
- `vt_current.seen_count` != count of event rows for a permission

Guard payload exposes:
- `stale_permissions_count`
- `stale_count_mismatch_count`
- `max_lag_seconds` / `max_lag_days`
- a small sample list (top 10) with lag + deltas

If drift > 0, the UI shows a warning in Health + Permission Overview.

## VT status visibility (read-only)
The UI is read-only for load resilience status. It must not infer or apply logic.

Per-key cooldown fields (table: `virustotal_api_keys`):
- `api_key_id`
- `is_enabled`, `is_visible`
- `daily_quota_limit`, `daily_quota_used`, `quota_day_utc`
- `cooldown_until_utc` (indexed)
- `last_429_at_utc`, `last_429_retry_after_seconds`

Under-quota is derived by Python (treat usage as 0 if `quota_day_utc != UTC_DATE()`).

Global hold fields (table: `virustotal_system_control`):
- `hold_until_utc`, `hold_reason_code`
- `last_429_key_id`, `last_429_endpoint`, `last_429_retry_after_seconds`

Stop/hold codes are case-sensitive tokens. UI should display verbatim with a fallback label.
Known stop reasons include:
- `RATE_LIMIT_429_STRICT`
- `RATE_LIMIT_ALL_KEYS_COOLING`
- `INTERRUPTED`
- `dry_run`
- `snapshot_error`
- `HOLD_<REASON>` (derived from hold reason)

Known hold reason codes (tooltip list):
- `RATE_LIMIT_429`
- `DAILY_QUOTA_EXHAUSTED`
- `NETWORK_DOWN`

### UI display rules
- Show key identifiers as `api_key_id` plus last6 (never display full keys).
- Treat timestamps as UTC; API payloads should use ISO 8601 with `Z`.
- If schema is missing, return a safe "unavailable" response rather than 500s.

## Current backend contract
The Python consumer owns queue apply and rollup maintenance:

- Queue lifecycle includes `queued`, `claimed`, `applied`, `error`, `rejected`, and `skipped` where present.
- Canonical queue actions from web are `defer`, `aosp`, `google`, `oem`, `app_defined`, and `reject`.
- Legacy queue action aliases may exist; for example `aosp_promote` normalizes to `aosp`.
- Rollup rules for `android_permission_enrich_vt_current`:
  - `last_seen_at_utc` = max event time
  - `first_seen_at_utc` = min event time
  - `seen_count` = event count
  - `last_sample_id` matches the latest event
- Evidence packs are run-scoped (no cross-run leakage)

## vt_event replay and duplicates
`android_permission_enrich_vt_event` is the history surface. Current Python
ingest has replay/idempotency logic for known VT slices, but older rows and
brownfield imports can still carry duplicate or conflicting evidence. UI pages
must report evidence counts as backend-provided event rows and avoid
client-side dedupe claims.

## Operator semantics (UI copy)
- Queued = pending Python apply (not applied yet).
- Applied = Python processed the queue row and wrote the intended dictionary or lifecycle update.
- Skipped/rejected/error are terminal or review states, not evidence truth.
