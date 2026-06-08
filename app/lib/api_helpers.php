<?php
// app/lib/api_helpers.php
// Shared helpers for API endpoints (JSON output + method checks).

declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/logger.php';

if (!defined('APP_REQUEST_START')) {
    define('APP_REQUEST_START', $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
}

/**
 * Compute elapsed milliseconds for the current request.
 */
function api_elapsed_ms(): ?float
{
    if (!defined('APP_REQUEST_START')) {
        return null;
    }
    return round((microtime(true) - (float)APP_REQUEST_START) * 1000, 2);
}

/**
 * Provide a request ID for API calls.
 */
function api_request_id(): string
{
    static $requestId = null;
    if ($requestId === null) {
        $requestId = bin2hex(random_bytes(4));
        header('X-Request-Id: ' . $requestId);
    }
    return $requestId;
}

/**
 * Resolve a route/endpoint label for logging.
 */
function api_log_context(): array
{
    $route = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'api'));
    $endpoint = (string)($_SERVER['REQUEST_URI'] ?? '');
    return [
        'route' => $route,
        'endpoint' => $endpoint,
    ];
}

/**
 * Check whether the request is from localhost.
 */
function api_is_local_request(): bool
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($remote, ['127.0.0.1', '::1'], true);
}

/**
 * Check whether the provided write token matches the configured env token.
 */
function api_has_valid_write_token(): bool
{
    $token = getenv('BS_API_TOKEN') ?: '';
    if ($token === '') {
        return false;
    }
    $header = (string)($_SERVER['HTTP_X_BS_TOKEN'] ?? '');
    return $header !== '' && hash_equals($token, $header);
}

/**
 * Require same-origin browser writes when no API token is provided.
 * This blocks cross-site POSTs from untrusted pages even on localhost.
 */
function api_is_same_origin_write(): bool
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return false;
    }

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $candidate = $origin !== '' ? $origin : $referer;
    if ($candidate === '') {
        return false;
    }

    $parts = @parse_url($candidate);
    if (!is_array($parts)) {
        return false;
    }
    $originHost = (string)($parts['host'] ?? '');
    if ($originHost === '') {
        return false;
    }
    $originScheme = strtolower((string)($parts['scheme'] ?? 'http'));
    $originPort = isset($parts['port']) ? (int)$parts['port'] : (($originScheme === 'https') ? 443 : 80);

    $hostParts = explode(':', $host, 2);
    $reqHost = strtolower(trim((string)($hostParts[0] ?? '')));
    if ($reqHost === '') {
        return false;
    }
    $reqPort = isset($hostParts[1]) ? (int)$hostParts[1] : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 443 : 80));

    return strtolower($originHost) === $reqHost && $originPort === $reqPort;
}

/**
 * Fallback for modern browsers when Origin/Referer are not sent.
 * Only trust this in localhost mode via require_write_access().
 */
function api_is_same_site_fetch(): bool
{
    $site = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($site === '') {
        return false;
    }
    return in_array($site, ['same-origin', 'same-site'], true);
}

/**
 * Require localhost or a shared secret for write endpoints.
 */
function require_write_access(): void
{
    if (api_has_valid_write_token()) {
        return;
    }

    if (api_is_local_request() && (api_is_same_origin_write() || api_is_same_site_fetch())) {
        return;
    }

    api_error('Forbidden', 403, 'ERR_WRITE_FORBIDDEN');
    exit;
}

/**
 * Emit a JSON success payload.
 */
function json_ok(array $payload = []): void
{
    if (!array_key_exists('ok', $payload)) {
        $payload['ok'] = true;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

/**
 * Emit a JSON error payload and set HTTP status.
 */
function json_error(string $message, int $http = 500, array $payload = []): void
{
    http_response_code($http);
    $payload = array_merge(['ok' => false, 'error' => $message], $payload);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

/**
 * Standard API success wrapper (optional data/meta nesting).
 */
function api_ok(array $data = [], array $meta = []): void
{
    if (array_key_exists('ok', $data) || array_key_exists('data', $data) || array_key_exists('meta', $data)) {
        json_ok($data);
        return;
    }

    $requestId = api_request_id();
    if (!array_key_exists('request_id', $meta)) {
        $meta['request_id'] = $requestId;
    }
    if (!array_key_exists('server_utc_now', $meta)) {
        $meta['server_utc_now'] = gmdate('Y-m-d H:i:s') . ' UTC';
    }
    $elapsedMs = api_elapsed_ms();
    if ($elapsedMs !== null && !array_key_exists('elapsed_ms', $meta)) {
        $meta['elapsed_ms'] = $elapsedMs;
    }

    if (defined('APP_API_CONTRACT_VERSION') && APP_API_CONTRACT_VERSION !== '') {
        if (!array_key_exists('contract_version', $meta)) {
            $meta['contract_version'] = APP_API_CONTRACT_VERSION;
        }
    }

    json_ok([
        'ok' => true,
        'data' => $data,
        'meta' => $meta,
    ]);
}

/**
 * Standard API error wrapper.
 */
function api_error(
    string $message,
    int $http = 500,
    string $code = 'ERR_UNKNOWN',
    array $extra = [],
    ?Throwable $exception = null
): void {
    $requestId = $extra['request_id'] ?? api_request_id();
    $logContext = array_merge(api_log_context(), $extra);
    $payload = array_merge([
        'ok' => false,
        'error' => $message,
        'code' => $code,
        'request_id' => $requestId,
        'server_utc_now' => gmdate('Y-m-d H:i:s') . ' UTC',
    ], $extra);
    $elapsedMs = api_elapsed_ms();
    if ($elapsedMs !== null) {
        $payload['elapsed_ms'] = $elapsedMs;
    }

    if ($exception !== null) {
        log_exception($exception, $requestId, 'api', $code, $logContext, 'api');
        if (is_sql_exception($exception)) {
            log_exception($exception, $requestId, 'db', $code, $logContext, 'db');
        }
    } else {
        log_event('ERROR', 'api', $code, $message, $requestId, $logContext, 'api');
    }

    if (defined('APP_DEBUG') && APP_DEBUG && $exception !== null) {
        $payload['debug'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    json_error($message, $http, $payload);
}

/**
 * Require GET requests for API endpoints.
 */
function require_get(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_error('Method Not Allowed', 405, 'ERR_METHOD_NOT_ALLOWED');
        exit;
    }
}

/**
 * Require POST requests for API endpoints.
 */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_error('Method Not Allowed', 405, 'ERR_METHOD_NOT_ALLOWED');
        exit;
    }
}
