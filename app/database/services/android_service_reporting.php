<?php
// app/database/services/android_service_reporting.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/transient_cache.php';
require_once __DIR__ . '/health_service.php';

function db_android_permission_summary_cache_key(string $scope): string
{
    return md5(json_encode([
        'scope' => $scope,
        'primary_catalog' => db_primary_catalog_name(),
        'permission_catalog' => db_permission_intel_catalog_name(),
        'split_enabled' => db_permission_intel_split_enabled() ? '1' : '0',
        'payload_contract' => 'permission_summary_contract_v6',
        'version' => defined('APP_VERSION') ? APP_VERSION : 'dev',
    ], JSON_UNESCAPED_SLASHES) ?: '');
}

function db_android_permission_summary_cached(string $namespace, string $scope, int $ttlSeconds, callable $loader, bool $allowStale = true): array
{
    static $requestCache = [];

    $cacheKey = db_android_permission_summary_cache_key($scope);
    $memoKey = $namespace . ':' . $cacheKey;
    if (isset($requestCache[$memoKey]) && is_array($requestCache[$memoKey])) {
        return $requestCache[$memoKey];
    }

    $cached = app_transient_cache_read($namespace, $cacheKey, $ttlSeconds);
    if (is_array($cached)) {
        $requestCache[$memoKey] = $cached;
        return $requestCache[$memoKey];
    }

    if ($allowStale) {
        $stale = app_transient_cache_read_stale($namespace, $cacheKey);
        if (is_array($stale)) {
            $requestCache[$memoKey] = $stale;
            return $requestCache[$memoKey];
        }
    }

    $value = $loader();
    if (is_array($value)) {
        app_transient_cache_write($namespace, $cacheKey, $value);
        $requestCache[$memoKey] = $value;
        return $requestCache[$memoKey];
    }

    return [];
}

function db_android_permission_current_unknown_review_page_cached(array $filters, int $ttlSeconds): array
{
    ksort($filters);
    $scope = 'current_unknown_review:' . md5(json_encode($filters, JSON_UNESCAPED_SLASHES) ?: '');
    return db_android_permission_summary_cached(
        'perm_current_unknown_review',
        $scope,
        $ttlSeconds,
        static fn(): array => db_android_permission_current_unknown_review_page($filters),
        false
    );
}

function db_android_permission_ledger_diagnostics_overview_cached(int $limit, int $ttlSeconds): array
{
    $scope = 'ledger_diagnostics_overview:' . $limit;
    return db_android_permission_summary_cached(
        'perm_ledger_diag_overview',
        $scope,
        $ttlSeconds,
        static fn(): array => db_android_permission_ledger_diagnostics_overview($limit),
        false
    );
}

function db_android_permission_ledger_diagnostics_page_fast_cached(array $filters, int $ttlSeconds): array
{
    ksort($filters);
    $scope = 'ledger_diagnostics_page_fast:' . md5(json_encode($filters, JSON_UNESCAPED_SLASHES) ?: '');
    return db_android_permission_summary_cached(
        'perm_ledger_diag_page_fast',
        $scope,
        $ttlSeconds,
        static fn(): array => db_android_permission_ledger_diagnostics_page_fast($filters),
        false
    );
}

function db_android_permission_page_total_meta(array $page, int $totalCount): array
{
    $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
    $pageNumber = max(1, (int)($meta['page'] ?? 1));
    $pageSize = max(1, (int)($meta['page_size'] ?? 1));
    $totalPages = (int)max(1, (int)ceil($totalCount / $pageSize));
    $meta['total_count'] = $totalCount;
    $meta['total_pages'] = $totalPages;
    $meta['has_more'] = ($pageNumber * $pageSize) < $totalCount;
    $page['meta'] = $meta;
    return $page;
}

function db_android_permission_queue_metrics_cached(int $ttlSeconds): array
{
    return db_android_permission_summary_cached(
        'perm_queue_metrics',
        'queue_metrics',
        $ttlSeconds,
        static fn(): array => db_one(sql_android_permission_queue_metrics()) ?? []
    );
}

function db_android_permission_current_evidence_risk_counts_cached(int $ttlSeconds): array
{
    return db_android_permission_summary_cached(
        'perm_current_evidence_risk_counts',
        'current_evidence_risk_counts',
        $ttlSeconds,
        static fn(): array => db_android_permission_current_evidence_risk_counts()
    );
}

function db_android_permission_gap_workflow_state(string $reason): string
{
    $key = strtolower(trim($reason));
    return match ($key) {
        'missing_vt_confidence_for_strong_attack_surface' => 'behavior_strong_vt_missing',
        'missing_vt_confidence_for_attack_surface' => 'evidence_missing',
        'strong_attack_surface_low_vt_action',
        'strong_attack_surface_weak_vt_confidence' => 'behavior_vt_conflict',
        'attack_surface_supports_vt_review' => 'behavior_supports_review',
        default => 'review_context',
    };
}

function db_android_permission_gap_workflow_label(string $workflowState): string
{
    $key = strtolower(trim($workflowState));
    return match ($key) {
        'behavior_strong_vt_missing' => 'Behavior strong, VT evidence missing',
        'evidence_missing' => 'Evidence missing',
        'behavior_vt_conflict' => 'Behavior/VT conflict',
        'behavior_supports_review' => 'Behavior supports VT review',
        default => 'Review context',
    };
}

function db_android_permission_gap_reason_label(string $reason): string
{
    $key = strtolower(trim($reason));
    return match ($key) {
        'missing_vt_confidence_for_strong_attack_surface' => 'Missing VT confidence for strong behavior',
        'missing_vt_confidence_for_attack_surface' => 'Missing VT confidence',
        'strong_attack_surface_low_vt_action' => 'Strong behavior but low VT action',
        'strong_attack_surface_weak_vt_confidence' => 'Strong behavior but weak VT confidence',
        'attack_surface_supports_vt_review' => 'Behavior supports VT review',
        default => 'Review context',
    };
}

function db_android_permission_gap_sample_key(array $row): string
{
    $sha = trim((string)($row['sha256'] ?? ''));
    if ($sha !== '') {
        return 'sha:' . strtolower($sha);
    }
    $sampleId = trim((string)($row['sample_id'] ?? ''));
    if ($sampleId !== '') {
        return 'id:' . $sampleId;
    }
    return '';
}

