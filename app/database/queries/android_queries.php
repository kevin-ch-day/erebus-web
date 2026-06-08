<?php
// app/database/queries/android_queries.php
declare(strict_types=1);

function sql_android_exact_permission_match_expr_for(string $left, string $right): string
{
    return "BINARY {$left} = BINARY {$right}";
}

function sql_android_normalized_permission_match_expr_for(string $left, string $right): string
{
    return "LOWER(TRIM({$left})) = LOWER(TRIM({$right}))";
}

function sql_android_permissions_summary(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT
            sample_id,
            bucket,
            rule_fired,
            COUNT(*) AS perm_count
        FROM {$obsSample}
        WHERE sample_id = :sample_id
        GROUP BY sample_id, bucket, rule_fired
        ORDER BY perm_count DESC
    ";
}

function sql_android_permissions_detail(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT
            permission_string,
            classification,
            bucket,
            rule_fired,
            observed_at_utc AS observed_at
        FROM {$obsSample}
        WHERE sample_id = :sample_id
        ORDER BY observed_at_utc DESC, permission_string ASC
    ";
}

function sql_android_bucket_key_expr(): string
{
    return "COALESCE(NULLIF(TRIM(bucket),''),'UNKNOWN')";
}

function sql_android_unknown_condition(string $bucketExpr): string
{
    return "UPPER({$bucketExpr}) IN ('UNKNOWN','UNCLASSIFIED')";
}

function sql_android_permission_namespace_expr(): string
{
    return sql_android_permission_namespace_expr_for('permission_string');
}

function sql_android_permission_namespace_expr_for(string $col): string
{
    return "CASE
        WHEN {$col} LIKE 'android.permission.%' THEN 'android.permission'
        WHEN {$col} LIKE 'android.%' THEN 'android'
        WHEN {$col} LIKE '%.%' THEN SUBSTRING_INDEX({$col}, '.', 2)
        ELSE {$col}
    END";
}

function sql_android_permission_namespace_drift_expr(): string
{
    return sql_android_permission_namespace_drift_expr_for('permission_string');
}

function sql_android_permission_namespace_drift_expr_for(string $col): string
{
    return "CASE
        WHEN {$col} LIKE 'android.permission.%' THEN 'android.permission'
        WHEN {$col} LIKE '%.%' THEN SUBSTRING_INDEX({$col}, '.', 3)
        ELSE {$col}
    END";
}

function sql_android_permission_health_totals(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE
                    WHEN classification IS NULL OR UPPER(classification) = 'UNKNOWN' THEN 1
                    ELSE 0
                END) AS unknown_count,
            SUM(CASE
                    WHEN classification IS NOT NULL AND UPPER(classification) <> 'UNKNOWN' THEN 1
                    ELSE 0
                END) AS known_count,
            MAX(observed_at_utc) AS last_observed_at_utc
        FROM {$obsSample}
    ";
}

