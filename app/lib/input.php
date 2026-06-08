<?php
// app/lib/input.php
// Input helpers for request parameters.

declare(strict_types=1);

/**
 * Fetch an integer parameter with clamping.
 */
function get_int(string $key, int $default, int $min, int $max, ?array $source = null): int
{
    $source = $source ?? $_GET;
    if (!isset($source[$key])) {
        return $default;
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }

    if ($value < $min) return $min;
    if ($value > $max) return $max;
    return (int)$value;
}

/**
 * Fetch a string parameter (trimmed, length-limited).
 */
function get_str(string $key, int $maxLen, string $default = '', ?array $source = null): string
{
    $source = $source ?? $_GET;
    if (!isset($source[$key])) {
        return $default;
    }

    $value = trim((string)$source[$key]);
    if ($value === '') {
        return $default;
    }

    if (mb_strlen($value) > $maxLen) {
        $value = mb_substr($value, 0, $maxLen);
    }

    return $value;
}

/**
 * Fetch a string parameter constrained to an allowed list.
 */
function get_enum(string $key, array $allowed, ?string $default = null, ?array $source = null): ?string
{
    $value = get_str($key, 64, '', $source);
    if ($value === '') {
        return $default;
    }

    return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * Fetch a boolean parameter (truthy/falsey strings).
 */
function get_bool(string $key, bool $default = false, ?array $source = null): bool
{
    $value = get_str($key, 16, '', $source);
    if ($value === '') {
        return $default;
    }

    $truthy = ['1', 'true', 'yes', 'on'];
    $falsey = ['0', 'false', 'no', 'off'];

    $lower = strtolower($value);
    if (in_array($lower, $truthy, true)) return true;
    if (in_array($lower, $falsey, true)) return false;

    return $default;
}
