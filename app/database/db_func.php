<?php
// app/database/db_func.php
// Legacy include: load domain service files.

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/db_engine.php';
require_once __DIR__ . '/db_queries.php';
require_once __DIR__ . '/services/schema_service.php';
require_once __DIR__ . '/services/health_service.php';
require_once __DIR__ . '/services/samples_service.php';
require_once __DIR__ . '/services/runs_service.php';
require_once __DIR__ . '/services/android_service.php';
require_once __DIR__ . '/services/vt_service.php';
require_once __DIR__ . '/services/analysis_service.php';
require_once __DIR__ . '/services/family_service.php';
require_once __DIR__ . '/services/stack_service.php';
require_once __DIR__ . '/services/landing_service.php';
require_once __DIR__ . '/services/intake_service.php';
require_once __DIR__ . '/services/dataset_readiness_service.php';
