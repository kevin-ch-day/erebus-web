# Web DB-Backed Endpoint Notes

These PHP endpoints are read-only DB-backed web contracts for VT key visibility
and Permission Queue visibility. Python still owns VT enrichment, queue apply,
and maintenance; the web endpoints expose operator state from MariaDB.

## Pages with fallback
- VT Key Controls (`index.php?p=vt_key_controls`)
- Permission Queue (`index.php?p=permissions_queue`)

## Banner behavior
- If an enhanced/remote API is unavailable, pages can still show DB-backed
  read-only state from these PHP endpoints.
- Operators should not infer that queue apply is available from these endpoints.

## DB-backed endpoints (read-only)
- `app/api/fallback_vt_status.php` (parity with the live VT status contract)
- `app/api/fallback_vt_health.php` (parity with the live VT health contract)
- `app/api/fallback_permission_queue.php` (parity with the live permission-queue contract)

## Data sources
- VT status/health: `virustotal_api_keys`, `virustotal_system_control`
- Permission queue: `android_permission_dict_queue`

## Read-only guarantee
- No INSERT/UPDATE/DELETE queries.
- Prepared statements only.
- Limits enforced to avoid large fetches.

## Contract parity
Fallback payloads must match API shapes:
- field names
- UTC timestamp formatting
- null behavior
- queue action/status semantics
- unknown codes surfaced once (do not double-wrap `unknown:<raw>`)

Queue fallback payloads may expose both raw and normalized actions. For example,
stored `aosp_promote` rows normalize to canonical web action `aosp`, while the
raw value remains available for diagnostics.

If Python adds fields later:
- update the fallback payload, or return `null` for the new fields.