function sql_android_permission_trend(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT
            SUM(CASE WHEN observed_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS total_7d,
            SUM(CASE
                    WHEN observed_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                     AND (classification IS NULL OR UPPER(classification) = 'UNKNOWN')
                    THEN 1 ELSE 0
                END) AS unknown_7d,
            SUM(CASE WHEN observed_at_utc < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                      AND observed_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS total_prev_7d,
            SUM(CASE WHEN observed_at_utc < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                      AND observed_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
                      AND (classification IS NULL OR UPPER(classification) = 'UNKNOWN')
                      THEN 1 ELSE 0 END) AS unknown_prev_7d
        FROM {$obsSample}
    ";
}

function sql_android_permission_bucket_distribution(): string
{
    $bucketExpr = sql_android_bucket_key_expr();
    $obsSample = db_catalog_table('android_permission_obs_sample');

    return "
        SELECT
            {$bucketExpr} AS bucket_key,
            COUNT(*) AS perm_count
        FROM {$obsSample}
        GROUP BY bucket_key
        ORDER BY perm_count DESC
    ";
}

function sql_android_permission_catalog_base(): string
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('permission_string');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT
            permission_string,
            {$namespaceExpr} AS namespace,
            classification,
            bucket,
            COUNT(DISTINCT sample_id) AS seen_count,
            MIN(observed_at_utc) AS first_seen_at_utc,
            MAX(observed_at_utc) AS last_seen_at_utc
        FROM {$obsSample}
    ";
}

function sql_android_permission_catalog_count_base(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        SELECT COUNT(DISTINCT permission_string) AS total_count
        FROM {$obsSample}
    ";
}

function sql_android_permission_namespace_registry_base(): string
{
    $namespaceExpr = sql_android_permission_namespace_drift_expr_for('permission_string');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');

    return "
        SELECT
            {$namespaceExpr} AS namespace,
            COUNT(*) AS seen_count,
            COUNT(DISTINCT permission_string) AS permission_count,
            MIN(ingested_at_utc) AS first_seen_at_utc,
            MAX(ingested_at_utc) AS last_seen_at_utc
        FROM {$vtEvent}
    ";
}

function sql_android_permission_review(): string
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    $latestQueueNormalized = sql_android_permission_latest_queue_normalized_subquery();
    $exactMainMatch1 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_1');
    $exactMainMatch2 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_2');
    $exactMainMatch3 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_3');
    $exactMainMatch4 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_4');
    $exactMainMatch5 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_5');
    $exactMainMatch6 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_6');
    $exactMainMatch7 = sql_android_exact_permission_match_expr_for('u.permission_string', ':permission_main_exact_7');
    $normalizedMainMatch1 = sql_android_normalized_permission_match_expr_for('u.permission_string', ':permission_main_normalized_1');
    $normalizedMainMatch2 = sql_android_normalized_permission_match_expr_for('u.permission_string', ':permission_main_normalized_2');
    $normalizedMainMatch3 = sql_android_normalized_permission_match_expr_for('u.permission_string', ':permission_main_normalized_3');
    $normalizedMainMatch4 = sql_android_normalized_permission_match_expr_for('u.permission_string', ':permission_main_normalized_4');
    $normalizedMainMatch5 = sql_android_normalized_permission_match_expr_for('u.permission_string', ':permission_main_normalized_5');
    return "
        SELECT
            u.permission_string,
            {$namespaceExpr} AS namespace,
            u.triage_status,
            u.notes,
            CASE WHEN {$exactMainMatch1} THEN 1 ELSE 0 END AS exact_case_match,
            CASE WHEN {$normalizedMainMatch1} THEN 1 ELSE 0 END AS normalized_case_match,
            CASE
                WHEN {$exactMainMatch2} THEN 'exact'
                WHEN {$normalizedMainMatch2} THEN 'normalized_only'
                ELSE 'none'
            END AS match_semantics,
            CASE
                WHEN {$exactMainMatch3} THEN 'Exact match'
                WHEN {$normalizedMainMatch3} THEN 'Normalized-only'
                ELSE 'No match'
            END AS match_semantics_label,
            CASE
                WHEN {$normalizedMainMatch4} AND NOT {$exactMainMatch4} THEN 1 ELSE 0
            END AS normalized_only_match,
            CASE
                WHEN {$normalizedMainMatch5} AND NOT {$exactMainMatch5} THEN 'Case-form drift'
                ELSE NULL
            END AS match_warning,
            COALESCE(stats_exact.event_count, 0) AS event_count,
            COALESCE(stats_exact.event_count, 0) AS event_count_exact,
            COALESCE(stats_norm.event_count, 0) AS event_count_normalized,
            COALESCE(stats_exact.sample_count, 0) AS sample_count,
            COALESCE(stats_exact.sample_count, 0) AS sample_count_exact,
            COALESCE(stats_norm.sample_count, 0) AS sample_count_normalized,
            COALESCE(stats_exact.event_count, 0) AS seen_count,
            stats_exact.first_seen_at_utc,
            stats_exact.last_seen_at_utc,
            obs_exact.bucket AS bucket,
            obs_exact.bucket AS bucket_exact,
            obs_norm.bucket AS bucket_normalized,
            obs_exact.classification AS classification,
            obs_exact.classification AS classification_exact,
            obs_norm.classification AS classification_normalized,
            q_exact.queue_status,
            q_exact.queue_status AS queue_status_exact,
            q_norm.queue_status AS queue_status_normalized,
            q_exact.queue_action,
            q_exact.queue_action AS queue_action_exact,
            q_norm.queue_action AS queue_action_normalized,
            q_exact.queue_updated_at_utc,
            q_exact.queue_updated_at_utc AS queue_updated_at_utc_exact,
            q_norm.queue_updated_at_utc AS queue_updated_at_utc_normalized,
            q_exact.queue_processed_at_utc,
            q_exact.queue_processed_at_utc AS queue_processed_at_utc_exact,
            q_norm.queue_processed_at_utc AS queue_processed_at_utc_normalized,
            q_exact.queue_error_message,
            q_exact.queue_error_message AS queue_error_message_exact,
            q_norm.queue_error_message AS queue_error_message_normalized,
            CASE WHEN EXISTS (
                SELECT 1 FROM " . db_catalog_table('android_permission_dict_aosp') . " a
                WHERE BINARY a.constant_value = BINARY u.permission_string
                LIMIT 1
            ) THEN 1 ELSE 0 END AS already_in_aosp_exact,
            CASE WHEN EXISTS (
                SELECT 1 FROM " . db_catalog_table('android_permission_dict_aosp') . " a
                WHERE LOWER(TRIM(a.constant_value)) = LOWER(TRIM(u.permission_string))
                LIMIT 1
            ) THEN 1 ELSE 0 END AS already_in_aosp_normalized,
            CASE WHEN {$exactMainMatch6} THEN 1 ELSE 0 END AS has_ledger_anchor_exact,
            1 AS has_ledger_anchor_normalized,
            CASE
                WHEN {$exactMainMatch7} THEN 'Exact ledger anchor'
                ELSE 'Normalized ledger anchor'
            END AS ledger_anchor_label,
            CASE
                WHEN COALESCE(stats_exact.event_count, 0) > 0 OR COALESCE(stats_exact.sample_count, 0) > 0 THEN 'Exact evidence'
                WHEN COALESCE(stats_norm.event_count, 0) > 0 OR COALESCE(stats_norm.sample_count, 0) > 0
                    OR obs_norm.permission_string IS NOT NULL THEN 'Normalized evidence'
                ELSE 'No evidence'
            END AS evidence_label,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM " . db_catalog_table('android_permission_dict_aosp') . " a
                    WHERE BINARY a.constant_value = BINARY u.permission_string
                    LIMIT 1
                ) THEN 'Exact AOSP'
                WHEN EXISTS (
                    SELECT 1 FROM " . db_catalog_table('android_permission_dict_aosp') . " a
                    WHERE LOWER(TRIM(a.constant_value)) = LOWER(TRIM(u.permission_string))
                    LIMIT 1
                ) THEN 'Normalized AOSP'
                ELSE 'No AOSP match'
            END AS aosp_match_label
        FROM {$dictUnknown} u
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS event_count,
                COUNT(DISTINCT sample_id) AS sample_count,
                MIN(ingested_at_utc) AS first_seen_at_utc,
                MAX(ingested_at_utc) AS last_seen_at_utc
            FROM {$vtEvent}
            WHERE BINARY permission_string = BINARY :permission_stats_exact
            GROUP BY permission_string
        ) stats_exact ON BINARY stats_exact.permission_string = BINARY u.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS event_count,
                COUNT(DISTINCT sample_id) AS sample_count,
                MIN(ingested_at_utc) AS first_seen_at_utc,
                MAX(ingested_at_utc) AS last_seen_at_utc
            FROM {$vtEvent}
            WHERE LOWER(permission_string) = LOWER(:permission_stats_normalized)
            GROUP BY permission_string
        ) stats_norm ON LOWER(TRIM(stats_norm.permission_string)) = LOWER(TRIM(u.permission_string))
        LEFT JOIN (
            SELECT
                permission_string,
                COALESCE(NULLIF(TRIM(bucket),''),'UNKNOWN') AS bucket,
                MAX(classification) AS classification
            FROM {$obsSample}
            WHERE BINARY permission_string = BINARY :permission_obs_exact
            GROUP BY permission_string
        ) obs_exact ON BINARY obs_exact.permission_string = BINARY u.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COALESCE(NULLIF(TRIM(bucket),''),'UNKNOWN') AS bucket,
                MAX(classification) AS classification
            FROM {$obsSample}
            WHERE LOWER(permission_string) = LOWER(:permission_obs_normalized)
            GROUP BY permission_string
        ) obs_norm ON LOWER(TRIM(obs_norm.permission_string)) = LOWER(TRIM(u.permission_string))
        LEFT JOIN (
            SELECT
                q.permission_string,
                q.queue_action,
                q.status AS queue_status,
                q.updated_at_utc AS queue_updated_at_utc,
                q.processed_at_utc AS queue_processed_at_utc,
                q.error_message AS queue_error_message
            FROM {$dictQueue} q
            LEFT JOIN {$dictQueue} q2
              ON BINARY q2.permission_string = BINARY q.permission_string
             AND (
                    q2.updated_at_utc > q.updated_at_utc
                    OR (
                        q2.updated_at_utc = q.updated_at_utc
                        AND q2.queue_id > q.queue_id
                    )
                 )
            WHERE q2.queue_id IS NULL
        ) q_exact ON BINARY q_exact.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueueNormalized}
        ) q_norm ON q_norm.permission_string_normalized = LOWER(TRIM(u.permission_string))
        WHERE LOWER(u.permission_string) = LOWER(:permission_main)
        LIMIT 1
    ";
}

