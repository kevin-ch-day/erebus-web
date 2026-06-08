<?php
// public/index.php
declare(strict_types=1);

// ------------------------------------------------------------
// Erebus Web Console Router (trusted internal tool)
//
// Goals (v1):
// - Safety & stability (NOT security; no auth yet).
// - Allowlisted routes only (prevents arbitrary include).
// - Consistent error behavior (APP_DEBUG).
// - Keep this file small and boring.
// ------------------------------------------------------------

require_once __DIR__ . '/../app/lib/app_config.php';
require_once __DIR__ . '/../app/lib/error.php';
require_once __DIR__ . '/../app/lib/logger.php';
require_once __DIR__ . '/../app/lib/routes.php';

// Optional: standardize timezone behavior for PHP itself.
// (DB remains UTC; UI formatting handled in time.php.)
date_default_timezone_set('UTC');

// Error visibility (APP_DEBUG controls browser output)
error_reporting(E_ALL);
ini_set('display_errors', (defined('APP_DEBUG') && APP_DEBUG) ? '1' : '0');

// Request ID for logging + error reporting.
$requestId = bin2hex(random_bytes(4));

// Global error/exception handling.
set_exception_handler(function (Throwable $e) use ($requestId): void {
    $context = [
        'route' => $_GET['p'] ?? null,
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
    ];
    log_exception($e, $requestId, 'app', 'UNCAUGHT_EXCEPTION', $context, 'app');
    render_error_page('Application Error', 'An unexpected error occurred.', $requestId, $e, 500);
});

set_error_handler(function (int $severity, string $message, string $file, int $line) use ($requestId): bool {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    log_event(
        'ERROR',
        'app',
        'PHP_ERROR',
        $message,
        $requestId,
        [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'route' => $_GET['p'] ?? null,
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        ],
        'app'
    );
    return true;
});

register_shutdown_function(function () use ($requestId): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    log_event(
        'ERROR',
        'app',
        'PHP_FATAL',
        $error['message'],
        $requestId,
        [
            'file' => $error['file'],
            'line' => $error['line'],
            'route' => $_GET['p'] ?? null,
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        ],
        'app'
    );
    if (!headers_sent()) {
        render_error_page('Application Error', 'A fatal error occurred.', $requestId, null, 500);
    }
});

// Normalize page param (defensive). Support legacy ?page=<route> links too,
// but do not confuse numeric page pagination with route selection.
$legacyPageRaw = trim((string)($_GET['page'] ?? ''));
$pageParam = trim((string)($_GET['p'] ?? ''));
if ($pageParam === '' && $legacyPageRaw !== '' && !ctype_digit($legacyPageRaw)) {
    $pageParam = $legacyPageRaw;
}
$page = strtolower($pageParam);
if ($page === '') {
    $page = 'landing';
}

// If samples filters are present but no page is set, assume samples.
if ($page === 'landing' && $pageParam === '') {
    $samplesKeys = ['page', 'page_size', 'status', 'q', 'family', 'columns', 'sort_by', 'sort_dir'];
    foreach ($samplesKeys as $key) {
        if (isset($_GET[$key])) {
            $page = 'samples';
            break;
        }
    }
}

$page = app_resolve_route_key($page, 'landing');

// Resolve view (fallback to error)
$view = app_route_view($page);
$routeContext = [
    'route' => $page,
    'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
];
if ($view === null) {
    log_event('WARN', 'security', 'ROUTE_NOT_FOUND', 'Unknown route', $requestId, $routeContext, 'security');
    render_error_page('Not Found', 'The requested page was not found.', $requestId, null, 404);
    exit;
}

// Handle settings POST before layout output (headers/cookies must be sent early).
if ($page === 'settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_once __DIR__ . '/../app/handlers/settings_post.php';
    exit;
}

// Extra safety: ensure file exists (should always be true in normal operation)
if (!is_file($view)) {
    log_event(
        'WARN',
        'app',
        'VIEW_MISSING',
        'Missing view file',
        $requestId,
        array_merge($routeContext, ['view' => $view]),
        'app'
    );
    render_error_page('Not Found', 'The requested page was not found.', $requestId, null, 404);
    exit;
}

// Shared layout shell
require_once __DIR__ . '/../app/lib/header.php';

try {
    require $view;
} catch (Throwable $e) {
    log_exception($e, $requestId, 'app', 'VIEW_EXCEPTION', $routeContext, 'app');
    render_error_page('Application Error', 'An unexpected error occurred.', $requestId, $e, 500);
}

require_once __DIR__ . '/../app/lib/footer.php';