function db_android_permission_classification_gaps(int $limit = 25): array
{
    $limit = max(1, min($limit, 250));
    $schema = db_android_permission_classification_gap_schema_status();
    if (!$schema['ok']) {
        return [
            'data' => [
                'summary' => [],
                'gaps' => [],
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

    $attackSurface = db_catalog_table('v_android_permission_attack_surface_current');
    $vtConfidence = db_catalog_table('vt_sample_verdict_confidence_current');
    $attackRollup = "
        SELECT
            sample_id,
            COUNT(*) AS sample_attack_surface_rows,
            SUM(CASE WHEN max_mapping_strength_rank >= 3 THEN 1 ELSE 0 END) AS sample_strong_attack_surface_rows
        FROM {$attackSurface}
        WHERE max_mapping_strength_rank >= 2
        GROUP BY sample_id
    ";

    $sql = "
        SELECT *
        FROM (
            SELECT
                a.sample_id,
                v.sha256,
                a.package_name,
                a.attack_technique_id,
                a.attack_name,
                a.tactic,
                a.mapped_permission_count,
                a.permissions,
                a.max_mapping_strength_rank,
                rollup.sample_attack_surface_rows,
                rollup.sample_strong_attack_surface_rows,
                v.vt_malicious_count,
                v.vt_suspicious_count,
                v.vt_harmless_count,
                v.vt_total_engines,
                v.confidence_score,
                v.confidence_bucket,
                v.recommended_action,
                CASE
                    WHEN v.sample_id IS NULL AND a.max_mapping_strength_rank >= 3
                        THEN 'missing_vt_confidence_for_strong_attack_surface'
                    WHEN v.sample_id IS NULL
                        THEN 'missing_vt_confidence_for_attack_surface'
                    WHEN a.max_mapping_strength_rank >= 3
                     AND LOWER(COALESCE(v.recommended_action, '')) IN ('ignore', 'monitor')
                        THEN 'strong_attack_surface_low_vt_action'
                    WHEN a.max_mapping_strength_rank >= 3
                     AND LOWER(COALESCE(v.confidence_bucket, '')) IN ('none', 'weak')
                        THEN 'strong_attack_surface_weak_vt_confidence'
                    WHEN LOWER(COALESCE(v.recommended_action, '')) = 'review'
                     AND a.max_mapping_strength_rank >= 2
                        THEN 'attack_surface_supports_vt_review'
                    ELSE 'attack_surface_context'
                END AS classification_gap_reason,
                CASE
                    WHEN v.sample_id IS NULL AND a.max_mapping_strength_rank >= 3 THEN 'high'
                    WHEN v.sample_id IS NULL THEN 'medium'
                    WHEN a.max_mapping_strength_rank >= 3
                     AND LOWER(COALESCE(v.recommended_action, '')) IN ('ignore', 'monitor') THEN 'high'
                    WHEN a.max_mapping_strength_rank >= 3
                     AND LOWER(COALESCE(v.confidence_bucket, '')) IN ('none', 'weak') THEN 'high'
                    WHEN LOWER(COALESCE(v.recommended_action, '')) = 'review'
                     AND a.max_mapping_strength_rank >= 2 THEN 'medium'
                    ELSE 'low'
                END AS review_priority
            FROM {$attackSurface} a
            INNER JOIN ({$attackRollup}) rollup
              ON rollup.sample_id = a.sample_id
            LEFT JOIN {$vtConfidence} v
              ON v.sample_id = a.sample_id
            WHERE a.max_mapping_strength_rank >= 2
        ) gaps
        WHERE review_priority IN ('high', 'medium')
        ORDER BY
            CASE review_priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC,
            sample_strong_attack_surface_rows DESC,
            sample_attack_surface_rows DESC,
            max_mapping_strength_rank DESC,
            mapped_permission_count DESC,
            COALESCE(vt_malicious_count, 0) ASC,
            sample_id ASC
        LIMIT :limit
    ";

    $stmt = db()->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $summary = [];
    $summarySampleSets = [];
    foreach ($rows as &$row) {
        $reason = (string)($row['classification_gap_reason'] ?? 'unknown');
        $priority = (string)($row['review_priority'] ?? 'low');
        $workflowState = db_android_permission_gap_workflow_state($reason);
        $workflowLabel = db_android_permission_gap_workflow_label($workflowState);
        $row['workflow_state'] = $workflowState;
        $row['workflow_label'] = $workflowLabel;
        $row['workflow_reason_label'] = db_android_permission_gap_reason_label($reason);
        $row['sample_attack_surface_rows'] = (int)($row['sample_attack_surface_rows'] ?? 0);
        $row['sample_strong_attack_surface_rows'] = (int)($row['sample_strong_attack_surface_rows'] ?? 0);
        $row['is_multi_behavior_sample'] = $row['sample_strong_attack_surface_rows'] > 1 ? 1 : 0;

        $key = $workflowState . '|' . $priority;
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'classification_gap_reason' => $reason,
                'workflow_state' => $workflowState,
                'workflow_label' => $workflowLabel,
                'review_priority' => $priority,
                'sample_count' => 0,
                'attack_row_count' => 0,
            ];
            $summarySampleSets[$key] = [];
        }
        $summary[$key]['attack_row_count']++;
        $sampleKey = db_android_permission_gap_sample_key($row);
        if ($sampleKey !== '') {
            $summarySampleSets[$key][$sampleKey] = true;
            $summary[$key]['sample_count'] = count($summarySampleSets[$key]);
        }
    }
    unset($row);

    return [
        'data' => [
            'summary' => array_values($summary),
            'gaps' => $rows,
        ],
        'meta' => [
            'schema_available' => true,
            'primary_database' => db_primary_catalog_name(),
            'permission_intel_database' => db_permission_intel_catalog_name(),
            'permission_intel_split' => db_permission_intel_split_enabled(),
            'limit' => $limit,
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        ],
    ];
}

function db_android_permission_unknowns_page(array $filters): array
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $riskExpr = db_android_permission_risk_expr('u.permission_string', $namespaceExpr);
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $latestQueue = sql_android_permission_latest_queue_subquery();
    $latestQueueNormalized = sql_android_permission_latest_queue_normalized_subquery();

    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(10, min((int)($filters['page_size'] ?? 100), 500));
    $offset = max(0, ($page - 1) * $pageSize);
    $skipCount = (bool)($filters['skip_count'] ?? false);

    $where = [];
    $params = [];

    $term = trim((string)($filters['q'] ?? ''));
    if ($term !== '') {
        $where[] = "(u.permission_string LIKE :q_permission OR {$namespaceExpr} LIKE :q_namespace OR u.triage_status LIKE :q_status)";
        $likeValue = '%' . $term . '%';
        $params['q_permission'] = $likeValue;
        $params['q_namespace'] = $likeValue;
        $params['q_status'] = $likeValue;
    }

    $namespace = trim((string)($filters['namespace'] ?? ''));
    if ($namespace !== '') {
        $where[] = "{$namespaceExpr} = :namespace";
        $params['namespace'] = $namespace;
    }

    $risk = strtolower(trim((string)($filters['risk'] ?? '')));
    if (in_array($risk, ['high', 'medium', 'low'], true)) {
        $where[] = "{$riskExpr} = :risk";
        $params['risk'] = $risk;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $allowedStatuses = array_map('strtolower', perm_triage_status_keys());
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $where[] = "u.triage_status = :triage_status";
        $params['triage_status'] = $status;
    } else {
        $includeResolved = (bool)($filters['include_resolved'] ?? true);
        if (!$includeResolved) {
            $actionable = $filters['actionable_statuses'] ?? perm_actionable_triage_status_keys();
            $actionable = array_values(array_unique(array_filter(array_map(
                static fn($v) => strtolower(trim((string)$v)),
                is_array($actionable) ? $actionable : []
            ))));
            if ($actionable) {
                add_where_in($where, $params, 'u.triage_status', 'triage_status', $actionable);
            }
        }
    }

    $queued = strtolower(trim((string)($filters['queued'] ?? '')));
    $allowedQueue = array_map('strtolower', perm_queue_status_keys());
    $needsQueueJoinForFilter = false;
    if ($queued !== '' && in_array($queued, $allowedQueue, true)) {
        $where[] = "COALESCE(q.queue_status, q_norm.queue_status, '') = :queue_status";
        $params['queue_status'] = $queued;
        $needsQueueJoinForFilter = true;
    }

    $sort = strtolower(trim((string)($filters['sort'] ?? 'seen_desc')));
    $sortMap = [
        'seen_desc' => "u.seen_count DESC, u.permission_string ASC",
        'seen_asc' => "u.seen_count ASC, u.permission_string ASC",
        'last_seen_desc' => "u.last_seen_at_utc DESC, u.permission_string ASC",
        'last_seen_asc' => "u.last_seen_at_utc ASC, u.permission_string ASC",
        'permission_asc' => "u.permission_string ASC",
        'permission_desc' => "u.permission_string DESC",
        'status_asc' => "u.triage_status ASC, u.seen_count DESC",
        'status_desc' => "u.triage_status DESC, u.seen_count DESC",
        'namespace_asc' => "{$namespaceExpr} ASC, u.permission_string ASC",
        'namespace_desc' => "{$namespaceExpr} DESC, u.permission_string ASC",
        'risk_desc' => "CASE {$riskExpr} WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC, u.seen_count DESC",
        'risk_asc' => "CASE {$riskExpr} WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC, u.seen_count DESC",
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['seen_desc'];

    $fromSql = "
        FROM {$dictUnknown} u
        LEFT JOIN (
            {$latestQueue}
        ) q ON BINARY q.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueueNormalized}
        ) q_norm ON q_norm.permission_string_normalized = LOWER(TRIM(u.permission_string))
    ";

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $dataSql = "
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
            CASE
                WHEN q.permission_string IS NOT NULL THEN 'exact'
                WHEN q_norm.permission_string IS NOT NULL THEN 'normalized_only'
                ELSE 'none'
            END AS queue_match_semantics,
            CASE
                WHEN q.permission_string IS NOT NULL THEN 'Exact match'
                WHEN q_norm.permission_string IS NOT NULL THEN 'Normalized-only'
                ELSE 'No match'
            END AS queue_match_semantics_label,
            CASE
                WHEN q.permission_string IS NULL
                 AND q_norm.permission_string IS NOT NULL THEN 'Case-form drift'
                ELSE NULL
            END AS queue_match_warning,
            CASE
                WHEN q.permission_string IS NOT NULL THEN 'Exact ledger anchor'
                WHEN q_norm.permission_string IS NOT NULL THEN 'Normalized ledger anchor'
                ELSE 'No ledger anchor'
            END AS queue_anchor_label,
            u.seen_count,
            u.first_seen_at_utc,
            u.last_seen_at_utc,
            {$riskExpr} AS risk_hint
        {$fromSql}
        {$whereSql}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($dataSql);
    $dataParams = $params;
    $dataParams['limit'] = $pageSize;
    $dataParams['offset'] = $offset;
    bind_named_params($stmt, $dataParams);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if ($skipCount) {
        $totalCount = count($rows);
        $totalPages = 1;
    } else {
        $countFromSql = $needsQueueJoinForFilter
            ? $fromSql
            : "FROM {$dictUnknown} u";
        $countRow = db_one(
            "SELECT COUNT(*) AS total_count {$countFromSql} {$whereSql}",
            $params
        ) ?? [];
        $totalCount = (int)($countRow['total_count'] ?? 0);
        $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;
    }

    return [
        'rows' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
            'sort' => $sort,
        ],
    ];
}

