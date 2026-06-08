<?php
// app/lib/error.php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/logger.php';

/**
 * Render a friendly error page with optional debug details.
 */
function render_error_page(
    string $title,
    string $message,
    string $requestId,
    ?Throwable $exception = null,
    int $httpStatus = 500
): void {
    http_response_code($httpStatus);

    $errorTitle = $title;
    $errorMessage = $message;
    $errorHttpStatus = $httpStatus;
    $utcNow = gmdate('Y-m-d H:i:s') . ' UTC';
    $showDebug = (defined('APP_DEBUG') && APP_DEBUG);
    $errorException = $exception;
    $errorRoute = trim((string)($_GET['p'] ?? ''));
    $errorRequestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $errorRouteLabel = $errorRoute !== '' ? $errorRoute : '(none)';
    $errorRecoveryLinks = $httpStatus === 404
        ? [
            ['label' => 'Open landing', 'href' => page_url('landing'), 'primary' => true],
            ['label' => 'Open VT health', 'href' => page_url('health')],
            ['label' => 'Open repair queue', 'href' => page_url('family_taxonomy_queue')],
        ]
        : [
            ['label' => 'Retry landing', 'href' => page_url('landing'), 'primary' => true],
            ['label' => 'Open VT health', 'href' => page_url('health')],
            ['label' => 'Open stack audit', 'href' => page_url('stack_audit')],
        ];
    $errorSummary = $httpStatus === 404
        ? 'The route or bookmark does not map to a live page in this console.'
        : 'The page could not complete because the web app hit an internal failure.';
    $errorHint = $httpStatus === 404
        ? 'Use the landing page or sidebar to reopen a known route. If this came from an old bookmark, update it to the current page path.'
        : 'Retry once. If the problem persists, keep the request ID and inspect API health, logs, or recent code changes before continuing.';

    $pageTitle = $title . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Erebus Web');

    $title = $pageTitle;
    require __DIR__ . '/header.php';
    require __DIR__ . '/../views/error.php';
    require __DIR__ . '/footer.php';
}
