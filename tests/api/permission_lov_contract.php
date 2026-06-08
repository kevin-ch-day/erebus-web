<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/permissions.php';

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/android_permission_lov.php';

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
        $triageStatuses = $payload['triage_statuses'] ?? null;
        if (!is_array($triageStatuses)) {
            $errors[] = 'Expected triage_statuses to be an array.';
        } else {
            $deprecated = array_flip(array_map('strtolower', perm_deprecated_triage_status_keys()));
            foreach ($triageStatuses as $idx => $item) {
                $key = strtolower(trim((string)($item['key'] ?? '')));
                if ($key === '') {
                    $errors[] = sprintf('triage_statuses[%d] is missing key.', $idx);
                    continue;
                }
                if (isset($deprecated[$key])) {
                    $errors[] = sprintf('LOV exposes deprecated triage status %s.', $key);
                }
                foreach (['concept_label', 'backlog_effect', 'workflow_role'] as $field) {
                    if (!array_key_exists($field, $item) || trim((string)$item[$field]) === '') {
                        $errors[] = sprintf('triage_statuses[%d] is missing %s for key %s.', $idx, $field, $key);
                    }
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: permission LOV contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: permission LOV contract check passed.\n";
exit(0);
