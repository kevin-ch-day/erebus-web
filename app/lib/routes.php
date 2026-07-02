<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

function app_route_manifest(): array
{
    static $manifest = null;
    if (is_array($manifest)) {
        return $manifest;
    }

    $phase2 = defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY;
    $phase3 = defined('FEATURE_PHASE3_OPS') && FEATURE_PHASE3_OPS;

    $manifest = [
        'landing' => [
            'label' => 'Home',
            'section' => 'Threat Workspace',
            'nav_section' => 'console',
            'view' => __DIR__ . '/../views/landing.php',
            'scripts' => ['assets/js/pages/landing_page.js'],
        ],
        'malware_samples' => [
            'label' => 'Malware Samples',
            'section' => 'Threat Workspace',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/samples.php',
            'aliases' => ['samples'],
        ],
        'sample' => [
            'label' => 'Sample Detail',
            'section' => 'Threat Workspace',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/sample_detail.php',
            'aliases' => ['sample_detail'],
        ],
        'family_taxonomy_check' => [
            'label' => 'Taxonomy Workspace',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_check.php',
            'scripts' => ['assets/js/pages/family_taxonomy_check_page.js'],
        ],
        'family_taxonomy_gaps' => [
            'label' => 'Coverage & Gaps',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_gaps.php',
        ],
        'family_taxonomy_queue' => [
            'label' => 'Repair Queue',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_queue.php',
            'scripts' => ['assets/js/pages/family_taxonomy_queue_page.js'],
            'aliases' => ['taxonomy_repairs', 'taxonomy_repair_queue'],
        ],
        'family_taxonomy_repair_planning' => [
            'label' => 'Repair Planning',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_repair_planning.php',
            'scripts' => ['assets/js/pages/family_taxonomy_repair_planning_page.js'],
            'nav_hidden' => true,
        ],
        'family_taxonomy_conflicts' => [
            'label' => 'Conflicts & Governance',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_conflicts.php',
        ],
        'family_taxonomy_signal_hygiene' => [
            'label' => 'Signal Hygiene',
            'section' => 'Taxonomy & Repair',
            'nav_section' => 'malware',
            'view' => __DIR__ . '/../views/family_taxonomy_signal_hygiene.php',
        ],
        'dataset_readiness' => [
            'label' => 'Dataset Readiness',
            'section' => 'Dataset Curation',
            'nav_section' => 'dataset',
            'view' => __DIR__ . '/../views/dataset_readiness.php',
        ],
        'label_surfaces' => [
            'label' => 'Label Surfaces',
            'section' => 'Dataset Curation',
            'nav_section' => 'dataset',
            'view' => __DIR__ . '/../views/label_surfaces.php',
        ],
        'type_benchmark' => [
            'label' => 'Type Benchmark',
            'section' => 'Dataset Curation',
            'nav_section' => 'dataset',
            'view' => __DIR__ . '/../views/type_benchmark.php',
        ],
        'authority_consistency_debt' => [
            'label' => 'Authority Consistency Debt',
            'section' => 'Dataset Curation',
            'nav_section' => 'dataset',
            'view' => __DIR__ . '/../views/authority_consistency_debt.php',
        ],
        'dataset_exports' => [
            'label' => 'Export Readiness',
            'section' => 'Dataset Curation',
            'nav_section' => 'dataset',
            'view' => __DIR__ . '/../views/dataset_exports.php',
            'nav_hidden' => true,
        ],
        'check_hash' => [
            'label' => 'Check Hash',
            'section' => 'Threat Workspace',
            'nav_section' => 'intake',
            'view' => __DIR__ . '/../views/check_hash.php',
            'scripts' => ['assets/js/pages/check_hash_page.js'],
        ],
        'ingest_backlog' => [
            'label' => 'Ingest Backlog',
            'section' => 'Threat Workspace',
            'nav_section' => 'intake',
            'view' => __DIR__ . '/../views/ingest_backlog.php',
            'scripts' => ['assets/js/pages/ingest_backlog_page.js'],
        ],
        'pending_source_mix' => [
            'label' => 'Pending Source Mix',
            'section' => 'Threat Workspace',
            'nav_section' => 'console',
            'view' => __DIR__ . '/../views/pending_source_mix.php',
            'scripts' => ['assets/js/pages/pending_source_mix_page.js'],
        ],
        'submit_artifact' => [
            'label' => 'Submit Artifact',
            'section' => 'Threat Workspace',
            'nav_section' => 'intake',
            'view' => __DIR__ . '/../views/submit_artifact.php',
            'scripts' => ['assets/js/pages/submit_artifact_page.js'],
        ],
        'permissions_overview' => [
            'label' => 'Permission Overview',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_overview.php',
            'scripts' => ['assets/js/pages/permissions_overview_page.js'],
        ],
        'analysis_fusion' => [
            'label' => 'Analysis Fusion',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/analysis_fusion.php',
            'enabled' => $phase2,
        ],
        'permissions_drift' => [
            'label' => 'Permission Drift',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_drift.php',
            'scripts' => ['assets/js/pages/permissions_drift_page.js'],
        ],
        'permissions_triage' => [
            'label' => 'Permission Triage',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_triage.php',
            'scripts' => ['assets/js/pages/permissions_triage_page.js'],
        ],
        'permissions_queue' => [
            'label' => 'Permission Queue',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_queue.php',
            'scripts' => ['assets/js/pages/permissions_queue_page.js'],
            'enabled' => $phase2,
        ],
        'permissions_evidence' => [
            'label' => 'Permission Evidence',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_evidence.php',
            'scripts' => ['assets/js/pages/permissions_evidence_page.js'],
        ],
        'permissions_aosp' => [
            'label' => 'AOSP Permissions',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_aosp.php',
            'scripts' => ['assets/js/pages/permissions_aosp_page.js'],
            'nav_hidden' => true,
        ],
        'permissions_google' => [
            'label' => 'Google Permissions',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_google.php',
            'scripts' => ['assets/js/pages/permissions_google_page.js'],
            'nav_hidden' => true,
        ],
        'permissions_oem_registry' => [
            'label' => 'OEM Registry',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_oem_registry.php',
            'scripts' => ['assets/js/pages/permissions_oem_registry_page.js'],
            'nav_hidden' => true,
        ],
        'permissions_oem_permissions' => [
            'label' => 'OEM Permissions',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_oem_permissions.php',
            'scripts' => ['assets/js/pages/permissions_oem_permissions_page.js'],
            'nav_hidden' => true,
        ],
        'permissions_review' => [
            'label' => 'Permission Review',
            'section' => 'Permission Intel',
            'nav_section' => 'permissions',
            'view' => __DIR__ . '/../views/permissions_review.php',
        ],
        'health' => [
            'label' => 'Pipeline Health',
            'section' => 'VirusTotal API',
            'nav_section' => 'pipeline',
            'view' => __DIR__ . '/../views/health.php',
            'scripts' => ['assets/js/pages/health_page.js'],
        ],
        'pipeline_ops' => [
            'label' => 'Pipeline Ops',
            'section' => 'VirusTotal API',
            'nav_section' => 'pipeline',
            'view' => __DIR__ . '/../views/pipeline_ops.php',
            'scripts' => ['assets/js/pages/pipeline_ops_page.js'],
        ],
        'runs' => [
            'label' => 'Run Ledger',
            'section' => 'VirusTotal API',
            'nav_section' => 'pipeline',
            'view' => __DIR__ . '/../views/runs.php',
            'scripts' => ['assets/js/pages/runs_page.js'],
        ],
        'vt_snapshot_inventory' => [
            'label' => 'Snapshot Inventory',
            'section' => 'VirusTotal API',
            'nav_section' => 'pipeline',
            'view' => __DIR__ . '/../views/vt_snapshot_inventory.php',
            'scripts' => ['assets/js/pages/vt_snapshot_inventory_page.js'],
            'nav_hidden' => true,
        ],
        'vt_confidence' => [
            'label' => 'VT Confidence',
            'section' => 'VirusTotal API',
            'nav_section' => 'pipeline',
            'view' => __DIR__ . '/../views/vt_confidence.php',
            'enabled' => $phase2,
        ],
        'settings' => [
            'label' => 'Settings',
            'section' => 'Platform',
            'nav_section' => 'admin',
            'view' => __DIR__ . '/../views/settings.php',
        ],
        'time_reference' => [
            'label' => 'Time',
            'section' => 'Platform',
            'nav_section' => 'admin',
            'view' => __DIR__ . '/../views/time_reference.php',
        ],
        'schema_inventory' => [
            'label' => 'Schema Inventory',
            'section' => 'Platform',
            'nav_section' => 'admin',
            'view' => __DIR__ . '/../views/schema_inventory.php',
            'scripts' => ['assets/js/pages/schema_inventory_page.js'],
            'nav_hidden' => true,
        ],
        'stack_audit' => [
            'label' => 'Tech Stack Audit',
            'section' => 'Platform',
            'nav_section' => 'admin',
            'view' => __DIR__ . '/../views/stack_audit.php',
            'scripts' => ['assets/js/pages/stack_audit_page.js'],
            'nav_hidden' => true,
        ],
        'admin_diagnostics' => [
            'label' => 'Admin Diagnostics',
            'section' => 'Platform',
            'nav_section' => 'admin',
            'view' => __DIR__ . '/../views/admin_diagnostics.php',
            'enabled' => $phase3,
        ],
    ];

    return $manifest;
}

