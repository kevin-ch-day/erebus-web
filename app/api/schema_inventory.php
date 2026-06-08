<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $payload = db_schema_inventory();
    api_ok($payload, [
        'primary_database' => db_primary_catalog_name(),
        'permission_intel_database' => db_permission_intel_catalog_name(),
        'permission_intel_split' => db_permission_intel_split_enabled(),
    ]);
} catch (Throwable $e) {
    api_error('Failed to load schema inventory.', 500, 'ERR_SCHEMA_INVENTORY', [], $e);
}
