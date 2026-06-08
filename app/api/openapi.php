<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';

require_get();

$specPath = dirname(__DIR__, 2) . '/openapi.json';

if (!is_file($specPath)) {
    api_error('OpenAPI contract file is missing.', 500, 'ERR_OPENAPI_MISSING');
    exit;
}

$raw = file_get_contents($specPath);
if ($raw === false) {
    api_error('Failed to read OpenAPI contract file.', 500, 'ERR_OPENAPI_READ');
    exit;
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    api_error('OpenAPI contract file is invalid JSON.', 500, 'ERR_OPENAPI_INVALID');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
