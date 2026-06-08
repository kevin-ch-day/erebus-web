<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/schema_inventory.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        $payload = $decoded['data'] ?? $decoded;
        $meta = $decoded['meta'] ?? [];

        if (!array_key_exists('summary', $payload) || !is_array($payload['summary'])) {
            $errors[] = 'Missing data.summary object.';
        }
        if (!array_key_exists('surfaces', $payload) || !is_array($payload['surfaces'])) {
            $errors[] = 'Missing data.surfaces array.';
        }
        foreach (['primary_database', 'permission_intel_database', 'permission_intel_split'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }

        $names = [];
        foreach (($payload['surfaces'] ?? []) as $surface) {
            if (isset($surface['name'])) {
                $names[] = (string)$surface['name'];
            }
        }
        foreach ([
            'virustotal_sample_vendor_verdicts',
            'virustotal_sample_vendor_engine_verdicts',
            'vt_vendor_reliability_profile',
            'vt_vendor_projection_profile',
            'virustotal_sample_signal_current',
            'vt_sample_verdict_confidence_current',
            'v_vt_false_positive_review_candidates',
            'v_vt_false_positive_review_candidates_effective',
            'v_android_permission_attack_surface_current',
            'android_permission_attack_mapping',
            'android_permission_concept',
            'android_permission_token_alias',
            'permission_governance_snapshot_rows',
            'permission_signal_catalog',
            'vw_permission_remaining_work_queues',
            'vw_permission_unknown_unresolved_candidates',
            'vw_permission_source_family_coverage',
        ] as $expectedSurface) {
            if (!in_array($expectedSurface, $names, true)) {
                $errors[] = sprintf('Missing known surface: %s.', $expectedSurface);
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: schema inventory contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: schema inventory contract check passed.\n";
exit(0);
