<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/database/db_func.php';

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$errors = [];

$sampleRow = db_one('SELECT sample_id FROM malware_sample_catalog ORDER BY sample_id DESC LIMIT 1');
$sampleId = (int)($sampleRow['sample_id'] ?? 0);
if ($sampleId <= 0) {
    $errors[] = 'No sample row available for sample detail contract.';
}

if ($errors === []) {
    $url = $baseUrl . '/api.php/sample_detail.php?sample_id=' . $sampleId;
    $response = @file_get_contents($url);
    if ($response === false) {
        $errors[] = sprintf('Request failed for %s', $url);
    } else {
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
        } else {
            $payload = $decoded['data'] ?? $decoded;
            foreach (['ok', 'sample', 'platform_context'] as $key) {
                if (!array_key_exists($key, $payload)) {
                    $errors[] = sprintf('Missing required key: %s', $key);
                }
            }

            $platformContext = $payload['platform_context'] ?? null;
            if (!is_array($platformContext)) {
                $errors[] = 'Expected platform_context to be an object.';
            } else {
                $requiredFields = [
                    'primary_catalog',
                    'permission_intel_catalog',
                    'split_enabled',
                    'primary_schema_head',
                    'permission_intel_schema_head',
                    'schema_heads_match',
                    'sample_has_last_run',
                    'sample_last_run_against_current_primary',
                    'sample_last_run_schema_matches_primary_head',
                    'sample_last_run_perm_taxonomy_matches_latest',
                    'sample_platform_state_mismatch',
                ];
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $platformContext)) {
                        $errors[] = sprintf('Missing platform_context.%s', $field);
                    }
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: sample detail platform contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: sample detail platform contract check passed.\n";
exit(0);
