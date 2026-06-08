<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app/database/services/stack_service.php',
        __DIR__ . '/app/database/services/family_service.php',
        __DIR__ . '/app/api/stack_audit.php',
        __DIR__ . '/app/api/family_taxonomy_check.php',
        __DIR__ . '/app/api/family_taxonomy_queue_export.php',
        __DIR__ . '/bin/erebus_console.php',
        __DIR__ . '/tests/phpunit',
    ]);
