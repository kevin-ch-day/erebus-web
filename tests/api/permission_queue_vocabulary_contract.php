<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/database/db_func.php';
require_once __DIR__ . '/../../app/lib/permissions.php';

$errors = [];

$dictQueue = db_catalog_table('android_permission_dict_queue');
$activeStatuses = ['queued', 'claimed', 'pending'];
$placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

$rows = db_all(
    "SELECT queue_action, status, COUNT(*) AS n
     FROM {$dictQueue}
     WHERE status IN ({$placeholders})
     GROUP BY queue_action, status
     ORDER BY n DESC",
    $activeStatuses
);

$canonical = array_flip(array_map('strtolower', perm_extract_keys(perm_queue_actions())));
$aliases = perm_queue_action_aliases();

foreach ($rows as $row) {
    $raw = strtolower(trim((string)($row['queue_action'] ?? '')));
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($raw === '') {
        $errors[] = sprintf('Active queue row has blank queue_action for status %s.', $status);
        continue;
    }
    if (isset($aliases[$raw])) {
        $errors[] = sprintf(
            'Active queue still uses legacy alias %s for status %s (%d rows); expected canonical %s.',
            $raw,
            $status,
            (int)($row['n'] ?? 0),
            $aliases[$raw]
        );
        continue;
    }
    if (!isset($canonical[$raw])) {
        $errors[] = sprintf(
            'Active queue uses unknown action %s for status %s (%d rows).',
            $raw,
            $status,
            (int)($row['n'] ?? 0)
        );
    }
}

$duplicateRows = db_all(
    "SELECT permission_string_norm, COUNT(*) AS n
     FROM {$dictQueue}
     WHERE status IN ({$placeholders})
       AND permission_string_norm IS NOT NULL
       AND permission_string_norm <> ''
     GROUP BY permission_string_norm
     HAVING COUNT(*) > 1
     ORDER BY n DESC
     LIMIT 20",
    $activeStatuses
);

foreach ($duplicateRows as $row) {
    $errors[] = sprintf(
        'Active queue has duplicate rows for normalized permission %s (%d rows).',
        (string)($row['permission_string_norm'] ?? ''),
        (int)($row['n'] ?? 0)
    );
}

$missingNormRow = db_one(
    "SELECT COUNT(*) AS n
     FROM {$dictQueue}
     WHERE status IN ({$placeholders})
       AND (permission_string_norm IS NULL OR permission_string_norm = '')",
    $activeStatuses
);
$missingNormCount = (int)($missingNormRow['n'] ?? 0);
if ($missingNormCount > 0) {
    $errors[] = sprintf('Active queue has %d row(s) missing permission_string_norm.', $missingNormCount);
}

if ($errors !== []) {
    echo "FAIL: permission queue vocabulary contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: permission queue vocabulary contract check passed.\n";
exit(0);