function db_android_permission_current_unknown_review_page(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(10, min((int)($filters['page_size'] ?? 100), 500));
    $offset = max(0, ($page - 1) * $pageSize);
    $skipCount = (bool)($filters['skip_count'] ?? false);
    $laneScope = strtolower(trim((string)($filters['lane_scope'] ?? 'active')));

    $where = [];
    $params = [];

    $term = trim((string)($filters['q'] ?? ''));
    if ($term !== '') {
        $where[] = "(r.permission_string LIKE :q_permission OR r.namespace LIKE :q_namespace OR COALESCE(r.dict_unknown_triage_status, '') LIKE :q_status OR r.review_lane_label LIKE :q_lane)";
        $likeValue = '%' . $term . '%';
        $params['q_permission'] = $likeValue;
        $params['q_namespace'] = $likeValue;
        $params['q_status'] = $likeValue;
        $params['q_lane'] = $likeValue;
    }

    $namespace = trim((string)($filters['namespace'] ?? ''));
    if ($namespace !== '') {
        $where[] = "r.namespace = :namespace";
        $params['namespace'] = $namespace;
    }

    $risk = strtolower(trim((string)($filters['risk'] ?? '')));
    if (in_array($risk, ['high', 'medium', 'low'], true)) {
        $where[] = "r.risk_hint = :risk";
        $params['risk'] = $risk;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $allowedStatuses = array_map('strtolower', perm_triage_status_keys());
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $where[] = "COALESCE(r.dict_unknown_triage_status, '') = :triage_status";
        $params['triage_status'] = $status;
    }

    $queued = strtolower(trim((string)($filters['queued'] ?? '')));
    $allowedQueue = array_map('strtolower', perm_queue_status_keys());
    if ($queued !== '' && in_array($queued, $allowedQueue, true)) {
        $where[] = "COALESCE(r.queue_status, '') = :queue_status";
        $params['queue_status'] = $queued;
    }

    if ($laneScope === 'active') {
        $where[] = "r.review_lane_label = :lane_active";
        $params['lane_active'] = 'active_review_candidate';
    } elseif ($laneScope === 'governed') {
        add_where_in(
            $where,
            $params,
            'r.review_lane_label',
            'review_lane',
            [
                'governed_launcher_ecosystem',
                'governed_known_google',
                'malformed_or_conflict',
                'resolved_or_dictionary_known',
                'missing_ledger_context',
            ]
        );
    }

    $baseSql = sql_android_permission_current_unknown_review_page();
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $dataSql = "
        SELECT *
        FROM ({$baseSql}) r
        {$whereSql}
        ORDER BY
            r.current_unknown_samples DESC,
            r.current_unknown_obs_rows DESC,
            r.last_observed_at_utc DESC,
            r.permission_string ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($dataSql);
    $dataParams = $params;
    $dataParams['limit'] = $pageSize;
    $dataParams['offset'] = $offset;
    bind_named_params($stmt, $dataParams);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status'] = $row['dict_unknown_triage_status'] ?? null;
        $row['triage_status_display'] = perm_triage_status_label((string)($row['dict_unknown_triage_status'] ?? ''));
        $row['seen_count'] = (int)($row['historical_ledger_seen_count'] ?? 0);
        $row['last_seen_at_utc'] = $row['last_observed_at_utc'] ?? null;
    }
    unset($row);

    if ($skipCount) {
        $totalCount = count($rows);
        $totalPages = 1;
    } else {
        $countRow = db_one(
            "SELECT COUNT(*) AS total_count FROM ({$baseSql}) r {$whereSql}",
            $params
        ) ?? [];
        $totalCount = (int)($countRow['total_count'] ?? 0);
        $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;
    }

    return [
        'rows' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
            'lane_scope' => $laneScope,
            'sort' => 'current_unknown_samples_desc',
        ],
    ];
}

function db_android_permission_ledger_diagnostics_page(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(10, min((int)($filters['page_size'] ?? 100), 500));
    $offset = max(0, ($page - 1) * $pageSize);
    $skipCount = (bool)($filters['skip_count'] ?? false);

    $where = ["r.diagnostic_label IS NOT NULL"];
    $params = [];

    $term = trim((string)($filters['q'] ?? ''));
    if ($term !== '') {
        $where[] = "(r.permission_string LIKE :q_permission OR r.namespace LIKE :q_namespace OR COALESCE(r.triage_status, '') LIKE :q_status OR r.diagnostic_label LIKE :q_diag)";
        $likeValue = '%' . $term . '%';
        $params['q_permission'] = $likeValue;
        $params['q_namespace'] = $likeValue;
        $params['q_status'] = $likeValue;
        $params['q_diag'] = $likeValue;
    }

    $namespace = trim((string)($filters['namespace'] ?? ''));
    if ($namespace !== '') {
        $where[] = "r.namespace = :namespace";
        $params['namespace'] = $namespace;
    }

    $risk = strtolower(trim((string)($filters['risk'] ?? '')));
    if (in_array($risk, ['high', 'medium', 'low'], true)) {
        $where[] = "r.risk_hint = :risk";
        $params['risk'] = $risk;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $allowedStatuses = array_map('strtolower', perm_triage_status_keys());
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $where[] = "COALESCE(r.triage_status, '') = :triage_status";
        $params['triage_status'] = $status;
    }

    $queued = strtolower(trim((string)($filters['queued'] ?? '')));
    $allowedQueue = array_map('strtolower', perm_queue_status_keys());
    if ($queued !== '' && in_array($queued, $allowedQueue, true)) {
        $where[] = "COALESCE(r.queue_status, '') = :queue_status";
        $params['queue_status'] = $queued;
    }

    $sort = strtolower(trim((string)($filters['sort'] ?? 'seen_desc')));
    $sortMap = [
        'seen_desc' => "r.historical_ledger_seen_count DESC, r.permission_string ASC",
        'seen_asc' => "r.historical_ledger_seen_count ASC, r.permission_string ASC",
        'last_seen_desc' => "r.last_seen_at_utc DESC, r.permission_string ASC",
        'last_seen_asc' => "r.last_seen_at_utc ASC, r.permission_string ASC",
        'permission_asc' => "r.permission_string ASC",
        'permission_desc' => "r.permission_string DESC",
        'status_asc' => "r.triage_status ASC, r.historical_ledger_seen_count DESC",
        'status_desc' => "r.triage_status DESC, r.historical_ledger_seen_count DESC",
        'namespace_asc' => "r.namespace ASC, r.permission_string ASC",
        'namespace_desc' => "r.namespace DESC, r.permission_string ASC",
        'risk_desc' => "CASE r.risk_hint WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC, r.historical_ledger_seen_count DESC",
        'risk_asc' => "CASE r.risk_hint WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC, r.historical_ledger_seen_count DESC",
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['seen_desc'];

    $baseSql = sql_android_permission_ledger_diagnostics_page();
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $dataSql = "
        SELECT *
        FROM ({$baseSql}) r
        {$whereSql}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($dataSql);
    $dataParams = $params;
    $dataParams['limit'] = $pageSize;
    $dataParams['offset'] = $offset;
    bind_named_params($stmt, $dataParams);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status_display'] = perm_triage_status_label((string)($row['triage_status'] ?? ''));
        $row['seen_count'] = (int)($row['historical_ledger_seen_count'] ?? 0);
    }
    unset($row);

    if ($skipCount) {
        $totalCount = count($rows);
        $totalPages = 1;
    } else {
        $countRow = db_one(
            "SELECT COUNT(*) AS total_count FROM ({$baseSql}) r {$whereSql}",
            $params
        ) ?? [];
        $totalCount = (int)($countRow['total_count'] ?? 0);
        $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;
    }

    return [
        'rows' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
            'sort' => $sort,
        ],
    ];
}

function db_android_permission_ledger_diagnostics_overview(int $limit = 10): array
{
    $limit = max(1, min($limit, 25));
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $riskExpr = db_android_permission_risk_expr('u.permission_string', $namespaceExpr);
    $riskReasonExpr = db_android_permission_risk_reason_expr('u.permission_string', $namespaceExpr);
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $unknownCondition = sql_android_unknown_condition('classification');
    $diagnosticPriorityExpr = "
        CASE r.diagnostic_label
            WHEN 'recent_ledger_without_evidence' THEN 1
            WHEN 'orphan_ledger_row' THEN 2
            WHEN 'ledger_only_no_evidence' THEN 3
            WHEN 'governed_historical_residue' THEN 4
            WHEN 'resolved_high_seen_historical' THEN 5
            ELSE 6
        END
    ";

    $sql = "
        SELECT *
        FROM (
            SELECT
                u.permission_string,
                {$namespaceExpr} AS namespace,
                {$riskExpr} AS risk_hint,
                {$riskReasonExpr} AS risk_reason,
                u.triage_status,
                COALESCE(u.seen_count, 0) AS historical_ledger_seen_count,
                u.first_seen_at_utc,
                u.last_seen_at_utc,
                COALESCE(obs.current_total_samples, 0) AS current_total_samples,
                COALESCE(obs.current_unknown_samples, 0) AS current_unknown_samples,
                COALESCE(evt.vt_event_count, 0) AS vt_event_count,
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
            END AS diagnostic_label
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
                    COUNT(*) AS vt_event_count
                FROM {$vtEvent}
                GROUP BY permission_string
            ) evt
              ON BINARY evt.permission_string = BINARY u.permission_string
        ) r
        WHERE r.diagnostic_label IS NOT NULL
          AND NOT (
              r.current_unknown_samples = 0
              AND r.current_total_samples > 0
              AND COALESCE(r.triage_status, '') IN ('resolved_aosp', 'resolved_oem', 'gms_known', 'launcher_ecosystem')
          )
        ORDER BY {$diagnosticPriorityExpr} ASC, r.historical_ledger_seen_count DESC, r.permission_string ASC
        LIMIT :limit
    ";

    $stmt = db()->prepare($sql);
    bind_named_params($stmt, ['limit' => $limit]);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status_display'] = perm_triage_status_label((string)($row['triage_status'] ?? ''));
        $row['seen_count'] = (int)($row['historical_ledger_seen_count'] ?? 0);
    }
    unset($row);

    return [
        'rows' => $rows,
        'meta' => [
            'page' => 1,
            'page_size' => $limit,
            'total_count' => count($rows),
            'total_pages' => 1,
            'has_more' => false,
            'sort' => 'seen_desc',
        ],
    ];
}

