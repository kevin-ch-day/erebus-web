<?php
// app/lib/time.php
// Time helpers for Erebus Web.
//
// Assumptions:
// - DB timestamps are UTC strings (e.g., '2026-01-06 01:23:45').
// - DB logic/scheduling uses UTC ONLY.
// - These helpers are for UI display + Settings page only.

declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Canonical timezone key -> IANA timezone ID.
 */
function tz_map(): array
{
    return [
        'minneapolis' => TZ_MINNEAPOLIS,
        'denver'      => TZ_DENVER,
        'las_vegas'   => TZ_LAS_VEGAS,
        'new_york'    => TZ_NEW_YORK,
        'anchorage'   => TZ_ANCHORAGE,
        'honolulu'    => TZ_HONOLULU,
        'utc'         => TZ_UTC,
        'amsterdam'   => TZ_AMSTERDAM,
        'paris'       => TZ_PARIS,
        'tokyo'       => TZ_TOKYO,
        'dubai'       => TZ_DUBAI,
    ];
}

/**
 * Optional aliases (accepted input) -> canonical key.
 * Useful if someone types ?tz=mpls etc.
 */
function tz_aliases(): array
{
    return [
        'mpls'    => 'minneapolis',
        'chicago' => 'minneapolis',
        'mountain' => 'denver',
        'den'      => 'denver',

        'vegas'   => 'las_vegas',
        'la'      => 'las_vegas',
        'nyc'     => 'new_york',
        'newyork' => 'new_york',
        'alaska'  => 'anchorage',
        'hawaii'  => 'honolulu',
        'hnl'     => 'honolulu',
    ];
}

/**
 * Return canonical key from user input, or null if invalid.
 */
function tz_canonical_key(?string $key): ?string
{
    $key = strtolower(trim((string)$key));
    if ($key === '') return null;

    $map = tz_map();
    if (isset($map[$key])) return $key;

    $aliases = tz_aliases();
    $maybe = $aliases[$key] ?? null;
    if ($maybe !== null && isset($map[$maybe])) return $maybe;

    return null;
}

/**
 * Convert a key (or alias) to a timezone ID.
 * Falls back to APP_TZ_DISPLAY_DEFAULT if invalid.
 */
function tz_from_key(?string $key): string
{
    $canon = tz_canonical_key($key);
    if ($canon === null) return APP_TZ_DISPLAY_DEFAULT;

    return tz_map()[$canon];
}

/**
 * Validate a timezone ID string safely.
 * If invalid/empty, returns APP_TZ_DISPLAY_DEFAULT.
 */
function tz_safe(?string $tzId): string
{
    $tzId = trim((string)$tzId);
    if ($tzId === '') return APP_TZ_DISPLAY_DEFAULT;

    try {
        new DateTimeZone($tzId);
        return $tzId;
    } catch (Throwable $e) {
        return APP_TZ_DISPLAY_DEFAULT;
    }
}

/**
 * Get the default timezone *key* corresponding to APP_TZ_DISPLAY_DEFAULT.
 * If no match, returns 'minneapolis'.
 */
function tz_default_key(): string
{
    foreach (tz_map() as $k => $tzId) {
        if ($tzId === APP_TZ_DISPLAY_DEFAULT) return $k;
    }
    return 'minneapolis';
}

/**
 * Current selected primary display timezone key (cookie-based).
 * Returns default key if cookie missing/invalid.
 */
function tz_current_key(): string
{
    $raw = $_COOKIE[TZ_COOKIE_NAME] ?? '';
    $canon = tz_canonical_key((string)$raw);
    return $canon ?? tz_default_key();
}

/**
 * Current selected primary display timezone ID (cookie-based).
 */
function tz_current_id(): string
{
    return tz_map()[tz_current_key()];
}

/**
 * Persist the primary display timezone key into the cookie.
 * Returns true if applied, false if invalid key.
 */
