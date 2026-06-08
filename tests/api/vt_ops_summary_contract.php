<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/vt_ops_summary.php';

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

        foreach (['generated_at_utc', 'catalogs', 'schema_heads', 'system_control', 'metrics', 'vt_surface_summary', 'confidence_schema', 'key_posture', 'vendor_model_summary', 'signal_surface_summary', 'family_taxonomy_summary'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            }
        }
        foreach (['primary_database', 'permission_intel_database', 'permission_intel_split'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }

        if (isset($payload['vt_surface_summary']) && is_array($payload['vt_surface_summary'])) {
            foreach (['known_count', 'available_count', 'missing_count', 'missing_names'] as $key) {
                if (!array_key_exists($key, $payload['vt_surface_summary'])) {
                    $errors[] = sprintf('Missing data.vt_surface_summary.%s.', $key);
                }
            }
        }

        if (isset($payload['key_posture']) && is_array($payload['key_posture'])) {
            foreach (['supports_leases', 'total_keys', 'enabled_visible_keys', 'eligible_keys', 'cooling_keys', 'quota_blocked_keys'] as $key) {
                if (!array_key_exists($key, $payload['key_posture'])) {
                    $errors[] = sprintf('Missing data.key_posture.%s.', $key);
                }
            }
        }

        if (isset($payload['vendor_model_summary']) && is_array($payload['vendor_model_summary'])) {
            foreach (['canonical_vendor_rows', 'projection_rows', 'collision_rows', 'reliability_rows', 'projection_profile_rows', 'delta_rows_30d'] as $key) {
                if (!array_key_exists($key, $payload['vendor_model_summary'])) {
                    $errors[] = sprintf('Missing data.vendor_model_summary.%s.', $key);
                }
            }
        }

        if (isset($payload['signal_surface_summary']) && is_array($payload['signal_surface_summary'])) {
            foreach (['signal_current_rows', 'confidence_rows', 'parse_versions'] as $key) {
                if (!array_key_exists($key, $payload['signal_surface_summary'])) {
                    $errors[] = sprintf('Missing data.signal_surface_summary.%s.', $key);
                }
            }
        }

        if (isset($payload['family_taxonomy_summary']) && is_array($payload['family_taxonomy_summary'])) {
            foreach (['available', 'mismatch_rows', 'signal_only_rows', 'catalog_only_rows', 'high_conflict_rows', 'risk_class'] as $key) {
                if (!array_key_exists($key, $payload['family_taxonomy_summary'])) {
                    $errors[] = sprintf('Missing data.family_taxonomy_summary.%s.', $key);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: vt ops summary contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: vt ops summary contract check passed.\n";
exit(0);