function sql_android_permission_unknowns(int $limit = 50): string
{
    $limit = max(1, min(200, $limit));
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $latestQueue = sql_android_permission_latest_queue_subquery();
    $latestQueueNormalized = sql_android_permission_latest_queue_normalized_subquery();

    return "
        SELECT
            u.permission_string,
            {$namespaceExpr} AS namespace,
            u.triage_status,
            u.notes,
            q.queue_status,
            q.queue_status AS queue_status_exact,
            q_norm.queue_status AS queue_status_normalized,
            q.queue_action,
            q.queue_action AS queue_action_exact,
            q_norm.queue_action AS queue_action_normalized,
            q.queue_updated_at_utc,
            q.queue_processed_at_utc,
            q.queue_error_message,
            q_norm.queue_updated_at_utc AS queue_updated_at_utc_normalized,
            q_norm.queue_processed_at_utc AS queue_processed_at_utc_normalized,
            q_norm.queue_error_message AS queue_error_message_normalized,
            COUNT(e.event_id) AS seen_count,
            COUNT(e.event_id) AS seen_count_exact,
            (
                SELECT COUNT(*)
                FROM {$vtEvent} en
                WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
            ) AS seen_count_normalized,
            MIN(e.ingested_at_utc) AS first_seen_at_utc,
            MAX(e.ingested_at_utc) AS last_seen_at_utc,
            CASE WHEN EXISTS (
                SELECT 1
                FROM {$vtEvent} en
                WHERE BINARY en.permission_string = BINARY u.permission_string
                LIMIT 1
            ) THEN 1 ELSE 0 END AS has_vt_event_exact,
            CASE WHEN EXISTS (
                SELECT 1
                FROM {$vtEvent} en
                WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
                LIMIT 1
            ) THEN 1 ELSE 0 END AS has_vt_event_normalized,
            CASE
                WHEN COUNT(e.event_id) > 0 THEN 'exact'
                WHEN EXISTS (
                    SELECT 1
                    FROM {$vtEvent} en
                    WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
                    LIMIT 1
                ) THEN 'normalized_only'
                ELSE 'none'
            END AS match_semantics,
            CASE
                WHEN COUNT(e.event_id) > 0 THEN 'Exact match'
                WHEN EXISTS (
                    SELECT 1
                    FROM {$vtEvent} en
                    WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
                    LIMIT 1
                ) THEN 'Normalized-only'
                ELSE 'No match'
            END AS match_semantics_label,
            CASE
                WHEN COUNT(e.event_id) = 0
                 AND EXISTS (
                    SELECT 1
                    FROM {$vtEvent} en
                    WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
                    LIMIT 1
                 ) THEN 'Case-form drift'
                ELSE NULL
            END AS match_warning,
            CASE
                WHEN COUNT(e.event_id) > 0 THEN 'Exact evidence'
                WHEN EXISTS (
                    SELECT 1
                    FROM {$vtEvent} en
                    WHERE LOWER(TRIM(en.permission_string)) = LOWER(TRIM(u.permission_string))
                    LIMIT 1
                ) THEN 'Normalized evidence'
                ELSE 'No evidence'
            END AS evidence_label,
            CASE
                WHEN q.permission_string IS NOT NULL THEN 'Exact ledger anchor'
                WHEN q_norm.permission_string IS NOT NULL THEN 'Normalized ledger anchor'
                ELSE 'No ledger anchor'
            END AS ledger_anchor_label
        FROM {$dictUnknown} u
        LEFT JOIN {$vtEvent} e
            ON BINARY e.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueue}
        ) q ON BINARY q.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueueNormalized}
        ) q_norm ON q_norm.permission_string_normalized = LOWER(TRIM(u.permission_string))
        GROUP BY u.permission_string, {$namespaceExpr}, u.triage_status, u.notes, q.queue_status, q.queue_action,
                 q.queue_updated_at_utc, q.queue_processed_at_utc, q.queue_error_message,
                 q_norm.queue_status, q_norm.queue_action, q_norm.queue_updated_at_utc,
                 q_norm.queue_processed_at_utc, q_norm.queue_error_message, q_norm.permission_string
        ORDER BY seen_count DESC
        LIMIT {$limit}
    ";
}

function sql_android_permission_namespace_drift(int $limit = 50): string
{
    $limit = max(1, min(200, $limit));
    $namespaceExpr = sql_android_permission_namespace_drift_expr_for('permission_string');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');

    return "
        SELECT
            {$namespaceExpr} AS namespace,
            COUNT(*) AS seen_count,
            COUNT(DISTINCT permission_string) AS permission_count,
            MIN(ingested_at_utc) AS first_seen_at_utc,
            MAX(ingested_at_utc) AS last_seen_at_utc
        FROM {$vtEvent}
        GROUP BY {$namespaceExpr}
        ORDER BY seen_count DESC
        LIMIT {$limit}
    ";
}

function sql_android_permission_namespace_drift_obs_sample(int $limit = 50): string
{
    $limit = max(1, min(200, $limit));
    $namespaceExpr = sql_android_permission_namespace_drift_expr_for('permission_string');
    $obsSample = db_catalog_table('android_permission_obs_sample');

    return "
        SELECT
            {$namespaceExpr} AS namespace,
            COUNT(*) AS seen_count,
            COUNT(DISTINCT permission_string) AS permission_count,
            MIN(observed_at_utc) AS first_seen_at_utc,
            MAX(observed_at_utc) AS last_seen_at_utc
        FROM {$obsSample}
        GROUP BY {$namespaceExpr}
        ORDER BY seen_count DESC
        LIMIT {$limit}
    ";
}

function sql_android_permission_enrich_vt_event_count(): string
{
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    return "
        SELECT COUNT(*) AS event_count
        FROM {$vtEvent}
    ";
}

function sql_android_permission_new_unknowns_24h(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $unknownCondition = sql_android_unknown_condition('classification');
    return "
        SELECT COUNT(DISTINCT permission_string) AS new_unknowns_24h
        FROM {$obsSample}
        WHERE observed_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
          AND {$unknownCondition}
          AND LOWER(TRIM(permission_string)) NOT LIKE '%.dynamic_receiver_not_exported_permission'
    ";
}

function sql_android_permission_triage_status_counts(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        SELECT triage_status, COUNT(*) AS cnt
        FROM {$dictUnknown}
        GROUP BY triage_status
    ";
}

function sql_android_permission_effective_unknown_metrics(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $dictOem = db_catalog_table('android_permission_dict_oem');
    $effectiveStatuses = array_map(
        static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
        perm_effective_unknown_triage_status_keys()
    );
    $effectiveStatusList = $effectiveStatuses !== [] ? implode(', ', $effectiveStatuses) : "''";
    return "
        SELECT
            COUNT(*) AS raw_unknown,
            SUM(
                CASE
                    WHEN u.triage_status NOT IN ({$effectiveStatusList}) THEN 0
                    WHEN u.triage_status = 'new'
                         AND LOWER(TRIM(u.permission_string)) LIKE '%.dynamic_receiver_not_exported_permission' THEN 0
                    WHEN u.triage_status = 'oem_candidate'
                         AND EXISTS (
                             SELECT 1
                             FROM {$dictOem} o
                             WHERE BINARY o.permission_string = BINARY u.permission_string
                         ) THEN 0
                    ELSE 1
                END
            ) AS effective_unknown,
            SUM(
                CASE
                    WHEN u.triage_status = 'oem_candidate'
                         AND EXISTS (
                             SELECT 1
                             FROM {$dictOem} o
                             WHERE BINARY o.permission_string = BINARY u.permission_string
                         ) THEN 1
                    ELSE 0
                END
            ) AS oem_already_resolved_not_retagged
        FROM {$dictUnknown} u
    ";
}

function sql_android_permission_latest_queue_subquery(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        SELECT
            q.permission_string,
            q.queue_action,
            q.status AS queue_status,
            q.updated_at_utc AS queue_updated_at_utc,
            q.processed_at_utc AS queue_processed_at_utc,
            q.error_message AS queue_error_message
        FROM {$dictQueue} q
        LEFT JOIN {$dictQueue} q2
          ON BINARY q2.permission_string = BINARY q.permission_string
         AND (
                q2.updated_at_utc > q.updated_at_utc
                OR (
                    q2.updated_at_utc = q.updated_at_utc
                    AND q2.queue_id > q.queue_id
                )
             )
        WHERE q2.queue_id IS NULL
    ";
}

function sql_android_permission_latest_queue_normalized_subquery(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        SELECT
            LOWER(TRIM(q.permission_string)) AS permission_string_normalized,
            q.permission_string,
            q.queue_action,
            q.status AS queue_status,
            q.updated_at_utc AS queue_updated_at_utc,
            q.processed_at_utc AS queue_processed_at_utc,
            q.error_message AS queue_error_message
        FROM {$dictQueue} q
        LEFT JOIN {$dictQueue} q2
          ON LOWER(TRIM(q2.permission_string)) = LOWER(TRIM(q.permission_string))
         AND (
                q2.updated_at_utc > q.updated_at_utc
                OR (
                    q2.updated_at_utc = q.updated_at_utc
                    AND q2.queue_id > q.queue_id
                )
             )
        WHERE q2.queue_id IS NULL
    ";
}