function db_android_permission_ledger_diagnostics_page_fast(array $filters): array
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $riskExpr = db_android_permission_risk_expr('u.permission_string', $namespaceExpr);
    $riskReasonExpr = db_android_permission_risk_reason_expr('u.permission_string', $namespaceExpr);
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $obsSample = db_catalog_table('android_permission_obs_sample');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');
    $latestQueue = sql_android_permission_latest_queue_subquery();
    $unknownCondition = sql_android_unknown_condition('classification');
    $diagnosticPriorityExpr = "
        CASE r.diagnostic_label
            WHEN 'recent_ledger_without_evidence' THEN 1
            WHEN 'orphan_ledger_row' THEN 2
            WHEN 'ledger_only_no_evidence' THEN 3
            WHEN 'governed_historical_residue' THEN 4
            WHEN 'resolved_high_seen_historical' THEN 5
            ELSE 6
        END
    ";

    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(10, min((int)($filters['page_size'] ?? 100), 500));
    $offset = max(0, ($page - 1) * $pageSize);
    $skipCount = (bool)($filters['skip_count'] ?? false);

    $baseSql = "
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
            CASE WHEN COALESCE(evt.vt_event_count, 0) > 0 THEN 1 ELSE 0 END AS has_vt_event,
            COALESCE(obs.current_unknown_samples, 0) AS current_unknown_samples,
            q.queue_status,
            q.queue_action,
            q.queue_updated_at_utc,
            q.queue_processed_at_utc,
            q.queue_error_message,
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
            END AS diagnostic_label
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
                COUNT(*) AS vt_event_count
            FROM {$vtEvent}
            GROUP BY permission_string
        ) evt
          ON BINARY evt.permission_string = BINARY u.permission_string
        LEFT JOIN (
            {$latestQueue}
        ) q
          ON BINARY q.permission_string = BINARY u.permission_string
    ";

    $where = ["r.diagnostic_label IS NOT NULL"];
    $where[] = "NOT (
        r.current_unknown_samples = 0
        AND r.has_obs_sample = 1
        AND COALESCE(r.triage_status, '') IN ('resolved_aosp', 'resolved_oem', 'gms_known', 'launcher_ecosystem')
    )";
    $params = [];

    $term = trim((string)($filters['q'] ?? ''));
    if ($term !== '') {
        $where[] = "(r.permission_string LIKE :q_permission OR r.namespace LIKE :q_namespace OR COALESCE(r.triage_status, '') LIKE :q_status OR r.diagnostic_label LIKE :q_diag)";
        $likeValue = '%' . $term . '%';
        $params['q_permission'] = $likeValue;
        $params['q_namespace'] = $likeValue;
        $params['q_status'] = $likeValue;
        $params['q_diag'] = $likeValue;
    }

    $namespace = trim((string)($filters['namespace'] ?? ''));
    if ($namespace !== '') {
        $where[] = "r.namespace = :namespace";
        $params['namespace'] = $namespace;
    }

    $risk = strtolower(trim((string)($filters['risk'] ?? '')));
    if (in_array($risk, ['high', 'medium', 'low'], true)) {
        $where[] = "r.risk_hint = :risk";
        $params['risk'] = $risk;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $allowedStatuses = array_map('strtolower', perm_triage_status_keys());
    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $where[] = "COALESCE(r.triage_status, '') = :triage_status";
        $params['triage_status'] = $status;
    }

    $queued = strtolower(trim((string)($filters['queued'] ?? '')));
    $allowedQueue = array_map('strtolower', perm_queue_status_keys());
    if ($queued !== '' && in_array($queued, $allowedQueue, true)) {
        $where[] = "COALESCE(r.queue_status, '') = :queue_status";
        $params['queue_status'] = $queued;
    }

    $sort = strtolower(trim((string)($filters['sort'] ?? 'seen_desc')));
    $sortMap = [
        'seen_desc' => "{$diagnosticPriorityExpr} ASC, r.historical_ledger_seen_count DESC, r.permission_string ASC",
        'seen_asc' => "r.historical_ledger_seen_count ASC, r.permission_string ASC",
        'last_seen_desc' => "r.last_seen_at_utc DESC, r.permission_string ASC",
        'last_seen_asc' => "r.last_seen_at_utc ASC, r.permission_string ASC",
        'permission_asc' => "r.permission_string ASC",
        'permission_desc' => "r.permission_string DESC",
        'status_asc' => "r.triage_status ASC, r.historical_ledger_seen_count DESC",
        'status_desc' => "r.triage_status DESC, r.historical_ledger_seen_count DESC",
        'namespace_asc' => "r.namespace ASC, r.permission_string ASC",
        'namespace_desc' => "r.namespace DESC, r.permission_string ASC",
        'risk_desc' => "CASE r.risk_hint WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC, r.historical_ledger_seen_count DESC",
        'risk_asc' => "CASE r.risk_hint WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC, r.historical_ledger_seen_count DESC",
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['seen_desc'];

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $dataSql = "
        SELECT *
        FROM ({$baseSql}) r
        {$whereSql}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($dataSql);
    $dataParams = $params;
    $dataParams['limit'] = $pageSize;
    $dataParams['offset'] = $offset;
    bind_named_params($stmt, $dataParams);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status_display'] = perm_triage_status_label((string)($row['triage_status'] ?? ''));
        $row['seen_count'] = (int)($row['historical_ledger_seen_count'] ?? 0);
    }
    unset($row);

    if ($skipCount) {
        $totalCount = count($rows);
        $totalPages = 1;
    } else {
        $countRow = db_one(
            "SELECT COUNT(*) AS total_count FROM ({$baseSql}) r {$whereSql}",
            $params
        ) ?? [];
        $totalCount = (int)($countRow['total_count'] ?? 0);
        $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;
    }

    return [
        'rows' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
            'sort' => $sort,
        ],
    ];
}

