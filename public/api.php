<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/lib/app_config.php';

$requested = trim((string)($_GET['f'] ?? ''));
if ($requested === '') {
    $pathInfo = trim((string)($_SERVER['PATH_INFO'] ?? ''));
    if ($pathInfo !== '') {
        $requested = ltrim($pathInfo, '/');
    } else {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $prefix = $scriptName !== '' ? ($scriptName . '/') : '/api.php/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, $prefix)) {
            $requested = substr($path, strlen($prefix));
        }
    }
}

if ($requested === '') {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Missing API target.',
        'code' => 'ERR_API_PROXY_TARGET',
    ]);
    exit;
}

$normalized = ltrim(str_replace('\\', '/', $requested), '/');
if (!preg_match('/^[A-Za-z0-9_.\\/-]+\\.php$/', $normalized)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid API target.',
        'code' => 'ERR_API_PROXY_INVALID',
    ]);
    exit;
}

$apiRoot = realpath(APP_ROOT . '/app/api');
$target = realpath(APP_ROOT . '/app/api/' . $normalized);
if ($apiRoot === false || $target === false || !str_starts_with($target, $apiRoot . DIRECTORY_SEPARATOR) || !is_file($target)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'API target not found.',
        'code' => 'ERR_API_PROXY_NOT_FOUND',
    ]);
    exit;
}

require $target;
