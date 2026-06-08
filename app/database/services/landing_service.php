<?php
declare(strict_types=1);

require_once __DIR__ . '/health_service.php';
require_once __DIR__ . '/family_service.php';
require_once __DIR__ . '/dataset_readiness_service.php';
require_once __DIR__ . '/stack_service.php';

function db_landing_snapshot(): array
{
    $health = db_health(false);
    $familySummary = is_array($health['family_taxonomy_summary'] ?? null) ? $health['family_taxonomy_summary'] : [];
    $metrics = is_array($health['metrics'] ?? null) ? $health['metrics'] : [];
    $systemControl = is_array($health['system_control'] ?? null) ? $health['system_control'] : [];
    $dataset = db_dataset_readiness_overview(true);
    $typeBenchmark = is_array($dataset['type_benchmark'] ?? null) ? $dataset['type_benchmark'] : [];
    $typeSummary = is_array($typeBenchmark['summary'] ?? null) ? $typeBenchmark['summary'] : [];
    $authorityConsistency = is_array($typeBenchmark['authority_consistency_summary'] ?? null) ? $typeBenchmark['authority_consistency_summary'] : [];
    $stackAudit = db_stack_audit();
    $stackCapabilities = is_array($stackAudit['capabilities'] ?? null) ? $stackAudit['capabilities'] : [];
    $stackGaps = is_array($stackAudit['gap_inventory'] ?? null) ? $stackAudit['gap_inventory'] : [];

    return [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'health' => [
            'eligible_now' => (int)($metrics['eligible_now'] ?? 0),
            'processing_now' => (int)($metrics['processing_now'] ?? 0),
            'error_count' => (int)($metrics['error_count'] ?? 0),
            'retry_wait_count' => (int)($metrics['retry_wait_count'] ?? 0),
            'stale_claims' => (int)($metrics['stale_claims'] ?? 0),
            'reason_breakdown' => array_slice(is_array($metrics['reason_breakdown'] ?? null) ? $metrics['reason_breakdown'] : [], 0, 6),
            'hold_until_utc' => (string)($systemControl['hold_until_utc'] ?? ''),
            'hold_reason_code' => (string)($systemControl['hold_reason_code'] ?? ''),
        ],
        'family' => [
            'summary' => $familySummary,
            'top_mismatch_pairs' => db_family_taxonomy_top_mismatch_pairs(6),
        ],
        'stack' => [
            'gap_count' => count($stackGaps),
            'gaps' => $stackGaps,
            'ui_spec_count' => (int)($stackCapabilities['ui_spec_count'] ?? 0),
            'api_contract_count' => (int)($stackCapabilities['api_contract_count'] ?? 0),
            'typed_source_page_count' => (int)($stackCapabilities['typed_source_page_count'] ?? 0),
            'ts_page_count' => (int)($stackCapabilities['ts_page_count'] ?? 0),
        ],
        'dataset' => [
            'clean_benchmark_rows' => (int)($typeSummary['clean_benchmark_rows'] ?? 0),
            'persisted_authority_fact_count' => (int)($typeSummary['persisted_authority_fact_count'] ?? 0),
            'held_persisted_authority_consistency_debt_count' => (int)($typeSummary['held_persisted_authority_consistency_debt_count'] ?? 0),
            'projection_materialization_debt_count' => (int)($typeSummary['projection_without_persisted_fact_count'] ?? 0),
            'unresolved_authority_count' => (int)($typeSummary['unresolved_authority_count'] ?? 0),
            'generic_policy_hold_count' => (int)($typeSummary['generic_token_policy_hold_count'] ?? 0),
            'class_count' => (int)($typeSummary['class_count'] ?? 0),
            'trainable_class_count_n10' => (int)($typeSummary['trainable_class_count_n10'] ?? 0),
            'top_class' => (string)($typeSummary['top_class'] ?? ''),
            'top_class_share' => $typeSummary['top_class_share'] ?? null,
            'authority_consistency_families' => (int)($authorityConsistency['family_count'] ?? 0),
        ],
    ];
}
