<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/android_permission_classification_gaps.php?limit=3';

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

        foreach (['summary', 'gaps'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            } elseif (!is_array($payload[$key])) {
                $errors[] = sprintf('Expected data.%s to be an array.', $key);
            }
        }

        foreach (['schema_available', 'primary_database', 'permission_intel_database', 'permission_intel_split', 'limit'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }

        if (($meta['schema_available'] ?? false) === true && isset($payload['gaps'][0])) {
            $row = $payload['gaps'][0];
            foreach ([
                'sample_id',
                'attack_technique_id',
                'max_mapping_strength_rank',
                'classification_gap_reason',
                'workflow_state',
                'workflow_label',
                'review_priority',
            ] as $rowKey) {
                if (!array_key_exists($rowKey, $row)) {
                    $errors[] = sprintf('Missing gaps[0].%s.', $rowKey);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: classification gaps contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: classification gaps contract check passed.\n";
exit(0);