function tz_set_cookie(string $key): bool
{
    $canon = tz_canonical_key($key);
    if ($canon === null) return false;

    $days = (int)TZ_COOKIE_DAYS;
    $expires = time() + max(1, $days) * 86400;
    $paths = [];
    $paths[] = defined('COOKIE_PATH') ? COOKIE_PATH : '/';
    if (defined('BASE_URL') && BASE_URL !== '') {
        $paths[] = rtrim((string)BASE_URL, '/') . '/';
    }
    $paths[] = '/';
    $paths = array_values(array_unique(array_filter($paths)));

    foreach ($paths as $path) {
        setcookie(TZ_COOKIE_NAME, $canon, [
            'expires'  => $expires,
            'path'     => $path,
            'secure'   => false, // v1 internal
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    // Make it available immediately in this request too
    $_COOKIE[TZ_COOKIE_NAME] = $canon;
    return true;
}

/**
 * Current selected secondary display timezone key (cookie-based).
 * Returns null when the second operator clock is disabled.
 */
function tz_current_secondary_key(): ?string
{
    $raw = strtolower(trim((string)($_COOKIE[TZ_SECONDARY_COOKIE_NAME] ?? '')));
    if ($raw === '' || $raw === 'none') {
        return null;
    }

    $canon = tz_canonical_key($raw);
    if ($canon !== null && $canon !== tz_current_key()) {
        return $canon;
    }

    $fallback = defined('APP_TZ_DISPLAY_SECONDARY') ? APP_TZ_DISPLAY_SECONDARY : null;
    if ($fallback === null) {
        return null;
    }

    foreach (tz_map() as $key => $tzId) {
        if ($tzId === $fallback && $key !== tz_current_key()) {
            return $key;
        }
    }

    return null;
}

/**
 * Current selected secondary display timezone ID (cookie-based).
 * Returns null when the second operator clock is disabled.
 */
function tz_current_secondary_id(): ?string
{
    $key = tz_current_secondary_key();
    if ($key === null) {
        return null;
    }
    return tz_map()[$key] ?? null;
}

/**
 * Persist the optional secondary display timezone key into the cookie.
 * Accepts "none" or empty string to disable the second operator clock.
 */
function tz_set_secondary_cookie(?string $key): bool
{
    $raw = strtolower(trim((string)$key));
    $value = 'none';

    if ($raw !== '' && $raw !== 'none') {
        $canon = tz_canonical_key($raw);
        if ($canon === null || $canon === tz_current_key()) {
            return false;
        }
        $value = $canon;
    }

    $days = (int)TZ_COOKIE_DAYS;
    $expires = time() + max(1, $days) * 86400;
    $paths = [];
    $paths[] = defined('COOKIE_PATH') ? COOKIE_PATH : '/';
    if (defined('BASE_URL') && BASE_URL !== '') {
        $paths[] = rtrim((string)BASE_URL, '/') . '/';
    }
    $paths[] = '/';
    $paths = array_values(array_unique(array_filter($paths)));

    foreach ($paths as $path) {
        setcookie(TZ_SECONDARY_COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => $path,
            'secure'   => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    $_COOKIE[TZ_SECONDARY_COOKIE_NAME] = $value;
    return true;
}

/**
 * Parse a UTC timestamp string to a DateTimeImmutable.
 * Returns null if input is null/empty/unparseable.
 */
function utc_parse(?string $utcTs): ?DateTimeImmutable
{
    if ($utcTs === null) return null;
    $utcTs = trim($utcTs);
    if ($utcTs === '') return null;

    try {
        return new DateTimeImmutable($utcTs, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Format a UTC timestamp string into a timezone.
 * Returns '' if input is null/empty/unparseable.
 */
function fmt_utc_to_tz(?string $utcTs, ?string $tzId = null, string $fmt = 'Y-m-d H:i:s'): string
{
    $dt = utc_parse($utcTs);
    if ($dt === null) return '';

    $tzId = tz_safe($tzId ?: APP_TZ_DISPLAY_DEFAULT);
    return $dt->setTimezone(new DateTimeZone($tzId))->format($fmt);
}

/**
 * Format UTC timestamp string into the currently selected display timezone (cookie-based).
 */
function fmt_utc_display(?string $utcTs, string $fmt = 'Y-m-d H:i:s'): string
{
    return fmt_utc_to_tz($utcTs, tz_current_id(), $fmt);
}

/**
 * Format UTC timestamp string into the default display timezone.
 */
function fmt_utc_local(?string $utcTs, string $fmt = 'Y-m-d H:i:s'): string
{
    return fmt_utc_to_tz($utcTs, APP_TZ_DISPLAY_DEFAULT, $fmt);
}

/**
 * Format UTC timestamp string into the secondary timezone (if enabled).
 * Returns null if the second operator clock is disabled.
 */
function fmt_utc_secondary(?string $utcTs, string $fmt = 'Y-m-d H:i:s'): ?string
{
    $secondaryTz = tz_current_secondary_id();
    if ($secondaryTz === null) {
        return null;
    }
    return fmt_utc_to_tz($utcTs, $secondaryTz, $fmt);
}

/**
 * Dual-time display: primary (selected display TZ) + secondary (optional).
 * Example:
 * "2026-01-06 13:20:00 (America/Chicago) | 2026-01-06 19:20:00 (UTC)"
 */
function fmt_utc_dual(?string $utcTs, string $fmt = 'Y-m-d H:i:s'): string
{
    $primaryTz = tz_current_id();
    $primary = fmt_utc_to_tz($utcTs, $primaryTz, $fmt);

    $secondaryTz = tz_current_secondary_id();
    if ($secondaryTz === null) return $primary;
    $secondary = fmt_utc_to_tz($utcTs, $secondaryTz, $fmt);

    return "{$primary} ({$primaryTz}) | {$secondary} ({$secondaryTz})";
}

/**
 * Debug helper: current time in all supported timezones.
 */
function now_in_all_timezones(string $fmt = 'Y-m-d H:i:s'): array
{
    $utcNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $out = [];
    foreach (tz_map() as $key => $tzId) {
        $tzId = tz_safe($tzId);
        $out[$key] = [
            'tz'  => $tzId,
            'now' => $utcNow->setTimezone(new DateTimeZone($tzId))->format($fmt),
        ];
    }
    return $out;
}

/**
 * UI dropdown options for Settings.
 */
function tz_display_options(): array
{
    return [
        ['key' => 'new_york',    'label' => 'Eastern default: New York (America/New_York)', 'tz' => TZ_NEW_YORK],
        ['key' => 'minneapolis', 'label' => 'Central default: Minneapolis (America/Chicago)', 'tz' => TZ_MINNEAPOLIS],
        ['key' => 'denver',      'label' => 'Mountain default: Denver (America/Denver)', 'tz' => TZ_DENVER],
        ['key' => 'las_vegas',   'label' => 'Pacific default: Las Vegas (America/Los_Angeles)', 'tz' => TZ_LAS_VEGAS],
        ['key' => 'anchorage',   'label' => 'Alaska default: Anchorage (America/Anchorage)', 'tz' => TZ_ANCHORAGE],
        ['key' => 'honolulu',    'label' => 'Hawaii default: Honolulu (Pacific/Honolulu)', 'tz' => TZ_HONOLULU],
        ['key' => 'utc',         'label' => 'UTC', 'tz' => TZ_UTC],
        ['key' => 'amsterdam',   'label' => 'Amsterdam (Europe/Amsterdam)', 'tz' => TZ_AMSTERDAM],
        ['key' => 'paris',       'label' => 'Paris (Europe/Paris)', 'tz' => TZ_PARIS],
        ['key' => 'tokyo',       'label' => 'Tokyo (Asia/Tokyo)', 'tz' => TZ_TOKYO],
        ['key' => 'dubai',       'label' => 'Dubai (Asia/Dubai)', 'tz' => TZ_DUBAI],
    ];
}

/**
 * UI dropdown options for the optional second operator clock.
 */
function tz_secondary_options(): array
{
    return array_merge(
        [['key' => 'none', 'label' => 'No second operator clock', 'tz' => '']],
        tz_display_options()
    );
}

function tz_display_label(string $key): string
{
    foreach (tz_display_options() as $opt) {
        if (($opt['key'] ?? '') === $key) {
            return (string)$opt['label'];
        }
    }
    return $key;
}

function tz_us_defaults(): array
{
    return [
        ['zone' => 'Eastern', 'key' => 'new_york', 'label' => 'New York', 'tz' => TZ_NEW_YORK],
        ['zone' => 'Central', 'key' => 'minneapolis', 'label' => 'Minneapolis', 'tz' => TZ_MINNEAPOLIS],
        ['zone' => 'Mountain', 'key' => 'denver', 'label' => 'Denver', 'tz' => TZ_DENVER],
        ['zone' => 'Pacific', 'key' => 'las_vegas', 'label' => 'Las Vegas', 'tz' => TZ_LAS_VEGAS],
        ['zone' => 'Alaska', 'key' => 'anchorage', 'label' => 'Anchorage', 'tz' => TZ_ANCHORAGE],
        ['zone' => 'Hawaii', 'key' => 'honolulu', 'label' => 'Honolulu', 'tz' => TZ_HONOLULU],
    ];
}
