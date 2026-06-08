<?php
// app/database/services/family_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../../lib/transient_cache.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/schema_service.php';
require_once __DIR__ . '/family/taxonomy_core.php';
require_once __DIR__ . '/family/taxonomy_resolution.php';
require_once __DIR__ . '/family_service_reporting.php';