function db_android_permission_new_risk_counts(): array
{
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $riskExpr = db_android_permission_risk_expr('u.permission_string', $namespaceExpr);
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $row = db_one("
        SELECT
            SUM(CASE WHEN {$riskExpr} = 'high' THEN 1 ELSE 0 END) AS high_count,
            SUM(CASE WHEN {$riskExpr} = 'medium' THEN 1 ELSE 0 END) AS medium_count,
            SUM(CASE WHEN {$riskExpr} = 'low' THEN 1 ELSE 0 END) AS low_count
        FROM {$dictUnknown} u
        WHERE u.triage_status = 'new'
          AND LOWER(TRIM(u.permission_string)) NOT LIKE '%.dynamic_receiver_not_exported_permission'
    ") ?? [];

    return [
        'high' => (int)($row['high_count'] ?? 0),
        'medium' => (int)($row['medium_count'] ?? 0),
        'low' => (int)($row['low_count'] ?? 0),
    ];
}

function db_android_permission_current_evidence_risk_counts(): array
{
    $baseSql = sql_android_permission_current_unknown_review_page();
    $rows = db_all("
        SELECT risk_hint, COUNT(*) AS cnt
        FROM ({$baseSql}) r
        WHERE r.review_lane_label = 'active_review_candidate'
        GROUP BY risk_hint
    ");

    $counts = [
        'high' => 0,
        'medium' => 0,
        'low' => 0,
    ];
    foreach ($rows as $row) {
        $key = strtolower((string)($row['risk_hint'] ?? ''));
        if (!array_key_exists($key, $counts)) {
            continue;
        }
        $counts[$key] = (int)($row['cnt'] ?? 0);
    }
    return $counts;
}

function db_android_permission_intelligence(int $unknownLimit = 100, int $namespaceLimit = 100, array $triageFilters = [], string $mode = 'full'): array
{
    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['full', 'overview', 'triage', 'drift'], true)) {
        $mode = 'full';
    }

    $warnings = [];
    $namespaceSource = 'vt_event';
    $namespaceReason = null;
    $health = db_one(sql_android_permission_health_totals()) ?? [];
    $currentUnknownSummary = db_one(sql_android_permission_current_unknown_summary()) ?? [];
    $trend = db_one(sql_android_permission_trend()) ?? [];
    $unknownFilters = array_merge([
        'page' => 1,
        'page_size' => $unknownLimit,
        'sort' => 'seen_desc',
        'include_resolved' => true,
        'skip_count' => true,
        'actionable_statuses' => perm_actionable_triage_status_keys(),
    ], $triageFilters);
    $bucketRows = [];
    $unknownPage = ['rows' => [], 'meta' => ['page' => 1, 'page_size' => 0, 'total_count' => 0, 'total_pages' => 1, 'has_more' => false, 'sort' => 'seen_desc']];
    $unknownRows = [];
    $actionableReviewPage = ['rows' => [], 'meta' => ['page' => 1, 'page_size' => 0, 'total_count' => 0, 'total_pages' => 1, 'has_more' => false, 'sort' => 'seen_desc']];
    $actionableReviewRows = [];
    $currentEvidenceReviewPage = ['rows' => [], 'meta' => ['page' => 1, 'page_size' => 0, 'total_count' => 0, 'total_pages' => 1, 'has_more' => false, 'lane_scope' => 'active', 'sort' => 'current_unknown_samples_desc']];
    $currentEvidenceReviewRows = [];
    $governedCurrentUnknownPage = ['rows' => [], 'meta' => ['page' => 1, 'page_size' => 0, 'total_count' => 0, 'total_pages' => 1, 'has_more' => false, 'lane_scope' => 'governed', 'sort' => 'current_unknown_samples_desc']];
    $governedCurrentUnknownRows = [];
    $ledgerDiagnosticPage = ['rows' => [], 'meta' => ['page' => 1, 'page_size' => 0, 'total_count' => 0, 'total_pages' => 1, 'has_more' => false, 'sort' => 'seen_desc']];
    $ledgerDiagnosticRows = [];

    $includeLegacyLists = ($mode === 'full');
    $includeOverviewRows = in_array($mode, ['full', 'overview'], true);
    $includeTriageRows = in_array($mode, ['full', 'triage'], true);
    $includeNamespaceDrift = in_array($mode, ['full', 'drift'], true);
    $includeRollupGuard = ($mode === 'full');
    $triageView = strtolower(trim((string)($triageFilters['triage_view'] ?? 'active')));
    if (!in_array($triageView, ['active', 'governed', 'ledger'], true)) {
        $triageView = 'active';
    }
    $embeddedRowLimit = ($mode === 'overview')
        ? min($unknownLimit, 5)
        : min($unknownLimit, 10);

    if ($mode !== 'drift') {
        $bucketRows = db_all(sql_android_permission_bucket_distribution());
    }

    if ($includeLegacyLists) {
        $unknownPage = db_android_permission_unknowns_page($unknownFilters);
        $unknownRows = $unknownPage['rows'];
        $actionableReviewPage = db_android_permission_unknowns_page(array_merge($unknownFilters, [
            'include_resolved' => false,
            'actionable_statuses' => perm_review_triage_status_keys(),
        ]));
        $actionableReviewRows = $actionableReviewPage['rows'];
    }

    if ($includeOverviewRows) {
        $currentEvidenceReviewPage = db_android_permission_current_unknown_review_page_cached(array_merge($unknownFilters, [
            'lane_scope' => 'active',
            'page_size' => $embeddedRowLimit,
        ]), 180);
        $currentEvidenceReviewRows = $currentEvidenceReviewPage['rows'];
        $currentEvidenceReviewCountMeta = db_android_permission_current_unknown_review_page(array_merge($unknownFilters, [
            'lane_scope' => 'active',
            'page' => 1,
            'page_size' => 1,
            'skip_count' => false,
        ]));
        $currentEvidenceReviewPage = db_android_permission_page_total_meta(
            $currentEvidenceReviewPage,
            (int)($currentEvidenceReviewCountMeta['meta']['total_count'] ?? count($currentEvidenceReviewRows))
        );
        $governedCurrentUnknownPage = db_android_permission_current_unknown_review_page_cached(array_merge($unknownFilters, [
            'lane_scope' => 'governed',
            'page_size' => $embeddedRowLimit,
        ]), 180);
        $governedCurrentUnknownRows = $governedCurrentUnknownPage['rows'];
        $governedCurrentUnknownCountMeta = db_android_permission_current_unknown_review_page(array_merge($unknownFilters, [
            'lane_scope' => 'governed',
            'page' => 1,
            'page_size' => 1,
            'skip_count' => false,
        ]));
        $governedCurrentUnknownPage = db_android_permission_page_total_meta(
            $governedCurrentUnknownPage,
            (int)($governedCurrentUnknownCountMeta['meta']['total_count'] ?? count($governedCurrentUnknownRows))
        );
        $ledgerDiagnosticPage = db_android_permission_ledger_diagnostics_overview_cached($embeddedRowLimit, 180);
        $ledgerDiagnosticRows = $ledgerDiagnosticPage['rows'];
        $ledgerDiagnosticCountMeta = db_android_permission_ledger_diagnostics_page_fast([
            'page' => 1,
            'page_size' => 1,
            'skip_count' => false,
        ]);
        $ledgerDiagnosticPage = db_android_permission_page_total_meta(
            $ledgerDiagnosticPage,
            (int)($ledgerDiagnosticCountMeta['meta']['total_count'] ?? count($ledgerDiagnosticRows))
        );
    } elseif ($includeTriageRows) {
        $currentEvidenceReviewPage = db_android_permission_current_unknown_review_page(array_merge($unknownFilters, [
            'lane_scope' => 'active',
        ]));
        $currentEvidenceReviewRows = $currentEvidenceReviewPage['rows'];
        if ($triageView === 'governed') {
            $governedCurrentUnknownPage = db_android_permission_current_unknown_review_page_cached(array_merge($unknownFilters, [
                'lane_scope' => 'governed',
            ]), 120);
            $governedCurrentUnknownRows = $governedCurrentUnknownPage['rows'];
        }
        if ($triageView === 'ledger') {
            $ledgerDiagnosticPage = db_android_permission_ledger_diagnostics_page_fast_cached($unknownFilters, 120);
            $ledgerDiagnosticRows = $ledgerDiagnosticPage['rows'];
        }
    }

    $triageCountsRaw = db_all(sql_android_permission_triage_status_counts());
    $effectiveMetricsRow = db_one(sql_android_permission_effective_unknown_metrics()) ?? [];
    $queueActionCountsRaw = db_all(sql_android_permission_queue_action_counts());
    $namespaceRows = [];
    if ($includeNamespaceDrift) {
        try {
            $eventCountRow = db_one(sql_android_permission_enrich_vt_event_count()) ?? [];
            $eventCount = (int)($eventCountRow['event_count'] ?? 0);
            if ($eventCount === 0) {
                $namespaceSource = 'obs_sample';
                $namespaceReason = 'vt_event_empty';
                $namespaceRows = db_all(sql_android_permission_namespace_drift_obs_sample($namespaceLimit));
            } else {
                $namespaceRows = db_all(sql_android_permission_namespace_drift($namespaceLimit));
            }
        } catch (Throwable $e) {
            $namespaceRows = [];
            $warnings[] = 'namespace_drift_failed';
            $namespaceSource = 'unavailable';
            $namespaceReason = 'namespace_drift_failed';
        }
        foreach ($namespaceRows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $class = perm_namespace_class_for((string)($row['namespace'] ?? ''));
            $profile = perm_namespace_review_profile_for((string)($row['namespace'] ?? ''));
            $row['namespace_class'] = $class['key'];
            $row['namespace_class_label'] = $class['label'];
            $row['namespace_class_name'] = $class['class_name'];
            $row['review_bucket'] = $profile['review_bucket'];
            $row['validation_label'] = $profile['validation_label'];
            $row['review_hint'] = $profile['review_hint'];
        }
        unset($row);
    }
    $newUnknowns = db_one(sql_android_permission_new_unknowns_24h()) ?? [];
    $newNamespaces = db_one(sql_android_permission_new_namespaces_7d()) ?? [];
    $securityUnknowns = db_one(sql_android_permission_security_sensitive_unknowns()) ?? [];
    $taxonomyRow = db_one(sql_perm_taxonomy_latest());
    $queueMetrics = ($mode === 'full')
        ? (db_one(sql_android_permission_queue_metrics()) ?? [])
        : db_android_permission_queue_metrics_cached(180);
    $rollupGuard = $includeRollupGuard
        ? db_android_permission_rollup_guard()
        : [
            'stale_permissions_count' => 0,
            'stale_count_mismatch_count' => 0,
            'max_lag_seconds' => null,
            'max_lag_days' => null,
            'sample' => [],
            'sample_limit' => 0,
        ];

    $total = (int)($health['total_count'] ?? 0);
    $unknownCount = (int)($health['unknown_count'] ?? 0);
    $knownCount = (int)($health['known_count'] ?? 0);
    $currentUnknownObsRows = (int)($currentUnknownSummary['current_unknown_obs_rows'] ?? $unknownCount);
    $currentUnknownSamples = (int)($currentUnknownSummary['current_unknown_samples'] ?? 0);
    $currentUnknownPermissions = (int)($currentUnknownSummary['current_unknown_permissions'] ?? 0);
    if ($total === 0) {
        $total = $knownCount + $unknownCount;
    }

    $unknownPct = $total > 0 ? round(($unknownCount / $total) * 100, 2) : 0.0;
    $knownPct = $total > 0 ? round(($knownCount / $total) * 100, 2) : 0.0;

    $total7d = (int)($trend['total_7d'] ?? 0);
    $unknown7d = (int)($trend['unknown_7d'] ?? 0);
    $totalPrev7d = (int)($trend['total_prev_7d'] ?? 0);
    $unknownPrev7d = (int)($trend['unknown_prev_7d'] ?? 0);

    $unknownPct7d = $total7d > 0 ? round(($unknown7d / $total7d) * 100, 2) : 0.0;
    $unknownPctPrev7d = $totalPrev7d > 0 ? round(($unknownPrev7d / $totalPrev7d) * 100, 2) : 0.0;
    $unknownPctDelta = $totalPrev7d > 0 ? round($unknownPct7d - $unknownPctPrev7d, 2) : null;

    $bucketMap = perm_bucket_label_map();
    $bucketDefs = perm_bucket_definitions();
    $bucketTotals = [];
    foreach ($bucketRows as $row) {
        $key = perm_bucket_key((string)($row['bucket_key'] ?? ''));
        $label = $bucketMap[$key] ?? perm_bucket_label((string)($row['bucket_key'] ?? ''));
        $bucketTotals[] = [
            'bucket_key' => $key,
            'bucket_label' => $label,
            'perm_count' => (int)($row['perm_count'] ?? 0),
        ];
    }

    $triageCounts = [];
    foreach ($triageCountsRaw as $row) {
        $key = strtolower((string)($row['triage_status'] ?? ''));
        if ($key === '') {
            continue;
        }
        $triageCounts[$key] = (int)($row['cnt'] ?? 0);
    }
    $triageDisplayCounts = perm_display_triage_status_counts($triageCounts);
    foreach ($unknownRows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status_display'] = perm_triage_status_label((string)($row['triage_status'] ?? ''));
    }
    unset($row);
    foreach ($actionableReviewRows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['triage_status_display'] = perm_triage_status_label((string)($row['triage_status'] ?? ''));
    }
    unset($row);
    $unknownLedgerCount = array_sum($triageCounts);
    $ledgerInventoryRows = $unknownLedgerCount;
    $rawUnknown = (int)($effectiveMetricsRow['raw_unknown'] ?? $unknownLedgerCount);
    $effectiveUnknown = (int)($effectiveMetricsRow['effective_unknown'] ?? $unknownLedgerCount);
    $oemAlreadyResolvedNotRetagged = (int)($effectiveMetricsRow['oem_already_resolved_not_retagged'] ?? 0);
    $resolvedOemCount = (int)($triageCounts['resolved_oem'] ?? 0);
    $newRiskCounts = db_android_permission_new_risk_counts();
    $currentEvidenceRiskCounts = ($mode === 'triage')
        ? []
        : (($mode === 'full')
            ? db_android_permission_current_evidence_risk_counts()
            : db_android_permission_current_evidence_risk_counts_cached(180));
    $launcherEcosystemUnknowns = (int)($triageCounts['launcher_ecosystem'] ?? 0);
    $appDefinedUnknowns = (int)($triageCounts['app_defined'] ?? 0);
    $resolvedAospCount = (int)($triageCounts['resolved_aosp'] ?? 0);
    $resolvedUnknowns = $resolvedAospCount + $resolvedOemCount;
    // Truth surface should follow the effective burden metric, not raw status counts,
    // because some known recurrence noise is intentionally suppressed from operator backlog.
    $actionableWorkflowUnknowns = perm_actionable_workflow_unknown_count($effectiveUnknown);
    $ledgerActionableStatusRows = $actionableWorkflowUnknowns;
    $ledgerUnresolvedCompatRows = $effectiveUnknown;
    $explainedWorkflowUnknowns = max(0, $unknownLedgerCount - $actionableWorkflowUnknowns);
    $currentEvidenceReviewBacklog = (int)($currentEvidenceReviewPage['meta']['total_count'] ?? count($currentEvidenceReviewRows));
    $governedCurrentUnknownBacklog = (int)($governedCurrentUnknownPage['meta']['total_count'] ?? count($governedCurrentUnknownRows));
    $currentEvidenceBacklog = $currentEvidenceReviewBacklog + $governedCurrentUnknownBacklog;
    $ledgerDiagnosticBacklog = (int)($ledgerDiagnosticPage['meta']['total_count'] ?? count($ledgerDiagnosticRows));

    $taxonomyVersion = $taxonomyRow['perm_taxonomy_version'] ?? null;
    $taxonomyTimestamp = $taxonomyRow['finished_at_utc'] ?? null;
    $configuredTriageStatuses = array_map('strtolower', perm_triage_status_keys());
    $operatorTriageStatuses = array_map('strtolower', perm_extract_keys(perm_operator_triage_statuses()));
    $liveTriageStatuses = array_keys($triageCounts);
    $unexpectedLiveTriageStatuses = array_values(array_diff($liveTriageStatuses, $configuredTriageStatuses));
    $queueActionCounts = [];
    $queueActionCountsNormalized = [];
    foreach ($queueActionCountsRaw as $row) {
        $rawAction = strtolower(trim((string)($row['queue_action'] ?? '')));
        if ($rawAction === '') {
            continue;
        }
        $count = (int)($row['cnt'] ?? 0);
        $queueActionCounts[$rawAction] = $count;
        $normalizedAction = perm_normalize_queue_action($rawAction);
        $queueActionCountsNormalized[$normalizedAction] = (int)($queueActionCountsNormalized[$normalizedAction] ?? 0) + $count;
    }
    $workflowDebt = db_platform_workflow_debt_summary();
    $legacyQueueActionsActive = is_array($workflowDebt['legacy_queue_actions_active'] ?? null)
        ? $workflowDebt['legacy_queue_actions_active']
        : [];
    $legacyQueueActionsTotal = is_array($workflowDebt['legacy_queue_actions_total'] ?? null)
        ? $workflowDebt['legacy_queue_actions_total']
        : [];

    return [
        'data' => [
            'taxonomy' => [
                'version' => $taxonomyVersion,
                'updated_at_utc' => $taxonomyTimestamp,
                'buckets' => $bucketDefs,
            ],
            'health' => [
                'known_count' => $knownCount,
                'unknown_count' => $currentUnknownObsRows,
                'total_count' => $total,
                'known_pct' => $knownPct,
                'unknown_pct' => $unknownPct,
                'current_unknown_obs_rows' => $currentUnknownObsRows,
                'current_unknown_samples' => $currentUnknownSamples,
                'current_unknown_permissions' => $currentUnknownPermissions,
                'current_evidence_review_backlog' => $currentEvidenceReviewBacklog,
                'current_evidence_backlog' => $currentEvidenceBacklog,
                'governed_current_unknown_backlog' => $governedCurrentUnknownBacklog,
                'unknown_dict_count' => $ledgerInventoryRows,
                'ledger_inventory_rows' => $ledgerInventoryRows,
                'actionable_review_backlog' => $currentEvidenceReviewBacklog,
                'ledger_actionable_status_rows' => $ledgerActionableStatusRows,
                'workflow_unknown_backlog' => $currentEvidenceBacklog,
                'ledger_unresolved_compat_rows' => $ledgerUnresolvedCompatRows,
                'raw_unknown' => $rawUnknown,
                'effective_unknown' => $effectiveUnknown,
                'effective_unknown_compat_legacy' => $effectiveUnknown,
                'resolved_oem_count' => $resolvedOemCount,
                'oem_already_resolved_not_retagged' => $oemAlreadyResolvedNotRetagged,
                'last_observed_at_utc' => $health['last_observed_at_utc'] ?? null,
                'last_taxonomy_refresh_at_utc' => $taxonomyTimestamp,
                'unknown_pct_7d' => $unknownPct7d,
                'unknown_pct_prev_7d' => $unknownPctPrev7d,
                'unknown_pct_delta' => $unknownPctDelta,
                'total_7d' => $total7d,
                'unknown_7d' => $unknown7d,
            ],
            'bucket_distribution' => $bucketTotals,
            'unknown_permissions' => $unknownRows,
            'unknown_page' => $unknownPage['meta'],
            'actionable_review_rows' => $actionableReviewRows,
            'actionable_review_page' => $actionableReviewPage['meta'],
            'current_evidence_review_rows' => $currentEvidenceReviewRows,
            'current_evidence_review_page' => $currentEvidenceReviewPage['meta'],
            'governed_current_unknown_rows' => $governedCurrentUnknownRows,
            'governed_current_unknown_page' => $governedCurrentUnknownPage['meta'],
            'ledger_diagnostic_rows' => $ledgerDiagnosticRows,
            'ledger_diagnostic_page' => $ledgerDiagnosticPage['meta'],
            'triage_status_counts' => $triageCounts,
            'triage_status_counts_display' => $triageDisplayCounts,
            'metrics' => [
                'current_unknown_obs_rows' => $currentUnknownObsRows,
                'current_unknown_samples' => $currentUnknownSamples,
                'current_unknown_permissions' => $currentUnknownPermissions,
                'current_evidence_review_backlog' => $currentEvidenceReviewBacklog,
                'current_evidence_backlog' => $currentEvidenceBacklog,
                'governed_current_unknown_backlog' => $governedCurrentUnknownBacklog,
                'ledger_diagnostic_backlog' => $ledgerDiagnosticBacklog,
                'ledger_inventory_rows' => $ledgerInventoryRows,
                'ledger_actionable_status_rows' => $ledgerActionableStatusRows,
                'ledger_unresolved_compat_rows' => $ledgerUnresolvedCompatRows,
                'raw_unknown' => $rawUnknown,
                'actionable_review_backlog' => $currentEvidenceReviewBacklog,
                'workflow_unknown_backlog' => $currentEvidenceBacklog,
                'effective_unknown' => $effectiveUnknown,
                'effective_unknown_compat_legacy' => $effectiveUnknown,
                'resolved_oem_count' => $resolvedOemCount,
                'resolved_aosp_count' => $resolvedAospCount,
                'oem_already_resolved_not_retagged' => $oemAlreadyResolvedNotRetagged,
                'triage_status_counts' => $triageCounts,
                'triage_status_counts_display' => $triageDisplayCounts,
                'queue_counts' => [
                    'queued' => (int)($queueMetrics['queued_current_unknown_count'] ?? 0),
                    'queued_current_unknown' => (int)($queueMetrics['queued_current_unknown_count'] ?? 0),
                    'queued_evidence_backed' => (int)($queueMetrics['queued_evidence_backed_count'] ?? 0),
                    'queued_static_no_anchor' => (int)($queueMetrics['queued_static_no_anchor_count'] ?? 0),
                    'queued_raw' => (int)($queueMetrics['queued_count'] ?? 0),
                    'applied' => (int)($queueMetrics['applied_count'] ?? 0),
                    'error' => (int)($queueMetrics['error_count'] ?? 0),
                    'rejected' => (int)($queueMetrics['rejected_count'] ?? 0),
                    'skipped' => (int)($queueMetrics['skipped_count'] ?? 0),
                ],
                'queue_action_counts' => $queueActionCountsNormalized,
                'current_evidence_risk_counts' => $currentEvidenceRiskCounts,
            ],
            'operator_summary' => [
                'actionable_review_backlog' => $currentEvidenceReviewBacklog,
                'workflow_unknown_backlog' => $currentEvidenceBacklog,
                'current_evidence_backlog' => $currentEvidenceBacklog,
                'workflow_unknown_backlog_raw' => $rawUnknown,
                'unknown_ledger_entries' => $unknownLedgerCount,
                'actionable_workflow_unknowns' => $actionableWorkflowUnknowns,
                'explained_workflow_unknowns' => $explainedWorkflowUnknowns,
                'current_evidence_review_backlog' => $currentEvidenceReviewBacklog,
                'governed_current_unknown_backlog' => $governedCurrentUnknownBacklog,
                'ledger_diagnostic_backlog' => $ledgerDiagnosticBacklog,
                'launcher_ecosystem_unknowns' => $launcherEcosystemUnknowns,
                'launcher_ecosystem_explained' => $launcherEcosystemUnknowns,
                'app_defined_unknowns' => $appDefinedUnknowns,
                'resolved_unknowns' => $resolvedUnknowns,
                'resolved_aosp_unknowns' => $resolvedAospCount,
                'resolved_oem_unknowns' => $resolvedOemCount,
                'triage_status_counts_display' => $triageDisplayCounts,
                'queued_dict_decisions' => (int)($queueMetrics['queued_current_unknown_count'] ?? 0),
                'queued_dict_decisions_raw' => (int)($queueMetrics['queued_count'] ?? 0),
                'queued_static_no_anchor' => (int)($queueMetrics['queued_static_no_anchor_count'] ?? 0),
                'applied_dict_decisions' => (int)($queueMetrics['applied_count'] ?? 0),
                'error_dict_decisions' => (int)($queueMetrics['error_count'] ?? 0),
                'effective_unknown_compat_legacy' => $effectiveUnknown,
            ],
            'session' => [
                'unknown_total' => $currentEvidenceBacklog,
                'unknown_total_raw' => $rawUnknown,
                'unknown_total_effective' => $currentEvidenceBacklog,
                'ledger_unknown_total_effective' => $effectiveUnknown,
                'actionable_review_backlog' => $currentEvidenceReviewBacklog,
                'current_evidence_review_backlog' => $currentEvidenceReviewBacklog,
                'current_evidence_backlog' => $currentEvidenceBacklog,
                'governed_current_unknown_backlog' => $governedCurrentUnknownBacklog,
                'ledger_diagnostic_backlog' => $ledgerDiagnosticBacklog,
                'resolved_oem_count' => $resolvedOemCount,
                'new_risk_counts' => $newRiskCounts,
                'current_evidence_risk_counts' => $currentEvidenceRiskCounts,
                'workflow_unknown_backlog' => $currentEvidenceBacklog,
                'actionable_workflow_unknowns' => $actionableWorkflowUnknowns,
            ],
            'contract' => [
                'permission_metrics_version' => '2026-05-17a',
                'unknown_total_source' => 'current_evidence_backlog',
                'operator_summary_source' => 'current_observation_truth_with_ledger_diagnostics',
            ],
            'status_model' => [
                'configured_triage_statuses' => $configuredTriageStatuses,
                'operator_triage_statuses' => $operatorTriageStatuses,
                'deprecated_configured_triage_statuses' => [],
                'live_triage_statuses' => $liveTriageStatuses,
                'deprecated_live_triage_statuses' => [],
                'unexpected_live_triage_statuses' => $unexpectedLiveTriageStatuses,
                'raw_queue_action_counts' => $queueActionCounts,
                'normalized_queue_action_counts' => $queueActionCountsNormalized,
                'legacy_queue_actions_total' => $legacyQueueActionsTotal,
                'legacy_queue_actions_active' => $legacyQueueActionsActive,
            ],
            'namespace_drift' => $namespaceRows,
            'maintenance' => [
                'new_unknowns_24h' => (int)($newUnknowns['new_unknowns_24h'] ?? 0),
                'new_namespaces_7d' => (int)($newNamespaces['new_namespaces_7d'] ?? 0),
                'security_sensitive_unknowns' => (int)($securityUnknowns['security_sensitive_unknowns'] ?? 0),
            ],
            'queue' => [
                'queued_count' => (int)($queueMetrics['queued_current_unknown_count'] ?? 0),
                'queued_current_unknown_count' => (int)($queueMetrics['queued_current_unknown_count'] ?? 0),
                'queued_evidence_backed_count' => (int)($queueMetrics['queued_evidence_backed_count'] ?? 0),
                'queued_static_no_anchor_count' => (int)($queueMetrics['queued_static_no_anchor_count'] ?? 0),
                'queued_raw_count' => (int)($queueMetrics['queued_count'] ?? 0),
                'applied_count' => (int)($queueMetrics['applied_count'] ?? 0),
                'error_count' => (int)($queueMetrics['error_count'] ?? 0),
                'rejected_count' => (int)($queueMetrics['rejected_count'] ?? 0),
                'skipped_count' => (int)($queueMetrics['skipped_count'] ?? 0),
                'last_queued_at_utc' => $queueMetrics['last_queued_at_utc'] ?? null,
                'last_current_unknown_queued_at_utc' => $queueMetrics['last_current_unknown_queued_at_utc'] ?? null,
                'last_applied_at_utc' => $queueMetrics['last_applied_at_utc'] ?? null,
                'last_error_at_utc' => $queueMetrics['last_error_at_utc'] ?? null,
            ],
            'rollup_guard' => $rollupGuard,
        ],
        'meta' => [
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
            'unknown_limit' => $unknownLimit,
            'namespace_limit' => $namespaceLimit,
            'mode' => $mode,
            'triage_view' => $triageView,
            'warnings' => $warnings,
            'namespace_drift_source' => $namespaceSource,
            'namespace_drift_reason' => $namespaceReason,
        ],
    ];
}

