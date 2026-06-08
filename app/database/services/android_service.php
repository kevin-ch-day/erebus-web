<?php
// app/database/services/android_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/query_helpers.php';
require_once __DIR__ . '/../../lib/transient_cache.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/../queries/android_queries.php';
require_once __DIR__ . '/../queries/runs_queries.php';
require_once __DIR__ . '/schema_service.php';

function db_invalidate_android_permission_summary_caches(): void
{
    foreach ([
        'perm_current_unknown_review',
        'perm_ledger_diag_overview',
        'perm_ledger_diag_page_fast',
        'perm_queue_metrics',
        'perm_current_evidence_risk_counts',
        'health_diag',
        'health_rollup_guard',
    ] as $namespace) {
        app_transient_cache_delete_namespace($namespace);
    }
}

/**
 * Bucket/rule_fired breakdown for a sample's observed permissions.
 */
function db_android_permissions_summary(int $sampleId): array
{
    $rows = db_all(sql_android_permissions_summary(), ['sample_id' => $sampleId]);

    return [
        'data' => $rows,
        'meta' => [
            'sample_id' => $sampleId,
        ],
    ];
}

/**
 * Detailed permission observations for a sample.
 */
function db_android_permissions_detail(int $sampleId): array
{
    $rows = db_all(sql_android_permissions_detail(), ['sample_id' => $sampleId]);

    return [
        'data' => $rows,
        'meta' => [
            'sample_id' => $sampleId,
        ],
    ];
}

/**
 * Update triage status for an unknown permission.
 */