function app_route_is_enabled(array $meta): bool
{
    return !array_key_exists('enabled', $meta) || $meta['enabled'] === true;
}

function app_route_catalog(): array
{
    return array_filter(
        app_route_manifest(),
        static fn(array $meta): bool => app_route_is_enabled($meta)
    );
}

function app_route_alias_map(): array
{
    static $aliases = null;
    if (is_array($aliases)) {
        return $aliases;
    }

    $aliases = [];
    foreach (app_route_catalog() as $routeKey => $meta) {
        foreach (($meta['aliases'] ?? []) as $alias) {
            $aliases[(string)$alias] = $routeKey;
        }
    }
    return $aliases;
}

function app_resolve_route_key(string $requested, string $default = 'landing'): string
{
    $key = strtolower(trim($requested));
    if ($key === '') {
        $key = $default;
    }
    $aliases = app_route_alias_map();
    return $aliases[$key] ?? $key;
}

function app_route_meta(string $routeKey): ?array
{
    $resolved = app_resolve_route_key($routeKey);
    $catalog = app_route_catalog();
    return $catalog[$resolved] ?? null;
}

function app_route_view(string $routeKey): ?string
{
    $meta = app_route_meta($routeKey);
    return $meta['view'] ?? null;
}

function app_route_scripts(string $routeKey): array
{
    $meta = app_route_meta($routeKey);
    $scripts = $meta['scripts'] ?? [];
    return is_array($scripts) ? $scripts : [];
}