/**
 * Evidence rows for a permission string.
 */
function db_android_permission_evidence(string $permission, int $limit = 25): array
{
    $limit = max(1, min(200, $limit));
    $permission = trim($permission);
    if ($permission === '') {
        return [
            'data' => [],
            'meta' => [
                'limit' => $limit,
                'total_count' => 0,
            ],
        ];
    }

    $rows = db_all(sql_android_permission_evidence($limit), ['permission' => $permission]);
    $countRow = db_one(sql_android_permission_evidence_count(), ['permission' => $permission]) ?? [];

    return [
        'data' => $rows,
        'meta' => [
            'limit' => $limit,
            'total_count' => (int)($countRow['total_count'] ?? 0),
        ],
    ];
}

/**
 * Review payload for a single permission string.
 */
function db_android_permission_review(string $permission): array
{
    $permission = trim($permission);
    if ($permission === '') {
        return ['data' => null];
    }

    $row = db_one(sql_android_permission_review(), [
        'permission_main_exact_1' => $permission,
        'permission_main_exact_2' => $permission,
        'permission_main_exact_3' => $permission,
        'permission_main_exact_4' => $permission,
        'permission_main_exact_5' => $permission,
        'permission_main_exact_6' => $permission,
        'permission_main_exact_7' => $permission,
        'permission_main_normalized_1' => $permission,
        'permission_main_normalized_2' => $permission,
        'permission_main_normalized_3' => $permission,
        'permission_main_normalized_4' => $permission,
        'permission_main_normalized_5' => $permission,
        'permission_main' => $permission,
        'permission_stats_exact' => $permission,
        'permission_stats_normalized' => $permission,
        'permission_obs_exact' => $permission,
        'permission_obs_normalized' => $permission,
    ]);
    $taxonomy = db_one(sql_perm_taxonomy_latest()) ?? [];

    return [
        'data' => $row,
        'meta' => [
            'permission' => $permission,
            'taxonomy_version' => $taxonomy['perm_taxonomy_version'] ?? null,
            'taxonomy_updated_at_utc' => $taxonomy['finished_at_utc'] ?? null,
        ],
    ];
}