function sql_android_permission_current_unknown_summary(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $dictAosp = db_catalog_table('android_permission_dict_aosp');
    $dictOem = db_catalog_table('android_permission_dict_oem');
    $unknownCondition = sql_android_unknown_condition('o.classification');
    return "
        SELECT
            COUNT(*) AS current_unknown_obs_rows,
            COUNT(DISTINCT o.sample_id) AS current_unknown_samples,
            COUNT(DISTINCT o.permission_string) AS current_unknown_permissions
        FROM {$obsSample} o
        LEFT JOIN {$dictUnknown} u
          ON BINARY u.permission_string = BINARY o.permission_string
        LEFT JOIN {$dictAosp} a
          ON BINARY a.constant_value = BINARY o.permission_string
        LEFT JOIN {$dictOem} d
          ON BINARY d.permission_string = BINARY o.permission_string
        WHERE {$unknownCondition}
          AND NOT (
              COALESCE(u.triage_status, '') IN ('launcher_ecosystem', 'gms_known', 'malformed', 'resolved_aosp', 'resolved_oem', 'app_defined')
              OR a.constant_value IS NOT NULL
              OR d.permission_string IS NOT NULL
          )
    ";
}

function sql_android_permission_current_unknown_review_page(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $dictAosp = db_catalog_table('android_permission_dict_aosp');
    $dictOem = db_catalog_table('android_permission_dict_oem');
    $latestQueue = sql_android_permission_latest_queue_subquery();
    $latestQueueNormalized = sql_android_permission_latest_queue_normalized_subquery();
    $namespaceExpr = sql_android_permission_namespace_expr_for('uo.permission_string');
    $riskExpr = db_android_permission_risk_expr('uo.permission_string', $namespaceExpr);
    $riskReasonExpr = db_android_permission_risk_reason_expr('uo.permission_string', $namespaceExpr);
    $unknownCondition = sql_android_unknown_condition('classification');

    return "
        SELECT
            uo.permission_string,
            {$namespaceExpr} AS namespace,
            {$riskExpr} AS risk_hint,
            {$riskReasonExpr} AS risk_reason,
            uo.current_unknown_obs_rows,
            uo.current_unknown_samples,
            COALESCE(ot.current_total_samples, uo.current_unknown_samples) AS current_total_samples,
            COALESCE(evt.vt_event_count, 0) AS vt_event_count,
            COALESCE(evt_norm.vt_event_count, 0) AS vt_event_count_normalized,
            u.triage_status AS dict_unknown_triage_status,
            u_norm.triage_status AS dict_unknown_triage_status_normalized,
            CASE WHEN u.permission_string IS NULL THEN 0 ELSE 1 END AS has_ledger_anchor_exact,
            CASE WHEN u_norm.permission_string IS NULL THEN 0 ELSE 1 END AS has_ledger_anchor_normalized,
            CASE WHEN a.constant_value IS NULL THEN 0 ELSE 1 END AS already_in_aosp_exact,
            CASE WHEN a_norm.constant_value IS NULL THEN 0 ELSE 1 END AS already_in_aosp_normalized,
            CASE WHEN d.permission_string IS NULL THEN 0 ELSE 1 END AS already_in_oem_exact,
            CASE WHEN d_norm.permission_string IS NULL THEN 0 ELSE 1 END AS already_in_oem_normalized,
            CASE WHEN q.permission_string IS NULL THEN 0 ELSE 1 END AS has_queue_exact,
            CASE WHEN q_norm.permission_string IS NULL THEN 0 ELSE 1 END AS has_queue_normalized,
            CASE
                WHEN (
                    u.permission_string IS NOT NULL
                    OR evt.vt_event_count IS NOT NULL
                    OR a.constant_value IS NOT NULL
                    OR d.permission_string IS NOT NULL
                    OR q.permission_string IS NOT NULL
                ) THEN 'exact'
                WHEN (
                    u_norm.permission_string IS NOT NULL
                    OR evt_norm.vt_event_count IS NOT NULL
                    OR a_norm.constant_value IS NOT NULL
                    OR d_norm.permission_string IS NOT NULL
                    OR q_norm.permission_string IS NOT NULL
                ) THEN 'normalized_only'
                ELSE 'none'
            END AS match_semantics,
            CASE
                WHEN (
                    u.permission_string IS NOT NULL
                    OR evt.vt_event_count IS NOT NULL
                    OR a.constant_value IS NOT NULL
                    OR d.permission_string IS NOT NULL
                    OR q.permission_string IS NOT NULL
                ) THEN 'Exact match'
                WHEN (
                    u_norm.permission_string IS NOT NULL
                    OR evt_norm.vt_event_count IS NOT NULL
                    OR a_norm.constant_value IS NOT NULL
                    OR d_norm.permission_string IS NOT NULL
                    OR q_norm.permission_string IS NOT NULL
                ) THEN 'Normalized-only'
                ELSE 'No match'
            END AS match_semantics_label,
            CASE
                WHEN (
                    u.permission_string IS NULL
                    AND (
                        u_norm.permission_string IS NOT NULL
                        OR evt_norm.vt_event_count IS NOT NULL
                        OR a_norm.constant_value IS NOT NULL
                        OR d_norm.permission_string IS NOT NULL
                        OR q_norm.permission_string IS NOT NULL
                    )
                ) THEN 'Case-form drift'
                ELSE NULL
            END AS match_warning,
            CASE
                WHEN u.permission_string IS NOT NULL THEN 'Exact ledger anchor'
                WHEN u_norm.permission_string IS NOT NULL THEN 'Normalized ledger anchor'
                ELSE 'No ledger anchor'
            END AS ledger_anchor_label,
            CASE
                WHEN evt.vt_event_count IS NOT NULL AND evt.vt_event_count > 0 THEN 'Exact evidence'
                WHEN evt_norm.vt_event_count IS NOT NULL AND evt_norm.vt_event_count > 0 THEN 'Normalized evidence'
                ELSE 'No evidence'
            END AS evidence_label,
            CASE
                WHEN a.constant_value IS NOT NULL THEN 'Exact AOSP'
                WHEN a_norm.constant_value IS NOT NULL THEN 'Normalized AOSP'
                ELSE 'No AOSP match'
            END AS aosp_match_label,
            CASE
                WHEN u.permission_string IS NULL THEN 'missing_ledger_context'
                WHEN u.triage_status = 'new'
                 AND LOWER(TRIM(u.permission_string)) LIKE '%.dynamic_receiver_not_exported_permission'
                    THEN 'resolved_or_dictionary_known'
                WHEN u.triage_status = 'launcher_ecosystem' THEN 'governed_launcher_ecosystem'
                WHEN u.triage_status = 'gms_known' THEN 'governed_known_google'
                WHEN u.triage_status = 'malformed' THEN 'malformed_or_conflict'
                WHEN u.triage_status IN ('resolved_aosp', 'resolved_oem', 'app_defined')
                  OR a.constant_value IS NOT NULL
                  OR d.permission_string IS NOT NULL THEN 'resolved_or_dictionary_known'
                ELSE 'active_review_candidate'
            END AS review_lane_label,
            uo.first_observed_at_utc,
            uo.last_observed_at_utc,
            COALESCE(u.seen_count, 0) AS historical_ledger_seen_count,
            q.queue_status,
            q.queue_action,
            q.queue_updated_at_utc,
            q.queue_processed_at_utc,
            q.queue_error_message
        FROM (
            SELECT
                permission_string,
                COUNT(*) AS current_unknown_obs_rows,
                COUNT(DISTINCT sample_id) AS current_unknown_samples,
                MIN(observed_at_utc) AS first_observed_at_utc,
                MAX(observed_at_utc) AS last_observed_at_utc
            FROM {$obsSample}
            WHERE {$unknownCondition}
            GROUP BY permission_string
        ) uo
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(DISTINCT sample_id) AS current_total_samples
            FROM {$obsSample}
            GROUP BY permission_string
        ) ot
          ON BINARY ot.permission_string = BINARY uo.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS vt_event_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) evt
          ON BINARY evt.permission_string = BINARY uo.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS vt_event_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) evt_norm
          ON LOWER(TRIM(evt_norm.permission_string)) = LOWER(TRIM(uo.permission_string))
        LEFT JOIN {$dictUnknown} u
          ON BINARY u.permission_string = BINARY uo.permission_string
        LEFT JOIN {$dictUnknown} u_norm
          ON LOWER(TRIM(u_norm.permission_string)) = LOWER(TRIM(uo.permission_string))
        LEFT JOIN {$dictAosp} a
          ON BINARY a.constant_value = BINARY uo.permission_string
        LEFT JOIN {$dictAosp} a_norm
          ON LOWER(TRIM(a_norm.constant_value)) = LOWER(TRIM(uo.permission_string))
        LEFT JOIN {$dictOem} d
          ON BINARY d.permission_string = BINARY uo.permission_string
        LEFT JOIN {$dictOem} d_norm
          ON LOWER(TRIM(d_norm.permission_string)) = LOWER(TRIM(uo.permission_string))
        LEFT JOIN (
            {$latestQueue}
        ) q
          ON BINARY q.permission_string = BINARY uo.permission_string
        LEFT JOIN (
            {$latestQueueNormalized}
        ) q_norm
          ON q_norm.permission_string_normalized = LOWER(TRIM(uo.permission_string))
    ";
}

