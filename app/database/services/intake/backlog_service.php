<?php
declare(strict_types=1);

function db_ingest_backlog_totals(?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');
    $params = [];
    $filterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'artifact_source', 'workload_lane', $params);
    $row = db_one(
        "
        SELECT
            COUNT(DISTINCT NULLIF(TRIM(workload_lane), '')) AS lane_count,
            COUNT(DISTINCT ingest_batch_id) AS batch_rows,
            COUNT(*) AS queue_rows,
            SUM(CASE WHEN queue_status = 'PENDING' THEN 1 ELSE 0 END) AS pending_rows,
            SUM(CASE WHEN queue_status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing_rows,
            SUM(CASE WHEN queue_status = 'DONE' THEN 1 ELSE 0 END) AS done_rows,
            SUM(CASE WHEN queue_status = 'FAILED' THEN 1 ELSE 0 END) AS failed_rows,
            SUM(
                CASE
                    WHEN COALESCE(artifact_name, '') = ''
                     AND COALESCE(artifact_family, '') = ''
                     AND COALESCE(artifact_category, '') = ''
                     AND COALESCE(artifact_subtype, '') = ''
                    THEN 1 ELSE 0
                END
            ) AS low_context_rows,
            MIN(CASE WHEN queue_status = 'PENDING' THEN record_created_at_utc ELSE NULL END) AS oldest_pending_at_utc,
            MAX(CASE WHEN queue_status = 'PENDING' THEN record_created_at_utc ELSE NULL END) AS newest_pending_at_utc
        FROM {$queueTable}
        WHERE 1=1 {$filterSql}
        ",
        $params
    ) ?? [];

    return [
        'lane_count' => (int)($row['lane_count'] ?? 0),
        'batch_rows' => (int)($row['batch_rows'] ?? 0),
        'queue_rows' => (int)($row['queue_rows'] ?? 0),
        'pending_rows' => (int)($row['pending_rows'] ?? 0),
        'processing_rows' => (int)($row['processing_rows'] ?? 0),
        'done_rows' => (int)($row['done_rows'] ?? 0),
        'failed_rows' => (int)($row['failed_rows'] ?? 0),
        'low_context_rows' => (int)($row['low_context_rows'] ?? 0),
        'oldest_pending_at_utc' => (string)($row['oldest_pending_at_utc'] ?? ''),
        'newest_pending_at_utc' => (string)($row['newest_pending_at_utc'] ?? ''),
    ];
}

