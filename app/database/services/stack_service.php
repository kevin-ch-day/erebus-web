<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';

function db_stack_project_root(): string
{
    return dirname(__DIR__, 3);
}

function db_stack_safe_json_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function db_stack_glob_count(string $pattern): int
{
    $matches = glob($pattern);
    return is_array($matches) ? count($matches) : 0;
}

function db_stack_dependency_snapshot(): array
{
    $root = db_stack_project_root();
    $packageJson = db_stack_safe_json_file($root . '/package.json');
    $packageLock = db_stack_safe_json_file($root . '/package-lock.json');
    $packages = is_array($packageLock['packages'] ?? null) ? $packageLock['packages'] : [];

    $manifestDeps = array_merge(
        is_array($packageJson['dependencies'] ?? null) ? $packageJson['dependencies'] : [],
        is_array($packageJson['devDependencies'] ?? null) ? $packageJson['devDependencies'] : []
    );

    $readInstalled = static function (string $name) use ($packages): array {
        $pkg = $packages['node_modules/' . $name] ?? [];
        return [
            'installed' => (string)($pkg['version'] ?? ''),
            'engines' => is_array($pkg['engines'] ?? null) ? $pkg['engines'] : [],
        ];
    };

    $deps = [];
    foreach (['vite', 'typescript', 'alpinejs', '@playwright/test'] as $name) {
        $installed = $readInstalled($name);
        $deps[] = [
            'name' => $name,
            'wanted' => (string)($manifestDeps[$name] ?? ''),
            'installed' => $installed['installed'],
            'engines' => $installed['engines'],
        ];
    }

    return [
        'package_name' => (string)($packageJson['name'] ?? ''),
        'package_version' => (string)($packageJson['version'] ?? ''),
        'dependencies' => $deps,
    ];
}

function db_stack_capability_summary(): array
{
    $root = db_stack_project_root();

    $playwrightConfig = is_file($root . '/playwright.config.ts');
    $composerPresent = is_file($root . '/composer.json');
    $phpunitPresent = is_file($root . '/phpunit.xml') || is_file($root . '/phpunit.xml.dist');
    $phpstanPresent = is_file($root . '/phpstan.neon') || is_file($root . '/phpstan.neon.dist');
    $psalmPresent = is_file($root . '/psalm.xml') || is_file($root . '/psalm.xml.dist');
    $rectorPresent = is_file($root . '/rector.php');
    $openapiPresent = db_stack_glob_count($root . '/docs/*openapi*') > 0 || db_stack_glob_count($root . '/openapi*') > 0;

    $uiSpecCount = db_stack_glob_count($root . '/tests/ui/*.spec.ts');
    $apiContractCount = db_stack_glob_count($root . '/tests/api/*_contract.php');
    $viewCount = db_stack_glob_count($root . '/app/views/*.php');
    $apiEndpointCount = db_stack_glob_count($root . '/app/api/*.php');
    $tsPageCount = db_stack_glob_count($root . '/public/assets/js/pages/*.js');
    $typedSourcePageCount = db_stack_glob_count($root . '/frontend/pages/*.ts');

    return [
        'server_rendered_php_pages' => true,
        'json_api_endpoints' => true,
        'typed_frontend_shell' => is_file($root . '/tsconfig.json') && is_file($root . '/vite.config.js'),
        'vite_build_present' => is_file($root . '/vite.config.js'),
        'playwright_config_present' => $playwrightConfig,
        'composer_present' => $composerPresent,
        'phpunit_present' => $phpunitPresent,
        'phpstan_present' => $phpstanPresent,
        'psalm_present' => $psalmPresent,
        'rector_present' => $rectorPresent,
        'openapi_present' => $openapiPresent,
        'view_count' => $viewCount,
        'api_endpoint_count' => $apiEndpointCount,
        'ts_page_count' => $tsPageCount,
        'typed_source_page_count' => $typedSourcePageCount,
        'ui_spec_count' => $uiSpecCount,
        'api_contract_count' => $apiContractCount,
    ];
}

