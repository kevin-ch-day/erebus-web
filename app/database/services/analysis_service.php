<?php
// app/database/services/analysis_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/schema_service.php';

function db_analysis_fusion_schema_status(): array
{
    return db_schema_requirements_status(db_known_schema_requirements([
        'malware_sample_catalog',
        'vt_sample_verdict_confidence_current',
        'virustotal_sample_signal_current',
        'v_android_permission_attack_surface_current',
        'v_android_permission_attack_surface_summary',
    ]));
}

function db_analysis_fusion(int $limit = 50): array
{
    $limit = max(1, min($limit, 250));
    $schema = db_analysis_fusion_schema_status();
    if (!$schema['ok']) {
        return [
            'data' => [
                'summary' => [],
                'fusion_rows' => [],
                'attack_surface_summary' => [],
                'schema_missing' => $schema['missing'],
            ],
            'meta' => [
                'schema_available' => false,
                'primary_database' => db_primary_catalog_name(),
                'permission_intel_database' => db_permission_intel_catalog_name(),
                'permission_intel_split' => db_permission_intel_split_enabled(),
                'limit' => $limit,
            ],
        ];
    }

    $vtConfidence = db_catalog_table('vt_sample_verdict_confidence_current');
    $signalCurrent = db_catalog_table('virustotal_sample_signal_current');
    $attackCurrent = db_catalog_table('v_android_permission_attack_surface_current');
    $attackSummary = db_catalog_table('v_android_permission_attack_surface_summary');
    $sampleCatalog = db_catalog_table('malware_sample_catalog');
    $familyAlignmentExpr = "
        CASE
            WHEN NULLIF(TRIM(COALESCE(m.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'unlabeled'
            WHEN NULLIF(TRIM(COALESCE(m.family_label, '')), '') IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'catalog_only'
            WHEN NULLIF(TRIM(COALESCE(m.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                THEN 'signal_only'
            WHEN LOWER(TRIM(COALESCE(m.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                THEN 'aligned'
            ELSE 'mismatch'
        END
    ";

    $attackAggSql = "
        SELECT
            sample_id,
            MAX(package_name) AS package_name,
            COUNT(DISTINCT attack_technique_id) AS attack_technique_count,
            COALESCE(SUM(mapped_permission_count), 0) AS mapped_permission_count,
            MIN(max_mapping_strength_rank) AS strongest_mapping_rank,
            GROUP_CONCAT(DISTINCT attack_technique_id ORDER BY attack_technique_id SEPARATOR ', ') AS attack_technique_ids,
            GROUP_CONCAT(DISTINCT attack_name ORDER BY attack_name SEPARATOR ', ') AS attack_names,
            GROUP_CONCAT(DISTINCT tactic ORDER BY tactic SEPARATOR ', ') AS tactics
        FROM {$attackCurrent}
        GROUP BY sample_id
    ";

    $sampleUniverseSql = "
        SELECT sample_id FROM {$vtConfidence}
        UNION
        SELECT sample_id FROM ({$attackAggSql}) attack_samples
    ";

    $summary = db_all("
        SELECT fusion_bucket, COUNT(*) AS sample_count
        FROM (
            SELECT
                s.sample_id,
                CASE
                    WHEN a.sample_id IS NOT NULL AND c.sample_id IS NULL
                        THEN 'behavior_outpaces_vt'
                    WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('review','weak','none')
                        THEN 'behavior_outpaces_vt'
                    WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                        THEN 'aligned_high_signal'
                    WHEN a.sample_id IS NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                        THEN 'vt_without_permission_behavior'
                    WHEN a.sample_id IS NOT NULL
                        THEN 'behavior_with_moderate_vt'
                    ELSE 'vt_only_context'
                END AS fusion_bucket
            FROM ({$sampleUniverseSql}) s
            LEFT JOIN {$vtConfidence} c ON c.sample_id = s.sample_id
            LEFT JOIN ({$attackAggSql}) a ON a.sample_id = s.sample_id
        ) x
        GROUP BY fusion_bucket
        ORDER BY FIELD(
            fusion_bucket,
            'behavior_outpaces_vt',
            'vt_without_permission_behavior',
            'aligned_high_signal',
            'behavior_with_moderate_vt',
            'vt_only_context'
        )
    ");

    $stmt = db()->prepare("
        SELECT
            s.sample_id,
            c.sha256,
            COALESCE(a.package_name, m.sample_label) AS package_name,
            m.sample_label,
            m.family_label,
            sig.popular_threat_name,
            sig.popular_threat_label,
            c.vt_malicious_count,
            c.vt_suspicious_count,
            c.vt_harmless_count,
            c.vt_total_engines,
            c.confidence_score,
            c.confidence_bucket,
            c.recommended_action,
            COALESCE(a.attack_technique_count, 0) AS attack_technique_count,
            COALESCE(a.mapped_permission_count, 0) AS mapped_permission_count,
            a.strongest_mapping_rank,
            a.attack_technique_ids,
            a.attack_names,
            a.tactics,
            {$familyAlignmentExpr} AS family_alignment_status,
            CASE
                WHEN a.sample_id IS NOT NULL AND c.sample_id IS NULL
                    THEN 'behavior_outpaces_vt'
                WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('review','weak','none')
                    THEN 'behavior_outpaces_vt'
                WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                    THEN 'aligned_high_signal'
                WHEN a.sample_id IS NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                    THEN 'vt_without_permission_behavior'
                WHEN a.sample_id IS NOT NULL
                    THEN 'behavior_with_moderate_vt'
                ELSE 'vt_only_context'
            END AS fusion_bucket,
            CASE
                WHEN {$familyAlignmentExpr} = 'mismatch'
                    THEN CONCAT('Catalog family ', COALESCE(NULLIF(TRIM(m.family_label), ''), '(empty)'), ' disagrees with VT signal ', COALESCE(NULLIF(TRIM(sig.popular_threat_name), ''), '(empty)'), '.')
                WHEN a.sample_id IS NOT NULL AND c.sample_id IS NULL
                    THEN 'Permission ATT&CK behavior is present but VT confidence is missing.'
                WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('review','weak','none')
                    THEN 'Permission ATT&CK behavior is present but VT confidence is weak or review-only.'
                WHEN a.sample_id IS NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                    THEN 'VT confidence is strong but no mapped permission ATT&CK behavior is present.'
                WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                    THEN 'VT confidence and permission ATT&CK behavior both point to high-risk evidence.'
                WHEN a.sample_id IS NOT NULL
                    THEN 'Permission ATT&CK behavior exists with moderate or non-high VT support.'
                ELSE 'VT confidence exists without permission ATT&CK context.'
            END AS fusion_reason
        FROM ({$sampleUniverseSql}) s
        LEFT JOIN {$vtConfidence} c ON c.sample_id = s.sample_id
        LEFT JOIN ({$attackAggSql}) a ON a.sample_id = s.sample_id
        LEFT JOIN {$sampleCatalog} m ON m.sample_id = s.sample_id
        LEFT JOIN {$signalCurrent} sig ON sig.sample_id = s.sample_id
        ORDER BY
            FIELD(
                CASE
                    WHEN {$familyAlignmentExpr} = 'mismatch'
                        THEN 'behavior_outpaces_vt'
                    WHEN a.sample_id IS NOT NULL AND c.sample_id IS NULL
                        THEN 'behavior_outpaces_vt'
                    WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('review','weak','none')
                        THEN 'behavior_outpaces_vt'
                    WHEN a.sample_id IS NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                        THEN 'vt_without_permission_behavior'
                    WHEN a.sample_id IS NOT NULL AND LOWER(COALESCE(c.confidence_bucket, 'none')) IN ('high','strong')
                        THEN 'aligned_high_signal'
                    WHEN a.sample_id IS NOT NULL
                        THEN 'behavior_with_moderate_vt'
                    ELSE 'vt_only_context'
                END,
                'behavior_outpaces_vt',
                'vt_without_permission_behavior',
                'aligned_high_signal',
                'behavior_with_moderate_vt',
                'vt_only_context'
            ),
            COALESCE(a.attack_technique_count, 0) DESC,
            CASE WHEN c.sample_id IS NULL THEN 0 ELSE 1 END ASC,
            c.confidence_score ASC,
            c.vt_malicious_count DESC,
            s.sample_id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $attackRows = db_all("
        SELECT
            attack_technique_id,
            attack_name,
            tactic,
            sample_count,
            mapped_permission_observations
        FROM {$attackSummary}
        ORDER BY sample_count DESC, mapped_permission_observations DESC, attack_technique_id ASC
        LIMIT 25
    ");

    return [
        'data' => [
            'summary' => $summary,
            'fusion_rows' => $stmt->fetchAll(),
            'attack_surface_summary' => $attackRows,
        ],
        'meta' => [
            'schema_available' => true,
            'primary_database' => db_primary_catalog_name(),
            'permission_intel_database' => db_permission_intel_catalog_name(),
            'permission_intel_split' => db_permission_intel_split_enabled(),
            'schema_surface' => 'analysis_fusion_v1',
            'limit' => $limit,
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        ],
    ];
}