/**
 * Queue a dictionary update for a permission (triage -> pipeline).
 */
function db_queue_permission_update(
    string $permission,
    string $queueAction,
    ?string $proposedBucket = null,
    ?string $proposedClassification = null,
    ?string $triageStatus = null,
    ?string $notes = null,
    ?string $operator = null
): array {
    $permission = trim($permission);
    $queueAction = perm_normalize_queue_action($queueAction);
    $triageStatus = $triageStatus !== null ? strtolower(trim($triageStatus)) : null;
    $operator = trim((string)$operator);
    if ($operator === '') {
        $operator = 'web';
    }
    if ($permission === '') {
        return ['data' => ['queued' => 0]];
    }

    return db_tx(function () use (
        $permission,
        $queueAction,
        $proposedBucket,
        $proposedClassification,
        $triageStatus,
        $notes,
        $operator
    ): array {
        $unknown = db_one(sql_unknown_permission_by_string(), ['permission' => $permission]);
        if (!$unknown) {
            return [
                'data' => [
                    'queued' => 0,
                    'warnings' => ['not_found'],
                ],
            ];
        }

        $canonicalPermission = (string)($unknown['permission_string'] ?? $permission);

        $existing = db_one(sql_permission_queue_any_by_permission(), ['permission' => $canonicalPermission]);
        if ($existing && isset($existing['queue_id'])) {
            $queueId = (int)$existing['queue_id'];
            $previousStatus = strtolower((string)($existing['status'] ?? ''));
            $queued = db_exec(sql_permission_queue_requeue_by_id(), [
                'queue_id' => $queueId,
                'queue_action' => $queueAction,
                'proposed_bucket' => $proposedBucket,
                'proposed_classification' => $proposedClassification,
                'triage_status' => $triageStatus,
                'notes' => $notes,
                'updated_by' => $operator,
            ]);

            return [
                'data' => [
                    'queued' => $queued,
                    'queue_id' => $queueId,
                    'operation' => 'updated',
                    'previous_status' => $previousStatus,
                ],
            ];
        }

        $params = [
            'permission' => $canonicalPermission,
            'queue_action' => $queueAction,
            'proposed_bucket' => $proposedBucket,
            'proposed_classification' => $proposedClassification,
            'triage_status' => $triageStatus,
            'notes' => $notes,
            'requested_by' => $operator,
            'updated_by' => $operator,
            'source_system' => 'web',
        ];

        $queued = db_exec(sql_permission_queue_insert(), $params);
        $queueId = null;
        try {
            $queueId = db()->lastInsertId();
        } catch (Throwable $e) {
            $queueId = null;
        }

        return [
            'data' => [
                'queued' => $queued,
                'queue_id' => $queueId,
                'operation' => 'created',
            ],
        ];
    });
}

