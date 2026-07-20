<?php

declare(strict_types=1);

$baseUrl = rtrim(getenv('BASE_URL') ?: 'http://localhost', '/');
$endpoints = [
    'android_permission_queue_update.php',
    'android_permission_triage_update.php',
    'artifact_ingest_queue.php',
    'sample_update.php',
];
$errors = [];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . '/api.php/' . $endpoint;
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nOrigin: " . $baseUrl,
            'content' => '{}',
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;

    if (!is_array($decoded)) {
        $errors[] = sprintf('%s did not return a JSON response.', $endpoint);
        continue;
    }

    if (($decoded['code'] ?? '') === 'ERR_WRITE_DISABLED') {
        continue;
    }

    if (($decoded['code'] ?? '') === 'ERR_WRITE_FORBIDDEN') {
        echo "SKIP: write-gate contract requires a localhost same-origin test target.\n";
        exit(0);
    }

    $errors[] = sprintf('%s was not blocked by FEATURE_PHASE3_OPS: %s', $endpoint, (string)($decoded['code'] ?? 'no error code'));
}

if ($errors !== []) {
    echo "FAIL: write operations disabled contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: disabled write operations are blocked before payload handling.\n";
