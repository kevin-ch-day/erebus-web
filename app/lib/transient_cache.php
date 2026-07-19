<?php
declare(strict_types=1);

function app_transient_cache_candidate_dirs(): array
{
    $dirs = [];

    $envDir = trim((string)(getenv('APP_CACHE_DIR') ?: ''));
    if ($envDir !== '') {
        $dirs[] = $envDir;
    }

    // Shared app storage first. php-fpm runs with PrivateTmp=yes, so /tmp is not
    // shared with CLI warmers. logs/cache is often httpd_log_t and not writable.
    if (defined('APP_ROOT')) {
        $dirs[] = rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    }

    if (defined('LOG_DIR')) {
        $dirs[] = rtrim((string)LOG_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache';
    }

    $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'erebus_web_cache';
    $dirs[] = $tmpDir;

    return array_values(array_unique($dirs));
}

function app_transient_cache_probe_dir(string $dir): bool
{
    if ($dir === '') {
        return false;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }
    if (!is_writable($dir)) {
        return false;
    }

    $probePath = $dir . DIRECTORY_SEPARATOR . '.probe_' . bin2hex(random_bytes(4));
    $probeValue = 'ok:' . bin2hex(random_bytes(4));
    $bytes = @file_put_contents($probePath, $probeValue, LOCK_EX);
    if ($bytes === false || $bytes !== strlen($probeValue)) {
        @unlink($probePath);
        return false;
    }

    $readBack = @file_get_contents($probePath);
    @unlink($probePath);
    return is_string($readBack) && $readBack === $probeValue;
}

function app_transient_cache_dir(): string
{
    static $resolved = null;
    if (is_string($resolved) && $resolved !== '') {
        return $resolved;
    }

    foreach (app_transient_cache_candidate_dirs() as $dir) {
        if (app_transient_cache_probe_dir($dir)) {
            $resolved = $dir;
            return $resolved;
        }
    }

    // Last resort: still require a writable probe so php-fpm and CLI do not
    // silently diverge onto an unreadable/unwritable path.
    $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'erebus_web_cache';
    if (app_transient_cache_probe_dir($fallback)) {
        $resolved = $fallback;
        return $resolved;
    }

    $resolved = $fallback;
    return $resolved;
}

function app_transient_cache_path(string $namespace, string $cacheKey): string
{
    $safeNamespace = preg_replace('/[^a-z0-9_\\-]+/i', '_', $namespace) ?? 'default';
    return app_transient_cache_dir() . DIRECTORY_SEPARATOR . $safeNamespace . '_' . $cacheKey . '.json';
}

function app_transient_cache_read(string $namespace, string $cacheKey, int $ttlSeconds): ?array
{
    $path = app_transient_cache_path($namespace, $cacheKey);
    if (!is_file($path)) {
        return null;
    }

    $mtime = @filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttlSeconds) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = @unserialize($raw, ['allowed_classes' => false]);
    return is_array($decoded) ? $decoded : null;
}

function app_transient_cache_read_stale(string $namespace, string $cacheKey): ?array
{
    $path = app_transient_cache_path($namespace, $cacheKey);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = @unserialize($raw, ['allowed_classes' => false]);
    return is_array($decoded) ? $decoded : null;
}

function app_transient_cache_write(string $namespace, string $cacheKey, array $payload): void
{
    $path = app_transient_cache_path($namespace, $cacheKey);
    $serialized = serialize($payload);
    if ($serialized === '') {
        return;
    }
    if (@file_put_contents($path, $serialized, LOCK_EX) === false) {
        return;
    }
    if ((int)(@filesize($path) ?: 0) <= 0) {
        @unlink($path);
    }
}

function app_transient_cache_delete_namespace(string $namespace): int
{
    $safeNamespace = preg_replace('/[^a-z0-9_\\-]+/i', '_', $namespace) ?? 'default';
    $pattern = app_transient_cache_dir() . DIRECTORY_SEPARATOR . $safeNamespace . '_*.json';
    $deleted = 0;
    foreach (glob($pattern) ?: [] as $path) {
        if (is_string($path) && is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}
