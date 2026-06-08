<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/permissions.php';

require_get();

try {
    $data = [
        'triage_statuses' => perm_operator_triage_statuses_with_metadata(),
        'buckets' => perm_bucket_definitions(),
        'classifications' => perm_classifications(),
        'namespace_classes' => perm_namespace_classes(),
        'oem_review_outcomes' => perm_oem_review_outcomes(),
        'oem_prefixes' => perm_oem_namespace_prefixes(),
        'queue_actions' => perm_queue_actions(),
        'queue_statuses' => perm_queue_statuses(),
    ];

    api_ok($data, ['generated_at_utc' => gmdate('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    api_error('Failed to load permission LOVs.', 500, 'ERR_PERMISSION_LOV', [], $e);
}
