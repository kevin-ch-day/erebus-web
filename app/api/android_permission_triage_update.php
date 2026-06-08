<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../database/db_func.php';

require_post();
require_write_access();

try {
    $permission = get_str('permission', 255, '', $_POST);
    $status = strtolower(get_str('triage_status', 64, '', $_POST));
    $notesProvided = array_key_exists('notes', $_POST);
    $notes = $notesProvided ? get_str('notes', 1000, '', $_POST) : null;

    if ($permission === '' || $status === '') {
        api_error('permission and triage_status required', 400, 'ERR_TRIAGE_INPUT');
        exit;
    }

    $allowed = array_map('strtolower', perm_extract_keys(perm_operator_triage_statuses()));

    if ($allowed && !in_array($status, $allowed, true)) {
        api_error('Invalid triage_status', 400, 'ERR_TRIAGE_STATUS');
        exit;
    }

    $operator = $_SERVER['REMOTE_ADDR'] ?? 'web';
    $payload = db_update_unknown_permission_status($permission, $status, $notes, $operator);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to update triage status.', 500, 'ERR_TRIAGE_UPDATE', [], $e);
}