function sql_android_permission_ledger_diagnostics_page(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $latestQueue = sql_android_permission_latest_queue_subquery();
    $latestQueueNormalized = sql_android_permission_latest_queue_normalized_subquery();
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $riskExpr = db_android_permission_risk_expr('u.permission_string', $namespaceExpr);
    $riskReasonExpr = db_android_permission_risk_reason_expr('u.permission_string', $namespaceExpr);
    $unknownCondition = sql_android_unknown_condition('classification');

    return "
        SELECT
            u.permission_string,
            {$namespaceExpr} AS namespace,
            {$riskExpr} AS risk_hint,
            {$riskReasonExpr} AS risk_reason,
            u.triage_status,
            COALESCE(u.seen_count, 0) AS historical_ledger_seen_count,
            u.first_seen_at_utc,
            u.last_seen_at_utc,
            CASE WHEN COALESCE(obs.current_total_samples, 0) > 0 THEN 1 ELSE 0 END AS has_obs_sample,
            CASE WHEN COALESCE(obs.current_total_samples, 0) > 0 THEN 1 ELSE 0 END AS has_obs_sample_exact,
            CASE WHEN COALESCE(obs_norm.current_total_samples, 0) > 0 THEN 1 ELSE 0 END AS has_obs_sample_normalized,
            CASE WHEN COALESCE(evt.vt_event_count, 0) > 0 THEN 1 ELSE 0 END AS has_vt_event,
            CASE WHEN COALESCE(evt.vt_event_count, 0) > 0 THEN 1 ELSE 0 END AS has_vt_event_exact,
            CASE WHEN COALESCE(evt_norm.vt_event_count, 0) > 0 THEN 1 ELSE 0 END AS has_vt_event_normalized,
            COALESCE(obs.current_unknown_samples, 0) AS current_unknown_samples,
            CASE
                WHEN COALESCE(obs.current_total_samples, 0) > 0 OR COALESCE(evt.vt_event_count, 0) > 0 OR q.permission_string IS NOT NULL
                    THEN 'exact'
                WHEN COALESCE(obs_norm.current_total_samples, 0) > 0 OR COALESCE(evt_norm.vt_event_count, 0) > 0 OR q_norm.permission_string IS NOT NULL
                    THEN 'normalized_only'
                ELSE 'none'
            END AS match_semantics,
            CASE
                WHEN COALESCE(obs.current_total_samples, 0) > 0 OR COALESCE(evt.vt_event_count, 0) > 0 OR q.permission_string IS NOT NULL
                    THEN 'Exact match'
                WHEN COALESCE(obs_norm.current_total_samples, 0) > 0 OR COALESCE(evt_norm.vt_event_count, 0) > 0 OR q_norm.permission_string IS NOT NULL
                    THEN 'Normalized-only'
                ELSE 'No match'
            END AS match_semantics_label,
            CASE
                WHEN COALESCE(obs.current_total_samples, 0) = 0
                 AND COALESCE(evt.vt_event_count, 0) = 0
                 AND q.permission_string IS NULL
                 AND (
                     COALESCE(obs_norm.current_total_samples, 0) > 0
                     OR COALESCE(evt_norm.vt_event_count, 0) > 0
                     OR q_norm.permission_string IS NOT NULL
                 )
                    THEN 'Case-form drift'
                ELSE NULL
            END AS match_warning,
            CASE
                WHEN COALESCE(obs.current_total_samples, 0) > 0 OR COALESCE(evt.vt_event_count, 0) > 0 THEN 'Exact evidence'
                WHEN COALESCE(obs_norm.current_total_samples, 0) > 0 OR COALESCE(evt_norm.vt_event_count, 0) > 0 THEN 'Normalized evidence'
                ELSE 'No evidence'
            END AS evidence_label,
            CASE
                WHEN q.permission_string IS NOT NULL THEN 'Exact ledger anchor'
                WHEN q_norm.permission_string IS NOT NULL THEN 'Normalized ledger anchor'
                ELSE 'No ledger anchor'
            END AS ledger_anchor_label,
            CASE
                WHEN COALESCE(obs.current_unknown_samples, 0) > 0
                 AND COALESCE(u.triage_status, '') NOT IN ('launcher_ecosystem', 'gms_known', 'malformed', 'resolved_aosp', 'resolved_oem', 'app_defined')
                    THEN NULL
                WHEN COALESCE(obs.current_total_samples, 0) = 0
                 AND COALESCE(evt.vt_event_count, 0) = 0
                 AND COALESCE(u.last_seen_at_utc, u.first_seen_at_utc) >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                    THEN 'recent_ledger_without_evidence'
                WHEN COALESCE(obs.current_total_samples, 0) = 0
                 AND COALESCE(evt.vt_event_count, 0) = 0
                    THEN 'orphan_ledger_row'
                WHEN u.triage_status IN ('resolved_aosp', 'resolved_oem')
                    THEN 'resolved_high_seen_historical'
                WHEN u.triage_status IN ('launcher_ecosystem', 'gms_known', 'app_defined', 'malformed')
                    THEN 'governed_historical_residue'
                ELSE 'ledger_only_no_evidence'
            END AS diagnostic_label,
            q.queue_status,
            q.queue_action,
            q.queue_updated_at_utc,
            q.queue_processed_at_utc,
            q.queue_error_message
        FROM {$dictUnknown} u
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(DISTINCT sample_id) AS current_total_samples,
                COUNT(DISTINCT CASE WHEN {$unknownCondition} THEN sample_id END) AS current_unknown_samples
            FROM {$obsSample}
            GROUP BY permission_string
        ) obs
          ON BINARY obs.permission_string = BINARY u.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(DISTINCT sample_id) AS current_total_samples,
                COUNT(DISTINCT CASE WHEN {$unknownCondition} THEN sample_id END) AS current_unknown_samples
            FROM {$obsSample}
            GROUP BY permission_string
        ) obs_norm
          ON LOWER(TRIM(obs_norm.permission_string)) = LOWER(TRIM(u.permission_string))
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS vt_event_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) evt
          ON BINARY evt.permission_string = BINARY u.permission_string
        LEFT JOIN (
            SELECT
                permission_string,
                COUNT(*) AS vt_event_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) evt_norm
          ON LOWER(TRIM(evt_norm.permission_string)) = LOWER(TRIM(u.permission_string))
        LEFT JOIN (
            {$latestQueue}
        ) q
          ON BINARY q.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueueNormalized}
        ) q_norm
          ON q_norm.permission_string_normalized = LOWER(TRIM(u.permission_string))
    ";
}

