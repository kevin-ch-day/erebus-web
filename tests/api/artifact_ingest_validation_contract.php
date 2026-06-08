<?php
declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$url = rtrim($baseUrl, '/') . '/api.php/artifact_ingest_queue.php';
$payload = json_encode([
    'items' => [
        [
            'artifact_hash' => 'not-a-valid-hash',
            'artifact_source' => 'manual',
        ],
    ],
], JSON_UNESCAPED_SLASHES);

$headers = [
    'Content-Type: application/json',
    'Origin: ' . rtrim($baseUrl, '/'),
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $payload,
        'ignore_errors' => true,
        'timeout' => 10,
    ],
]);

$raw = @file_get_contents($url, false, $context);
if ($raw === false) {
    fwrite(STDERR, "Request failed for {$url}\n");
    exit(1);
}

$decoded = json_decode($raw, true);
$errors = [];

if (!is_array($decoded)) {
    $errors[] = 'Expected JSON response.';
} else {
    if (($decoded['ok'] ?? null) !== true) {
        $errors[] = 'Expected ok=true response envelope for invalid-row queue submission.';
    }
    $data = $decoded['data'] ?? null;
    if (!is_array($data)) {
        $errors[] = 'Expected response.data array.';
    } else {
        if (($data['accepted'] ?? null) !== 0) {
            $errors[] = 'Expected accepted=0 for invalid hash row.';
        }
        if (($data['failed'] ?? null) !== 1) {
            $errors[] = 'Expected failed=1 for invalid hash row.';
        }
        if (!is_array($data['row_results'] ?? null) || count($data['row_results']) !== 1) {
            $errors[] = 'Expected one row_results entry.';
        } else {
            $status = strtolower((string)($data['row_results'][0]['status'] ?? ''));
            if ($status !== 'invalid_hash') {
                $errors[] = 'Expected row_results[0].status=invalid_hash.';
            }
        }
        if (!is_array($data['warnings'] ?? null)) {
            $errors[] = 'Expected warnings array.';
        }
    }
}

if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "artifact_ingest_validation_contract ok\n";
