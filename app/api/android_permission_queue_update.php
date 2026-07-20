<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../database/db_func.php';

require_post();
require_write_access();
require_operations_enabled();

try {
    $permission = get_str('permission', 255, '', $_POST);
    $queueAction = perm_normalize_queue_action(get_str('queue_action', 32, '', $_POST));
    $triageStatus = strtolower(get_str('triage_status', 64, '', $_POST));
    $notes = get_str('notes', 2000, '', $_POST);
    $proposedBucket = strtoupper(get_str('proposed_bucket', 64, '', $_POST));
    $proposedClassification = strtoupper(get_str('proposed_classification', 64, '', $_POST));

    if ($permission === '' || $queueAction === '') {
        api_error('permission and queue_action required', 400, 'ERR_QUEUE_INPUT');
        exit;
    }

    $allowedActions = perm_valid_queue_action_keys_with_aliases();
    if ($allowedActions && !in_array($queueAction, $allowedActions, true)) {
        api_error('Invalid queue_action', 400, 'ERR_QUEUE_ACTION');
        exit;
    }

    $needsNotes = in_array($queueAction, ['oem', 'reject'], true);
    if ($needsNotes && mb_strlen($notes) < 10) {
        api_error('Notes required for this queue action.', 400, 'ERR_QUEUE_NOTES_REQUIRED');
        exit;
    }

    $allowedBuckets = array_map('strtoupper', perm_bucket_keys());
    if ($proposedBucket !== '' && $allowedBuckets && !in_array($proposedBucket, $allowedBuckets, true)) {
        api_error('Invalid proposed_bucket', 400, 'ERR_QUEUE_BUCKET');
        exit;
    }

    $allowedClassifications = array_map('strtoupper', perm_classification_keys());
    if ($proposedClassification !== '' && $allowedClassifications && !in_array($proposedClassification, $allowedClassifications, true)) {
        api_error('Invalid proposed_classification', 400, 'ERR_QUEUE_CLASSIFICATION');
        exit;
    }

    if ($triageStatus !== '') {
        $allowedStatuses = array_map('strtolower', perm_extract_keys(perm_operator_triage_statuses()));
        if ($allowedStatuses && !in_array($triageStatus, $allowedStatuses, true)) {
            api_error('Invalid triage_status', 400, 'ERR_QUEUE_TRIAGE_STATUS');
            exit;
        }
    }

    $operator = $_SERVER['REMOTE_ADDR'] ?? 'web';
    $payload = db_queue_permission_update(
        $permission,
        $queueAction,
        $proposedBucket !== '' ? $proposedBucket : null,
        $proposedClassification !== '' ? $proposedClassification : null,
        $triageStatus !== '' ? $triageStatus : null,
        $notes !== '' ? $notes : null,
        $operator
    );

    $data = $payload['data'] ?? [];
    if (!empty($data['warnings']) && in_array('not_found', $data['warnings'], true)) {
        api_error('Permission not found in triage queue.', 404, 'ERR_QUEUE_NOT_FOUND');
        exit;
    }

    api_ok($data, $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to queue dictionary update.', 500, 'ERR_QUEUE_UPDATE', [], $e);
}
