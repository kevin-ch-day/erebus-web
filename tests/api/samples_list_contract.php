<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/samples_list.php?page_size=5&columns=simple&sort_by=id&sort_dir=desc&page=1';

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
        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];

        foreach (['page', 'page_size', 'total_count', 'total_pages', 'has_more', 'rows'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('Missing data.%s', $key);
            }
        }

        foreach (['generated_at_utc', 'schema_surface', 'server_utc_now', 'request_id'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s', $key);
            }
        }

        if (array_key_exists('rows', $data) && !is_array($data['rows'])) {
            $errors[] = 'Expected data.rows to be an array.';
        }
    }
}

if ($errors !== []) {
    echo "FAIL: samples list contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: samples list contract check passed.\n";
exit(0);
