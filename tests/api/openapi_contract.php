<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/openapi.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        foreach (['openapi', 'info', 'paths'] as $key) {
            if (!array_key_exists($key, $decoded)) {
                $errors[] = sprintf('Missing top-level key: %s', $key);
            }
        }

        if (($decoded['openapi'] ?? '') !== '3.1.0') {
            $errors[] = 'Expected openapi version 3.1.0.';
        }

        $paths = $decoded['paths'] ?? [];
        foreach ([
            '/api.php/openapi.php',
            '/api.php/stack_audit.php',
            '/api.php/landing_snapshot.php',
            '/api.php/family_taxonomy_check.php',
            '/api.php/health.php',
            '/api.php/samples_list.php',
        ] as $path) {
            if (!array_key_exists($path, $paths)) {
                $errors[] = sprintf('Missing path contract: %s', $path);
            }
        }

        $defs = $decoded['$defs'] ?? [];
        $familyCheck = $defs['FamilyTaxonomyCheckData'] ?? [];
        $familyRequired = $familyCheck['required'] ?? [];
        foreach (['governance_inventory', 'apply_plan'] as $key) {
            if (!in_array($key, $familyRequired, true)) {
                $errors[] = sprintf('Expected FamilyTaxonomyCheckData.required to include %s.', $key);
            }
        }

        foreach (['FamilyTaxonomyGovernanceInventory', 'FamilyTaxonomyApplyPlan', 'LandingSnapshotData', 'LandingSnapshotResponse', 'HealthData', 'SamplesListData'] as $defKey) {
            if (!array_key_exists($defKey, $defs)) {
                $errors[] = sprintf('Missing schema definition: %s', $defKey);
            }
        }

        $healthResponse = $defs['HealthResponse'] ?? [];
        $samplesResponse = $defs['SamplesListResponse'] ?? [];
        foreach ([['HealthResponse', $healthResponse], ['SamplesListResponse', $samplesResponse]] as [$label, $schema]) {
            $required = $schema['required'] ?? [];
            foreach (['ok', 'data', 'meta'] as $requiredKey) {
                if (!in_array($requiredKey, $required, true)) {
                    $errors[] = sprintf('Expected %s.required to include %s.', $label, $requiredKey);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: openapi contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: openapi contract passed.\n";
exit(0);
