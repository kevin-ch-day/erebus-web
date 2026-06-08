<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/landing_snapshot.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        if (!array_key_exists('ok', $decoded) || !is_bool($decoded['ok'])) {
            $errors[] = 'Expected boolean ok envelope.';
        }

        $payload = $decoded['data'] ?? [];
        $meta = $decoded['meta'] ?? [];

        foreach (['generated_at_utc', 'health', 'family', 'stack'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            }
        }

        if (isset($payload['health']) && is_array($payload['health'])) {
            foreach (['eligible_now', 'processing_now', 'error_count', 'retry_wait_count', 'stale_claims', 'reason_breakdown', 'hold_until_utc', 'hold_reason_code'] as $key) {
                if (!array_key_exists($key, $payload['health'])) {
                    $errors[] = sprintf('Missing data.health.%s.', $key);
                }
            }
        }

        if (isset($payload['family']) && is_array($payload['family'])) {
            foreach (['summary', 'top_mismatch_pairs'] as $key) {
                if (!array_key_exists($key, $payload['family'])) {
                    $errors[] = sprintf('Missing data.family.%s.', $key);
                }
            }
        }

        if (isset($payload['stack']) && is_array($payload['stack'])) {
            foreach (['gap_count', 'gaps', 'ui_spec_count', 'api_contract_count', 'typed_source_page_count', 'ts_page_count'] as $key) {
                if (!array_key_exists($key, $payload['stack'])) {
                    $errors[] = sprintf('Missing data.stack.%s.', $key);
                }
            }
        }

        foreach (['generated_at_utc', 'schema_surface'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: landing snapshot contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: landing snapshot contract passed.\n";
exit(0);
