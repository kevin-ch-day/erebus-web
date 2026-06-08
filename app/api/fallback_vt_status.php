<?php
// app/api/fallback_vt_status.php
// Read-only DB-backed VT status contract for the web app.
// Must remain parity with the live VT status payload (fields, UTC formats, nulls).

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

function fallback_vt_status_iso_utc(?string $value): ?string
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
    $keys = [];
    foreach (($snapshot['keys'] ?? []) as $row) {
        $keys[] = [
            'api_key_id' => isset($row['api_key_id']) ? (int)$row['api_key_id'] : 0,
            'last6' => $row['last6'] !== null ? (string)$row['last6'] : null,
            'is_enabled' => isset($row['is_enabled']) ? (int)$row['is_enabled'] : 0,
            'is_visible' => isset($row['is_visible']) ? (int)$row['is_visible'] : 0,
            'daily_quota_limit' => $row['daily_quota_limit'] !== null ? (int)$row['daily_quota_limit'] : null,
            'daily_quota_used' => $row['daily_quota_used'] !== null ? (int)$row['daily_quota_used'] : null,
            'quota_day_utc' => $row['quota_day_utc'] !== null ? (string)$row['quota_day_utc'] : null,
            'cooldown_until_utc' => fallback_vt_status_iso_utc($row['cooldown_until_utc'] ?? null),
            'last_429_at_utc' => fallback_vt_status_iso_utc($row['last_429_at_utc'] ?? null),
            'last_429_retry_after_seconds' => $row['last_429_retry_after_seconds'] !== null ? (int)$row['last_429_retry_after_seconds'] : null,
            'lease_until_utc' => ($snapshot['supports_leases'] ?? false) ? fallback_vt_status_iso_utc($row['lease_until_utc'] ?? null) : null,
            'lease_owner' => ($snapshot['supports_leases'] ?? false) ? ($row['lease_owner'] !== null ? (string)$row['lease_owner'] : null) : null,
            'rate_limit_429_count' => $row['rate_limit_429_count'] !== null ? (int)$row['rate_limit_429_count'] : null,
        ];
    }
    $hold = $snapshot['hold'] ?? [];

    $payload = [
        'ok' => true,
        'generated_at_utc' => (string)($snapshot['generated_at_utc'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        'supports_leases' => (bool)($snapshot['supports_leases'] ?? false),
        'keys' => $keys,
        'hold' => [
            'hold_until_utc' => fallback_vt_status_iso_utc($hold['hold_until_utc'] ?? null),
            'hold_reason_code' => $hold['hold_reason_code'] ?? null,
            'last_429_key_id' => $hold['last_429_key_id'] ?? null,
            'last_429_endpoint' => $hold['last_429_endpoint'] ?? null,
            'last_429_retry_after_seconds' => $hold['last_429_retry_after_seconds'] ?? null,
        ],
    ];

    json_ok($payload);
} catch (Throwable $e) {
    api_error('Fallback vt/status failed.', 500, 'ERR_FALLBACK_VT_STATUS', [], $e);
}
