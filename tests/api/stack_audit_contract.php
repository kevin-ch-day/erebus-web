<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/stack_audit.php';

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

        foreach (['runtime', 'capabilities', 'architecture_profile', 'gap_inventory', 'upgrade_tracks', 'research_anchors', 'cli_entrypoints'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            }
        }

        if (isset($payload['runtime']) && is_array($payload['runtime'])) {
            foreach (['php_runtime', 'app_package', 'app_package_version', 'frontend_dependencies'] as $key) {
                if (!array_key_exists($key, $payload['runtime'])) {
                    $errors[] = sprintf('Missing data.runtime.%s.', $key);
                }
            }
        }

        if (isset($payload['capabilities']) && is_array($payload['capabilities'])) {
            foreach (['composer_present', 'playwright_config_present', 'ui_spec_count', 'api_contract_count', 'typed_source_page_count'] as $key) {
                if (!array_key_exists($key, $payload['capabilities'])) {
                    $errors[] = sprintf('Missing data.capabilities.%s.', $key);
                }
            }

            if (!array_key_exists('openapi_present', $payload['capabilities'])) {
                $errors[] = 'Missing data.capabilities.openapi_present.';
            } elseif ($payload['capabilities']['openapi_present'] !== true) {
                $errors[] = 'Expected data.capabilities.openapi_present to be true.';
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
    echo "FAIL: stack audit contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: stack audit contract passed.\n";
exit(0);