function sql_android_permission_new_namespaces_7d(): string
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('permission_string');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $unknownCondition = sql_android_unknown_condition('classification');

    return "
        SELECT COUNT(*) AS new_namespaces_7d
        FROM (
            SELECT {$namespaceExpr} AS namespace, MIN(observed_at_utc) AS first_seen_at_utc
            FROM {$obsSample}
            WHERE {$unknownCondition}
            GROUP BY {$namespaceExpr}
        ) t
        WHERE t.first_seen_at_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
    ";
}

function sql_android_permission_security_sensitive_unknowns(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $unknownCondition = sql_android_unknown_condition('classification');
    return "
        SELECT COUNT(DISTINCT permission_string) AS security_sensitive_unknowns
        FROM {$obsSample}
        WHERE {$unknownCondition}
          AND LOWER(TRIM(permission_string)) NOT LIKE '%.dynamic_receiver_not_exported_permission'
          AND (
               LOWER(permission_string) LIKE '%sms%'
           OR LOWER(permission_string) LIKE '%overlay%'
           OR LOWER(permission_string) LIKE '%account%'
          )
    ";
}

function sql_android_permission_evidence(int $limit = 50): string
{
    $limit = max(1, min(200, $limit));
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $sampleCatalog = db_catalog_table('malware_sample_catalog');
    $vtState = db_catalog_table('virustotal_sample_state');
    $runLedger = db_catalog_table('virustotal_run_ledger');

    return "
        SELECT
            e.sample_id,
            c.sample_label,
            e.package_name,
            e.permission_string,
            COALESCE(e.vt_analysis_date_utc, e.ingested_at_utc) AS observed_at_utc,
            s.last_run_id AS run_id,
            s.vt_status_code,
            r.stopped_reason,
            r.perm_taxonomy_version,
            r.ok_count,
            u.triage_status AS triage_status_exact,
            u_norm.triage_status AS triage_status_normalized,
            CASE
                WHEN u.permission_string IS NOT NULL THEN 'exact'
                WHEN u_norm.permission_string IS NOT NULL THEN 'normalized_only'
                ELSE 'none'
            END AS match_semantics
        FROM {$vtEvent} e
        LEFT JOIN {$dictUnknown} u ON BINARY u.permission_string = BINARY e.permission_string
        LEFT JOIN {$dictUnknown} u_norm ON LOWER(TRIM(u_norm.permission_string)) = LOWER(TRIM(e.permission_string))
        LEFT JOIN {$sampleCatalog} c ON c.sample_id = e.sample_id
        LEFT JOIN {$vtState} s ON s.sample_id = e.sample_id
        LEFT JOIN {$runLedger} r ON r.run_id = s.last_run_id
        WHERE BINARY e.permission_string = BINARY :permission
        ORDER BY e.ingested_at_utc DESC
        LIMIT {$limit}
    ";
}

function sql_android_permission_evidence_count(): string
{
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    return "
        SELECT COUNT(*) AS total_count
        FROM {$vtEvent}
        WHERE BINARY permission_string = BINARY :permission
    ";
}

function sql_android_permission_rollup_drift_summary(): string
{
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $vtCurrent = db_catalog_table('android_permission_enrich_vt_current');
    return "
        SELECT
            SUM(CASE
                WHEN c.permission_string IS NULL THEN 1
                WHEN e.event_last_seen_at_utc IS NOT NULL
                     AND (c.last_seen_at_utc IS NULL OR c.last_seen_at_utc < e.event_last_seen_at_utc)
                THEN 1 ELSE 0 END) AS stale_permissions_count,
            SUM(CASE
                WHEN c.permission_string IS NULL THEN 1
                WHEN e.event_seen_count != COALESCE(c.seen_count, 0)
                THEN 1 ELSE 0 END) AS stale_count_mismatch_count,
            MAX(CASE
                WHEN c.permission_string IS NULL THEN NULL
                WHEN e.event_last_seen_at_utc IS NOT NULL
                     AND c.last_seen_at_utc IS NOT NULL
                     AND e.event_last_seen_at_utc > c.last_seen_at_utc
                THEN TIMESTAMPDIFF(SECOND, c.last_seen_at_utc, e.event_last_seen_at_utc)
                ELSE NULL END) AS max_lag_seconds
        FROM (
            SELECT
                permission_string,
                MAX(COALESCE(vt_analysis_date_utc, ingested_at_utc)) AS event_last_seen_at_utc,
                COUNT(*) AS event_seen_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) e
        LEFT JOIN {$vtCurrent} c
            ON BINARY c.permission_string = BINARY e.permission_string
    ";
}

function sql_android_permission_rollup_drift_samples(int $limit = 10): string
{
    $limit = max(1, min(50, $limit));
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $vtCurrent = db_catalog_table('android_permission_enrich_vt_current');
    return "
        SELECT
            e.permission_string,
            e.event_last_seen_at_utc,
            c.last_seen_at_utc AS current_last_seen_at_utc,
            e.event_seen_count,
            COALESCE(c.seen_count, 0) AS current_seen_count,
            CASE
                WHEN c.last_seen_at_utc IS NULL OR e.event_last_seen_at_utc IS NULL THEN NULL
                WHEN e.event_last_seen_at_utc > c.last_seen_at_utc
                THEN TIMESTAMPDIFF(SECOND, c.last_seen_at_utc, e.event_last_seen_at_utc)
                ELSE 0 END AS lag_seconds,
            (e.event_seen_count - COALESCE(c.seen_count, 0)) AS count_delta
        FROM (
            SELECT
                permission_string,
                MAX(COALESCE(vt_analysis_date_utc, ingested_at_utc)) AS event_last_seen_at_utc,
                COUNT(*) AS event_seen_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) e
        LEFT JOIN {$vtCurrent} c
            ON BINARY c.permission_string = BINARY e.permission_string
        WHERE c.permission_string IS NULL
           OR e.event_seen_count != COALESCE(c.seen_count, 0)
           OR (e.event_last_seen_at_utc IS NOT NULL
               AND (c.last_seen_at_utc IS NULL OR c.last_seen_at_utc < e.event_last_seen_at_utc))
        ORDER BY
            CASE WHEN c.permission_string IS NULL THEN 1 ELSE 0 END DESC,
            COALESCE(lag_seconds, 0) DESC,
            ABS(e.event_seen_count - COALESCE(c.seen_count, 0)) DESC,
            e.permission_string ASC
        LIMIT {$limit}
    ";
}

function sql_unknown_permission_update_status(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET triage_status = :triage_status
        WHERE LOWER(permission_string) = LOWER(:permission)
    ";
}

function sql_unknown_permission_update_notes(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET notes = :notes
        WHERE LOWER(permission_string) = LOWER(:permission)
    ";
}

function sql_android_permission_obs_reclassify_by_permission(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        UPDATE {$obsSample}
        SET
            classification = :classification,
            bucket = :bucket,
            rule_fired = :rule_fired
        WHERE LOWER(permission_string) = LOWER(:permission)
    ";
}