function db_ingest_operator_snapshot(?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $catalogTable = db_catalog_table('malware_sample_catalog');
    $stateTable = db_catalog_table('virustotal_sample_state');
    $piCurrentTable = db_catalog_table('android_permission_enrich_vt_current');
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');

    $catalogRows = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$catalogTable}");
    $stateRows = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$stateTable}");
    $vtTerminalRows = db_ingest_count_sql(
        "
        SELECT COUNT(*) AS cnt
        FROM {$stateTable}
        WHERE vt_status_code IN ('LOOKED_UP', 'NO_DATA', 'QUARANTINED', 'DISABLED')
        "
    );
    $piCurrentRows = db_ingest_table_exists('android_permission_enrich_vt_current')
        ? db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$piCurrentTable}")
        : null;
    $eligibleNow = db_ingest_count_sql(
        "
        SELECT COUNT(*) AS cnt
        FROM {$stateTable}
        WHERE claim_token IS NULL
          AND (
              vt_status_code IN ('NEW', 'REANALYZE')
              OR (
                  vt_status_code IN ('ERROR', 'RETRY_WAIT')
                  AND (next_eligible_at_utc IS NULL OR next_eligible_at_utc <= utc_timestamp())
              )
          )
        "
    );

    $queueAutoIncrement = db_ingest_column_is_auto_increment('malware_artifact_ingest_queue', 'ingest_id');
    $registryMd5Indexed = db_ingest_has_index('malware_artifact_hash_registry', 'md5');
    $registrySha1Indexed = db_ingest_has_index('malware_artifact_hash_registry', 'sha1');

    $compositionParams = [];
    $compositionFilterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'artifact_source', 'workload_lane', $compositionParams);
    $composition = db_one(
        "
        SELECT
            COUNT(*) AS pending_total,
            SUM(CASE WHEN COALESCE(artifact_name, '') <> '' THEN 1 ELSE 0 END) AS artifact_name_rows,
            SUM(CASE WHEN COALESCE(artifact_family, '') <> '' THEN 1 ELSE 0 END) AS family_hint_rows,
            SUM(CASE WHEN COALESCE(artifact_category, '') <> '' THEN 1 ELSE 0 END) AS category_hint_rows,
            SUM(CASE WHEN COALESCE(artifact_subtype, '') <> '' THEN 1 ELSE 0 END) AS subtype_hint_rows,
            SUM(
                CASE
                    WHEN COALESCE(artifact_source, '') <> ''
                     AND COALESCE(artifact_name, '') = ''
                     AND COALESCE(artifact_family, '') = ''
                     AND COALESCE(artifact_category, '') = ''
                     AND COALESCE(artifact_subtype, '') = ''
                    THEN 1 ELSE 0
                END
            ) AS batch_only_rows,
            SUM(
                CASE
                    WHEN COALESCE(artifact_name, '') = ''
                     AND COALESCE(artifact_family, '') = ''
                     AND COALESCE(artifact_category, '') = ''
                     AND COALESCE(artifact_subtype, '') = ''
                    THEN 1 ELSE 0
                END
            ) AS low_context_rows,
            SUM(
                CASE
                    WHEN LOWER(COALESCE(artifact_subtype, '')) IN ('apk', 'android', 'android_apk')
                      OR LOWER(COALESCE(artifact_name, '')) LIKE '%.apk'
                      OR LOWER(COALESCE(artifact_source, '')) LIKE '%.apk%'
                    THEN 1 ELSE 0
                END
            ) AS android_apk_hint_rows,
            COUNT(DISTINCT NULLIF(TRIM(artifact_source), '')) AS distinct_source_values,
            COUNT(DISTINCT NULLIF(TRIM(workload_lane), '')) AS distinct_workload_lanes
        FROM {$queueTable}
        WHERE queue_status = 'PENDING' {$compositionFilterSql}
        ",
        $compositionParams
    ) ?? [];

    $topSourceParams = [];
    $topSourceFilterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'TRIM(artifact_source)', 'TRIM(workload_lane)', $topSourceParams);
    $topSource = db_one(
        "
        SELECT TRIM(artifact_source) AS source_value, COUNT(*) AS cnt
        FROM {$queueTable}
        WHERE queue_status = 'PENDING'
          AND COALESCE(TRIM(artifact_source), '') <> ''
          {$topSourceFilterSql}
        GROUP BY TRIM(artifact_source)
        ORDER BY cnt DESC, source_value ASC
        LIMIT 1
        ",
        $topSourceParams
    ) ?? [];

    $pendingSources = db_ingest_backlog_pending_sources(50, $laneFilter !== '' ? $laneFilter : null);
    $androidPendingRows = 0;
    $genericPendingRows = 0;
    $otherPendingRows = 0;
    $androidSourceCount = 0;
    $genericSourceCount = 0;
    $otherSourceCount = 0;
    $topAndroidSourceValue = '';
    $topAndroidSourceCount = 0;
    $topGenericSourceValue = '';
    $topGenericSourceCount = 0;

    foreach ($pendingSources as $sourceRow) {
        $sourceValue = trim((string)($sourceRow['artifact_source'] ?? ''));
        $pendingRowsForSource = (int)($sourceRow['pending_rows'] ?? 0);
        if ($pendingRowsForSource <= 0) {
            continue;
        }

        if (db_ingest_is_android_ingest_source($sourceValue)) {
            $androidPendingRows += $pendingRowsForSource;
            $androidSourceCount++;
            if ($pendingRowsForSource > $topAndroidSourceCount) {
                $topAndroidSourceValue = $sourceValue;
                $topAndroidSourceCount = $pendingRowsForSource;
            }
            continue;
        }

        if (db_ingest_is_generic_reservoir_source($sourceValue)) {
            $genericPendingRows += $pendingRowsForSource;
            $genericSourceCount++;
            if ($pendingRowsForSource > $topGenericSourceCount) {
                $topGenericSourceValue = $sourceValue;
                $topGenericSourceCount = $pendingRowsForSource;
            }
            continue;
        }

        $otherPendingRows += $pendingRowsForSource;
        $otherSourceCount++;
    }

    $topLaneParams = [];
    $topLaneFilterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'TRIM(artifact_source)', 'TRIM(workload_lane)', $topLaneParams);
    $topLane = db_one(
        "
        SELECT TRIM(workload_lane) AS lane_value, COUNT(*) AS cnt
        FROM {$queueTable}
        WHERE queue_status = 'PENDING'
          AND COALESCE(TRIM(workload_lane), '') <> ''
          {$topLaneFilterSql}
        GROUP BY TRIM(workload_lane)
        ORDER BY cnt DESC, lane_value ASC
        LIMIT 1
        ",
        $topLaneParams
    ) ?? [];

    $statusParams = [];
    $statusFilterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'artifact_source', 'workload_lane', $statusParams);
    $pending = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'PENDING' {$statusFilterSql}", $statusParams) ?? 0;
    $processing = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'PROCESSING' {$statusFilterSql}", $statusParams) ?? 0;
    $failed = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'FAILED' {$statusFilterSql}", $statusParams) ?? 0;
    $done = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'DONE' {$statusFilterSql}", $statusParams) ?? 0;
    $total = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE 1=1 {$statusFilterSql}", $statusParams) ?? 0;

    $pendingTotal = (int)($composition['pending_total'] ?? 0);
    $topSourceValue = trim((string)($topSource['source_value'] ?? ''));
    $topSourceCount = (int)($topSource['cnt'] ?? 0);
    $topLaneValue = trim((string)($topLane['lane_value'] ?? ''));
    $distinctSources = (int)($composition['distinct_source_values'] ?? 0);
    $lowContextRows = (int)($composition['low_context_rows'] ?? 0);
    $batchOnlyRows = (int)($composition['batch_only_rows'] ?? 0);
    $androidApkHintRows = (int)($composition['android_apk_hint_rows'] ?? 0);
    $artifactNameRows = (int)($composition['artifact_name_rows'] ?? 0);
    $familyHintRows = (int)($composition['family_hint_rows'] ?? 0);
    $categoryHintRows = (int)($composition['category_hint_rows'] ?? 0);
    $subtypeHintRows = (int)($composition['subtype_hint_rows'] ?? 0);

    $isGenericDiscoveryBatch = $pendingTotal > 0
        && ($topLaneValue === 'raw_hash_reservoir' || str_starts_with(strtolower($topSourceValue), 'raw_hash_reservoir_'))
        && $lowContextRows > 0
        && $batchOnlyRows === $lowContextRows
        && $androidApkHintRows === 0
        && $artifactNameRows === 0
        && $familyHintRows === 0
        && $categoryHintRows === 0
        && $subtypeHintRows === 0;

    $intakeFocus = null;
    if ($pendingTotal > 0) {
        if ($androidPendingRows > 0 && $topAndroidSourceValue !== '') {
            $intakeFocus = 'Android feed focus: ' . db_ingest_friendly_source_label($topAndroidSourceValue) . ' (' . number_format($topAndroidSourceCount) . ')';
        } elseif ($isGenericDiscoveryBatch && $distinctSources === 1 && $topSourceCount === $pendingTotal) {
            $intakeFocus = db_ingest_friendly_source_label($topSourceValue) . ' | ' . db_ingest_friendly_cohort_kind($topLaneValue);
        } elseif ($topSourceValue !== '' && $topSourceCount > 0) {
            $intakeFocus = db_ingest_friendly_source_label($topSourceValue) . ' (' . number_format($topSourceCount) . ')';
        }
    }

    $recommendedPath = null;
    $recommendedPathDetail = null;
    if ($pending > 0 && (($eligibleNow ?? 0) === 0)) {
        $recommendedPath = 'Process the queue into VirusTotal and state updates.';
        if ($androidPendingRows > 0) {
            $recommendedPathDetail = 'Use source-filtered Android batches first; the generic reservoir should stay on bounded waves.';
        } else {
            $recommendedPathDetail = 'The VirusTotal eligibility view will stay at zero until queued backlog rows are processed.';
        }
    }

    return [
        'queue_label' => db_ingest_queue_status_label($pending, $processing, $failed, $done, $total),
        'catalog_rows' => $catalogRows,
        'state_rows' => $stateRows,
        'vt_terminal_rows' => $vtTerminalRows,
        'pi_current_rows' => $piCurrentRows,
        'state_eligible_now' => $eligibleNow,
        'import_readiness' => db_ingest_import_readiness_label($queueAutoIncrement),
        'scale_warning' => db_ingest_scale_warning_label($registryMd5Indexed, $registrySha1Indexed),
        'intake_focus' => $intakeFocus,
        'android_pending_rows' => $androidPendingRows,
        'android_source_count' => $androidSourceCount,
        'top_android_source_value' => $topAndroidSourceValue !== '' ? $topAndroidSourceValue : null,
        'top_android_source_rows' => $topAndroidSourceCount,
        'generic_reservoir_pending_rows' => $genericPendingRows,
        'generic_reservoir_source_count' => $genericSourceCount,
        'top_generic_reservoir_value' => $topGenericSourceValue !== '' ? $topGenericSourceValue : null,
        'top_generic_reservoir_rows' => $topGenericSourceCount,
        'other_source_pending_rows' => $otherPendingRows,
        'other_source_count' => $otherSourceCount,
        'recovery_suggested' => $processing > 0 ? 'Review queue cleanup and reclaim leftover PROCESSING rows.' : null,
        'recommended_path' => $recommendedPath,
        'recommended_path_detail' => $recommendedPathDetail,
        'workflow_line' => 'Load intake, review queue status, inspect pending rows, then process the queue into VirusTotal and state.',
        'top_source_value' => $topSourceValue !== '' ? $topSourceValue : null,
        'top_workload_lane' => $topLaneValue !== '' ? $topLaneValue : null,
    ];
}

