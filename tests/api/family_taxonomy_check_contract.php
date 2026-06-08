<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/family_taxonomy_check.php?limit=5';

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

        foreach (['summary', 'rows', 'mismatch_pairs', 'remediation_summary', 'issue_inventory', 'queue_presets', 'fix_action_inventory', 'decision_inventory', 'governance_inventory', 'apply_plan', 'repair_opportunities'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing data.%s.', $key);
            } elseif (!is_array($payload[$key])) {
                $errors[] = sprintf('Expected data.%s to be an array.', $key);
            }
        }

        if (isset($payload['remediation_summary']) && is_array($payload['remediation_summary'])) {
            foreach (['math', 'priority_lanes', 'mismatch_pair_classes', 'row_pattern_summary', 'top_mismatch_pairs'] as $key) {
                if (!array_key_exists($key, $payload['remediation_summary'])) {
                    $errors[] = sprintf('Missing data.remediation_summary.%s.', $key);
                }
            }
        }

        if (isset($payload['issue_inventory']) && is_array($payload['issue_inventory'])) {
            foreach (['total_rows', 'issue_kind_counts', 'top_catalog_labels', 'top_signal_labels'] as $key) {
                if (!array_key_exists($key, $payload['issue_inventory'])) {
                    $errors[] = sprintf('Missing data.issue_inventory.%s.', $key);
                }
            }
        }

        if (isset($payload['fix_action_inventory']) && is_array($payload['fix_action_inventory'])) {
            foreach (['total_rows', 'action_counts', 'top_target_families'] as $key) {
                if (!array_key_exists($key, $payload['fix_action_inventory'])) {
                    $errors[] = sprintf('Missing data.fix_action_inventory.%s.', $key);
                }
            }
        }

        if (isset($payload['decision_inventory']) && is_array($payload['decision_inventory'])) {
            foreach (['total_rows', 'decision_mode_counts', 'decision_priority_counts'] as $key) {
                if (!array_key_exists($key, $payload['decision_inventory'])) {
                    $errors[] = sprintf('Missing data.decision_inventory.%s.', $key);
                }
            }
        }

        if (isset($payload['governance_inventory']) && is_array($payload['governance_inventory'])) {
            foreach (['total_rows', 'targeted_rows', 'untargeted_rows', 'target_groups', 'untargeted_pair_groups', 'untargeted_top_signal_labels', 'untargeted_top_catalog_labels'] as $key) {
                if (!array_key_exists($key, $payload['governance_inventory'])) {
                    $errors[] = sprintf('Missing data.governance_inventory.%s.', $key);
                }
            }
        }

        if (isset($payload['apply_plan']) && is_array($payload['apply_plan'])) {
            foreach (['dry_run', 'supported_actions', 'plan_rows', 'summary'] as $key) {
                if (!array_key_exists($key, $payload['apply_plan'])) {
                    $errors[] = sprintf('Missing data.apply_plan.%s.', $key);
                }
            }
            if (isset($payload['apply_plan']['summary']) && is_array($payload['apply_plan']['summary'])) {
                foreach (['candidate_rows', 'plan_group_count', 'excluded_rows', 'excluded_reasons'] as $key) {
                    if (!array_key_exists($key, $payload['apply_plan']['summary'])) {
                        $errors[] = sprintf('Missing data.apply_plan.summary.%s.', $key);
                    }
                }
            }
        }

        if (isset($payload['queue_presets'][0]) && is_array($payload['queue_presets'][0])) {
            foreach (['title', 'count', 'description', 'button_label', 'decision_mode'] as $key) {
                if (!array_key_exists($key, $payload['queue_presets'][0])) {
                    $errors[] = sprintf('Missing data.queue_presets[0].%s.', $key);
                }
            }
        }

        foreach (['schema_available', 'primary_database', 'limit', 'pattern', 'fix_action', 'target_family', 'decision_mode'] as $key) {
            if (!array_key_exists($key, $meta)) {
                $errors[] = sprintf('Missing meta.%s.', $key);
            }
        }

        if (($meta['schema_available'] ?? false) === true && isset($payload['rows'][0])) {
            $row = $payload['rows'][0];
            foreach (['sample_id', 'sha256', 'alignment_status', 'review_lane', 'issue_kind', 'issue_reason', 'suggested_fix_action', 'suggested_target_family', 'suggested_fix_confidence', 'suggested_fix_reason', 'decision_mode', 'decision_priority', 'decision_why'] as $rowKey) {
                if (!array_key_exists($rowKey, $row)) {
                    $errors[] = sprintf('Missing rows[0].%s.', $rowKey);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: family taxonomy check contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: family taxonomy check contract passed.\n";
exit(0);