function sql_android_permission_obs_reclassify_by_governed_status(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$obsSample} o
        JOIN {$dictUnknown} u
          ON u.permission_string_norm = o.permission_string_norm
        SET
            o.classification = CASE
                WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'OEM'
                WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'GOOGLE'
                WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'OEM'
                WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'APP_DEFINED'
                ELSE o.classification
            END,
            o.bucket = CASE
                WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'OEM_EXACT'
                WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'GOOGLE_GMS'
                WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'OEM_LAUNCHER_ECOSYSTEM'
                WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'APP_DEFINED_OTHER'
                ELSE o.bucket
            END,
            o.rule_fired = CASE
                WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'oem_dict'
                WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'gms_namespace'
                WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'launcher_ecosystem'
                WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'default'
                ELSE o.rule_fired
            END
        WHERE LOWER(TRIM(u.triage_status)) IN ('resolved_oem', 'gms_known', 'launcher_ecosystem', 'app_defined')
          AND (
                o.classification <> CASE
                    WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'OEM'
                    WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'GOOGLE'
                    WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'OEM'
                    WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'APP_DEFINED'
                    ELSE o.classification
                END
             OR COALESCE(o.bucket, '') <> CASE
                    WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'OEM_EXACT'
                    WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'GOOGLE_GMS'
                    WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'OEM_LAUNCHER_ECOSYSTEM'
                    WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'APP_DEFINED_OTHER'
                    ELSE COALESCE(o.bucket, '')
                END
             OR COALESCE(o.rule_fired, '') <> CASE
                    WHEN LOWER(TRIM(u.triage_status)) = 'resolved_oem' THEN 'oem_dict'
                    WHEN LOWER(TRIM(u.triage_status)) = 'gms_known' THEN 'gms_namespace'
                    WHEN LOWER(TRIM(u.triage_status)) = 'launcher_ecosystem' THEN 'launcher_ecosystem'
                    WHEN LOWER(TRIM(u.triage_status)) = 'app_defined' THEN 'default'
                    ELSE COALESCE(o.rule_fired, '')
                END
          )
    ";
}

function sql_unknown_permission_by_string(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        SELECT permission_string, triage_status, notes
        FROM {$dictUnknown}
        WHERE LOWER(permission_string) = LOWER(:permission)
        LIMIT 1
    ";
}

function sql_unknown_permission_audit_insert(): string
{
    $triageAudit = db_catalog_table('android_permission_triage_audit');
    return "
        INSERT INTO {$triageAudit}
            (permission_string, action, actor, source, context_json, note, recorded_at_utc)
        VALUES
            (:permission, :action, :actor, :source, :context_json, :note, UTC_TIMESTAMP())
    ";
}

function sql_unknown_permission_batch_promote_dynamic_receiver_tokens(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET
            triage_status = 'app_defined',
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN
                    '[2026-06-07] Governance move: DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION suffix is an app-local dynamic receiver permission pattern; classify as app-defined.'
                WHEN notes LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION suffix is an app-local dynamic receiver permission pattern%' THEN notes
                ELSE CONCAT(
                    notes,
                    '\n[2026-06-07] Governance move: DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION suffix is an app-local dynamic receiver permission pattern; classify as app-defined.'
                )
            END
        WHERE triage_status = 'new'
          AND permission_string LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'
    ";
}

function sql_unknown_permission_batch_promote_noted_resolved_oem(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET triage_status = 'resolved_oem'
        WHERE triage_status = 'oem_candidate'
          AND notes LIKE '%promoted to resolved_oem%'
    ";
}

function sql_unknown_permission_batch_retire_dynamic_receiver_artifacts(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET
            triage_status = 'malformed',
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN
                    '[2026-06-07] Governance move: token embeds DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION with trailing artifact bytes; classify as malformed parser residue.'
                WHEN notes LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION with trailing artifact bytes%' THEN notes
                ELSE CONCAT(
                    notes,
                    '\n[2026-06-07] Governance move: token embeds DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION with trailing artifact bytes; classify as malformed parser residue.'
                )
            END
        WHERE triage_status IN ('new', 'oem_candidate')
          AND permission_string LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION%'
          AND permission_string NOT LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'
    ";
}

function sql_unknown_permission_batch_promote_zero_telemetry_new_app_defined(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET
            triage_status = 'app_defined',
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN
                    '[2026-06-07] Governance move: zero-telemetry package-scoped custom permission residue; classify as app-defined.'
                WHEN notes LIKE '%zero-telemetry package-scoped custom permission residue%' THEN notes
                ELSE CONCAT(
                    notes,
                    '\n[2026-06-07] Governance move: zero-telemetry package-scoped custom permission residue; classify as app-defined.'
                )
            END
        WHERE triage_status = 'new'
          AND seen_count = 0
          AND (example_package_name IS NULL OR TRIM(example_package_name) = '')
          AND permission_string NOT LIKE 'android.permission.%'
          AND permission_string NOT LIKE 'com.google.%'
          AND permission_string NOT LIKE 'com.google.android.%'
          AND permission_string NOT LIKE 'com.android.vending.%'
          AND permission_string NOT LIKE 'com.huawei.%'
          AND permission_string NOT LIKE 'com.xiaomi.%'
          AND permission_string NOT LIKE 'com.samsung.%'
          AND permission_string NOT LIKE 'com.vivo.%'
          AND permission_string NOT LIKE 'com.oppo.%'
          AND permission_string NOT LIKE 'com.coloros.%'
          AND permission_string NOT LIKE 'com.sonymobile.%'
    ";
}

function sql_unknown_permission_batch_retire_misspelled_aosp_false_positives(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET
            triage_status = 'malformed',
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN
                    '[2026-06-07] Governance move: misspelled AOSP permission false positive; android.permission.change_configuratison does not match Android''s documented CHANGE_CONFIGURATION constant.'
                WHEN notes LIKE '%misspelled AOSP permission false positive; android.permission.change_configuratison%' THEN notes
                ELSE CONCAT(
                    notes,
                    '\n[2026-06-07] Governance move: misspelled AOSP permission false positive; android.permission.change_configuratison does not match Android''s documented CHANGE_CONFIGURATION constant.'
                )
            END
        WHERE triage_status = 'aosp_missing'
          AND permission_string = 'android.permission.change_configuratison'
    ";
}

function sql_oem_candidate_batch_insert_into_oem_dict(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $dictOem = db_catalog_table('android_permission_dict_oem');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    return "
        INSERT INTO {$dictOem}
            (
                permission_string,
                vendor_id,
                display_name,
                description,
                notes,
                protection_level,
                confidence,
                classification_source,
                first_seen_at_utc,
                last_seen_at_utc,
                seen_count
            )
        SELECT
            u.permission_string,
            CASE
                WHEN u.permission_string LIKE 'com.huawei.%' THEN 3
                WHEN u.permission_string LIKE 'com.samsung.%' THEN 1
                WHEN u.permission_string LIKE 'com.sonymobile.%' THEN 8
                ELSE NULL
            END AS vendor_id,
            SUBSTRING_INDEX(u.permission_string, '.', -1) AS display_name,
            NULL AS description,
            '[2026-06-07] Governance move: promoted from oem_candidate via observed OEM-prefixed exact permission batch.' AS notes,
            NULL AS protection_level,
            'medium' AS confidence,
            'manual' AS classification_source,
            MIN(o.observed_at_utc) AS first_seen_at_utc,
            MAX(o.observed_at_utc) AS last_seen_at_utc,
            COUNT(*) AS seen_count
        FROM {$dictUnknown} u
        JOIN {$obsSample} o
          ON o.permission_string_norm = u.permission_string_norm
        LEFT JOIN {$dictOem} d
          ON d.permission_string_norm = u.permission_string_norm
        WHERE u.triage_status = 'oem_candidate'
          AND d.permission_string IS NULL
          AND (
                u.permission_string LIKE 'com.huawei.%'
             OR u.permission_string LIKE 'com.samsung.%'
             OR u.permission_string LIKE 'com.sonymobile.%'
          )
        GROUP BY u.permission_string
    ";
}