function db_ingest_backlog_lane_summary(?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $params = [];
    $filterSql = db_ingest_filter_sql(null, $laneFilter, 'batch_label', 'workload_lane', $params);
    return db_all(
        "
        SELECT
            workload_lane,
            display_name,
            operator_surface,
            intended_platform_default,
            queue_policy_default,
            context_level_expectation,
            is_android_focused,
            is_generic_backlog,
            keep_separate_from_lamda,
            batch_rows,
            queue_rows,
            pending_rows,
            processing_rows,
            done_rows,
            failed_rows,
            low_context_rows,
            android_apk_hint_rows,
            windows_pe_hint_rows,
            oldest_pending_at_utc,
            newest_pending_at_utc
        FROM vw_malware_artifact_ingest_lane_summary
        WHERE is_active = 1 {$filterSql}
        ORDER BY pending_rows DESC, queue_rows DESC, display_name ASC
        ",
        $params
    );
}

function db_ingest_backlog_batch_summary(int $limit = 20, ?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $limit = clamp_int($limit, 1, 100, 20);
    $params = [];
    $filterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'batch_label', 'workload_lane', $params);

    return db_all(
        "
        SELECT
            ingest_batch_id,
            batch_label,
            workload_lane,
            source_system,
            source_kind,
            intended_platform,
            context_level,
            queue_policy,
            queue_rows,
            pending_rows,
            processing_rows,
            done_rows,
            failed_rows,
            low_context_rows,
            android_apk_hint_rows,
            windows_pe_hint_rows,
            oldest_pending_at_utc,
            newest_pending_at_utc,
            first_seen_in_queue_at_utc,
            last_seen_in_queue_at_utc
        FROM vw_malware_artifact_ingest_batch_summary
        WHERE queue_rows > 0 {$filterSql}
        ORDER BY pending_rows DESC, queue_rows DESC, ingest_batch_id DESC
        LIMIT {$limit}
        ",
        $params
    );
}

