<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/permissions.php';

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/fallback_permission_queue.php?limit=25&include_population_counts=1';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        $canonicalActions = array_flip(array_map('strtolower', perm_extract_keys(perm_queue_actions())));
        $canonicalStatuses = ['queued', 'claimed', 'applied', 'error', 'rejected', 'skipped'];

        $activeAliases = $decoded['legacy_queue_actions_active'] ?? null;
        if (!is_array($activeAliases)) {
            $errors[] = 'Expected legacy_queue_actions_active to be an array.';
        } elseif ($activeAliases !== []) {
            foreach ($activeAliases as $alias) {
                $errors[] = sprintf(
                    'Fallback queue still reports active legacy action %s -> %s (%s rows).',
                    (string)($alias['raw'] ?? ''),
                    (string)($alias['normalized'] ?? ''),
                    (string)($alias['count'] ?? '0')
                );
            }
        }

        $countsByActionActive = $decoded['counts_by_action_active'] ?? null;
        if (!is_array($countsByActionActive)) {
            $errors[] = 'Expected counts_by_action_active to be an object.';
        } else {
            foreach ($countsByActionActive as $key => $count) {
                $action = strtolower(trim((string)$key));
                if ($action === '') {
                    $errors[] = 'counts_by_action_active contains a blank action key.';
                    continue;
                }
                if (!isset($canonicalActions[$action])) {
                    $errors[] = sprintf('counts_by_action_active contains non-canonical action key %s.', $action);
                }
                if (!is_int($count) && !ctype_digit((string)$count)) {
                    $errors[] = sprintf('counts_by_action_active[%s] is not numeric.', $action);
                }
            }
        }

        $countsByStatusActive = $decoded['counts_by_status_active'] ?? null;
        if (!is_array($countsByStatusActive)) {
            $errors[] = 'Expected counts_by_status_active to be an object.';
        } else {
            foreach ($countsByStatusActive as $key => $count) {
                $status = strtolower(trim((string)$key));
                if (!in_array($status, $canonicalStatuses, true)) {
                    $errors[] = sprintf('counts_by_status_active contains unexpected status key %s.', $status);
                }
                if (!is_int($count) && !ctype_digit((string)$count)) {
                    $errors[] = sprintf('counts_by_status_active[%s] is not numeric.', $status);
                }
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: fallback permission queue contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: fallback permission queue contract check passed.\n";
exit(0);