function app_route_is_nav_visible(string $routeKey): bool
{
    $meta = app_route_meta($routeKey);
    if ($meta === null) {
        return false;
    }

    return ($meta['nav_hidden'] ?? false) !== true;
}

function app_nav_section_blueprint(): array
{
    return [
        [
            'key' => 'console',
            'label' => 'Threat Workspace',
            'default_collapsed' => false,
            'groups' => [
                'core' => ['label' => null],
            ],
        ],
        [
            'key' => 'malware',
            'label' => 'Malware Curation',
            'default_collapsed' => false,
            'groups' => [
                'catalog' => ['label' => 'Catalog'],
                'taxonomy' => ['label' => 'Taxonomy workspace'],
                'policy' => ['label' => 'Planning & policy'],
            ],
        ],
        [
            'key' => 'dataset',
            'label' => 'Dataset Curation',
            'default_collapsed' => false,
            'groups' => [
                'surfaces' => ['label' => 'Governed surfaces'],
                'release' => ['label' => 'Release planning'],
            ],
        ],
        [
            'key' => 'permissions',
            'label' => 'Permission Intel',
            'default_collapsed' => true,
            'groups' => [
                'operate' => ['label' => 'Operate'],
                'diagnostics' => ['label' => 'Diagnostics'],
                'reference' => ['label' => 'Reference surfaces'],
            ],
        ],
        [
            'key' => 'pipeline',
            'label' => 'VirusTotal API',
            'default_collapsed' => true,
            'groups' => [
                'operate' => ['label' => 'Operate'],
                'detail' => ['label' => 'Detail pages'],
            ],
        ],
        [
            'key' => 'admin',
            'label' => 'Platform & Admin',
            'default_collapsed' => true,
            'groups' => [
                'structure' => ['label' => 'Schema & stack'],
                'admin' => ['label' => 'Admin'],
            ],
        ],
    ];
}