function db_ingest_backlog_pending_sources(int $limit = 12, ?string $laneFilter = null): array
{
    $limit = clamp_int($limit, 1, 50, 12);
    $params = [];
    $filterSql = '';
    if ($laneFilter !== null && $laneFilter !== '') {
        $filterSql = ' AND COALESCE(workload_lane, \'\') = :lane_filter';
        $params['lane_filter'] = $laneFilter;
    }

    return db_all(
        "
        SELECT
            COALESCE(NULLIF(artifact_source, ''), '<blank>') AS artifact_source,
            COUNT(*) AS pending_rows
        FROM malware_artifact_ingest_queue
        WHERE queue_status = 'PENDING'
        {$filterSql}
        GROUP BY COALESCE(NULLIF(artifact_source, ''), '<blank>')
        ORDER BY pending_rows DESC, artifact_source ASC
        LIMIT {$limit}
        ",
        $params
    );
}

function db_ingest_cleanup_pressure(?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');
    $stateTable = db_catalog_table('virustotal_sample_state');
    $params = [];
    $filterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'artifact_source', 'workload_lane', $params);
    $qualifiedFilterSql = $filterSql !== ''
        ? str_replace(['artifact_source', 'workload_lane'], ['q.artifact_source', 'q.workload_lane'], $filterSql)
        : '';

    $doneRows = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'DONE' {$filterSql}", $params) ?? 0;
    $failedRows = db_ingest_count_sql("SELECT COUNT(*) AS cnt FROM {$queueTable} WHERE queue_status = 'FAILED' {$filterSql}", $params) ?? 0;
    $pendingTerminalRows = db_ingest_count_sql(
        "
        SELECT COUNT(*) AS cnt
        FROM {$queueTable} q
        JOIN {$stateTable} s
          ON s.sha256 = q.artifact_hash_sha256
        WHERE q.queue_status = 'PENDING'
          AND q.artifact_hash_type = 'sha256'
          AND s.vt_status_code IN ('LOOKED_UP', 'NO_DATA')
          {$qualifiedFilterSql}
        ",
        $params
    );
    $staleProcessingRows = db_ingest_count_sql(
        "
        SELECT COUNT(*) AS cnt
        FROM {$queueTable}
        WHERE queue_status = 'PROCESSING'
          {$filterSql}
          AND (
              lease_until_utc IS NULL
              OR lease_until_utc < utc_timestamp()
              OR claimed_at_utc IS NULL
              OR claimed_at_utc < utc_timestamp() - INTERVAL 60 MINUTE
          )
        ",
        $params
    );

    return [
        'done_rows' => $doneRows,
        'pending_terminal_rows' => $pendingTerminalRows,
        'stale_processing_rows' => $staleProcessingRows,
        'failed_rows' => $failedRows,
        'recommended_steps' => array_values(array_filter([
            ($staleProcessingRows ?? 0) > 0 ? 'Reclaim stale PROCESSING rows first so active work and abandoned claims are clearly separated.' : null,
            $doneRows > 0 ? 'Purge completed queue rows when you want to keep the queue compact after confirming the batch outcome.' : null,
            ($pendingTerminalRows ?? 0) > 0 ? 'Mark pending already-terminal rows as DONE before purge or broader cleanup.' : null,
            $failedRows > 0 ? 'Review FAILED rows as recovery candidates before broad queue-clearing actions.' : null,
            ($doneRows === 0 && ($pendingTerminalRows ?? 0) === 0 && ($staleProcessingRows ?? 0) === 0 && $failedRows === 0)
                ? 'No cleanup pressure is visible right now. Use queue summary or pending preview for routine monitoring.'
                : null,
        ])),
    ];
}

function db_ingest_backlog_preview_rows(int $limit = 10, ?string $sourceFilter = null, ?string $laneFilter = null): array
{
    $limit = clamp_int($limit, 1, 50, 10);
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');
    $params = [];
    $filterSql = db_ingest_filter_sql($sourceFilter, $laneFilter, 'artifact_source', 'workload_lane', $params);

    return db_all(
        "
        SELECT
            ingest_id,
            artifact_hash_type,
            COALESCE(NULLIF(artifact_hash_sha256, ''), NULLIF(artifact_hash_sha1, ''), NULLIF(artifact_hash_md5, ''), NULLIF(artifact_hash_norm, ''), NULLIF(artifact_hash_raw, '')) AS artifact_hash_value,
            COALESCE(NULLIF(artifact_source, ''), '<blank>') AS artifact_source,
            workload_lane,
            record_created_at_utc
        FROM {$queueTable}
        WHERE queue_status = 'PENDING'
        {$filterSql}
        ORDER BY ingest_id DESC
        LIMIT {$limit}
        ",
        $params
    );
}
