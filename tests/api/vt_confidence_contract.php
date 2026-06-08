<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/vt_confidence.php?limit=3';

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

        foreach (['summary', 'false_positive_review_summary', 'false_positive_review_candidates', 'vendor_model_summary', 'signal_surface_summary'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            } elseif (!is_array($payload[$key])) {
                $errors[] = sprintf('Expected data.%s to be an array.', $key);
            }
        }

        foreach (['schema_available', 'primary_database', 'limit'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }

        if (($meta['schema_available'] ?? false) === true && isset($payload['summary'][0])) {
            $row = $payload['summary'][0];
            foreach (['confidence_bucket', 'recommended_action', 'sample_count'] as $rowKey) {
                if (!array_key_exists($rowKey, $row)) {
                    $errors[] = sprintf('Missing summary[0].%s.', $rowKey);
                }
            }
        }

        if (($meta['schema_available'] ?? false) === true && isset($payload['false_positive_review_candidates'][0])) {
            $row = $payload['false_positive_review_candidates'][0];
            foreach (['sample_id', 'sha256', 'confidence_score', 'review_reason'] as $rowKey) {
                if (!array_key_exists($rowKey, $row)) {
                    $errors[] = sprintf('Missing false_positive_review_candidates[0].%s.', $rowKey);
                }
            }
        }

        if (isset($payload['vendor_model_summary'])) {
            foreach (['canonical_vendor_rows', 'projection_rows', 'reliability_rows', 'changed_engines_sum_30d'] as $key) {
                if (!array_key_exists($key, $payload['vendor_model_summary'])) {
                    $errors[] = sprintf('Missing data.vendor_model_summary.%s.', $key);
                }
            }
        }

        if (isset($payload['signal_surface_summary'])) {
            foreach (['signal_current_rows', 'confidence_rows', 'parse_versions'] as $key) {
                if (!array_key_exists($key, $payload['signal_surface_summary'])) {
                    $errors[] = sprintf('Missing data.signal_surface_summary.%s.', $key);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: VT confidence contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: VT confidence contract check passed.\n";
exit(0);