function db_update_unknown_permission_status(string $permission, string $status, ?string $notes = null, ?string $operator = null): array
{
    $permission = trim($permission);
    $status = strtolower(trim($status));
    $operator = trim((string)$operator);
    if ($operator === '') {
        $operator = 'web';
    }
    $result = [
        'updated' => 0,
        'notes_updated' => 0,
        'audit_written' => 0,
        'obs_reclassified' => 0,
        'warnings' => [],
    ];

    db_tx(function () use ($permission, $status, $notes, $operator, &$result): void {
        $current = db_one(sql_unknown_permission_by_string(), ['permission' => $permission]);
        if (!$current) {
            return;
        }

        $canonicalPermission = (string)($current['permission_string'] ?? $permission);
        $previousStatus = strtolower((string)($current['triage_status'] ?? ''));
        $previousNotes = array_key_exists('notes', $current) ? (string)($current['notes'] ?? '') : '';

        $rows = db_exec(sql_unknown_permission_update_status(), [
            'permission' => $canonicalPermission,
            'triage_status' => $status,
        ]);
        $result['updated'] = $rows;

        if ($notes !== null) {
            $notesRows = db_exec(sql_unknown_permission_update_notes(), [
                'permission' => $canonicalPermission,
                'notes' => $notes,
            ]);
            $result['notes_updated'] = $notesRows;
        }

        $obsMaterialization = perm_obs_materialization_for_triage_status($status);
        if ($obsMaterialization !== null) {
            $result['obs_reclassified'] = db_exec(sql_android_permission_obs_reclassify_by_permission(), [
                'permission' => $canonicalPermission,
                'classification' => $obsMaterialization['classification'],
                'bucket' => $obsMaterialization['bucket'],
                'rule_fired' => $obsMaterialization['rule_fired'],
            ]);
        }

        if ($result['updated'] === 0) {
            if ($previousStatus === strtolower($status)) {
                $result['updated'] = 1;
                $result['warnings'][] = 'no_change';
            }
        }

        $contextJson = json_encode([
            'previous_triage_status' => $previousStatus,
            'new_triage_status' => $status,
            'notes_updated' => $notes !== null,
            'previous_notes' => $previousNotes,
            'new_notes' => $notes,
        ], JSON_UNESCAPED_SLASHES);
        if ($contextJson === false) {
            $contextJson = null;
        }
        $auditRows = db_exec(sql_unknown_permission_audit_insert(), [
            'permission' => $canonicalPermission,
            'action' => 'triage_status_update',
            'actor' => $operator,
            'source' => 'web',
            'context_json' => $contextJson,
            'note' => $notes,
        ]);
        $result['audit_written'] = $auditRows;
    });

    if (($result['updated'] ?? 0) > 0 || ($result['obs_reclassified'] ?? 0) > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return ['data' => $result];
}

/**
 * Reconcile governed/resolved dictionary states into observation classifications.
 */
function db_reconcile_android_permission_observations(): array
{
    $updated = db_exec(sql_android_permission_obs_reclassify_by_governed_status());
    if ($updated > 0) {
        db_invalidate_android_permission_summary_caches();
    }
    return [
        'data' => [
            'updated' => $updated,
        ],
    ];
}

/**
 * Normalize exact dynamic receiver permission suffix tokens into app-defined.
 */
function db_repair_dynamic_receiver_permission_tokens(): array
{
    $updated = 0;
    db_tx(function () use (&$updated): void {
        $updated = db_exec(sql_unknown_permission_batch_promote_dynamic_receiver_tokens());
        if ($updated > 0) {
            db_exec(sql_android_permission_obs_reclassify_by_governed_status());
        }
    });
    if ($updated > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return [
        'data' => [
            'updated' => $updated,
        ],
    ];
}

/**
 * Repair ledger rows whose notes already record OEM resolution.
 */
function db_repair_noted_resolved_oem_tokens(): array
{
    $updated = 0;
    db_tx(function () use (&$updated): void {
        $updated = db_exec(sql_unknown_permission_batch_promote_noted_resolved_oem());
        if ($updated > 0) {
            db_exec(sql_android_permission_obs_reclassify_by_governed_status());
        }
    });
    if ($updated > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return [
        'data' => [
            'updated' => $updated,
        ],
    ];
}

/**
 * Retire parser-contaminated dynamic receiver artifact tokens.
 */
function db_repair_dynamic_receiver_artifact_tokens(): array
{
    $updated = 0;
    $reclassified = 0;
    db_tx(function () use (&$updated, &$reclassified): void {
        $updated = db_exec(sql_unknown_permission_batch_retire_dynamic_receiver_artifacts());
        $reclassified = db_exec(sql_android_permission_obs_reclassify_dynamic_receiver_artifacts());
    });
    if ($updated > 0 || $reclassified > 0) {
        db_invalidate_android_permission_summary_caches();
    }
    return [
        'data' => [
            'updated' => $updated,
            'reclassified_obs_rows' => $reclassified,
        ],
    ];
}

/**
 * Promote zero-telemetry package-scoped custom permissions out of the new lane.
 */
function db_repair_zero_telemetry_new_app_defined_tokens(): array
{
    $updated = 0;
    db_tx(function () use (&$updated): void {
        $updated = db_exec(sql_unknown_permission_batch_promote_zero_telemetry_new_app_defined());
        if ($updated > 0) {
            db_exec(sql_android_permission_obs_reclassify_by_governed_status());
        }
    });
    if ($updated > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return [
        'data' => [
            'updated' => $updated,
        ],
    ];
}

/**
 * Retire exact misspelled AOSP false positives from the AOSP gap lane.
 */
function db_repair_misspelled_aosp_false_positives(): array
{
    $updated = db_exec(sql_unknown_permission_batch_retire_misspelled_aosp_false_positives());
    if ($updated > 0) {
        db_invalidate_android_permission_summary_caches();
    }
    return [
        'data' => [
            'updated' => $updated,
        ],
    ];
}

/**
 * Materialize observed OEM candidates into the OEM dictionary and resolve them.
 */
function db_promote_observed_oem_candidates(): array
{
    $inserted = 0;
    $resolved = 0;
    db_tx(function () use (&$inserted, &$resolved): void {
        $inserted = db_exec(sql_oem_candidate_batch_insert_into_oem_dict());
        $resolved = db_exec(sql_unknown_permission_batch_promote_oem_candidates_to_resolved());
        if ($resolved > 0) {
            db_exec(sql_android_permission_obs_reclassify_by_governed_status());
        }
    });
    if ($inserted > 0 || $resolved > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return [
        'data' => [
            'inserted_oem_dict_rows' => $inserted,
            'resolved_unknown_rows' => $resolved,
        ],
    ];
}

/**
 * Remove bad OEM auto-seed authority from suspicious custom permissions.
 */
function db_repair_suspicious_oem_misclassification(): array
{
    $deleted = 0;
    $reclassified = 0;
    db_tx(function () use (&$deleted, &$reclassified): void {
        $deleted = db_exec(sql_oem_dict_delete_suspicious_autoseed_rows());
        $reclassified = db_exec(sql_android_permission_obs_reclassify_suspicious_app_defined());
    });
    if ($deleted > 0 || $reclassified > 0) {
        db_invalidate_android_permission_summary_caches();
    }

    return [
        'data' => [
            'deleted_oem_dict_rows' => $deleted,
            'reclassified_obs_rows' => $reclassified,
        ],
    ];
}

/**
 * Rollup drift guard for vt_current vs vt_event.
 */
function db_android_permission_rollup_guard(int $sampleLimit = 10): array
{
    static $requestCache = [];
    $sampleLimit = max(1, min(50, $sampleLimit));
    $cacheKey = hash('sha256', json_encode([
        'primary' => db_primary_catalog_name(),
        'permission_intel' => db_permission_intel_catalog_name(),
        'version' => APP_VERSION,
        'sample_limit' => $sampleLimit,
        'payload_contract' => 'android_permission_rollup_guard_v1',
    ], JSON_UNESCAPED_SLASHES) ?: '');

    if (isset($requestCache[$cacheKey]) && is_array($requestCache[$cacheKey])) {
        return $requestCache[$cacheKey];
    }

    $cached = app_transient_cache_read('health_rollup_guard', $cacheKey, 300);
    if (is_array($cached)) {
        $requestCache[$cacheKey] = $cached;
        return $requestCache[$cacheKey];
    }

    $staleCached = app_transient_cache_read_stale('health_rollup_guard', $cacheKey);
    if (is_array($staleCached)) {
        $requestCache[$cacheKey] = $staleCached;
        return $requestCache[$cacheKey];
    }

    $summary = db_one(sql_android_permission_rollup_drift_summary()) ?? [];
    $samples = db_all(sql_android_permission_rollup_drift_samples($sampleLimit));

    $maxLagSecondsRaw = $summary['max_lag_seconds'] ?? null;
    $maxLagSeconds = $maxLagSecondsRaw === null ? null : (int)$maxLagSecondsRaw;
    $maxLagDays = $maxLagSeconds !== null ? round($maxLagSeconds / 86400, 2) : null;

    $payload = [
        'stale_permissions_count' => (int)($summary['stale_permissions_count'] ?? 0),
        'stale_count_mismatch_count' => (int)($summary['stale_count_mismatch_count'] ?? 0),
        'max_lag_seconds' => $maxLagSeconds,
        'max_lag_days' => $maxLagDays,
        'sample' => $samples,
        'sample_limit' => $sampleLimit,
    ];

    $requestCache[$cacheKey] = $payload;
    app_transient_cache_write('health_rollup_guard', $cacheKey, $payload);

    return $requestCache[$cacheKey];
}

/**
 * Android permission intelligence overview.
 */
function db_android_permission_risk_expr(string $permExpr, string $namespaceExpr): string
{
    return "CASE
        WHEN LOWER({$permExpr}) REGEXP 'sms|mms|send_sms|receive_sms|read_sms|write_sms' THEN 'high'
        WHEN LOWER({$permExpr}) REGEXP 'accessibility|device_admin|bind_device_admin|notification_listener|package_usage_stats|usage_stats' THEN 'high'
        WHEN LOWER({$permExpr}) REGEXP 'request_install_packages|install_packages|manage_external_storage|manage_all_files|query_all_packages' THEN 'high'
        WHEN LOWER({$permExpr}) REGEXP 'record_audio|camera|read_contacts|read_call_log|read_phone|read_phone_state|access_fine_location|access_background_location' THEN 'high'
        WHEN LOWER({$permExpr}) REGEXP 'secure_element|security_center|inject_key_events|read_clipboard' THEN 'high'
        WHEN LOWER({$permExpr}) LIKE '%overlay%'
          OR LOWER({$permExpr}) LIKE '%system_alert_window%'
          OR LOWER({$permExpr}) LIKE '%draw_over_other_apps%' THEN 'high'
        WHEN LOWER({$permExpr}) LIKE '%account%' OR LOWER({$permExpr}) LIKE '%accounts%' THEN 'high'
        WHEN LOWER({$permExpr}) REGEXP 'ignore_battery_optimizations|schedule_exact_alarm|use_exact_alarm|bind_vpn_service|write_settings' THEN 'medium'
        WHEN LOWER({$permExpr}) LIKE '%launcher%'
          OR LOWER({$permExpr}) LIKE '%oem%'
          OR LOWER({$permExpr}) LIKE '%vendor%'
          OR LOWER({$namespaceExpr}) LIKE 'com.huawei%'
          OR LOWER({$namespaceExpr}) LIKE 'com.oppo%'
          OR LOWER({$namespaceExpr}) LIKE 'com.samsung%'
          OR LOWER({$namespaceExpr}) LIKE 'com.xiaomi%'
          OR LOWER({$namespaceExpr}) LIKE 'com.vivo%' THEN 'medium'
        WHEN LOWER({$permExpr}) LIKE '%analytics%'
          OR LOWER({$permExpr}) LIKE '%ads%'
          OR LOWER({$permExpr}) LIKE '%adservice%'
          OR LOWER({$permExpr}) LIKE '%adid%'
          OR LOWER({$permExpr}) LIKE '%ad_id%' THEN 'low'
        ELSE 'medium'
    END";
}

function db_android_permission_risk_reason_expr(string $permExpr, string $namespaceExpr): string
{
    return "CASE
        WHEN LOWER({$permExpr}) REGEXP 'sms|mms|send_sms|receive_sms|read_sms|write_sms' THEN 'sms_or_messaging'
        WHEN LOWER({$permExpr}) REGEXP 'accessibility|device_admin|bind_device_admin|notification_listener|package_usage_stats|usage_stats' THEN 'special_access_or_admin'
        WHEN LOWER({$permExpr}) REGEXP 'request_install_packages|install_packages|manage_external_storage|manage_all_files|query_all_packages' THEN 'installer_storage_or_package_visibility'
        WHEN LOWER({$permExpr}) REGEXP 'record_audio|camera|read_contacts|read_call_log|read_phone|read_phone_state|access_fine_location|access_background_location' THEN 'privacy_sensor_or_identity'
        WHEN LOWER({$permExpr}) REGEXP 'secure_element|security_center|inject_key_events|read_clipboard' THEN 'privileged_control_surface'
        WHEN LOWER({$permExpr}) LIKE '%overlay%'
          OR LOWER({$permExpr}) LIKE '%system_alert_window%'
          OR LOWER({$permExpr}) LIKE '%draw_over_other_apps%' THEN 'overlay_or_ui_deception'
        WHEN LOWER({$permExpr}) LIKE '%account%' OR LOWER({$permExpr}) LIKE '%accounts%' THEN 'account_access'
        WHEN LOWER({$permExpr}) REGEXP 'ignore_battery_optimizations|schedule_exact_alarm|use_exact_alarm|bind_vpn_service|write_settings' THEN 'special_settings_or_persistence'
        WHEN LOWER({$permExpr}) LIKE '%launcher%'
          OR LOWER({$permExpr}) LIKE '%oem%'
          OR LOWER({$permExpr}) LIKE '%vendor%'
          OR LOWER({$namespaceExpr}) LIKE 'com.huawei%'
          OR LOWER({$namespaceExpr}) LIKE 'com.oppo%'
          OR LOWER({$namespaceExpr}) LIKE 'com.samsung%'
          OR LOWER({$namespaceExpr}) LIKE 'com.xiaomi%'
          OR LOWER({$namespaceExpr}) LIKE 'com.vivo%' THEN 'oem_vendor_or_launcher'
        WHEN LOWER({$permExpr}) LIKE '%analytics%'
          OR LOWER({$permExpr}) LIKE '%ads%'
          OR LOWER({$permExpr}) LIKE '%adservice%'
          OR LOWER({$permExpr}) LIKE '%adid%'
          OR LOWER({$permExpr}) LIKE '%ad_id%' THEN 'advertising_or_analytics'
        ELSE 'generic_permission_context'
    END";
}

function db_android_permission_classification_gap_schema_status(): array
{
    return db_schema_requirements_status(db_known_schema_requirements([
        'vt_sample_verdict_confidence_current',
        'v_android_permission_attack_surface_current',
    ]));
}


require_once __DIR__ . '/android_service_reporting.php';
