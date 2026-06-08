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
        'family' => get_str('family', 128, ''),
        'family_alignment' => get_str('family_alignment', 32, ''),
        'status' => get_str('status', 32, ''),
        'sort_by' => get_str('sort_by', 32, 'id'),
        'sort_dir' => get_str('sort_dir', 8, 'desc'),
        'page' => $page,
        'page_size' => $pageSize,
    ];

    api_ok(db_samples_list($filters), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'samples_list_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load samples list.', 500, 'ERR_SAMPLES_LIST', [], $e);
}