function sql_unknown_permission_batch_promote_oem_candidates_to_resolved(): string
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$dictUnknown}
        SET
            triage_status = 'resolved_oem',
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN
                    '[2026-06-07] Governance move: promoted to resolved_oem via observed OEM-prefixed exact permission batch.'
                WHEN notes LIKE '%promoted to resolved_oem via observed OEM-prefixed exact permission batch%' THEN notes
                ELSE CONCAT(
                    notes,
                    '\n[2026-06-07] Governance move: promoted to resolved_oem via observed OEM-prefixed exact permission batch.'
                )
            END
        WHERE triage_status = 'oem_candidate'
          AND (
                permission_string LIKE 'com.huawei.%'
             OR permission_string LIKE 'com.samsung.%'
             OR permission_string LIKE 'com.sonymobile.%'
          )
    ";
}

function sql_oem_dict_delete_suspicious_autoseed_rows(): string
{
    $dictOem = db_catalog_table('android_permission_dict_oem');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        DELETE d
        FROM {$dictOem} d
        JOIN {$dictUnknown} u
          ON u.permission_string_norm = d.permission_string_norm
        WHERE u.triage_status IN ('brand_spoof', 'malicious_dga')
          AND d.notes LIKE '%[auto-seed] workflow oem_candidate; queued for apply%'
    ";
}

function sql_android_permission_obs_reclassify_suspicious_app_defined(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$obsSample} o
        JOIN {$dictUnknown} u
          ON u.permission_string_norm = o.permission_string_norm
        SET
            o.classification = 'APP_DEFINED',
            o.bucket = 'APP_DEFINED_OTHER',
            o.rule_fired = 'suspicious_app_defined'
        WHERE u.triage_status IN ('brand_spoof', 'malicious_dga')
          AND (
                o.classification <> 'APP_DEFINED'
             OR COALESCE(o.bucket, '') <> 'APP_DEFINED_OTHER'
             OR COALESCE(o.rule_fired, '') <> 'suspicious_app_defined'
          )
    ";
}

function sql_android_permission_obs_reclassify_dynamic_receiver_artifacts(): string
{
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    return "
        UPDATE {$obsSample} o
        JOIN {$dictUnknown} u
          ON u.permission_string_norm = o.permission_string_norm
        SET
            o.classification = 'APP_DEFINED',
            o.bucket = 'APP_DEFINED_OTHER',
            o.rule_fired = 'malformed_dynamic_receiver_artifact'
        WHERE u.triage_status = 'malformed'
          AND u.permission_string LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION%'
          AND u.permission_string NOT LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'
          AND (
                o.classification <> 'APP_DEFINED'
             OR COALESCE(o.bucket, '') <> 'APP_DEFINED_OTHER'
             OR COALESCE(o.rule_fired, '') <> 'malformed_dynamic_receiver_artifact'
          )
    ";
}

function sql_android_permission_queue_metrics(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $unknownCondition = sql_android_unknown_condition('classification');
    return "
        SELECT
            SUM(status = 'queued') AS queued_count,
            SUM(status = 'queued' AND cur.permission_norm IS NOT NULL) AS queued_current_unknown_count,
            SUM(status = 'queued' AND (cur.permission_norm IS NOT NULL OR obs.permission_norm IS NOT NULL OR evt.permission_norm IS NOT NULL OR unk.permission_norm IS NOT NULL)) AS queued_evidence_backed_count,
            SUM(status = 'queued' AND cur.permission_norm IS NULL AND obs.permission_norm IS NULL AND evt.permission_norm IS NULL AND unk.permission_norm IS NULL) AS queued_static_no_anchor_count,
            SUM(status = 'applied') AS applied_count,
            SUM(status = 'error') AS error_count,
            SUM(status = 'rejected') AS rejected_count,
            SUM(status = 'skipped') AS skipped_count,
            MAX(CASE WHEN status = 'queued' THEN updated_at_utc ELSE NULL END) AS last_queued_at_utc,
            MAX(CASE WHEN status = 'queued' AND cur.permission_norm IS NOT NULL THEN updated_at_utc ELSE NULL END) AS last_current_unknown_queued_at_utc,
            MAX(CASE WHEN status = 'applied' THEN processed_at_utc ELSE NULL END) AS last_applied_at_utc,
            MAX(CASE WHEN status = 'error' THEN processed_at_utc ELSE NULL END) AS last_error_at_utc
        FROM {$dictQueue} q
        LEFT JOIN (
            SELECT DISTINCT LOWER(TRIM(permission_string)) AS permission_norm
            FROM {$obsSample}
            WHERE {$unknownCondition}
        ) cur
          ON cur.permission_norm = LOWER(TRIM(q.permission_string))
        LEFT JOIN (
            SELECT DISTINCT LOWER(TRIM(permission_string)) AS permission_norm
            FROM {$obsSample}
        ) obs
          ON obs.permission_norm = LOWER(TRIM(q.permission_string))
        LEFT JOIN (
            SELECT DISTINCT LOWER(TRIM(permission_string)) AS permission_norm
            FROM {$vtEvent}
        ) evt
          ON evt.permission_norm = LOWER(TRIM(q.permission_string))
        LEFT JOIN (
            SELECT DISTINCT LOWER(TRIM(permission_string)) AS permission_norm
            FROM {$dictUnknown}
        ) unk
          ON unk.permission_norm = LOWER(TRIM(q.permission_string))
    ";
}

function sql_android_permission_queue_action_counts(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        SELECT queue_action, COUNT(*) AS cnt
        FROM {$dictQueue}
        GROUP BY queue_action
    ";
}

function sql_permission_queue_insert(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        INSERT INTO {$dictQueue}
            (permission_string, queue_action, proposed_bucket, proposed_classification, triage_status, notes, requested_by, updated_by, source_system)
        VALUES
            (:permission, :queue_action, :proposed_bucket, :proposed_classification, :triage_status, :notes, :requested_by, :updated_by, :source_system)
    ";
}

function sql_permission_queue_active_by_permission(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        SELECT queue_id, queue_action, status
        FROM {$dictQueue}
        WHERE LOWER(permission_string) = LOWER(:permission)
          AND status IN ('queued', 'claimed')
        ORDER BY updated_at_utc DESC, queue_id DESC
        LIMIT 1
    ";
}

function sql_permission_queue_any_by_permission(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        SELECT queue_id, queue_action, status
        FROM {$dictQueue}
        WHERE LOWER(permission_string) = LOWER(:permission)
        ORDER BY updated_at_utc DESC, queue_id DESC
        LIMIT 1
    ";
}

function sql_permission_queue_update_by_id(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        UPDATE {$dictQueue}
        SET
            queue_action = :queue_action,
            proposed_bucket = :proposed_bucket,
            proposed_classification = :proposed_classification,
            triage_status = :triage_status,
            notes = :notes,
            updated_by = :updated_by,
            updated_at_utc = UTC_TIMESTAMP()
        WHERE queue_id = :queue_id
    ";
}

function sql_permission_queue_requeue_by_id(): string
{
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    return "
        UPDATE {$dictQueue}
        SET
            queue_action = :queue_action,
            proposed_bucket = :proposed_bucket,
            proposed_classification = :proposed_classification,
            triage_status = :triage_status,
            notes = :notes,
            status = 'queued',
            processed_by = NULL,
            processed_at_utc = NULL,
            error_message = NULL,
            updated_by = :updated_by,
            updated_at_utc = UTC_TIMESTAMP()
        WHERE queue_id = :queue_id
    ";
}
