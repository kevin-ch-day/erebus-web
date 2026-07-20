<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/health.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        $requiredKeys = ['ok', 'data', 'meta'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $decoded)) {
                $errors[] = sprintf('Missing required key: %s', $key);
            }
        }

        if (array_key_exists('ok', $decoded) && !is_bool($decoded['ok'])) {
            $errors[] = 'Expected ok to be a boolean.';
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];

        if ($data === []) {
            $errors[] = 'Expected data to be an object.';
        }

        if (!array_key_exists('pipeline', $data)) {
            $errors[] = 'Missing health.pipeline block.';
        }

        $pipelineBlock = is_array($data['pipeline'] ?? null) ? $data['pipeline'] : [];
        if ($pipelineBlock !== [] && !array_key_exists('queue_lanes', $pipelineBlock)) {
            $errors[] = 'Missing health.pipeline.queue_lanes block.';
        }
        $queueLanes = is_array($pipelineBlock['queue_lanes'] ?? null) ? $pipelineBlock['queue_lanes'] : [];
        foreach (['lamda_pending', 'reservoir_pending'] as $laneKey) {
            if ($pipelineBlock !== [] && !array_key_exists($laneKey, $queueLanes)) {
                $errors[] = sprintf('Missing health.pipeline.queue_lanes.%s', $laneKey);
            }
        }

        if ($meta === []) {
            $errors[] = 'Expected meta to be an object.';
        }

        foreach (['generated_at_utc', 'schema_surface', 'server_utc_now', 'request_id'] as $metaKey) {
            if (!array_key_exists($metaKey, $meta)) {
                $errors[] = sprintf('Missing meta.%s', $metaKey);
            }
        }

        if (array_key_exists('metrics', $data) && !is_array($data['metrics'])) {
            $errors[] = 'Expected metrics to be an object.';
        }

        if (array_key_exists('utc_now', $data) && !is_string($data['utc_now'])) {
            $errors[] = 'Expected data.utc_now to be a string.';
        }

        if (array_key_exists('system_control', $data) && !is_array($data['system_control'])) {
            $errors[] = 'Expected data.system_control to be an object.';
        }

        if (array_key_exists('catalogs', $data) && !is_array($data['catalogs'])) {
            $errors[] = 'Expected data.catalogs to be an object.';
        } elseif (array_key_exists('catalogs', $data)) {
            foreach (['primary', 'permission_intel', 'split_enabled'] as $catalogKey) {
                if (!array_key_exists($catalogKey, $data['catalogs'])) {
                    $errors[] = sprintf('Missing data.catalogs.%s', $catalogKey);
                }
            }
        }

        $dbConfig = is_array($data['db_config'] ?? null) ? $data['db_config'] : [];
        $configurationContract = is_array($dbConfig['configuration_contract'] ?? null) ? $dbConfig['configuration_contract'] : [];
        if (!array_key_exists('state', $configurationContract)) {
            $errors[] = 'Missing health.db_config.configuration_contract.state.';
        }
        foreach (['env_files_loaded', 'env_keys_present', 'mysql_client_defaults_present', 'primary_host', 'primary_port', 'primary_user'] as $privateKey) {
            if (array_key_exists($privateKey, $dbConfig)) {
                $errors[] = sprintf('Health db_config must not expose %s.', $privateKey);
            }
        }
        if (array_key_exists('vt_key_status', $data)) {
            $errors[] = 'Health payload must not expose per-key VT status.';
        }

        if (array_key_exists('schema_heads', $data) && !is_array($data['schema_heads'])) {
            $errors[] = 'Expected data.schema_heads to be an object.';
        } elseif (array_key_exists('schema_heads', $data)) {
            foreach (['primary_head', 'permission_intel_head', 'heads_match'] as $headKey) {
                if (!array_key_exists($headKey, $data['schema_heads'])) {
                    $errors[] = sprintf('Missing data.schema_heads.%s', $headKey);
                }
            }
        }

        if (array_key_exists('workflow_debt', $data) && !is_array($data['workflow_debt'])) {
            $errors[] = 'Expected data.workflow_debt to be an object.';
        } elseif (array_key_exists('workflow_debt', $data)) {
            foreach (['deprecated_live_triage_statuses', 'unexpected_live_triage_statuses', 'legacy_queue_actions_active'] as $debtKey) {
                if (!array_key_exists($debtKey, $data['workflow_debt'])) {
                    $errors[] = sprintf('Missing data.workflow_debt.%s', $debtKey);
                }
            }
        }

        if (array_key_exists('vt_surface_summary', $data) && !is_array($data['vt_surface_summary'])) {
            $errors[] = 'Expected data.vt_surface_summary to be an object.';
        } elseif (array_key_exists('vt_surface_summary', $data)) {
            foreach (['known_count', 'available_count', 'missing_count', 'missing_names'] as $surfaceKey) {
                if (!array_key_exists($surfaceKey, $data['vt_surface_summary'])) {
                    $errors[] = sprintf('Missing data.vt_surface_summary.%s', $surfaceKey);
                }
            }
        }

        if (array_key_exists('family_taxonomy_summary', $data) && !is_array($data['family_taxonomy_summary'])) {
            $errors[] = 'Expected data.family_taxonomy_summary to be an object.';
        } elseif (array_key_exists('family_taxonomy_summary', $data)) {
            foreach (['available', 'mismatch_rows', 'signal_only_rows', 'catalog_only_rows', 'high_conflict_rows', 'risk_class'] as $key) {
                if (!array_key_exists($key, $data['family_taxonomy_summary'])) {
                    $errors[] = sprintf('Missing data.family_taxonomy_summary.%s', $key);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: health contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: health contract check passed.\n";
exit(0);
