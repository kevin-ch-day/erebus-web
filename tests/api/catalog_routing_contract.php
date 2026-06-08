<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/database/db_config.php';

$errors = [];

$primary = db_primary_catalog_name();
$permissionIntel = db_permission_intel_catalog_name();

if ($primary === '') {
    $errors[] = 'Primary catalog resolved to an empty name.';
}
if ($permissionIntel === '') {
    $errors[] = 'Permission Intel catalog resolved to an empty name.';
}

$expectedPiTables = [
    'android_permission_obs_sample',
    'v_android_permission_attack_surface_current',
    'vw_permission_remaining_work_queues',
    'android_attack_technique_lut',
    'permission_governance_snapshot_rows',
    'permission_signal_catalog',
];

foreach ($expectedPiTables as $table) {
    $resolved = db_table_catalog_name($table);
    if ($resolved !== $permissionIntel) {
        $errors[] = sprintf(
            'Expected %s to route to Permission Intel catalog %s, got %s.',
            $table,
            $permissionIntel,
            $resolved
        );
    }
}

$expectedPrimaryTables = [
    'malware_sample_catalog',
    'virustotal_sample_state',
    'vt_sample_verdict_confidence_current',
    'permission_coverage_report',
    'permission_discriminability_rank',
    'v_permission_research_status_v2',
];

foreach ($expectedPrimaryTables as $table) {
    $resolved = db_table_catalog_name($table);
    if ($resolved !== $primary) {
        $errors[] = sprintf(
            'Expected %s to route to primary catalog %s, got %s.',
            $table,
            $primary,
            $resolved
        );
    }
}

if ($errors !== []) {
    echo "FAIL: catalog routing contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: catalog routing contract check passed.\n";
exit(0);
