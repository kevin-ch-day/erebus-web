<?php
declare(strict_types=1);

function db_family_taxonomy_gap_surface_available(string $surfaceName): bool
{
    static $available = null;
    if (!is_array($available)) {
        $available = [];
        foreach (db_schema_inventory()['surfaces'] ?? [] as $surface) {
            $name = strtolower(trim((string)($surface['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $available[$name] = (bool)($surface['present'] ?? false);
        }
    }

    return (bool)($available[strtolower(trim($surfaceName))] ?? false);
}

function db_family_taxonomy_catalog_only_authority_summary(string $platform = 'android'): array
{
    if (!db_family_taxonomy_gap_surface_available('v_android_sample_family_type_authority')) {
        return [
            'total_rows' => 0,
            'authority_family_typed_rows' => 0,
            'resolved_unknown_rows' => 0,
            'generic_label_candidate_rows' => 0,
            'residual_review_rows' => 0,
            'missing_signal_row_rows' => 0,
            'coarse_vt_only_rows' => 0,
            'empty_signal_surface_rows' => 0,
            'source_batch_backed_rows' => 0,
            'authority_coverage_pct' => 0.0,
        ];
    }

    $sql = "
        SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN v.authority_bucket = 'authority_family_typed' THEN 1 ELSE 0 END) AS authority_family_typed_rows,
            SUM(CASE WHEN v.authority_bucket = 'resolved_unknown' THEN 1 ELSE 0 END) AS resolved_unknown_rows,
            SUM(CASE WHEN v.authority_bucket = 'generic_label_candidate' THEN 1 ELSE 0 END) AS generic_label_candidate_rows,
            SUM(CASE WHEN sig.sample_id IS NULL THEN 1 ELSE 0 END) AS missing_signal_row_rows,
            SUM(CASE
                WHEN sig.sample_id IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                 AND (
                    NULLIF(TRIM(COALESCE(sig.popular_threat_label, '')), '') IS NOT NULL
                    OR NULLIF(TRIM(COALESCE(sig.popular_threat_category, '')), '') IS NOT NULL
                    OR NULLIF(TRIM(COALESCE(c.vt_suggested_label, '')), '') IS NOT NULL
                 )
                THEN 1 ELSE 0 END) AS coarse_vt_only_rows,
            SUM(CASE
                WHEN sig.sample_id IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_category, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(c.vt_suggested_label, '')), '') IS NULL
                THEN 1 ELSE 0 END) AS empty_signal_surface_rows,
            SUM(CASE WHEN NULLIF(TRIM(COALESCE(c.source_batch_label, '')), '') IS NOT NULL THEN 1 ELSE 0 END) AS source_batch_backed_rows
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        LEFT JOIN " . db_catalog_table('v_android_sample_family_type_authority') . " v ON v.sample_id = c.sample_id
        WHERE c.platform = :platform
          AND NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
    ";
    $row = db_one($sql, ['platform' => $platform]) ?? [];

    $totalRows = (int)($row['total_rows'] ?? 0);
    $typedRows = (int)($row['authority_family_typed_rows'] ?? 0);
    $resolvedUnknownRows = (int)($row['resolved_unknown_rows'] ?? 0);
    $genericCandidateRows = (int)($row['generic_label_candidate_rows'] ?? 0);
    $missingSignalRowRows = (int)($row['missing_signal_row_rows'] ?? 0);
    $coarseVtOnlyRows = (int)($row['coarse_vt_only_rows'] ?? 0);
    $emptySignalSurfaceRows = (int)($row['empty_signal_surface_rows'] ?? 0);
    $sourceBatchBackedRows = (int)($row['source_batch_backed_rows'] ?? 0);
    $residualReviewRows = max(0, $totalRows - $typedRows - $resolvedUnknownRows);

    return [
        'total_rows' => $totalRows,
        'authority_family_typed_rows' => $typedRows,
        'resolved_unknown_rows' => $resolvedUnknownRows,
        'generic_label_candidate_rows' => $genericCandidateRows,
        'residual_review_rows' => $residualReviewRows,
        'missing_signal_row_rows' => $missingSignalRowRows,
        'coarse_vt_only_rows' => $coarseVtOnlyRows,
        'empty_signal_surface_rows' => $emptySignalSurfaceRows,
        'source_batch_backed_rows' => $sourceBatchBackedRows,
        'authority_coverage_pct' => $totalRows > 0 ? round(($typedRows / $totalRows) * 100, 2) : 0.0,
    ];
}

function db_family_taxonomy_catalog_only_anchor_families(string $platform = 'android', int $limit = 10): array
{
    if (!db_family_taxonomy_gap_surface_available('v_android_sample_family_type_authority')) {
        return [];
    }

    $limit = max(1, min($limit, 50));
    $sql = "
        SELECT
            c.family_label AS catalog_family,
            COUNT(*) AS row_count,
            MAX(NULLIF(TRIM(v.family_name), '')) AS governed_family_name,
            MAX(NULLIF(TRIM(v.family_slug), '')) AS governed_family_slug,
            MAX(NULLIF(TRIM(v.type_slug), '')) AS governed_type_slug,
            MAX(NULLIF(TRIM(v.authority_bucket), '')) AS authority_bucket,
            SUM(CASE WHEN v.authority_bucket = 'authority_family_typed' THEN 1 ELSE 0 END) AS typed_rows,
            SUM(CASE WHEN v.authority_bucket = 'resolved_unknown' THEN 1 ELSE 0 END) AS resolved_unknown_rows,
            SUM(CASE WHEN sig.sample_id IS NULL THEN 1 ELSE 0 END) AS missing_signal_row_rows,
            SUM(CASE
                WHEN sig.sample_id IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                 AND (
                    NULLIF(TRIM(COALESCE(sig.popular_threat_label, '')), '') IS NOT NULL
                    OR NULLIF(TRIM(COALESCE(sig.popular_threat_category, '')), '') IS NOT NULL
                    OR NULLIF(TRIM(COALESCE(c.vt_suggested_label, '')), '') IS NOT NULL
                 )
                THEN 1 ELSE 0 END) AS coarse_vt_only_rows,
            SUM(CASE
                WHEN sig.sample_id IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_category, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(c.vt_suggested_label, '')), '') IS NULL
                THEN 1 ELSE 0 END) AS empty_signal_surface_rows,
            SUM(CASE WHEN NULLIF(TRIM(COALESCE(c.source_batch_label, '')), '') IS NOT NULL THEN 1 ELSE 0 END) AS source_batch_backed_rows
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        LEFT JOIN " . db_catalog_table('v_android_sample_family_type_authority') . " v ON v.sample_id = c.sample_id
        WHERE c.platform = :platform
          AND NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
        GROUP BY c.family_label
        ORDER BY row_count DESC, c.family_label ASC
        LIMIT {$limit}
    ";

    $rows = db_all($sql, ['platform' => $platform]);
    foreach ($rows as &$row) {
        $rowCount = (int)($row['row_count'] ?? 0);
        $typedRows = (int)($row['typed_rows'] ?? 0);
        $resolvedUnknownRows = (int)($row['resolved_unknown_rows'] ?? 0);
        $row['typed_rows'] = $typedRows;
        $row['resolved_unknown_rows'] = $resolvedUnknownRows;
        $missingSignalRows = (int)($row['missing_signal_row_rows'] ?? 0);
        $coarseVtOnlyRows = (int)($row['coarse_vt_only_rows'] ?? 0);
        $emptySignalSurfaceRows = (int)($row['empty_signal_surface_rows'] ?? 0);
        $sourceBatchBackedRows = (int)($row['source_batch_backed_rows'] ?? 0);
        $row['missing_signal_row_rows'] = $missingSignalRows;
        $row['coarse_vt_only_rows'] = $coarseVtOnlyRows;
        $row['empty_signal_surface_rows'] = $emptySignalSurfaceRows;
        $row['source_batch_backed_rows'] = $sourceBatchBackedRows;
        $row['residual_rows'] = max(0, $rowCount - $typedRows - $resolvedUnknownRows);
        $row['governance_status'] = $typedRows === $rowCount
            ? 'governed_family_typed'
            : ($resolvedUnknownRows === $rowCount ? 'resolved_unknown' : 'mixed_review');
        if ($coarseVtOnlyRows === $rowCount) {
            $row['signal_gap_status'] = 'coarse_vt_only';
        } elseif ($emptySignalSurfaceRows === $rowCount) {
            $row['signal_gap_status'] = 'empty_signal_surface';
        } elseif ($missingSignalRows === $rowCount) {
            $row['signal_gap_status'] = 'missing_signal_row';
        } else {
            $row['signal_gap_status'] = 'mixed_gap_surface';
        }
    }
    unset($row);

    return $rows;
}
