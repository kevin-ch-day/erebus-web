<?php
// app/database/services/schema_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../../lib/transient_cache.php';
require_once __DIR__ . '/../db_engine.php';

function db_schema_cache_key(string $scope, array $payload = []): string
{
    return md5(json_encode([
        'scope' => $scope,
        'primary_catalog' => db_primary_catalog_name(),
        'permission_catalog' => db_permission_intel_catalog_name(),
        'split_enabled' => db_permission_intel_split_enabled() ? '1' : '0',
        'version' => defined('APP_VERSION') ? APP_VERSION : 'dev',
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES) ?: '');
}

function db_schema_cached(string $namespace, string $scope, int $ttlSeconds, callable $loader, array $payload = []): array
{
    static $requestCache = [];

    $cacheKey = db_schema_cache_key($scope, $payload);
    $memoKey = $namespace . ':' . $cacheKey;
    if (isset($requestCache[$memoKey]) && is_array($requestCache[$memoKey])) {
        return $requestCache[$memoKey];
    }

    $cached = app_transient_cache_read($namespace, $cacheKey, $ttlSeconds);
    if (is_array($cached)) {
        $requestCache[$memoKey] = $cached;
        return $requestCache[$memoKey];
    }

    $stale = app_transient_cache_read_stale($namespace, $cacheKey);
    if (is_array($stale)) {
        $requestCache[$memoKey] = $stale;
        return $requestCache[$memoKey];
    }

    $value = $loader();
    if (is_array($value)) {
        app_transient_cache_write($namespace, $cacheKey, $value);
        $requestCache[$memoKey] = $value;
        return $requestCache[$memoKey];
    }

    return [];
}

function db_known_schema_surfaces(): array
{
    return [
        [
            'name' => 'malware_sample_catalog',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'sample_identity',
            'consumer_pages' => ['malware_samples', 'sample_detail', 'classification_gaps', 'vt_confidence', 'analysis_fusion'],
            'columns' => ['sample_id', 'sha256'],
        ],
        [
            'name' => 'virustotal_sample_state',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_scheduler_state',
            'consumer_pages' => ['health', 'runs'],
            'columns' => ['sample_id', 'sha256', 'vt_status_code', 'claim_token', 'next_eligible_at_utc', 'reason_code'],
        ],
        [
            'name' => 'virustotal_api_keys',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_key_quota_state',
            'consumer_pages' => ['health'],
            'columns' => ['api_key_id', 'api_key', 'is_enabled', 'is_visible', 'daily_quota_limit', 'daily_quota_used', 'quota_day_utc', 'cooldown_until_utc', 'last_429_at_utc'],
        ],
        [
            'name' => 'virustotal_system_control',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_global_hold_state',
            'consumer_pages' => ['health'],
            'columns' => ['control_id', 'hold_until_utc', 'hold_reason_code', 'last_429_at_utc'],
        ],
        [
            'name' => 'virustotal_run_ledger',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_run_lineage',
            'consumer_pages' => ['health', 'runs'],
            'columns' => ['run_id', 'perm_taxonomy_version', 'finished_at_utc', 'ok_count'],
        ],
        [
            'name' => 'virustotal_sample_vendor_verdicts',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_verdict_canonical',
            'consumer_pages' => ['health', 'vt_confidence'],
            'columns' => ['sample_id', 'vendor_engine_id', 'verdict_category', 'verdict_label', 'engine_update', 'detection_method', 'updated_at_utc'],
        ],
        [
            'name' => 'virustotal_sample_vendor_engine_verdicts',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_verdict_projection',
            'consumer_pages' => ['health', 'vt_confidence'],
            'columns' => ['sample_id', 'updated_at'],
        ],
        [
            'name' => 'virustotal_vendor_engines',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_identity_catalog',
            'consumer_pages' => ['health'],
            'columns' => ['vendor_engine_id', 'vendor_name', 'vendor_key'],
        ],
        [
            'name' => 'vt_vendor_engine_name_collision_log',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_identity_collisions',
            'consumer_pages' => ['health'],
            'columns' => ['collision_id', 'sample_id', 'vendor_key', 'incoming_vendor_name', 'existing_vendor_name', 'vendor_engine_id', 'first_seen_at_utc', 'last_seen_at_utc'],
        ],
        [
            'name' => 'vt_vendor_reliability_profile',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_reliability_profile',
            'consumer_pages' => ['health', 'vt_confidence'],
            'columns' => ['vendor_key', 'display_name', 'reliability_weight', 'false_positive_tendency', 'instability_score', 'calibration_formula_version', 'calibrated_at_utc'],
        ],
        [
            'name' => 'vt_vendor_projection_profile',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_projection_profile',
            'consumer_pages' => ['health'],
            'columns' => ['vendor_key', 'display_name', 'wide_column_present', 'wide_populated_ratio', 'low_fill_candidate_flag', 'calibration_formula_version', 'refreshed_at_utc'],
        ],
        [
            'name' => 'vt_vendor_delta',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_vendor_drift_delta',
            'consumer_pages' => ['health'],
            'columns' => ['delta_id', 'sha256', 'fetched_at_utc', 'changed_engines_count', 'engines_new_count', 'engines_removed_count', 'labels_changed_count', 'categories_changed_count'],
        ],
        [
            'name' => 'virustotal_sample_signal_current',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_signal_current',
            'consumer_pages' => ['health', 'vt_confidence'],
            'columns' => ['sample_id', 'sha256', 'popular_threat_label', 'popular_threat_category', 'popular_threat_name', 'parse_version'],
        ],
        [
            'name' => 'vt_sample_verdict_confidence_current',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'vt_confidence_current',
            'consumer_pages' => ['vt_confidence', 'permissions_overview', 'analysis_fusion'],
            'columns' => ['sample_id', 'sha256', 'vt_malicious_count', 'vt_suspicious_count', 'vt_harmless_count', 'vt_total_engines', 'confidence_score', 'confidence_bucket', 'recommended_action'],
        ],
        [
            'name' => 'vw_malware_sample_catalog_family_resolution',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'governed_family_resolution_projection',
            'consumer_pages' => ['family_taxonomy_check', 'label_surfaces', 'type_benchmark'],
            'columns' => ['sample_id', 'resolved_family_name', 'resolution_review_status', 'mapping_rows', 'accepted_mapping_rows', 'distinct_families', 'accepted_distinct_families'],
        ],
        [
            'name' => 'label_authority_resolution_view',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'sample_label_authority_projection',
            'consumer_pages' => ['label_surfaces', 'type_benchmark', 'dataset_readiness'],
            'columns' => ['sample_id', 'classification_primary', 'classification_subtype', 'catalog_type_slug', 'governed_type_slug', 'effective_type_slug', 'explicit_authority_override_flag'],
        ],
        [
            'name' => 'v_android_sample_family_type_authority',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'sample_family_type_authority_projection',
            'consumer_pages' => ['label_surfaces', 'type_benchmark', 'dataset_readiness'],
            'columns' => ['sample_id', 'raw_classification_primary', 'raw_classification_subtype', 'type_slug', 'authority_bucket', 'authority_gap_reason', 'raw_vs_authority_status'],
        ],
        [
            'name' => 'android_malware_type',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'malware_type_catalog',
            'consumer_pages' => ['label_surfaces', 'type_benchmark', 'dataset_readiness'],
            'columns' => ['type_id', 'type_name', 'type_slug', 'parent_type_id', 'type_level', 'is_active'],
        ],
        [
            'name' => 'malware_family_authority_fact',
            'catalog_role' => 'primary',
            'object_kind' => 'table',
            'analysis_role' => 'family_type_authority_facts',
            'consumer_pages' => ['label_surfaces', 'type_benchmark', 'dataset_readiness'],
            'columns' => ['sample_id', 'governed_family_slug', 'governed_type_slug', 'authority_resolution_method', 'review_status', 'is_active'],
        ],
        [
            'name' => 'v_vt_evidence_confidence_summary',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'vt_confidence_bucket_summary',
            'consumer_pages' => ['vt_confidence'],
            'columns' => ['confidence_bucket', 'recommended_action', 'sample_count', 'min_confidence_score', 'avg_confidence_score', 'max_confidence_score'],
        ],
        [
            'name' => 'v_vt_false_positive_review_candidates',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'vt_false_positive_review',
            'consumer_pages' => ['vt_confidence'],
            'columns' => ['sample_id', 'sha256', 'sample_label', 'family_label', 'platform', 'android_package_name', 'vt_malicious_count', 'vt_suspicious_count', 'vt_harmless_count', 'vt_total_engines', 'raw_detection_ratio', 'confidence_score', 'confidence_bucket', 'recommended_action', 'review_reason'],
        ],
        [
            'name' => 'v_vt_false_positive_review_candidates_effective',
            'catalog_role' => 'primary',
            'object_kind' => 'view',
            'analysis_role' => 'vt_false_positive_review_suppression_aware',
            'consumer_pages' => ['vt_confidence'],
            'columns' => ['sample_id', 'sha256', 'sample_label', 'family_label', 'platform', 'android_package_name', 'vt_malicious_count', 'vt_suspicious_count', 'vt_harmless_count', 'vt_total_engines', 'raw_detection_ratio', 'confidence_score', 'confidence_bucket', 'recommended_action', 'review_reason'],
        ],
        [
            'name' => 'android_permission_obs_sample',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_observation_truth',
            'consumer_pages' => ['permissions_overview', 'permissions_triage', 'sample_detail'],
            'columns' => ['sample_id', 'permission_string', 'permission_string_norm', 'classification', 'bucket', 'observed_at_utc'],
        ],
        [
            'name' => 'android_permission_enrich_vt_event',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_vt_event_evidence',
            'consumer_pages' => ['permissions_overview', 'permissions_drift', 'permissions_evidence'],
            'columns' => ['sample_id', 'permission_string', 'ingested_at_utc'],
        ],
        [
            'name' => 'android_permission_dict_unknown',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_workflow_ledger',
            'consumer_pages' => ['permissions_overview', 'permissions_triage', 'permissions_review'],
            'columns' => ['permission_string', 'triage_status', 'seen_count', 'notes'],
        ],
        [
            'name' => 'android_permission_dict_queue',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_dictionary_intent_queue',
            'consumer_pages' => ['permissions_queue', 'permissions_review'],
            'columns' => ['permission_string', 'queue_action', 'status', 'updated_by', 'updated_at_utc'],
        ],
        [
            'name' => 'android_permission_concept',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_concept_canonical_tokens',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['concept_id', 'canonical_token', 'canonical_token_norm', 'concept_family', 'source_family_key', 'concept_status'],
        ],
        [
            'name' => 'android_permission_concept_token',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_concept_token_mapping',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['concept_token_id', 'concept_id', 'token_value', 'token_value_norm', 'token_role', 'mapping_source'],
        ],
        [
            'name' => 'android_permission_token_alias',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_token_alias_normalization',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['alias_id', 'raw_token', 'raw_token_norm', 'canonical_token', 'canonical_token_norm', 'rule_version'],
        ],
        [
            'name' => 'android_permission_token_anomaly_fact',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_token_anomaly_facts',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['token_anomaly_fact_id', 'token_value', 'token_value_norm', 'anomaly_class', 'confidence', 'is_active'],
        ],
        [
            'name' => 'android_permission_token_family_hint',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_source_family_hints',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['token_family_hint_id', 'permission_string', 'permission_string_norm', 'suggested_source_family_key', 'suggestion_kind', 'confidence'],
        ],
        [
            'name' => 'android_permission_authority_fact',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_authority_facts',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['authority_fact_id', 'permission_string', 'permission_string_norm', 'source_family_key', 'authority_source_type', 'is_current_best'],
        ],
        [
            'name' => 'android_permission_namespace_fact',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_namespace_authority_facts',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['namespace_fact_id', 'fact_key', 'match_kind', 'match_value', 'source_family_key', 'is_active'],
        ],
        [
            'name' => 'android_permission_non_permission_fact',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'non_permission_token_guardrails',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['non_permission_fact_id', 'token_value', 'token_value_norm', 'token_class', 'likely_real_permission', 'is_active'],
        ],
        [
            'name' => 'android_permission_review_state',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_review_decision_state',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['review_state_id', 'review_domain', 'review_subject_type', 'permission_string_norm', 'review_status', 'decision_type'],
        ],
        [
            'name' => 'android_attack_technique_lut',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'attack_mobile_technique_dictionary',
            'consumer_pages' => ['permissions_overview', 'analysis_fusion'],
            'columns' => ['attack_technique_id', 'attack_name', 'tactic', 'platform', 'source_url'],
        ],
        [
            'name' => 'android_permission_attack_mapping',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_to_attack_mapping',
            'consumer_pages' => ['permissions_overview', 'analysis_fusion'],
            'columns' => ['permission_string_norm', 'attack_technique_id', 'mapping_strength', 'active_flag'],
        ],
        [
            'name' => 'v_android_permission_attack_surface_current',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_attack_surface_samples',
            'consumer_pages' => ['permissions_overview'],
            'columns' => ['sample_id', 'package_name', 'attack_technique_id', 'attack_name', 'tactic', 'mapped_permission_count', 'permissions', 'max_mapping_strength_rank'],
        ],
        [
            'name' => 'v_android_permission_attack_surface_summary',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_attack_surface_summary',
            'consumer_pages' => ['permissions_overview'],
            'columns' => ['attack_technique_id', 'attack_name', 'tactic', 'sample_count', 'mapped_permission_observations'],
        ],
        [
            'name' => 'permission_governance_snapshots',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_governance_snapshot_header',
            'consumer_pages' => ['schema_inventory', 'future_governance'],
            'columns' => ['governance_version', 'snapshot_sha256', 'source_system', 'created_at_utc', 'loaded_at_utc'],
        ],
        [
            'name' => 'permission_governance_snapshot_rows',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_governance_snapshot_rows',
            'consumer_pages' => ['schema_inventory', 'future_governance'],
            'columns' => ['governance_version', 'permission_string', 'namespace_type', 'theme_primary', 'risk_class', 'triage_status'],
        ],
        [
            'name' => 'permission_signal_catalog',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_signal_dictionary',
            'consumer_pages' => ['schema_inventory', 'future_governance'],
            'columns' => ['signal_id', 'signal_key', 'display_name', 'default_weight', 'created_at', 'updated_at'],
        ],
        [
            'name' => 'permission_signal_mappings',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'table',
            'analysis_role' => 'permission_signal_assignments',
            'consumer_pages' => ['schema_inventory', 'future_governance'],
            'columns' => ['mapping_id', 'signal_key', 'perm_name', 'namespace', 'confidence', 'created_at'],
        ],
        [
            'name' => 'vw_permission_remaining_work_queues',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_research_work_queue_summary',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['review_domain', 'review_status', 'current_source_family_key', 'row_count', 'oldest_created_at_utc'],
        ],
        [
            'name' => 'vw_permission_gap_closure_summary',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_gap_closure_summary',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['source_surface', 'suggested_source_family_key', 'suggestion_kind', 'confidence', 'row_count'],
        ],
        [
            'name' => 'vw_permission_unknown_unresolved_candidates',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'unknown_permission_resolution_candidates',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['observed_token', 'raw_token_norm', 'candidate_source_family_key', 'triage_status', 'candidate_confidence', 'candidate_reason'],
        ],
        [
            'name' => 'vw_permission_vt_current_unresolved_candidates',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'vt_current_permission_resolution_candidates',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['observed_token', 'raw_token_norm', 'source_system', 'source_engine', 'seen_count', 'candidate_confidence'],
        ],
        [
            'name' => 'vw_permission_token_alias_aosp_case_candidates',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'aosp_case_alias_review_candidates',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['alias_id', 'raw_token', 'raw_token_norm', 'canonical_token', 'canonical_token_norm', 'rollout_priority'],
        ],
        [
            'name' => 'vw_permission_non_permission_signal_gaps',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'non_permission_token_signal_gaps',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['source_surface', 'token_value', 'effective_source_family_key', 'seen_count', 'candidate_reason'],
        ],
        [
            'name' => 'vw_permission_source_family_coverage',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_source_family_coverage',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['source_family_key', 'source_family_label', 'source_kind', 'coverage_status', 'pending_review_count'],
        ],
        [
            'name' => 'vw_permission_governed_summary',
            'catalog_role' => 'permission_intel',
            'object_kind' => 'view',
            'analysis_role' => 'permission_governed_summary',
            'consumer_pages' => ['schema_inventory', 'future_permission_research'],
            'columns' => ['dataset_name', 'effective_source_family_key', 'effective_review_lane', 'row_count', 'total_seen'],
        ],
    ];
}

function db_schema_surface_catalog_name(array $surface): string
{
    $role = (string)($surface['catalog_role'] ?? '');
    if ($role === 'permission_intel') {
        return db_permission_intel_catalog_name();
    }
    return db_primary_catalog_name();
}

/**
 * @param array<int, string>|null $surfaceNames
 * @return array<string, array<int, string>>
 */
function db_known_schema_requirements(?array $surfaceNames = null): array
{
    $nameFilter = null;
    if (is_array($surfaceNames)) {
        $nameFilter = [];
        foreach ($surfaceNames as $name) {
            $clean = trim((string)$name);
            if ($clean !== '') {
                $nameFilter[strtolower($clean)] = true;
            }
        }
    }

    $requirements = [];
    foreach (db_known_schema_surfaces() as $surface) {
        $name = (string)($surface['name'] ?? '');
        if ($name === '') {
            continue;
        }
        if ($nameFilter !== null && !isset($nameFilter[strtolower($name)])) {
            continue;
        }
        $requirements[$name] = array_values($surface['columns'] ?? []);
    }
    return $requirements;
}

/**
 * Verify required table/view columns across primary and Permission Intel catalogs.
 *
 * @param array<string, array<int, string>> $requirements
 */
function db_schema_requirements_status(array $requirements): array
{
    if (!$requirements) {
        return [
            'ok' => true,
            'missing' => [],
            'missing_count' => 0,
            'checked_at_utc' => gmdate('Y-m-d H:i:s'),
        ];
    }

    ksort($requirements);
    foreach ($requirements as &$columns) {
        $columns = array_values(array_map('strval', $columns));
        sort($columns);
    }
    unset($columns);

    return db_schema_cached(
        'schema_requirements_status',
        'requirements_status',
        300,
        static function () use ($requirements): array {
            $params = [];
            $where = [];
            $idx = 0;
            foreach ($requirements as $table => $columns) {
                foreach (array_values($columns) as $column) {
                    $where[] = '(table_schema = :schema_' . $idx . ' AND table_name = :table_' . $idx . ' AND column_name = :column_' . $idx . ')';
                    $params['schema_' . $idx] = db_table_catalog_name((string)$table);
                    $params['table_' . $idx] = (string)$table;
                    $params['column_' . $idx] = (string)$column;
                    $idx++;
                }
            }

            $rows = db_all(
                'SELECT table_schema, table_name, column_name FROM information_schema.columns WHERE ' . implode(' OR ', $where),
                $params
            );

            $present = [];
            foreach ($rows as $row) {
                $catalog = (string)($row['table_schema'] ?? '');
                $table = (string)($row['table_name'] ?? '');
                $column = (string)($row['column_name'] ?? '');
                if ($catalog === '' || $table === '' || $column === '') {
                    continue;
                }
                $present[strtolower($catalog . '.' . $table . '.' . $column)] = true;
            }

            $missing = [];
            foreach ($requirements as $table => $columns) {
                $catalog = db_table_catalog_name((string)$table);
                foreach (array_values($columns) as $column) {
                    $key = strtolower($catalog . '.' . (string)$table . '.' . (string)$column);
                    if (!isset($present[$key])) {
                        $missing[] = [
                            'table' => (string)$table,
                            'column' => (string)$column,
                            'catalog' => $catalog,
                        ];
                    }
                }
            }

            return [
                'ok' => count($missing) === 0,
                'missing' => $missing,
                'missing_count' => count($missing),
                'checked_at_utc' => gmdate('Y-m-d H:i:s'),
            ];
        },
        ['requirements' => $requirements]
    );
}

function db_schema_inventory(): array
{
    $surfaces = db_known_schema_surfaces();
    return db_schema_cached(
        'schema_inventory',
        'inventory',
        300,
        static function () use ($surfaces): array {
            $params = [];
            $where = [];
            foreach ($surfaces as $idx => $surface) {
                $where[] = '(c.table_schema = :schema_' . $idx . ' AND c.table_name = :table_' . $idx . ')';
                $params['schema_' . $idx] = db_schema_surface_catalog_name($surface);
                $params['table_' . $idx] = (string)$surface['name'];
            }

            $columns = [];
            if ($where) {
                $rows = db_all(
                    "SELECT c.table_schema, c.table_name, c.column_name, t.table_type
                     FROM information_schema.columns c
                     LEFT JOIN information_schema.tables t
                       ON t.table_schema = c.table_schema
                      AND t.table_name = c.table_name
                     WHERE " . implode(' OR ', $where) . "
                     ORDER BY c.table_schema, c.table_name, c.ordinal_position",
                    $params
                );
                foreach ($rows as $row) {
                    $key = strtolower((string)$row['table_schema'] . '.' . (string)$row['table_name']);
                    if (!isset($columns[$key])) {
                        $columns[$key] = [
                            'table_type' => (string)($row['table_type'] ?? ''),
                            'columns' => [],
                        ];
                    }
                    $columns[$key]['columns'][] = (string)$row['column_name'];
                }
            }

            $out = [];
            $missingSurfaceCount = 0;
            $missingColumnCount = 0;
            foreach ($surfaces as $surface) {
                $catalog = db_schema_surface_catalog_name($surface);
                $key = strtolower($catalog . '.' . (string)$surface['name']);
                $found = $columns[$key] ?? null;
                $presentColumns = $found['columns'] ?? [];
                $expectedColumns = array_values($surface['columns'] ?? []);
                $missingColumns = array_values(array_filter(
                    $expectedColumns,
                    static fn($column) => !in_array($column, $presentColumns, true)
                ));
                $available = $found !== null && count($missingColumns) === 0;
                if ($found === null) {
                    $missingSurfaceCount++;
                }
                $missingColumnCount += count($missingColumns);
                $out[] = [
                    'name' => $surface['name'],
                    'catalog' => $catalog,
                    'catalog_role' => $surface['catalog_role'],
                    'expected_object_kind' => $surface['object_kind'],
                    'actual_table_type' => $found['table_type'] ?? null,
                    'analysis_role' => $surface['analysis_role'],
                    'consumer_pages' => $surface['consumer_pages'],
                    'available' => $available,
                    'present' => $found !== null,
                    'expected_columns' => $expectedColumns,
                    'missing_columns' => $missingColumns,
                    'present_column_count' => count($presentColumns),
                ];
            }

            return [
                'summary' => [
                    'surface_count' => count($out),
                    'available_count' => count(array_filter($out, static fn($row) => (bool)$row['available'])),
                    'missing_surface_count' => $missingSurfaceCount,
                    'missing_column_count' => $missingColumnCount,
                ],
                'surfaces' => $out,
            ];
        },
        ['surface_names' => array_values(array_map(static fn($surface): string => (string)($surface['name'] ?? ''), $surfaces))]
    );
}

function db_schema_surface_present(string $surfaceName): bool
{
    static $present = null;
    if (!is_array($present)) {
        $present = [];
        foreach (db_schema_inventory()['surfaces'] ?? [] as $surface) {
            $name = strtolower(trim((string)($surface['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $present[$name] = (bool)($surface['present'] ?? false);
        }
    }

    return (bool)($present[strtolower(trim($surfaceName))] ?? false);
}

function db_vt_surface_inventory_summary(?array $inventory = null): array
{
    $inventory = is_array($inventory) ? $inventory : db_schema_inventory();
    $surfaces = array_values(array_filter(
        $inventory['surfaces'] ?? [],
        static function ($surface): bool {
            if (!is_array($surface)) {
                return false;
            }
            $catalogRole = (string)($surface['catalog_role'] ?? '');
            $analysisRole = (string)($surface['analysis_role'] ?? '');
            $name = (string)($surface['name'] ?? '');
            if ($catalogRole !== 'primary') {
                return false;
            }
            return str_starts_with($analysisRole, 'vt_')
                || str_starts_with($name, 'virustotal_')
                || str_starts_with($name, 'v_vt_')
                || str_starts_with($name, 'vt_');
        }
    ));

    $known = count($surfaces);
    $available = count(array_filter($surfaces, static fn($surface): bool => (bool)($surface['available'] ?? false)));
    $missing = array_values(array_map(
        static fn($surface): string => (string)($surface['name'] ?? ''),
        array_filter($surfaces, static fn($surface): bool => !(bool)($surface['available'] ?? false))
    ));

    return [
        'known_count' => $known,
        'available_count' => $available,
        'missing_count' => count($missing),
        'missing_names' => $missing,
    ];
}
