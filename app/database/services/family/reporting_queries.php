<?php
declare(strict_types=1);

function db_family_taxonomy_force_refresh_requested(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }

    $refresh = strtolower(trim((string)($_GET['refresh'] ?? '')));
    return in_array($refresh, ['1', 'true', 'yes', 'y'], true);
}

function db_family_taxonomy_check_transient_key(array $args): string
{
    return hash('sha256', json_encode([
        'args' => $args,
        'primary' => db_primary_catalog_name(),
        'version' => APP_VERSION,
        'cache_generation' => db_cache_generation_token(),
        'model' => 'family_taxonomy_check_v31',
    ], JSON_UNESCAPED_SLASHES));
}

function db_family_taxonomy_check_schema_status(): array
{
    return db_schema_requirements_status(db_known_schema_requirements([
        'malware_sample_catalog',
        'virustotal_sample_signal_current',
        'vt_sample_verdict_confidence_current',
        'vw_malware_sample_catalog_family_resolution',
    ]));
}

function db_family_taxonomy_sql_resolved_catalog_alignment_expr(
    string $reviewStatusExpr = 'res.resolution_review_status',
    string $acceptedMappingRowsExpr = 'res.accepted_mapping_rows',
    string $acceptedDistinctFamiliesExpr = 'res.accepted_distinct_families'
): string {
    return "(
        LOWER(TRIM(COALESCE({$reviewStatusExpr}, ''))) = 'accepted'
        AND COALESCE({$acceptedMappingRowsExpr}, 0) >= 1
        AND COALESCE({$acceptedDistinctFamiliesExpr}, 0) = 1
    )";
}

function db_family_taxonomy_sql_matching_resolution_secondary_alignment_expr(
    string $vtLabelExpr = 'c.vt_suggested_label',
    string $resolvedFamilyExpr = 'res.resolved_family_name',
    string $reviewStatusExpr = 'res.resolution_review_status',
    string $mappingRowsExpr = 'res.mapping_rows',
    string $distinctFamiliesExpr = 'res.distinct_families'
): string {
    $vtNormExpr = db_family_taxonomy_sql_normalize_expr($vtLabelExpr);
    $resolvedNormExpr = db_family_taxonomy_sql_normalize_expr($resolvedFamilyExpr);

    return "(
        LOWER(TRIM(COALESCE({$reviewStatusExpr}, ''))) = 'matching_only'
        AND COALESCE({$mappingRowsExpr}, 0) = 1
        AND COALESCE({$distinctFamiliesExpr}, 0) = 1
        AND {$resolvedNormExpr} <> ''
        AND LOCATE({$resolvedNormExpr}, {$vtNormExpr}) > 0
    )";
}