function app_nav_route_overrides(): array
{
    return [
        'landing' => ['section' => 'console', 'group' => 'core', 'order' => 10, 'label' => 'Home'],
        'malware_samples' => ['section' => 'console', 'group' => 'core', 'order' => 20],
        'check_hash' => ['section' => 'console', 'group' => 'core', 'order' => 30],
        'submit_artifact' => ['section' => 'console', 'group' => 'core', 'order' => 40],
        'ingest_backlog' => ['section' => 'console', 'group' => 'core', 'order' => 50],
        'pending_source_mix' => ['section' => 'console', 'group' => 'core', 'order' => 60, 'label' => 'Pending Source Mix'],

        'family_taxonomy_check' => ['section' => 'malware', 'group' => 'taxonomy', 'order' => 10, 'label' => 'Taxonomy Workspace'],
        'family_taxonomy_gaps' => ['section' => 'malware', 'group' => 'taxonomy', 'order' => 20],
        'family_taxonomy_queue' => ['section' => 'malware', 'group' => 'taxonomy', 'order' => 30],
        'family_taxonomy_conflicts' => ['section' => 'malware', 'group' => 'taxonomy', 'order' => 40],
        'family_taxonomy_repair_planning' => ['section' => 'malware', 'group' => 'policy', 'order' => 10],
        'family_taxonomy_signal_hygiene' => ['section' => 'malware', 'group' => 'policy', 'order' => 20],

        'dataset_readiness' => ['section' => 'dataset', 'group' => 'surfaces', 'order' => 10],
        'type_benchmark' => ['section' => 'dataset', 'group' => 'surfaces', 'order' => 20],
        'label_surfaces' => ['section' => 'dataset', 'group' => 'surfaces', 'order' => 30],
        'authority_consistency_debt' => ['section' => 'dataset', 'group' => 'surfaces', 'order' => 40],
        'dataset_exports' => ['section' => 'dataset', 'group' => 'release', 'order' => 10, 'label' => 'Export Readiness'],

        'permissions_overview' => ['section' => 'permissions', 'group' => 'operate', 'order' => 10],
        'permissions_triage' => ['section' => 'permissions', 'group' => 'operate', 'order' => 20],
        'permissions_review' => ['section' => 'permissions', 'group' => 'operate', 'order' => 30],
        'permissions_queue' => ['section' => 'permissions', 'group' => 'operate', 'order' => 40],
        'permissions_drift' => ['section' => 'permissions', 'group' => 'diagnostics', 'order' => 10],
        'permissions_evidence' => ['section' => 'permissions', 'group' => 'diagnostics', 'order' => 20],
        'analysis_fusion' => ['section' => 'permissions', 'group' => 'diagnostics', 'order' => 30],
        'permissions_aosp' => ['section' => 'permissions', 'group' => 'reference', 'order' => 10],
        'permissions_google' => ['section' => 'permissions', 'group' => 'reference', 'order' => 20],
        'permissions_oem_registry' => ['section' => 'permissions', 'group' => 'reference', 'order' => 30],
        'permissions_oem_permissions' => ['section' => 'permissions', 'group' => 'reference', 'order' => 40],

        'health' => ['section' => 'pipeline', 'group' => 'operate', 'order' => 20, 'label' => 'Pipeline Health'],
        'pipeline_ops' => ['section' => 'pipeline', 'group' => 'operate', 'order' => 10, 'label' => 'Pipeline Ops'],
        'vt_confidence' => ['section' => 'pipeline', 'group' => 'operate', 'order' => 30],
        'runs' => ['section' => 'pipeline', 'group' => 'operate', 'order' => 20, 'label' => 'Run Ledger'],
        'vt_snapshot_inventory' => ['section' => 'pipeline', 'group' => 'detail', 'order' => 20, 'label' => 'Snapshot Inventory'],

        'stack_audit' => ['section' => 'admin', 'group' => 'structure', 'order' => 10],
        'schema_inventory' => ['section' => 'admin', 'group' => 'structure', 'order' => 20],
        'admin_diagnostics' => ['section' => 'admin', 'group' => 'admin', 'order' => 10],
        'settings' => ['section' => 'admin', 'group' => 'admin', 'order' => 20],
        'time_reference' => ['section' => 'admin', 'group' => 'admin', 'order' => 30, 'label' => 'Time'],
    ];
}

function app_nav_sections_manifest(): array
{
    $blueprint = app_nav_section_blueprint();
    $overrides = app_nav_route_overrides();
    $catalog = app_route_catalog();
    $sections = [];

    foreach ($blueprint as $sectionConfig) {
        $sectionKey = (string)$sectionConfig['key'];
        $groups = [];
        foreach (($sectionConfig['groups'] ?? []) as $groupKey => $groupConfig) {
            $links = [];
            foreach ($overrides as $route => $override) {
                if (($override['section'] ?? null) !== $sectionKey || ($override['group'] ?? null) !== $groupKey) {
                    continue;
                }
                $meta = $catalog[$route] ?? null;
                if ($meta === null) {
                    continue;
                }
                if (($meta['nav_hidden'] ?? false) === true) {
                    continue;
                }
                $links[] = [
                    'route' => $route,
                    'label' => (string)($override['label'] ?? ($meta['label'] ?? $route)),
                    'order' => (int)($override['order'] ?? 999),
                ];
            }
            usort($links, static fn(array $a, array $b): int => ($a['order'] <=> $b['order']) ?: strcmp((string)$a['label'], (string)$b['label']));
            if ($links !== []) {
                $groups[] = [
                    'label' => $groupConfig['label'] ?? null,
                    'links' => array_map(
                        static fn(array $link): array => ['route' => $link['route'], 'label' => $link['label']],
                        $links
                    ),
                ];
            }
        }
        if ($groups !== []) {
            $sectionConfig['groups'] = $groups;
            $sections[] = $sectionConfig;
        }
    }

    return $sections;
}
