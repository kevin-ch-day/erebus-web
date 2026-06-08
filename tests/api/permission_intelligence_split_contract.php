<?php

declare(strict_types=1);

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
        $requiredArrayKeys = [
            'current_evidence_review_rows',
            'governed_current_unknown_rows',
            'ledger_diagnostic_rows',
            'current_evidence_review_page',
            'governed_current_unknown_page',
            'ledger_diagnostic_page',
            'unknown_permissions',
        ];
        $requiredObjectKeys = [
            'health',
            'metrics',
            'operator_summary',
            'queue',
            'session',
            'status_model',
        ];

        foreach ($requiredArrayKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing required key: %s', $key);
            } elseif (!is_array($payload[$key])) {
                $errors[] = sprintf('Expected %s to be an array.', $key);
            }
        }

        foreach ($requiredObjectKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = sprintf('Missing required key: %s', $key);
            } elseif (!is_array($payload[$key])) {
                $errors[] = sprintf('Expected %s to be an object.', $key);
            }
        }

        if (isset($payload['health']) && is_array($payload['health'])) {
            foreach ([
                'current_unknown_obs_rows',
                'unknown_count',
                'unknown_dict_count',
                'current_evidence_review_backlog',
                'current_evidence_backlog',
                'governed_current_unknown_backlog',
                'actionable_review_backlog',
                'workflow_unknown_backlog',
                'ledger_inventory_rows',
                'ledger_actionable_status_rows',
                'ledger_unresolved_compat_rows',
                'effective_unknown',
            ] as $metricKey) {
                if (!array_key_exists($metricKey, $payload['health'])) {
                    $errors[] = sprintf('Missing health.%s', $metricKey);
                }
            }

            $health = $payload['health'];
            $aliasPairs = [
                ['unknown_count', 'current_unknown_obs_rows'],
                ['unknown_dict_count', 'ledger_inventory_rows'],
                ['actionable_review_backlog', 'current_evidence_review_backlog'],
                ['workflow_unknown_backlog', 'current_evidence_backlog'],
                ['effective_unknown', 'ledger_unresolved_compat_rows'],
            ];
            foreach ($aliasPairs as [$aliasKey, $canonicalKey]) {
                if (array_key_exists($aliasKey, $health) && array_key_exists($canonicalKey, $health) && (string)$health[$aliasKey] !== (string)$health[$canonicalKey]) {
                    $errors[] = sprintf('Expected health.%s to match health.%s.', $aliasKey, $canonicalKey);
                }
            }
        }

        if (isset($payload['operator_summary']) && is_array($payload['operator_summary'])) {
            foreach ([
                'current_evidence_review_backlog',
                'current_evidence_backlog',
                'governed_current_unknown_backlog',
                'ledger_diagnostic_backlog',
                'workflow_unknown_backlog',
                'unknown_ledger_entries',
                'effective_unknown_compat_legacy',
                'queued_dict_decisions',
                'queued_dict_decisions_raw',
                'queued_static_no_anchor',
            ] as $summaryKey) {
                if (!array_key_exists($summaryKey, $payload['operator_summary'])) {
                    $errors[] = sprintf('Missing operator_summary.%s', $summaryKey);
                }
            }
        }

        if (isset($payload['queue']) && is_array($payload['queue'])) {
            foreach ([
                'queued_count',
                'queued_current_unknown_count',
                'queued_evidence_backed_count',
                'queued_static_no_anchor_count',
                'queued_raw_count',
            ] as $queueKey) {
                if (!array_key_exists($queueKey, $payload['queue'])) {
                    $errors[] = sprintf('Missing queue.%s', $queueKey);
                }
            }

            $queue = $payload['queue'];
            if (
                array_key_exists('queued_count', $queue)
                && array_key_exists('queued_current_unknown_count', $queue)
                && (string)$queue['queued_count'] !== (string)$queue['queued_current_unknown_count']
            ) {
                $errors[] = 'Expected queue.queued_count to match queue.queued_current_unknown_count.';
            }
        }

        if (isset($payload['session']) && is_array($payload['session'])) {
            foreach ([
                'unknown_total',
                'current_evidence_backlog',
                'current_evidence_review_backlog',
                'governed_current_unknown_backlog',
                'ledger_unknown_total_effective',
                'workflow_unknown_backlog',
            ] as $sessionKey) {
                if (!array_key_exists($sessionKey, $payload['session'])) {
                    $errors[] = sprintf('Missing session.%s', $sessionKey);
                }
            }

            $session = $payload['session'];
            foreach ([['unknown_total', 'current_evidence_backlog'], ['workflow_unknown_backlog', 'current_evidence_backlog']] as [$aliasKey, $canonicalKey]) {
                if (array_key_exists($aliasKey, $session) && array_key_exists($canonicalKey, $session) && (string)$session[$aliasKey] !== (string)$session[$canonicalKey]) {
                    $errors[] = sprintf('Expected session.%s to match session.%s.', $aliasKey, $canonicalKey);
                }
            }
        }

        if (isset($payload['status_model']) && is_array($payload['status_model'])) {
            foreach ([
                'configured_triage_statuses',
                'operator_triage_statuses',
                'deprecated_configured_triage_statuses',
                'live_triage_statuses',
                'deprecated_live_triage_statuses',
                'unexpected_live_triage_statuses',
            ] as $statusModelKey) {
                if (!array_key_exists($statusModelKey, $payload['status_model'])) {
                    $errors[] = sprintf('Missing status_model.%s', $statusModelKey);
                } elseif (!is_array($payload['status_model'][$statusModelKey])) {
                    $errors[] = sprintf('Expected status_model.%s to be an array.', $statusModelKey);
                }
            }
        }

        if (isset($payload['current_evidence_review_rows'][0])) {
            $row = $payload['current_evidence_review_rows'][0];
            foreach ([
                'permission_string',
                'current_unknown_obs_rows',
                'current_unknown_samples',
                'current_total_samples',
                'vt_event_count',
                'dict_unknown_triage_status',
                'review_lane_label',
                'risk_hint',
                'risk_reason',
                'first_observed_at_utc',
                'last_observed_at_utc',
                'historical_ledger_seen_count',
            ] as $rowKey) {
                if (!array_key_exists($rowKey, $row)) {
                    $errors[] = sprintf('Missing current_evidence_review_rows[0].%s', $rowKey);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: permission intelligence split contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: permission intelligence split contract check passed.\n";
exit(0);