function db_stack_gap_inventory(array $capabilities): array
{
    $gaps = [];

    if (!$capabilities['composer_present']) {
        $gaps[] = [
            'severity' => 'critical',
            'key' => 'composer_missing',
            'title' => 'Composer package boundary is missing',
            'why' => 'The app has no PHP dependency manager or framework package boundary, which blocks structured adoption of routing, validation, DI, and modern PHP tooling.',
        ];
    }

    if (!$capabilities['phpunit_present']) {
        $gaps[] = [
            'severity' => 'critical',
            'key' => 'php_test_harness_missing',
            'title' => 'No standard PHP test harness is configured',
            'why' => 'The app relies on ad hoc API contract scripts, but there is no PHPUnit/Pest layer for service, routing, or controller-level regression coverage.',
        ];
    }

    if (!$capabilities['phpstan_present'] && !$capabilities['psalm_present']) {
        $gaps[] = [
            'severity' => 'warn',
            'key' => 'php_static_analysis_missing',
            'title' => 'PHP static analysis is missing',
            'why' => 'The backend is growing in complexity, but there is no PHPStan or Psalm layer to catch type and nullability drift before runtime.',
        ];
    }

    if (!$capabilities['rector_present']) {
        $gaps[] = [
            'severity' => 'info',
            'key' => 'php_refactor_tooling_missing',
            'title' => 'Automated PHP refactor tooling is missing',
            'why' => 'A large-stack upgrade will be slower without a codemod layer for syntax modernization and repetitive framework migration edits.',
        ];
    }

    if (($capabilities['ui_spec_count'] ?? 0) <= 1) {
        $gaps[] = [
            'severity' => 'warn',
            'key' => 'ui_coverage_thin',
            'title' => 'Playwright exists, but UI coverage is still thin',
            'why' => 'The app already carries Playwright, but only a minimal smoke suite is present. The main operator workflows still lack end-to-end regression protection.',
        ];
    }

    if (!$capabilities['openapi_present']) {
        $gaps[] = [
            'severity' => 'warn',
            'key' => 'openapi_contract_missing',
            'title' => 'No machine-readable API contract layer',
            'why' => 'JSON endpoints are growing, but there is no OpenAPI or generated client boundary to keep the frontend and backend aligned during a larger migration.',
        ];
    }

    return $gaps;
}

function db_stack_upgrade_tracks(): array
{
    return [
        [
            'track_id' => 'harden_current',
            'title' => 'Harden the current PHP + TypeScript islands stack',
            'effort' => 'medium',
            'candidate_tech' => 'Composer, PHPUnit/Pest, PHPStan or Psalm, Rector, broader Playwright coverage',
            'why' => 'This gives the current app a stable floor before any framework or API rewrite.',
            'best_for' => 'Fastest risk reduction without changing routing or DB ownership.',
        ],
        [
            'track_id' => 'structured_php',
            'title' => 'Adopt a structured PHP platform incrementally',
            'effort' => 'large',
            'candidate_tech' => 'Symfony components first, or a Laravel migration if a full framework reset is approved',
            'why' => 'The biggest backend debt is hand-rolled routing, service wiring, validation, and error conventions.',
            'best_for' => 'Keeping PHP while gaining framework-grade routing, DI, middleware, validation, and auth.',
        ],
        [
            'track_id' => 'api_first_split',
            'title' => 'Move analysis APIs to an API-first backend',
            'effort' => 'very_large',
            'candidate_tech' => 'FastAPI for typed analysis APIs plus a stronger TypeScript operator console',
            'why' => 'The highest-growth surfaces are read-heavy analytics and queue review APIs that benefit from typed schemas and generated docs.',
            'best_for' => 'Long-term separation of UI, API, and DB concerns when the project is ready for a deliberate platform split.',
        ],
    ];
}

function db_stack_research_anchors(): array
{
    return [
        [
            'label' => 'PHP supported versions',
            'url' => 'https://www.php.net/supported-versions',
            'why' => 'Support-window baseline for backend runtime planning.',
        ],
        [
            'label' => 'Vite 7 announcement',
            'url' => 'https://vite.dev/blog/announcing-vite7',
            'why' => 'Current Node requirements and build-platform direction.',
        ],
        [
            'label' => 'TypeScript 5.9 release notes',
            'url' => 'https://www.typescriptlang.org/docs/handbook/release-notes/typescript-5-9.html',
            'why' => 'Current language features and stable Node 20 module mode.',
        ],
        [
            'label' => 'Alpine.js start here',
            'url' => 'https://alpinejs.dev/start-here',
            'why' => 'Current progressive-enhancement baseline for lightweight UI behavior.',
        ],
        [
            'label' => 'Playwright testing docs',
            'url' => 'https://playwright.dev/docs/writing-tests',
            'why' => 'Current E2E testing model with isolation and web-first assertions.',
        ],
        [
            'label' => 'Symfony components',
            'url' => 'https://symfony.com/doc/current/components/index.html',
            'why' => 'Incremental PHP modernization path without an immediate full rewrite.',
        ],
        [
            'label' => 'FastAPI overview',
            'url' => 'https://fastapi.tiangolo.com/',
            'why' => 'Typed API-first option with automatic interactive docs for a larger platform split.',
        ],
    ];
}