/**
 * Catalog list for AOSP permissions (read-only).
 */
function db_android_permission_catalog_aosp(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(1, min((int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE));
    $offset = max(0, ($page - 1) * $pageSize);

    $where = [];
    $params = [];

    add_where_in($where, $params, 'UPPER(bucket)', 'bucket', ['AOSP_EXACT', 'AOSP_HIDDEN_PRIV']);
    add_where_like($where, $params, 'permission_string', 'search', $filters['q'] ?? null);
    add_where_like($where, $params, sql_android_permission_namespace_expr_for('permission_string'), 'namespace', $filters['namespace'] ?? null);

    $bucket = trim((string)($filters['bucket'] ?? ''));
    if ($bucket !== '') {
        add_where_equals($where, $params, 'UPPER(bucket)', 'bucket_exact', strtoupper($bucket));
    }

    $sql = sql_android_permission_catalog_base();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY permission_string, namespace, classification, bucket';
    $sql .= ' ORDER BY seen_count DESC, permission_string ASC LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_named_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = sql_android_permission_catalog_count_base();
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;

    return [
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
        ],
    ];
}

/**
 * Catalog list for Google/GMS permissions (read-only).
 */
function db_android_permission_catalog_google(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(1, min((int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE));
    $offset = max(0, ($page - 1) * $pageSize);

    $where = [];
    $params = [];

    add_where_in($where, $params, 'UPPER(bucket)', 'bucket', ['GOOGLE_GMS']);
    add_where_like($where, $params, 'permission_string', 'search', $filters['q'] ?? null);
    add_where_like($where, $params, sql_android_permission_namespace_expr_for('permission_string'), 'namespace', $filters['namespace'] ?? null);

    $sql = sql_android_permission_catalog_base();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY permission_string, namespace, classification, bucket';
    $sql .= ' ORDER BY seen_count DESC, permission_string ASC LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_named_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = sql_android_permission_catalog_count_base();
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;

    return [
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
        ],
    ];
}

/**
 * OEM namespace registry (read-only).
 */
function db_android_permission_oem_registry(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(1, min((int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE));
    $offset = max(0, ($page - 1) * $pageSize);

    $where = [];
    $params = [];
    $namespaceExpr = sql_android_permission_namespace_drift_expr_for('permission_string');

    add_where_like($where, $params, $namespaceExpr, 'namespace', $filters['q'] ?? null);

    $class = trim((string)($filters['class'] ?? ''));
    if ($class === 'core') {
        add_where_prefix($where, $params, $namespaceExpr, 'ns_core', 'android');
    } elseif ($class === 'expected') {
        add_where_prefix($where, $params, $namespaceExpr, 'ns_expected', 'com.google');
    } elseif ($class === 'oem') {
        add_where_prefixes($where, $params, $namespaceExpr, 'ns_oem', perm_oem_namespace_prefixes());
        $overrideExclusions = perm_namespace_exact_override_values_excluding_class('oem');
        if ($overrideExclusions) {
            add_where_in($where, $params, "LOWER({$namespaceExpr})", 'ns_oem_override_ex', $overrideExclusions);
            $last = array_pop($where);
            if ($last !== null) {
                $where[] = 'NOT ' . $last;
            }
        }
    } elseif ($class === 'anomalous') {
        $subWhere = [];
        $subParams = [];
        add_where_prefix($subWhere, $subParams, $namespaceExpr, 'ns_core_ex', 'android');
        add_where_prefix($subWhere, $subParams, $namespaceExpr, 'ns_expected_ex', 'com.google');
        add_where_prefixes($subWhere, $subParams, $namespaceExpr, 'ns_oem_ex', perm_oem_namespace_prefixes());
        $overrideAnomalous = perm_namespace_exact_override_values_for_class('anomalous');
        if ($overrideAnomalous) {
            add_where_in($subWhere, $subParams, "LOWER({$namespaceExpr})", 'ns_anom_exact', $overrideAnomalous);
            $overrideInClause = array_pop($subWhere);
            if ($overrideInClause !== null) {
                $where[] = '(' . $overrideInClause . ' OR NOT (' . implode(' OR ', $subWhere) . '))';
                $params = array_merge($params, $subParams);
            }
        } else {
            if ($subWhere) {
                $where[] = 'NOT (' . implode(' OR ', $subWhere) . ')';
                $params = array_merge($params, $subParams);
            }
        }
    }

    $sql = sql_android_permission_namespace_registry_base();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY namespace';
    $sql .= ' ORDER BY seen_count DESC, namespace ASC LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_named_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $class = perm_namespace_class_for((string)($row['namespace'] ?? ''));
        $profile = perm_namespace_review_profile_for((string)($row['namespace'] ?? ''));
        $row['namespace_class'] = $class['key'];
        $row['namespace_class_label'] = $class['label'];
        $row['namespace_class_name'] = $class['class_name'];
        $row['oem_registry_scope'] = $class['key'] === 'oem' ? 'oem_candidate' : 'non_oem_namespace';
        $row['review_bucket'] = $profile['review_bucket'];
        $row['validation_label'] = $profile['validation_label'];
        $row['review_hint'] = $profile['review_hint'];
    }
    unset($row);

    $countSql = "SELECT COUNT(*) AS total_count FROM (" . sql_android_permission_namespace_registry_base();
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countSql .= ' GROUP BY ' . $namespaceExpr . ') t';
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;

    return [
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
            'default_class_recommended' => 'oem',
        ],
    ];
}

/**
 * OEM permission list (read-only).
 */
function db_android_permission_oem_permissions(array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(1, min((int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE));
    $offset = max(0, ($page - 1) * $pageSize);

    $where = [];
    $params = [];
    $namespaceExpr = sql_android_permission_namespace_expr_for('u.permission_string');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $vtEvent = db_catalog_table('android_permission_enrich_vt_event');

    add_where_like($where, $params, 'u.permission_string', 'search', $filters['q'] ?? null);
    add_where_like($where, $params, $namespaceExpr, 'namespace', $filters['namespace'] ?? null);
    add_where_equals($where, $params, 'u.triage_status', 'triage_status', $filters['status'] ?? null);

    $oemPrefixes = perm_oem_namespace_prefixes();
    add_where_prefixes($where, $params, 'u.permission_string', 'oem_prefix', $oemPrefixes);

    $sql = "
        SELECT
            u.permission_string,
            {$namespaceExpr} AS namespace,
            u.triage_status,
            u.notes,
            COUNT(DISTINCT e.sample_id) AS seen_count,
            MIN(e.ingested_at_utc) AS first_seen_at_utc,
            MAX(e.ingested_at_utc) AS last_seen_at_utc
        FROM {$dictUnknown} u
        LEFT JOIN {$vtEvent} e
            ON BINARY e.permission_string = BINARY u.permission_string
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY u.permission_string, namespace, u.triage_status, u.notes';
    $sql .= ' ORDER BY seen_count DESC, u.permission_string ASC LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_named_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = "SELECT COUNT(*) AS total_count FROM (SELECT u.permission_string FROM {$dictUnknown} u";
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countSql .= ' GROUP BY u.permission_string) t';
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;

    return [
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => max(1, $totalPages),
            'has_more' => ($offset + $pageSize) < $totalCount,
        ],
    ];
}
