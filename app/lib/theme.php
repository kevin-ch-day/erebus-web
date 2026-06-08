<?php
// app/lib/theme.php
// Theme helpers for Erebus Web.

declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Allowed theme keys.
 */
function theme_options(): array
{
    return [
        ['key' => 'dark', 'label' => 'Dark'],
        ['key' => 'light', 'label' => 'Light'],
    ];
}

/**
 * Canonicalize theme input or return null if invalid.
 */
function theme_canonical(?string $value): ?string
{
    $value = strtolower(trim((string)$value));
    if ($value === '') return null;
    if ($value === 'dark' || $value === 'light') return $value;

    // Accept common boolean-ish inputs.
    if (in_array($value, ['1', 'on', 'true', 'yes'], true)) return 'dark';
    if (in_array($value, ['0', 'off', 'false', 'no'], true)) return 'light';

    return null;
}

/**
 * Current selected theme key (cookie-based).
 */
function theme_current(): string
{
    $raw = $_COOKIE[THEME_COOKIE_NAME] ?? '';
    $canon = theme_canonical((string)$raw);
    $default = defined('APP_THEME_DEFAULT') ? (string)APP_THEME_DEFAULT : 'dark';
    return $canon ?? $default;
}

/**
 * Persist the theme key into the cookie.
 * Returns true if applied, false if invalid.
 */
function theme_set_cookie(string $value): bool
{
    $canon = theme_canonical($value);
    if ($canon === null) return false;

    $days = (int)(defined('TZ_COOKIE_DAYS') ? TZ_COOKIE_DAYS : 30);
    $expires = time() + max(1, $days) * 86400;
    $paths = [];
    $paths[] = defined('COOKIE_PATH') ? COOKIE_PATH : '/';
    if (defined('BASE_URL') && BASE_URL !== '') {
        $paths[] = rtrim((string)BASE_URL, '/') . '/';
    }
    $paths[] = '/';
    $paths = array_values(array_unique(array_filter($paths)));

    foreach ($paths as $path) {
        setcookie(THEME_COOKIE_NAME, $canon, [
            'expires'  => $expires,
            'path'     => $path,
            'secure'   => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    $_COOKIE[THEME_COOKIE_NAME] = $canon;
    return true;
}