function db_stack_cli_entrypoints(): array
{
    return [
        [
            'label' => 'Family summary',
            'command' => 'php bin/erebus_console.php family:summary --format=table',
            'why' => 'Fast terminal scorecard for family alignment, issue inventory, and decision lanes.',
        ],
        [
            'label' => 'Family queue export',
            'command' => 'php bin/erebus_console.php family:export --decision-mode=repair_after_alias_review --format=csv',
            'why' => 'Exports a filtered repair slice directly to CSV without remembering the row command syntax.',
        ],
        [
            'label' => 'Family dry-run apply plan',
            'command' => 'php bin/erebus_console.php family:apply-plan --decision-mode=repair_after_alias_review --format=sql',
            'why' => 'Builds a dry-run grouped repair plan and SQL preview without writing any catalog data.',
        ],
        [
            'label' => 'Family repair opportunities',
            'command' => 'php bin/erebus_console.php family:opportunities --limit=25 --format=table',
            'why' => 'Shows the strongest batch-repair candidates without opening the queue page.',
        ],
        [
            'label' => 'Family mismatch pairs',
            'command' => 'php bin/erebus_console.php family:pairs --format=table',
            'why' => 'Shows the dominant catalog-vs-VT mismatch pairs directly from the shell.',
        ],
        [
            'label' => 'Family driver inventory',
            'command' => 'php bin/erebus_console.php family:drivers --format=table',
            'why' => 'Summarizes issue kinds, top catalog labels, top VT signal labels, and decision lanes without browser navigation.',
        ],
        [
            'label' => 'Family governance targets',
            'command' => 'php bin/erebus_console.php family:governance --format=table',
            'why' => 'Groups the unresolved governance backlog by target hint, so operators can work from candidate family buckets instead of raw row hunting.',
        ],
        [
            'label' => 'Focused repair rows',
            'command' => 'php bin/erebus_console.php family:rows --decision-mode=ask_why_first --limit=50 --format=table',
            'why' => 'Lets operators inspect the exact high-friction repair queue from the shell.',
        ],
        [
            'label' => 'Stack audit JSON',
            'command' => 'php bin/erebus_console.php stack:audit --format=json',
            'why' => 'Machine-readable platform audit for upgrade planning and offline review.',
        ],
    ];
}

function db_stack_architecture_profile(array $capabilities): array
{
    return [
        [
            'layer' => 'Backend delivery',
            'current_shape' => 'Allowlisted PHP page router plus PHP JSON endpoints',
            'evidence' => sprintf('%d views, %d API endpoints', (int)$capabilities['view_count'], (int)$capabilities['api_endpoint_count']),
        ],
        [
            'layer' => 'Frontend shell',
            'current_shape' => 'Vite-built TypeScript islands with Alpine for light interaction',
            'evidence' => sprintf('%d page modules, TS config present', (int)$capabilities['ts_page_count']),
        ],
        [
            'layer' => 'Testing',
            'current_shape' => 'API contract scripts plus Playwright UI coverage',
            'evidence' => sprintf('%d API contracts, %d UI specs', (int)$capabilities['api_contract_count'], (int)$capabilities['ui_spec_count']),
        ],
        [
            'layer' => 'Platform structure',
            'current_shape' => ($capabilities['composer_present'] ? 'Composer-managed PHP project' : 'No Composer/framework package boundary yet'),
            'evidence' => $capabilities['composer_present'] ? 'Composer present' : 'composer.json absent',
        ],
    ];
}

function db_stack_audit(): array
{
    $root = db_stack_project_root();
    $deps = db_stack_dependency_snapshot();
    $capabilities = db_stack_capability_summary();

    return [
        'runtime' => [
            'php_runtime' => PHP_VERSION,
            'app_package' => $deps['package_name'],
            'app_package_version' => $deps['package_version'],
            'frontend_dependencies' => $deps['dependencies'],
        ],
        'capabilities' => $capabilities,
        'architecture_profile' => db_stack_architecture_profile($capabilities),
        'gap_inventory' => db_stack_gap_inventory($capabilities),
        'upgrade_tracks' => db_stack_upgrade_tracks(),
        'research_anchors' => db_stack_research_anchors(),
        'cli_entrypoints' => db_stack_cli_entrypoints(),
        'project_root' => $root,
    ];
}
