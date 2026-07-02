<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/pending_source_mix_snapshot.php';

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
        foreach (['sources', 'totals', 'pipeline', 'recommended_lane'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('Missing pending source mix key: %s', $key);
            }
        }
        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
        if (!array_key_exists('pending_rows', $totals)) {
            $errors[] = 'Missing pending source mix.totals.pending_rows';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("OK pending_source_mix_snapshot contract (%s)\n", $url));
exit(0);
