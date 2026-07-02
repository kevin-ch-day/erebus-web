<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $page = get_int('page', 1, 1, 100000);
    $pageSize = get_int('page_size', DEFAULT_PAGE_SIZE, 1, MAX_PAGE_SIZE);

    $filters = [
        'q' => get_str('q', 128, ''),
        'stopped_reason' => get_str('stopped_reason', 128, ''),
        'page' => $page,
        'page_size' => $pageSize,
    ];

    $payload = db_run_ledger_list($filters);
    api_ok($payload);
} catch (Throwable $e) {
    api_error('Failed to load run ledger list.', 500, 'ERR_RUNS_LIST', [], $e);
}
