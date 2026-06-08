<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/permissions.php';

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/android_permission_intelligence.php?limit=25&page_size=25';

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
        $statusModel = $payload['status_model'] ?? null;
        if (!is_array($statusModel)) {
            $errors[] = 'Expected status_model to be an object.';
        } else {
            $operatorStatuses = $statusModel['operator_triage_statuses'] ?? null;
            $deprecatedConfigured = $statusModel['deprecated_configured_triage_statuses'] ?? null;
            $deprecatedLive = $statusModel['deprecated_live_triage_statuses'] ?? null;
            $liveStatuses = $statusModel['live_triage_statuses'] ?? null;

            if (!is_array($operatorStatuses)) {
                $errors[] = 'Expected status_model.operator_triage_statuses to be an array.';
            }
            if (!is_array($deprecatedConfigured)) {
                $errors[] = 'Expected status_model.deprecated_configured_triage_statuses to be an array.';
            }
            if (!is_array($deprecatedLive)) {
                $errors[] = 'Expected status_model.deprecated_live_triage_statuses to be an array.';
            }
            if (!is_array($liveStatuses)) {
                $errors[] = 'Expected status_model.live_triage_statuses to be an array.';
            }

            if (is_array($operatorStatuses)) {
                $deprecatedMap = array_flip(array_map('strtolower', perm_deprecated_triage_status_keys()));
                foreach ($operatorStatuses as $statusKey) {
                    $key = strtolower(trim((string)$statusKey));
                    if ($key !== '' && isset($deprecatedMap[$key])) {
                        $errors[] = sprintf('operator_triage_statuses still exposes deprecated key %s.', $key);
                    }
                }
            }

            if (is_array($deprecatedLive) && is_array($liveStatuses)) {
                $liveMap = array_flip(array_map(static fn($value) => strtolower(trim((string)$value)), $liveStatuses));
                foreach ($deprecatedLive as $statusKey) {
                    $key = strtolower(trim((string)$statusKey));
                    if ($key !== '' && !isset($liveMap[$key])) {
                        $errors[] = sprintf('deprecated_live_triage_statuses contains %s which is not in live_triage_statuses.', $key);
                    }
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: operator status model contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: operator status model contract check passed.\n";
exit(0);
