<?php
// app/api/fallback_vt_health.php
// Read-only DB-backed VT health contract for the web app.
// Must remain parity with the live VT health payload (fields, UTC formats, nulls).

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

function fallback_vt_iso_utc(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s\Z');
    } catch (Throwable $e) {
        return null;
    }
}

try {
    $snapshot = db_vt_key_status_snapshot();
    $posture = $snapshot['key_posture'] ?? [];
    $hold = $snapshot['hold'] ?? [];

    json_ok([
        'ok' => true,
        'generated_at_utc' => (string)($snapshot['generated_at_utc'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        'eligible_key_count' => (int)($posture['eligible_keys'] ?? 0),
        'cooling_key_count' => (int)($posture['cooling_keys'] ?? 0),
        'hold_until_utc' => fallback_vt_iso_utc($hold['hold_until_utc'] ?? null),
        'hold_reason_code' => $hold['hold_reason_code'] ?? null,
        'stopped_reason' => null,
        'supports_leases' => (bool)($snapshot['supports_leases'] ?? false),
        'leased_key_count' => array_key_exists('leased_keys', $posture) ? $posture['leased_keys'] : null,
    ]);
} catch (Throwable $e) {
    api_error('Fallback vt/health failed.', 500, 'ERR_FALLBACK_VT_HEALTH', [], $e);
}
