<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/ingest_backlog_snapshot.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        foreach (['ok', 'data', 'meta'] as $key) {
            if (!array_key_exists($key, $decoded)) {
                $errors[] = sprintf('Missing required key: %s', $key);
            }
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        foreach (['totals', 'operator', 'cleanup', 'lanes', 'pipeline'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('Missing ingest snapshot key: %s', $key);
            }
        }

        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
        foreach (['pending_rows', 'processing_rows', 'failed_rows'] as $key) {
            if (!array_key_exists($key, $totals)) {
                $errors[] = sprintf('Missing ingest snapshot.totals key: %s', $key);
            }
        }

        $pipeline = is_array($data['pipeline'] ?? null) ? $data['pipeline'] : [];
        if (!array_key_exists('queue_lanes', $pipeline)) {
            $errors[] = 'Missing ingest snapshot.pipeline.queue_lanes';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("OK ingest_backlog_snapshot contract (%s)\n", $url));
exit(0);