function db_family_taxonomy_scorecard(): array
{
    $schema = db_family_taxonomy_check_schema_status();
    if (!$schema['ok']) {
        return [
            'available' => false,
            'missing' => $schema['missing'],
            'missing_count' => $schema['missing_count'],
        ];
    }

    $catalog = db_catalog_table('malware_sample_catalog');
    $signal = db_catalog_table('virustotal_sample_signal_current');
    $confidence = db_catalog_table('vt_sample_verdict_confidence_current');
    $resolution = db_catalog_table('vw_malware_sample_catalog_family_resolution');
    $typeAuthority = db_catalog_table('v_android_sample_family_type_authority');
    $governedAliasExpr = db_family_taxonomy_sql_governed_alias_expr('c.family_label', 'sig.popular_threat_name');
    $secondaryAliasExpr = db_family_taxonomy_sql_vt_secondary_alias_expr('c.family_label', 'c.vt_suggested_label', 'sig.popular_threat_name');
    $sourceBatchFamilyExpr = db_family_taxonomy_sql_source_batch_family_expr('c.family_label', 'c.source_batch_label');
    $resolvedAlignmentExpr = db_family_taxonomy_sql_resolved_catalog_alignment_expr();
    $matchingResolutionSecondaryExpr = db_family_taxonomy_sql_matching_resolution_secondary_alignment_expr();
    $authorityTypedAliasExpr = db_family_taxonomy_sql_authority_typed_alias_expr(
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedSecondaryAliasExpr = db_family_taxonomy_sql_authority_typed_secondary_alias_expr(
        'c.vt_suggested_label',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedHoldExpr = db_family_taxonomy_sql_authority_typed_hold_alignment_expr(
        'c.family_label',
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $alignmentExpr = "
        CASE
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'unlabeled'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'catalog_only'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                THEN 'signal_only'
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                THEN 'aligned'
            WHEN {$governedAliasExpr}
                THEN 'aligned'
            WHEN {$secondaryAliasExpr}
                THEN 'aligned'
            WHEN {$sourceBatchFamilyExpr}
                THEN 'aligned'
            WHEN {$resolvedAlignmentExpr}
                THEN 'aligned'
            WHEN {$matchingResolutionSecondaryExpr}
                THEN 'aligned'
            WHEN {$authorityTypedAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedSecondaryAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedHoldExpr}
                THEN 'aligned'
            ELSE 'mismatch'
        END
    ";
    $genericExpr = "
        CASE
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) IN ('trojan', 'adware', 'android', 'malware', 'riskware', 'generic', 'unknown')
                THEN 1
            ELSE 0
        END
    ";

    $row = db_one("
        SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN {$alignmentExpr} = 'aligned' THEN 1 ELSE 0 END) AS aligned_rows,
            SUM(CASE WHEN {$alignmentExpr} = 'mismatch' THEN 1 ELSE 0 END) AS mismatch_rows,
            SUM(CASE WHEN {$alignmentExpr} = 'signal_only' THEN 1 ELSE 0 END) AS signal_only_rows,
            SUM(CASE WHEN {$alignmentExpr} = 'catalog_only' THEN 1 ELSE 0 END) AS catalog_only_rows,
            SUM(CASE WHEN {$alignmentExpr} = 'unlabeled' THEN 1 ELSE 0 END) AS unlabeled_rows,
            SUM({$genericExpr}) AS generic_label_rows,
            SUM(CASE WHEN LOWER(COALESCE(conf.confidence_bucket, '')) IN ('high', 'strong') AND {$alignmentExpr} <> 'aligned' THEN 1 ELSE 0 END) AS high_conflict_rows,
            SUM(CASE WHEN LOWER(COALESCE(conf.confidence_bucket, '')) IN ('high', 'strong') AND {$alignmentExpr} = 'mismatch' THEN 1 ELSE 0 END) AS high_mismatch_rows
        FROM {$catalog} c
        LEFT JOIN {$signal} sig ON sig.sample_id = c.sample_id
        LEFT JOIN {$confidence} conf ON conf.sample_id = c.sample_id
        LEFT JOIN {$resolution} res ON res.sample_id = c.sample_id
        LEFT JOIN {$typeAuthority} fta ON fta.sample_id = c.sample_id
    ") ?? [];

    $total = (int)($row['total_rows'] ?? 0);
    $pct = static function (int $count, int $base): ?float {
        if ($base <= 0) {
            return null;
        }
        return round(($count / $base) * 100, 2);
    };

    $mismatch = (int)($row['mismatch_rows'] ?? 0);
    $signalOnly = (int)($row['signal_only_rows'] ?? 0);
    $catalogOnly = (int)($row['catalog_only_rows'] ?? 0);
    $unlabeled = (int)($row['unlabeled_rows'] ?? 0);
    $generic = (int)($row['generic_label_rows'] ?? 0);
    $highConflict = (int)($row['high_conflict_rows'] ?? 0);
    $riskClass = 'ok';
    if ($mismatch >= 1000 || $highConflict >= 500) {
        $riskClass = 'critical';
    } elseif ($mismatch >= 250 || $signalOnly >= 250 || $catalogOnly >= 250) {
        $riskClass = 'warn';
    }

    return [
        'available' => true,
        'total_rows' => $total,
        'aligned_rows' => (int)($row['aligned_rows'] ?? 0),
        'mismatch_rows' => $mismatch,
        'signal_only_rows' => $signalOnly,
        'catalog_only_rows' => $catalogOnly,
        'unlabeled_rows' => $unlabeled,
        'generic_label_rows' => $generic,
        'high_conflict_rows' => $highConflict,
        'high_mismatch_rows' => (int)($row['high_mismatch_rows'] ?? 0),
        'aligned_pct' => $pct((int)($row['aligned_rows'] ?? 0), $total),
        'mismatch_pct' => $pct($mismatch, $total),
        'signal_only_pct' => $pct($signalOnly, $total),
        'catalog_only_pct' => $pct($catalogOnly, $total),
        'unlabeled_pct' => $pct($unlabeled, $total),
        'generic_label_pct' => $pct($generic, $total),
        'risk_class' => $riskClass,
    ];
}

function db_family_taxonomy_surface_exists(string $surfaceName): bool
{
    static $cache = [];
    $key = strtolower(trim($surfaceName));
    if ($key === '') {
        return false;
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $row = db_one(
        'SELECT COUNT(*) AS row_count
         FROM information_schema.tables
         WHERE table_schema = :table_schema
           AND LOWER(table_name) = :table_name',
        [
            'table_schema' => db_primary_catalog_name(),
            'table_name' => $key,
        ]
    ) ?: [];
    $cache[$key] = (int)($row['row_count'] ?? 0) > 0;
    return $cache[$key];
}

function db_family_taxonomy_top_mismatch_pairs(int $limit = 6): array
{
    $schema = db_family_taxonomy_check_schema_status();
    if (!$schema['ok']) {
        return [];
    }

    $limit = max(1, min($limit, 20));
    $catalog = db_catalog_table('malware_sample_catalog');
    $signal = db_catalog_table('virustotal_sample_signal_current');
    $confidence = db_catalog_table('vt_sample_verdict_confidence_current');
    $resolution = db_catalog_table('vw_malware_sample_catalog_family_resolution');
    $typeAuthority = db_catalog_table('v_android_sample_family_type_authority');
    $governedAliasExpr = db_family_taxonomy_sql_governed_alias_expr('c.family_label', 'sig.popular_threat_name');
    $secondaryAliasExpr = db_family_taxonomy_sql_vt_secondary_alias_expr('c.family_label', 'c.vt_suggested_label', 'sig.popular_threat_name');
    $sourceBatchFamilyExpr = db_family_taxonomy_sql_source_batch_family_expr('c.family_label', 'c.source_batch_label');
    $resolvedAlignmentExpr = db_family_taxonomy_sql_resolved_catalog_alignment_expr();
    $matchingResolutionSecondaryExpr = db_family_taxonomy_sql_matching_resolution_secondary_alignment_expr();
    $authorityTypedAliasExpr = db_family_taxonomy_sql_authority_typed_alias_expr(
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedSecondaryAliasExpr = db_family_taxonomy_sql_authority_typed_secondary_alias_expr(
        'c.vt_suggested_label',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedHoldExpr = db_family_taxonomy_sql_authority_typed_hold_alignment_expr(
        'c.family_label',
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $alignmentExpr = "
        CASE
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'unlabeled'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'catalog_only'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                THEN 'signal_only'
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                THEN 'aligned'
            WHEN {$governedAliasExpr}
                THEN 'aligned'
            WHEN {$secondaryAliasExpr}
                THEN 'aligned'
            WHEN {$sourceBatchFamilyExpr}
                THEN 'aligned'
            WHEN {$resolvedAlignmentExpr}
                THEN 'aligned'
            WHEN {$matchingResolutionSecondaryExpr}
                THEN 'aligned'
            WHEN {$authorityTypedAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedSecondaryAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedHoldExpr}
                THEN 'aligned'
            ELSE 'mismatch'
        END
    ";

    $rows = db_all("
        SELECT
            COALESCE(NULLIF(TRIM(c.family_label), ''), '(empty)') AS catalog_family_label,
            COALESCE(NULLIF(TRIM(sig.popular_threat_name), ''), '(empty)') AS signal_family_name,
            COUNT(*) AS row_count
        FROM {$catalog} c
        LEFT JOIN {$signal} sig ON sig.sample_id = c.sample_id
        LEFT JOIN {$confidence} conf ON conf.sample_id = c.sample_id
        LEFT JOIN {$resolution} res ON res.sample_id = c.sample_id
        LEFT JOIN {$typeAuthority} fta ON fta.sample_id = c.sample_id
        WHERE {$alignmentExpr} = 'mismatch'
        GROUP BY catalog_family_label, signal_family_name
        ORDER BY row_count DESC, catalog_family_label ASC, signal_family_name ASC
        LIMIT {$limit}
    ");

    $signalDistribution = db_family_taxonomy_signal_family_distribution(array_map(
        static fn(array $row): string => (string)($row['signal_family_name'] ?? ''),
        $rows
    ));

    foreach ($rows as &$row) {
        $resolution = db_family_taxonomy_pair_resolution(
            (string)($row['catalog_family_label'] ?? ''),
            (string)($row['signal_family_name'] ?? ''),
            $signalDistribution
        );
        $row['pair_kind'] = db_family_taxonomy_pair_kind(
            (string)($row['catalog_family_label'] ?? ''),
            (string)($row['signal_family_name'] ?? '')
        );
        $row['resolution_action'] = $resolution['resolution_action'];
        $row['resolution_confidence'] = $resolution['resolution_confidence'];
        $row['resolution_target_family'] = $resolution['resolution_target_family'];
        $row['resolution_reason'] = $resolution['resolution_reason'];
    }
    unset($row);

    return $rows;
}

function db_family_taxonomy_apply_plan(
    int $limit = 50,
    string $alignment = '',
    string $query = '',
    string $pattern = '',
    string $pairCatalog = '',
    string $pairSignal = '',
    string $fixAction = '',
    string $targetFamily = '',
    string $decisionMode = ''
): array {
    $payload = db_family_taxonomy_check($limit, $alignment, '', $query, $pattern, $pairCatalog, $pairSignal, $fixAction, $targetFamily, $decisionMode);
    $rows = $payload['data']['rows'] ?? [];
    $plan = db_family_taxonomy_apply_plan_from_rows(is_array($rows) ? $rows : []);

    return [
        'data' => $plan,
        'meta' => array_merge($payload['meta'] ?? [], [
            'schema_surface' => 'family_taxonomy_apply_plan_v1',
            'dry_run' => true,
        ]),
    ];
}

function db_family_taxonomy_check(
    int $limit = 100,
    string $alignment = '',
    string $platform = '',
    string $query = '',
    string $pattern = '',
    string $pairCatalog = '',
    string $pairSignal = '',
    string $fixAction = '',
    string $targetFamily = '',
    string $decisionMode = '',
    bool $includeRows = true
): array {
    $cacheArgs = func_get_args();
    $cacheKey = md5(serialize($cacheArgs));
    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $limit = max(1, min($limit, 250));
    $alignment = strtolower(trim($alignment));
    $query = trim($query);
    $pattern = strtolower(trim($pattern));
    $pairCatalog = trim($pairCatalog);
    $pairSignal = trim($pairSignal);
    $fixAction = strtolower(trim($fixAction));
    $targetFamily = trim($targetFamily);
    $decisionMode = strtolower(trim($decisionMode));
    $transientKey = db_family_taxonomy_check_transient_key([
        'limit' => $limit,
        'alignment' => $alignment,
        'platform' => $platform,
        'query' => $query,
        'pattern' => $pattern,
        'pair_catalog' => $pairCatalog,
        'pair_signal' => $pairSignal,
        'fix_action' => $fixAction,
        'target_family' => $targetFamily,
        'decision_mode' => $decisionMode,
        'include_rows' => $includeRows ? '1' : '0',
    ]);
    $cacheTtlSeconds = $includeRows ? 900 : 1800;
    $forceRefresh = db_family_taxonomy_force_refresh_requested();
    $cached = $forceRefresh ? null : app_transient_cache_read('family_taxonomy_check_core_v3', $transientKey, $cacheTtlSeconds);
    if (is_array($cached)) {
        $cache[$cacheKey] = $cached;
        return $cache[$cacheKey];
    }
    if (!$forceRefresh) {
        $staleCached = app_transient_cache_read_stale('family_taxonomy_check_core_v3', $transientKey);
        if (is_array($staleCached)) {
            $cache[$cacheKey] = $staleCached;
            return $cache[$cacheKey];
        }
    }
    $schema = db_family_taxonomy_check_schema_status();

    if (!$schema['ok']) {
        $cache[$cacheKey] = [
            'data' => [
                'summary' => [],
                'rows' => [],
                'mismatch_pairs' => [],
                'issue_inventory' => [],
                'fix_action_inventory' => [],
                'decision_inventory' => [],
                'governance_inventory' => [],
                'apply_plan' => [],
                'repair_opportunities' => [],
                'queue_presets' => [],
                'remediation_summary' => [],
                'ask_why_inventory' => [],
                'schema_missing' => $schema['missing'],
            ],
            'meta' => [
                'schema_available' => false,
                'primary_database' => db_primary_catalog_name(),
                'limit' => $limit,
                'alignment' => $alignment,
                'platform' => $platform,
                'query' => $query,
                'pattern' => $pattern,
                'pair_catalog' => $pairCatalog,
                'pair_signal' => $pairSignal,
                'fix_action' => $fixAction,
                'target_family' => $targetFamily,
                'decision_mode' => $decisionMode,
                'include_rows' => $includeRows,
            ],
        ];
        app_transient_cache_write('family_taxonomy_check_core_v3', $transientKey, $cache[$cacheKey]);
        return $cache[$cacheKey];
    }

    $catalog = db_catalog_table('malware_sample_catalog');
    $signal = db_catalog_table('virustotal_sample_signal_current');
    $confidence = db_catalog_table('vt_sample_verdict_confidence_current');
    $resolution = db_catalog_table('vw_malware_sample_catalog_family_resolution');
    $persistedAuthority = db_catalog_table('malware_family_authority_fact');
    $typeAuthority = db_catalog_table('v_android_sample_family_type_authority');
    $governedAliasExpr = db_family_taxonomy_sql_governed_alias_expr('c.family_label', 'sig.popular_threat_name');
    $secondaryAliasExpr = db_family_taxonomy_sql_vt_secondary_alias_expr('c.family_label', 'c.vt_suggested_label', 'sig.popular_threat_name');
    $sourceBatchFamilyExpr = db_family_taxonomy_sql_source_batch_family_expr('c.family_label', 'c.source_batch_label');
    $resolvedAlignmentExpr = db_family_taxonomy_sql_resolved_catalog_alignment_expr();
    $matchingResolutionSecondaryExpr = db_family_taxonomy_sql_matching_resolution_secondary_alignment_expr();
    $authorityTypedAliasExpr = db_family_taxonomy_sql_authority_typed_alias_expr(
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedSecondaryAliasExpr = db_family_taxonomy_sql_authority_typed_secondary_alias_expr(
        'c.vt_suggested_label',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );
    $authorityTypedHoldExpr = db_family_taxonomy_sql_authority_typed_hold_alignment_expr(
        'c.family_label',
        'sig.popular_threat_name',
        'fta.authority_bucket',
        'fta.family_slug',
        'fta.family_name'
    );

    $alignmentExpr = "
        CASE
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'unlabeled'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'catalog_only'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                THEN 'signal_only'
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                THEN 'aligned'
            WHEN {$governedAliasExpr}
                THEN 'aligned'
            WHEN {$secondaryAliasExpr}
                THEN 'aligned'
            WHEN {$sourceBatchFamilyExpr}
                THEN 'aligned'
            WHEN {$resolvedAlignmentExpr}
                THEN 'aligned'
            WHEN {$matchingResolutionSecondaryExpr}
                THEN 'aligned'
            WHEN {$authorityTypedAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedSecondaryAliasExpr}
                THEN 'aligned'
            WHEN {$authorityTypedHoldExpr}
                THEN 'aligned'
            ELSE 'mismatch'
        END
    ";

    $genericExpr = "
        CASE
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) IN ('trojan', 'adware', 'android', 'malware', 'riskware', 'generic', 'unknown')
                THEN 1
            ELSE 0
        END
    ";

    $where = [];
    $params = [];

    $allowedAlignments = ['aligned', 'catalog_only', 'signal_only', 'mismatch', 'unlabeled', 'generic_label'];
    if ($alignment !== '' && in_array($alignment, $allowedAlignments, true)) {
        if ($alignment === 'generic_label') {
            $where[] = "{$genericExpr} = 1";
        } else {
            $where[] = "{$alignmentExpr} = :alignment";
            $params['alignment'] = $alignment;
        }
    }

    $allowedPlatforms = ['android', 'windows', 'linux', 'macos', 'unknown'];
    if ($platform !== '' && in_array($platform, $allowedPlatforms, true)) {
        $where[] = "LOWER(COALESCE(c.platform, 'unknown')) = :platform";
        $params['platform'] = $platform;
    }

    $allowedPatterns = ['unknown_catalog', 'generic_catalog', 'generic_signal', 'signal_overlap', 'short_signal', 'spy_bank_loader_signal', 'alias_candidate', 'alias_resolved', 'semantic_conflict', 'placeholder_catalog'];
    $phpOnlyPattern = false;
    if ($pattern !== '' && in_array($pattern, $allowedPatterns, true)) {
        if ($pattern === 'unknown_catalog') {
            $where[] = "LOWER(TRIM(COALESCE(c.family_label, ''))) = 'unknown'";
        } else {
            $phpOnlyPattern = true;
        }
    }

    if ($query !== '') {
        $where[] = '(
            c.sha256 = :q_exact
            OR c.sample_label LIKE :q_like_sample
            OR c.family_label LIKE :q_like_family
            OR sig.popular_threat_label LIKE :q_like_signal_label
            OR sig.popular_threat_name LIKE :q_like_signal_name
            OR c.android_package_name LIKE :q_like_package
        )';
        $params['q_exact'] = $query;
        $params['q_like_sample'] = '%' . $query . '%';
        $params['q_like_family'] = '%' . $query . '%';
        $params['q_like_signal_label'] = '%' . $query . '%';
        $params['q_like_signal_name'] = '%' . $query . '%';
        $params['q_like_package'] = '%' . $query . '%';
    }

    if ($pairCatalog !== '') {
        $where[] = 'TRIM(COALESCE(c.family_label, \'\')) = :pair_catalog';
        $params['pair_catalog'] = $pairCatalog;
    }
    if ($pairSignal !== '') {
        $where[] = 'TRIM(COALESCE(sig.popular_threat_name, \'\')) = :pair_signal';
        $params['pair_signal'] = $pairSignal;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $needsPhpFilter = $phpOnlyPattern || $fixAction !== '' || $targetFamily !== '' || $decisionMode !== '';
    $rows = [];
    if ($includeRows) {
        $sql = "
            SELECT
                c.sample_id,
                c.sha256,
                c.sample_label,
                c.family_label,
                c.classification_primary,
                c.classification_subtype,
                c.source_batch_label,
                c.android_package_name,
                c.platform,
                c.vt_suggested_label,
                sig.popular_threat_label,
                sig.popular_threat_category,
                sig.popular_threat_name,
                sig.parse_version,
                conf.confidence_score,
                conf.confidence_bucket,
                conf.recommended_action,
                res.resolved_family_name,
                res.resolution_review_status,
                res.mapping_rows,
                res.accepted_mapping_rows,
                res.distinct_families,
                res.accepted_distinct_families,
                af.authority_id AS persisted_authority_id,
                af.governed_family_slug AS persisted_governed_family_slug,
                af.governed_type_slug AS persisted_governed_type_slug,
                af.authority_resolution_method AS persisted_authority_resolution_method,
                af.review_status AS persisted_authority_review_status,
                fta.type_slug AS family_authority_type_slug,
                fta.family_slug AS family_authority_family_slug,
                fta.authority_bucket,
                fta.authority_gap_reason,
                {$alignmentExpr} AS alignment_status,
                {$genericExpr} AS generic_label_flag
            FROM {$catalog} c
            LEFT JOIN {$signal} sig ON sig.sample_id = c.sample_id
            LEFT JOIN {$confidence} conf ON conf.sample_id = c.sample_id
            LEFT JOIN {$resolution} res ON res.sample_id = c.sample_id
            LEFT JOIN {$persistedAuthority} af
                   ON af.sample_id = c.sample_id
                  AND af.is_active = 1
            LEFT JOIN {$typeAuthority} fta ON fta.sample_id = c.sample_id
            {$whereSql}
            ORDER BY
                FIELD({$alignmentExpr}, 'mismatch', 'signal_only', 'catalog_only', 'unlabeled', 'aligned'),
                {$genericExpr} DESC,
                FIELD(LOWER(COALESCE(conf.confidence_bucket, '')), 'high', 'strong', 'moderate', 'review', 'weak', 'none'),
                conf.confidence_score ASC,
                c.sample_id DESC
            LIMIT :limit
        ";

        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $fetchLimit = $needsPhpFilter ? min(max($limit * 250, 5000), 20000) : $limit;
        $stmt->bindValue(':limit', $fetchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $rows = db_family_taxonomy_enrich_rows($rows);
    }

    $inventorySql = "
        SELECT
            c.sample_id,
            c.sha256,
            c.sample_label,
            c.family_label,
            c.classification_primary,
            c.classification_subtype,
            c.source_batch_label,
            c.android_package_name,
            c.platform,
            c.vt_suggested_label,
            sig.popular_threat_name,
            sig.popular_threat_label,
            sig.popular_threat_category,
            sig.parse_version,
            conf.confidence_score,
            conf.confidence_bucket,
            conf.recommended_action,
            res.resolved_family_name,
            res.resolution_review_status,
            res.mapping_rows,
            res.accepted_mapping_rows,
            res.distinct_families,
            res.accepted_distinct_families,
            af.authority_id AS persisted_authority_id,
            af.governed_family_slug AS persisted_governed_family_slug,
            af.governed_type_slug AS persisted_governed_type_slug,
            af.authority_resolution_method AS persisted_authority_resolution_method,
            af.review_status AS persisted_authority_review_status,
            fta.type_slug AS family_authority_type_slug,
            fta.family_slug AS family_authority_family_slug,
            fta.authority_bucket,
            fta.authority_gap_reason,
            {$alignmentExpr} AS alignment_status,
            {$genericExpr} AS generic_label_flag
        FROM {$catalog} c
        LEFT JOIN {$signal} sig ON sig.sample_id = c.sample_id
        LEFT JOIN {$confidence} conf ON conf.sample_id = c.sample_id
        LEFT JOIN {$resolution} res ON res.sample_id = c.sample_id
        LEFT JOIN {$persistedAuthority} af
               ON af.sample_id = c.sample_id
              AND af.is_active = 1
        LEFT JOIN {$typeAuthority} fta ON fta.sample_id = c.sample_id
        {$whereSql}
    ";
    $inventoryRows = db_all($inventorySql, $params);
    $inventoryRows = db_family_taxonomy_enrich_rows($inventoryRows);

    if ($phpOnlyPattern) {
        $rows = array_values(array_filter($rows, static function (array $row) use ($pattern): bool {
            return db_family_taxonomy_row_matches_pattern($row, $pattern);
        }));
        $inventoryRows = array_values(array_filter($inventoryRows, static function (array $row) use ($pattern): bool {
            return db_family_taxonomy_row_matches_pattern($row, $pattern);
        }));
    }

    if ($fixAction !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($fixAction): bool {
            return strtolower((string)($row['suggested_fix_action'] ?? '')) === $fixAction;
        }));
        $inventoryRows = array_values(array_filter($inventoryRows, static function (array $row) use ($fixAction): bool {
            return strtolower((string)($row['suggested_fix_action'] ?? '')) === $fixAction;
        }));
    }

    if ($targetFamily !== '') {
        $targetFamilyNorm = strtolower(trim($targetFamily));
        $rows = array_values(array_filter($rows, static function (array $row) use ($targetFamilyNorm): bool {
            return strtolower(trim((string)($row['suggested_target_family'] ?? ''))) === $targetFamilyNorm;
        }));
        $inventoryRows = array_values(array_filter($inventoryRows, static function (array $row) use ($targetFamilyNorm): bool {
            return strtolower(trim((string)($row['suggested_target_family'] ?? ''))) === $targetFamilyNorm;
        }));
    }

    if ($decisionMode !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($decisionMode): bool {
            return strtolower((string)($row['decision_mode'] ?? '')) === $decisionMode;
        }));
        $inventoryRows = array_values(array_filter($inventoryRows, static function (array $row) use ($decisionMode): bool {
            return strtolower((string)($row['decision_mode'] ?? '')) === $decisionMode;
        }));
    }

    if ($includeRows) {
        $rows = array_slice($rows, 0, $limit);
    }

    $scorecard = ($where === [] && !$needsPhpFilter)
        ? db_family_taxonomy_scorecard()
        : db_family_taxonomy_scorecard_from_rows($inventoryRows);
    if ($needsPhpFilter) {
        $summary = db_family_taxonomy_summary_from_rows($inventoryRows);
    } elseif ($where === []) {
        $summary = db_family_taxonomy_summary_from_scorecard($scorecard);
    } else {
        $summary = db_family_taxonomy_summary_from_rows($inventoryRows);
    }
    $issueInventory = db_family_taxonomy_issue_inventory($inventoryRows);
    $fixActionInventory = db_family_taxonomy_fix_action_inventory($inventoryRows);
    $decisionInventory = db_family_taxonomy_decision_inventory($inventoryRows);
    $askWhyInventory = db_family_taxonomy_decision_issue_inventory($inventoryRows, 'ask_why_first');
    $platformInventory = db_family_taxonomy_platform_inventory($inventoryRows);
    $governanceInventory = db_family_taxonomy_governance_inventory($inventoryRows);
    $applyPlan = db_family_taxonomy_apply_plan_from_rows($inventoryRows);
    $repairOpportunities = db_family_taxonomy_repair_opportunities($inventoryRows);
    $queuePresets = db_family_taxonomy_queue_presets($issueInventory, $decisionInventory);
    $mismatchPairs = db_family_taxonomy_mismatch_pairs_from_rows($inventoryRows, 20);
    $remediationSummary = db_family_taxonomy_remediation_summary($scorecard, $mismatchPairs, $inventoryRows);
    $projectionMismatchIds = [];
    if (
        db_family_taxonomy_surface_exists('malware_family_authority_fact')
        && db_family_taxonomy_surface_exists('v_android_sample_family_type_authority')
    ) {
        $projectionSql = "
            SELECT c.sample_id
            FROM {$catalog} c
            LEFT JOIN {$signal} sig ON sig.sample_id = c.sample_id
            LEFT JOIN {$resolution} res ON res.sample_id = c.sample_id
            LEFT JOIN " . db_catalog_table('malware_family_authority_fact') . " af
                   ON af.sample_id = c.sample_id
                  AND af.is_active = 1
            LEFT JOIN " . db_catalog_table('v_android_sample_family_type_authority') . " fta
                   ON fta.sample_id = c.sample_id
            {$whereSql}
              " . ($whereSql === '' ? 'WHERE' : 'AND') . " {$alignmentExpr} = 'mismatch'
              AND af.sample_id IS NULL
              AND LOWER(TRIM(COALESCE(fta.authority_bucket, ''))) = 'authority_family_typed'";
        foreach (db_all($projectionSql, $params) as $projectionRow) {
            $projectionMismatchIds[(int)($projectionRow['sample_id'] ?? 0)] = true;
        }
    }
    $authorityMismatchSummary = [
        'resolved_catalog_truth_vs_noisy_signal' => 0,
        'unresolved_governance_gap' => 0,
        'true_semantic_conflict' => 0,
        'generic_signal_token' => 0,
        'projection_without_persisted_fact' => 0,
    ];
    foreach ($inventoryRows as $row) {
        if (strtolower(trim((string)($row['alignment_status'] ?? ''))) !== 'mismatch') {
            continue;
        }
        $sampleId = (int)($row['sample_id'] ?? 0);
        $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));
        $familyStatus = strtolower(trim((string)($row['family_resolution_status'] ?? '')));
        if (isset($projectionMismatchIds[$sampleId])) {
            $authorityMismatchSummary['projection_without_persisted_fact']++;
        } elseif ($issueKind === 'generic_signal') {
            $authorityMismatchSummary['generic_signal_token']++;
        } elseif ($familyStatus === 'accepted_resolution' || in_array($issueKind, ['alias_resolved', 'signal_overlap', 'short_signal_token'], true)) {
            $authorityMismatchSummary['resolved_catalog_truth_vs_noisy_signal']++;
        } elseif ($issueKind === 'semantic_conflict') {
            $authorityMismatchSummary['true_semantic_conflict']++;
        } else {
            $authorityMismatchSummary['unresolved_governance_gap']++;
        }
    }

    $cache[$cacheKey] = [
        'data' => [
            'summary' => $summary,
            'authority_mismatch_summary' => $authorityMismatchSummary,
            'rows' => $rows,
            'mismatch_pairs' => $mismatchPairs,
            'issue_inventory' => $issueInventory,
            'fix_action_inventory' => $fixActionInventory,
            'decision_inventory' => $decisionInventory,
            'ask_why_inventory' => $askWhyInventory,
            'platform_inventory' => $platformInventory,
            'governance_inventory' => $governanceInventory,
            'apply_plan' => $applyPlan,
            'repair_opportunities' => $repairOpportunities,
            'queue_presets' => $queuePresets,
            'remediation_summary' => $remediationSummary,
        ],
        'meta' => [
            'schema_available' => true,
            'primary_database' => db_primary_catalog_name(),
            'limit' => $limit,
            'alignment' => $alignment,
            'platform' => $platform,
            'query' => $query,
            'pattern' => $pattern,
            'pair_catalog' => $pairCatalog,
            'pair_signal' => $pairSignal,
            'fix_action' => $fixAction,
            'target_family' => $targetFamily,
            'decision_mode' => $decisionMode,
            'include_rows' => $includeRows,
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
            'schema_surface' => 'family_taxonomy_check_v1',
        ],
    ];
    app_transient_cache_write('family_taxonomy_check_core_v3', $transientKey, $cache[$cacheKey]);
    return $cache[$cacheKey];
}
