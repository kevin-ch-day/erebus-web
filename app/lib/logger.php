<?php
// app/lib/logger.php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Map log levels to numeric severity.
 */
function log_level_rank(string $level): int
{
    $map = [
        'DEBUG' => 10,
        'INFO' => 20,
        'WARN' => 30,
        'ERROR' => 40,
    ];
    $upper = strtoupper($level);
    return $map[$upper] ?? 20;
}

/**
 * Check if a level should be written based on LOG_LEVEL.
 */
function log_should_write(string $level): bool
{
    $min = defined('LOG_LEVEL') ? (string)LOG_LEVEL : 'INFO';
    return log_level_rank($level) >= log_level_rank($min);
}

/**
 * Extract request metadata for logs.
 */
function log_request_meta(): array
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $elapsedMs = null;
    if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        $elapsedMs = round((microtime(true) - (float)$_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2);
    }

    return [
        'method' => $method,
        'ip' => $ip,
        'user_agent' => $ua,
        'elapsed_ms' => $elapsedMs,
    ];
}

/**
 * Write a JSON log line to a named channel.
 */
function log_event(
    string $level,
    string $area,
    string $event,
    string $message,
    string $requestId,
    array $context = [],
    ?string $channel = null
): void {
    if (!log_should_write($level)) {
        return;
    }

    $logDir = defined('LOG_DIR') ? LOG_DIR : (__DIR__ . '/../../logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $channelName = $channel ?? $area;
    $logFile = rtrim($logDir, '/') . '/' . $channelName . '.log';
    $meta = log_request_meta();
    $entry = [
        'ts_utc' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'level' => $level,
        'area' => $area,
        'event' => $event,
        'msg' => $message,
        'request_id' => $requestId,
        'uri' => $_SERVER['REQUEST_URI'] ?? 'cli',
        'method' => $meta['method'],
        'ip' => $meta['ip'],
        'user_agent' => $meta['user_agent'],
        'elapsed_ms' => $meta['elapsed_ms'],
    ];

    if (!empty($context)) {
        $entry['ctx'] = $context;
    }

    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

/**
 * Log an exception with optional stack trace when APP_DEBUG is enabled.
 */
function log_exception(
    Throwable $e,
    string $requestId,
    string $area,
    string $event,
    array $context = [],
    ?string $channel = null
): void {
    $context = array_merge($context, [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if (defined('APP_DEBUG') && APP_DEBUG) {
        $context['trace'] = $e->getTraceAsString();
    }

    log_event('ERROR', $area, $event, $e->getMessage(), $requestId, $context, $channel);
}

/**
 * Detect SQL exceptions by message content.
 */
function is_sql_exception(Throwable $e): bool
{
    return str_contains($e->getMessage(), 'SQLSTATE');
}
