<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/../queries/dataset_readiness_queries.php';
require_once __DIR__ . '/schema_service.php';
require_once __DIR__ . '/family_service.php';
require_once __DIR__ . '/../../lib/transient_cache.php';

function db_dataset_readiness_schema_status(): array
{
    return db_schema_requirements_status([
        'malware_sample_catalog' => [
            'sample_id',
            'sha256',
            'family_label',
            'classification_primary',
            'classification_subtype',
        ],
        'virustotal_sample_signal_current' => [
            'sample_id',
            'popular_threat_name',
            'popular_threat_category',
        ],
    ]);
}

function db_dataset_readiness_surface_available(string $surfaceName): bool
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
    $key = strtolower(trim($surfaceName));
    if (isset($available[$key])) {
        return (bool)$available[$key];
    }

    $row = db_one(
        'SELECT COUNT(*) AS row_count
         FROM information_schema.tables
         WHERE table_schema = :table_schema
           AND LOWER(table_name) = :table_name',
        ['table_schema' => db_primary_catalog_name(), 'table_name' => $key]
    ) ?: [];
    $available[$key] = (int)($row['row_count'] ?? 0) > 0;
    return (bool)$available[$key];
}

function db_dataset_readiness_include_resolution_surface(): bool
{
    return db_dataset_readiness_surface_available('vw_malware_sample_catalog_family_resolution');
}

function db_dataset_readiness_non_target_type_tokens(): array
{
    return ['android', 'malware', 'generic', 'other', 'unknown', 'unclassified', 'misc', 'n_a', 'na'];
}

function db_dataset_readiness_slugify(?string $value): string
{
    $value = strtolower(trim((string)$value));
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_');
}

function db_dataset_readiness_include_label_authority_surface(): bool
{
    return db_dataset_readiness_surface_available('label_authority_resolution_view');
}

function db_dataset_readiness_include_type_authority_surface(): bool
{
    return db_dataset_readiness_surface_available('v_android_sample_family_type_authority');
}

function db_dataset_readiness_include_persisted_authority_surface(): bool
{
    return db_dataset_readiness_surface_available('malware_family_authority_fact');
}

function db_dataset_readiness_include_v2_governance_queue_surface(): bool
{
    return db_dataset_readiness_surface_available('vw_android_family_governance_queue')
        && db_dataset_readiness_surface_available('vw_android_family_v2_record_repair_queue')
        && db_dataset_readiness_surface_available('vw_android_family_v2_conflict_and_repair_queue');
}

function db_dataset_readiness_type_catalog(bool $activeOnly = false): array
{
    static $cache = [];
    $key = $activeOnly ? 'active' : 'all';
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if (!db_dataset_readiness_surface_available('android_malware_type')) {
        $cache[$key] = [];
        return $cache[$key];
    }

    $sql = '
        SELECT type_id, type_name, type_slug, parent_type_id, type_level, is_active
        FROM ' . db_catalog_table('android_malware_type') . '
        ' . ($activeOnly ? 'WHERE is_active = 1' : '') . '
        ORDER BY type_level ASC, type_slug ASC';
    $rows = db_all($sql);
    $catalog = [];
    foreach ($rows as $row) {
        $slug = trim((string)($row['type_slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $catalog[$slug] = $row;
    }
    $cache[$key] = $catalog;
    return $catalog;
}

function db_dataset_readiness_type_alias_map(bool $activeOnly = false): array
{
    static $cache = [];
    $key = $activeOnly ? 'active' : 'all';
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $map = [];
    foreach (db_dataset_readiness_type_catalog($activeOnly) as $slug => $row) {
        $map[db_dataset_readiness_slugify($slug)] = $slug;
        $typeName = trim((string)($row['type_name'] ?? ''));
        if ($typeName !== '') {
            $map[db_dataset_readiness_slugify($typeName)] = $slug;
        }
    }

    $overrides = [
        'ads' => 'adware',
        'advertising' => 'adware',
        'advertisement' => 'adware',
        'banking' => 'banker',
        'banking_trojan' => 'banker',
        'bank_trojan' => 'banker',
        'bot' => 'botnet',
        'coinminer' => 'miner',
        'cryptominer' => 'miner',
        'crypto_miner' => 'miner',
        'info_stealer' => 'stealer',
        'infostealer' => 'stealer',
        'credential_stealer' => 'stealer',
        'password_stealer' => 'stealer',
        'banking_stealer' => 'stealer',
        'sms_stealer' => 'stealer',
        'locker' => 'ransomware',
        'remote_access_trojan' => 'rat',
        'subscription_fraud' => 'subscription-fraud',
        'sms_fraud' => 'sms-trojan',
        'sms_trojan' => 'sms-trojan',
        'toll_fraud' => 'sms-trojan',
    ];
    foreach ($overrides as $alias => $slug) {
        if (isset(db_dataset_readiness_type_catalog(false)[$slug]) && (!$activeOnly || isset(db_dataset_readiness_type_catalog(true)[$slug]))) {
            $map[$alias] = $slug;
        }
    }

    $cache[$key] = $map;
    return $cache[$key];
}

function db_dataset_readiness_normalize_type_slug(?string $value, bool $allowInactive = false): ?string
{
    $slug = db_dataset_readiness_slugify($value);
    if ($slug === '' || in_array($slug, db_dataset_readiness_non_target_type_tokens(), true)) {
        return null;
    }

    $aliases = db_dataset_readiness_type_alias_map(!$allowInactive);
    return $aliases[$slug] ?? null;
}

function db_dataset_readiness_type_candidates(array $row): array
{
    return [
        'governed_type_authority' => db_dataset_readiness_normalize_type_slug((string)($row['governed_type_slug'] ?? ''), true),
        'effective_type_authority' => db_dataset_readiness_normalize_type_slug((string)($row['effective_type_slug'] ?? ''), true),
        'catalog_type_authority' => db_dataset_readiness_normalize_type_slug((string)($row['catalog_type_slug'] ?? ''), true),
        'family_type_authority' => db_dataset_readiness_normalize_type_slug((string)($row['family_authority_type_slug'] ?? ''), true),
        'classification_subtype' => db_dataset_readiness_normalize_type_slug((string)($row['classification_subtype'] ?? '')),
        'classification_primary' => db_dataset_readiness_normalize_type_slug((string)($row['classification_primary'] ?? '')),
        'vt_popular_threat_category' => db_dataset_readiness_normalize_type_slug((string)($row['popular_threat_category'] ?? '')),
    ];
}

function db_dataset_readiness_alignment_expr(): string
{
    $governedAliasExpr = db_family_taxonomy_sql_governed_alias_expr('c.family_label', 'sig.popular_threat_name');
    return "
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
            ELSE 'mismatch'
        END
    ";
}

function db_dataset_readiness_generic_family_expr(): string
{
    return "
        CASE
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) IN ('trojan', 'adware', 'android', 'malware', 'riskware', 'generic', 'unknown')
                THEN 1
            ELSE 0
        END
    ";
}

function db_dataset_readiness_canonical_family_expr(bool $includeResolution): string
{
    if ($includeResolution) {
        return "
            CASE
                WHEN NULLIF(TRIM(COALESCE(res.resolved_family_name, '')), '') IS NOT NULL
                     AND LOWER(TRIM(COALESCE(res.resolution_review_status, ''))) = 'accepted'
                    THEN res.resolved_family_name
                WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                    THEN c.family_label
                ELSE sig.popular_threat_name
            END
        ";
    }

    return "
        CASE
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                THEN c.family_label
            ELSE sig.popular_threat_name
        END
    ";
}

function db_dataset_readiness_sql_type_norm_expr(string $expr): string
{
    $normalized = "LOWER(TRIM(CONVERT(COALESCE({$expr}, '') USING utf8mb4))) COLLATE utf8mb4_general_ci";
    return "REPLACE(REPLACE(REPLACE(REPLACE({$normalized}, '-', '_'), ' ', '_'), '/', '_'), '.', '_')";
}

function db_dataset_readiness_sql_normalize_type_expr(string $expr, bool $allowInactive = false): string
{
    static $cache = [];
    $cacheKey = ($allowInactive ? 'all:' : 'active:') . $expr;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $groups = [];
    foreach (db_dataset_readiness_type_alias_map(!$allowInactive) as $alias => $slug) {
        $alias = db_dataset_readiness_slugify($alias);
        if ($alias === '' || $slug === '') {
            continue;
        }
        if (!isset($groups[$slug])) {
            $groups[$slug] = [];
        }
        if (!in_array($alias, $groups[$slug], true)) {
            $groups[$slug][] = $alias;
        }
    }

    $normalizedExpr = db_dataset_readiness_sql_type_norm_expr($expr);
    $cases = [];
    foreach (db_dataset_readiness_non_target_type_tokens() as $token) {
        $cases[] = 'WHEN ' . $normalizedExpr . ' = ' . db()->quote($token) . ' THEN NULL';
    }
    foreach ($groups as $slug => $aliases) {
        $quotedAliases = array_map(static fn(string $value): string => db()->quote($value), $aliases);
        $cases[] = 'WHEN ' . $normalizedExpr . ' IN (' . implode(', ', $quotedAliases) . ') THEN ' . db()->quote($slug);
    }

    $cache[$cacheKey] = "
        CASE
            WHEN {$normalizedExpr} = '' THEN NULL
            " . implode("
            ", $cases) . "
            ELSE NULL
        END
    ";
    return $cache[$cacheKey];
}

function db_dataset_readiness_type_derivation(array $row): array
{
    $candidates = db_dataset_readiness_type_candidates($row);
    $typeSlug = null;
    $source = 'unresolved';
    $confidence = 'none';
    $status = 'unresolved';

    if ($candidates['family_type_authority'] !== null) {
        $typeSlug = $candidates['family_type_authority'];
        $source = 'family_type_authority';
        $confidence = 'high';
        $status = 'authority_resolved';
    } elseif ($candidates['governed_type_authority'] !== null) {
        $typeSlug = $candidates['governed_type_authority'];
        $source = 'governed_type_authority';
        $confidence = 'high';
        $status = 'authority_resolved';
    } elseif ($candidates['catalog_type_authority'] !== null) {
        $typeSlug = $candidates['catalog_type_authority'];
        $source = 'catalog_family_type';
        $confidence = 'high';
        $status = 'family_type_resolved';
    } elseif ($candidates['effective_type_authority'] !== null) {
        $typeSlug = $candidates['effective_type_authority'];
        $source = 'effective_type_authority';
        $confidence = 'high';
        $status = 'family_type_resolved';
    } elseif ($candidates['classification_subtype'] !== null) {
        $typeSlug = $candidates['classification_subtype'];
        $source = 'classification_subtype';
        $confidence = 'medium';
        $status = 'subtype_fallback';
    } elseif ($candidates['classification_primary'] !== null) {
        $typeSlug = $candidates['classification_primary'];
        $source = 'classification_primary';
        $confidence = 'low';
        $status = 'primary_fallback';
    } elseif ($candidates['vt_popular_threat_category'] !== null) {
        $typeSlug = $candidates['vt_popular_threat_category'];
        $source = 'vt_popular_threat_category';
        $confidence = 'proposal';
        $status = 'proposal_only';
    }

    $reasons = [];
    foreach ($candidates as $candidateSource => $candidateType) {
        if ($candidateType === null || $typeSlug === null || $candidateType === $typeSlug) {
            continue;
        }
        $reasons[] = $candidateSource . '_disagrees';
    }

    return [
        'governed_type_slug' => in_array($source, ['governed_type_authority', 'catalog_family_type', 'family_type_authority'], true) ? $typeSlug : null,
        'proposed_type_slug' => $candidates['vt_popular_threat_category'],
        'effective_type_slug' => $typeSlug,
        'type_slug' => $typeSlug,
        'type_slug_source' => $source,
        'type_slug_confidence' => $confidence,
        'type_slug_resolution_status' => $status,
        'type_slug_conflict_flag' => $reasons !== [],
        'type_slug_conflict_reason' => $reasons === [] ? null : implode(', ', $reasons),
        'type_candidate_governed_authority' => $candidates['governed_type_authority'],
        'type_candidate_effective_authority' => $candidates['effective_type_authority'],
        'type_candidate_catalog_authority' => $candidates['catalog_type_authority'],
        'type_candidate_family_authority' => $candidates['family_type_authority'],
        'type_candidate_subtype' => $candidates['classification_subtype'],
        'type_candidate_primary' => $candidates['classification_primary'],
        'type_candidate_vt_category' => $candidates['vt_popular_threat_category'],
    ];
}

function db_dataset_readiness_has_persisted_authority_fact(array $row): bool
{
    return (int)($row['persisted_authority_id'] ?? 0) > 0
        || trim((string)($row['persisted_governed_type_slug'] ?? '')) !== ''
        || trim((string)($row['persisted_governed_family_slug'] ?? '')) !== '';
}

function db_dataset_readiness_projection_is_typed(array $row): bool
{
    return strtolower(trim((string)($row['authority_bucket'] ?? ''))) === 'authority_family_typed'
        && trim((string)($row['family_authority_type_slug'] ?? '')) !== '';
}

function db_dataset_readiness_open_conflict_family_slugs(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    if (!db_dataset_readiness_include_v2_governance_queue_surface()) {
        $cache = [];
        return $cache;
    }

    $rows = db_all(
        'SELECT DISTINCT LOWER(TRIM(COALESCE(family_slug, ""))) AS family_slug
         FROM ' . db_catalog_table('vw_android_family_v2_conflict_and_repair_queue') . '
         WHERE COALESCE(open_conflict_case_rows, 0) > 0'
    );
    $cache = [];
    foreach ($rows as $row) {
        $slug = trim((string)($row['family_slug'] ?? ''));
        if ($slug !== '') {
            $cache[$slug] = true;
        }
    }
    return $cache;
}

function db_dataset_readiness_row_has_open_conflict_case(array $row): bool
{
    $familySlugs = db_dataset_readiness_open_conflict_family_slugs();
    if ($familySlugs === []) {
        return false;
    }

    $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
    $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));
    if ($alignment !== 'mismatch' || $issueKind !== 'semantic_conflict') {
        return false;
    }

    $candidates = [
        strtolower(trim((string)($row['persisted_governed_family_slug'] ?? ''))),
        strtolower(trim((string)($row['family_authority_family_slug'] ?? ''))),
        strtolower(trim((string)($row['governed_family_slug'] ?? ''))),
        strtolower(trim((string)($row['effective_family_slug'] ?? ''))),
    ];
    foreach ($candidates as $slug) {
        if ($slug !== '' && isset($familySlugs[$slug])) {
            return true;
        }
    }
    return false;
}

function db_dataset_readiness_authority_tier(array $row): array
{
    $hasFact = db_dataset_readiness_has_persisted_authority_fact($row);
    $projectionTyped = db_dataset_readiness_projection_is_typed($row);
    $authorityBucket = strtolower(trim((string)($row['authority_bucket'] ?? '')));
    $gapReason = strtolower(trim((string)($row['authority_gap_reason'] ?? '')));
    $hasOpenConflict = db_dataset_readiness_row_has_open_conflict_case($row);

    if ($hasFact) {
        return [
            'authority_tier' => 'persisted_authority_fact',
            'authority_source' => trim((string)($row['persisted_authority_source_table'] ?? '')) !== ''
                ? trim((string)($row['persisted_authority_source_table'] ?? ''))
                : trim((string)($row['persisted_authority_source_system'] ?? '')),
        ];
    }
    if ($hasOpenConflict) {
        return [
            'authority_tier' => 'conflict_case',
            'authority_source' => 'vw_android_family_v2_conflict_and_repair_queue',
        ];
    }
    if ($projectionTyped) {
        return [
            'authority_tier' => 'derived_authority_projection',
            'authority_source' => 'v_android_sample_family_type_authority',
        ];
    }
    if (in_array($authorityBucket, ['generic_label_candidate', 'vt_tail_policy_hold_review'], true)
        || in_array($gapReason, ['resolved_token_policy_held_not_family', 'vt_tail_token_policy_held_not_family', 'resolved_token_coarse_behavior'], true)
    ) {
        return [
            'authority_tier' => 'generic_token_policy_hold',
            'authority_source' => 'v_android_sample_family_type_authority',
        ];
    }

    return [
        'authority_tier' => 'unresolved_authority',
        'authority_source' => $authorityBucket !== '' ? 'v_android_sample_family_type_authority' : 'unresolved',
    ];
}

function db_dataset_readiness_mismatch_bucket(array $row): ?string
{
    $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
    if ($alignment !== 'mismatch') {
        return null;
    }

    $tier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));
    $familyStatus = strtolower(trim((string)($row['family_resolution_status'] ?? '')));

    if ($tier === 'derived_authority_projection') {
        return 'projection_without_persisted_fact';
    }
    if ($tier === 'generic_token_policy_hold' || $issueKind === 'generic_signal') {
        return 'generic_signal_token';
    }
    if ($familyStatus === 'accepted_resolution' || $issueKind === 'alias_resolved' || $issueKind === 'signal_overlap' || $issueKind === 'short_signal_token') {
        return 'resolved_catalog_truth_vs_noisy_signal';
    }
    if ($tier === 'conflict_case' || $issueKind === 'semantic_conflict') {
        return 'true_semantic_conflict';
    }
    return 'unresolved_governance_gap';
}

function db_dataset_readiness_row_is_conflict_case(array $row): bool
{
    $tier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    if ($tier === 'conflict_case') {
        return true;
    }

    return db_dataset_readiness_mismatch_bucket($row) === 'true_semantic_conflict';
}

function db_dataset_readiness_type_benchmark_attach_family_metrics(array $classRows, array $familyRows, bool $benchmarkOnly): array
{
    $familiesByType = [];
    foreach ($familyRows as $row) {
        $type = trim((string)($row['governed_type_slug'] ?? ''));
        $family = trim((string)($row['canonical_family_label'] ?? ''));
        if ($type === '' || $family === '') {
            continue;
        }
        if (!isset($familiesByType[$type])) {
            $familiesByType[$type] = [];
        }
        $familiesByType[$type][$family] = (int)($row['sample_count'] ?? 0);
    }

    foreach ($classRows as &$row) {
        $type = trim((string)($row['governed_type_slug'] ?? ''));
        $sampleCount = (int)($row['sample_count'] ?? 0);
        $highConfidenceCount = (int)($row['high_confidence_count'] ?? 0);
        $topFamilyCount = 0;
        $topFamily = null;
        if (isset($familiesByType[$type]) && $familiesByType[$type] !== []) {
            arsort($familiesByType[$type]);
            $topFamily = (string)array_key_first($familiesByType[$type]);
            $topFamilyCount = (int)reset($familiesByType[$type]);
        }
        $row['top_family'] = $topFamily;
        $row['top_family_count'] = $topFamilyCount;
        $row['top_family_share'] = $sampleCount > 0 ? round(($topFamilyCount / $sampleCount) * 100, 2) : null;
        $row['trainable_n3'] = $sampleCount >= 3;
        $row['trainable_n10'] = $sampleCount >= 10;
        if ($benchmarkOnly) {
            if ($sampleCount >= 10) {
                $row['recommended_use'] = 'trainable_n10';
            } elseif ($sampleCount >= 3) {
                $row['recommended_use'] = 'trainable_n3_review';
            } else {
                $row['recommended_use'] = 'insufficient_support';
            }
        } else {
            if ($sampleCount >= 10 && $highConfidenceCount >= max(3, (int)floor($sampleCount / 2))) {
                $row['recommended_use'] = 'trainable_n10';
            } elseif ($sampleCount >= 3) {
                $row['recommended_use'] = 'trainable_n3_review';
            } else {
                $row['recommended_use'] = 'insufficient_support';
            }
        }
    }
    unset($row);

    usort($classRows, static function (array $a, array $b): int {
        $countCmp = ((int)($b['sample_count'] ?? 0)) <=> ((int)($a['sample_count'] ?? 0));
        if ($countCmp !== 0) {
            return $countCmp;
        }
        return strcmp((string)($a['governed_type_slug'] ?? ''), (string)($b['governed_type_slug'] ?? ''));
    });

    return $classRows;
}

function db_dataset_type_benchmark_aggregate_payload(
    bool $includeResolution,
    bool $includeLabelAuthority,
    bool $includeTypeAuthority
): array {
    $alignmentExpr = db_dataset_readiness_alignment_expr();
    $genericExpr = db_dataset_readiness_generic_family_expr();
    $canonicalFamilyExpr = db_dataset_readiness_canonical_family_expr($includeResolution);
    $derivedSql = sql_dataset_readiness_type_derived_base(
        $alignmentExpr,
        $genericExpr,
        $canonicalFamilyExpr,
        db_dataset_readiness_sql_normalize_type_expr('fta.type_slug', true),
        db_dataset_readiness_sql_normalize_type_expr('auth.governed_type_slug', true),
        db_dataset_readiness_sql_normalize_type_expr('auth.catalog_type_slug', true),
        db_dataset_readiness_sql_normalize_type_expr('auth.effective_type_slug', true),
        db_dataset_readiness_sql_normalize_type_expr('c.classification_subtype'),
        db_dataset_readiness_sql_normalize_type_expr('c.classification_primary'),
        db_dataset_readiness_sql_normalize_type_expr('sig.popular_threat_category'),
        $includeResolution,
        $includeLabelAuthority,
        $includeTypeAuthority
    );

    $summaryRow = db_one(sql_dataset_readiness_type_benchmark_summary_from_derived($derivedSql)) ?? [];
    $benchmarkClassRows = db_all(sql_dataset_readiness_type_class_counts_from_derived($derivedSql, true));
    $benchmarkFamilyRows = db_all(sql_dataset_readiness_type_family_counts_from_derived($derivedSql, true));
    $allTypedClassRows = db_all(sql_dataset_readiness_type_class_counts_from_derived($derivedSql, false));
    $allTypedFamilyRows = db_all(sql_dataset_readiness_type_family_counts_from_derived($derivedSql, false));

    $benchmarkClassRows = db_dataset_readiness_type_benchmark_attach_family_metrics($benchmarkClassRows, $benchmarkFamilyRows, true);
    $allTypedClassRows = db_dataset_readiness_type_benchmark_attach_family_metrics($allTypedClassRows, $allTypedFamilyRows, false);

    $sampleCount = (int)($summaryRow['benchmark_eligible_count'] ?? 0);
    $classCount = count($benchmarkClassRows);
    $topClass = $benchmarkClassRows[0]['governed_type_slug'] ?? null;
    $topClassCount = (int)($benchmarkClassRows[0]['sample_count'] ?? 0);
    $top5Count = 0;
    $trainableN3 = 0;
    $trainableN10 = 0;
    foreach ($benchmarkClassRows as $index => $row) {
        $rowCount = (int)($row['sample_count'] ?? 0);
        if ($rowCount >= 3) {
            $trainableN3++;
        }
        if ($rowCount >= 10) {
            $trainableN10++;
        }
        if ($index < 5) {
            $top5Count += $rowCount;
        }
    }

    return [
        'ok' => true,
        'summary' => [
            'sample_count' => $sampleCount,
            'class_count' => $classCount,
            'trainable_class_count_n3' => $trainableN3,
            'trainable_class_count_n10' => $trainableN10,
            'top_class' => $topClass,
            'top_class_count' => $topClassCount,
            'top_class_share' => $sampleCount > 0 ? round(($topClassCount / $sampleCount) * 100, 2) : null,
            'top_5_count' => $top5Count,
            'top_5_share' => $sampleCount > 0 ? round(($top5Count / $sampleCount) * 100, 2) : null,
            'generic_label_count' => (int)($summaryRow['generic_label_count'] ?? 0),
            'unresolved_count' => (int)($summaryRow['unresolved_count'] ?? 0),
            'taxonomy_mismatch_count' => (int)($summaryRow['taxonomy_mismatch_count'] ?? 0),
            'proposed_only_count' => (int)($summaryRow['proposed_only_count'] ?? 0),
            'conflict_count' => (int)($summaryRow['conflict_count'] ?? 0),
            'resolved_typed_count' => (int)($summaryRow['resolved_typed_count'] ?? 0),
            'benchmark_eligible_count' => (int)($summaryRow['benchmark_eligible_count'] ?? 0),
            'review_required_count' => max(0, (int)($summaryRow['resolved_typed_count'] ?? 0) - (int)($summaryRow['benchmark_eligible_count'] ?? 0)),
            'authority_resolved_count' => (int)($summaryRow['authority_resolved_count'] ?? 0),
            'subtype_fallback_count' => (int)($summaryRow['subtype_fallback_count'] ?? 0),
            'primary_fallback_count' => (int)($summaryRow['primary_fallback_count'] ?? 0),
            'high_confidence_count' => (int)($summaryRow['high_confidence_count'] ?? 0),
            'recommended_use' => 'Primary type target. Benchmark metrics below are restricted to clean aligned persisted authority rows; projection and fallback rows remain visible for curation and review.',
        ],
        'classes' => $benchmarkClassRows,
        'all_typed_classes' => $allTypedClassRows,
        'schema_missing' => [],
        'include_resolution_surface' => $includeResolution,
        'include_label_authority_surface' => $includeLabelAuthority,
        'include_type_authority_surface' => $includeTypeAuthority,
    ];
}

function db_dataset_readiness_family_resolution_status(array $row): string
{
    $reviewStatus = strtolower(trim((string)($row['resolution_review_status'] ?? '')));
    $resolvedFamily = trim((string)($row['resolved_family_name'] ?? ''));
    $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
    $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));

    if ($resolvedFamily !== '' && $reviewStatus === 'accepted') {
        return 'accepted_resolution';
    }
    if ($resolvedFamily !== '') {
        return 'mapped_pending_review';
    }
    if ($issueKind === 'alias_resolved') {
        return 'governed_alias';
    }
    if ($alignment === 'aligned') {
        return 'aligned';
    }
    if ($alignment === 'mismatch') {
        return 'taxonomy_mismatch';
    }
    if ($alignment === 'signal_only' || $alignment === 'catalog_only' || $alignment === 'unlabeled') {
        return 'coverage_gap';
    }
    return 'needs_review';
}

function db_dataset_readiness_recommended_row_use(array $row): string
{
    $derived = db_dataset_readiness_type_derivation($row);
    $authorityTier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    $realConflict = db_dataset_readiness_real_type_conflict($row);
    if ($authorityTier === 'persisted_authority_fact') {
        return (bool)($realConflict['flag'] ?? false)
            ? 'type_slug_target_with_conflict_review'
            : 'type_slug_target';
    }
    if ($authorityTier === 'derived_authority_projection') {
        return 'type_slug_projection_materialization_review';
    }
    if ($authorityTier === 'generic_token_policy_hold') {
        return 'hold_generic_signal_not_for_benchmark';
    }
    if ($authorityTier === 'conflict_case') {
        return 'governance_conflict_review';
    }
    if (($derived['type_slug_source'] ?? '') === 'effective_type_authority') {
        return 'type_slug_effective_authority_review';
    }
    if (($derived['type_slug_source'] ?? '') === 'classification_subtype') {
        return 'type_slug_subtype_fallback_review';
    }
    if (($derived['type_slug_source'] ?? '') === 'classification_primary') {
        return 'type_slug_primary_fallback_review';
    }
    if (($derived['type_slug_source'] ?? '') === 'vt_popular_threat_category') {
        return 'proposal_only_not_for_benchmark';
    }
    if (trim((string)($row['classification_subtype'] ?? '')) !== '') {
        return 'category_subtype_aux_only';
    }
    if (trim((string)($row['classification_primary'] ?? '')) !== '') {
        return 'category_primary_not_target';
    }
    return 'unresolved';
}

function db_dataset_readiness_high_authority_conflict_reason(array $row): ?string
{
    $fieldMap = [
        'persisted_governed_type_slug' => 'persisted_fact',
        'family_authority_type_slug' => 'family_type_authority',
        'governed_type_slug' => 'governed_projection',
        'effective_type_slug' => 'effective_type',
    ];

    $values = [];
    foreach ($fieldMap as $field => $label) {
        $value = strtolower(trim((string)($row[$field] ?? '')));
        if ($value === '') {
            continue;
        }
        $values[$label] = $value;
    }

    $unique = array_values(array_unique(array_values($values)));
    if (count($unique) <= 1) {
        return null;
    }

    $parts = [];
    foreach ($values as $label => $value) {
        $parts[] = $label . ':' . $value;
    }
    return 'high_authority_type_disagreement (' . implode(', ', $parts) . ')';
}

function db_dataset_readiness_real_type_conflict(array $row): array
{
    $reasons = [];

    if (db_dataset_readiness_row_has_open_conflict_case($row)) {
        $reasons[] = 'open_governance_conflict_case';
    }

    if (db_dataset_readiness_is_authority_consistency_debt_row($row)) {
        $reasons[] = 'authority_consistency_debt';
    }

    $highAuthorityReason = db_dataset_readiness_high_authority_conflict_reason($row);
    if ($highAuthorityReason !== null) {
        $reasons[] = $highAuthorityReason;
    }

    return [
        'flag' => $reasons !== [],
        'reason' => $reasons === [] ? null : implode('; ', $reasons),
    ];
}

function db_dataset_readiness_apply_edge_case_display_policy(array $row): array
{
    $authorityTier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    $typeSource = trim((string)($row['type_slug_source'] ?? ''));
    $typeSlug = trim((string)($row['type_slug'] ?? ''));
    $governedTypeSlug = trim((string)($row['governed_type_slug'] ?? ''));
    $authorityBucket = strtolower(trim((string)($row['authority_bucket'] ?? '')));
    $gapReason = strtolower(trim((string)($row['authority_gap_reason'] ?? '')));

    $row['display_type_slug'] = $governedTypeSlug !== '' ? $governedTypeSlug : $typeSlug;
    $row['proposed_type_slug_display'] = trim((string)($row['proposed_type_slug'] ?? ''));

    if ($authorityTier === 'derived_authority_projection'
        && strtolower(trim((string)($row['type_slug_resolution_status'] ?? ''))) === 'authority_resolved'
    ) {
        $row['type_slug_resolution_status'] = 'projection_materialization_debt';
    }

    if ($authorityTier === 'generic_token_policy_hold') {
        if ($row['proposed_type_slug_display'] === '' && $typeSlug !== '') {
            $row['proposed_type_slug_display'] = $typeSlug;
        }
        $row['display_type_slug'] = '';
        if (in_array($typeSource, ['classification_subtype', 'classification_primary', 'vt_popular_threat_category'], true)) {
            $row['type_slug_resolution_status'] = 'policy_hold';
        }
        return $row;
    }

    if ($authorityTier === 'unresolved_authority') {
        $holdReasons = [
            'resolved_token_unknown' => 'unknown_family_hold',
            'blank_family_singleton_no_signal' => 'insufficient_context',
            'low_context_blank_package_no_family_signal' => 'insufficient_context',
            'pua_without_family_signal' => 'insufficient_context',
        ];
        if ($row['proposed_type_slug_display'] === '' && $typeSlug !== '') {
            $row['proposed_type_slug_display'] = $typeSlug;
        }
        if (
            $authorityBucket === 'resolved_unknown'
            || isset($holdReasons[$gapReason])
            || in_array($typeSource, ['classification_subtype', 'classification_primary', 'vt_popular_threat_category'], true)
        ) {
            $row['display_type_slug'] = '';
            $row['type_slug_resolution_status'] = $holdReasons[$gapReason] ?? 'unresolved_authority';
        }
    }

    return $row;
}

function db_dataset_readiness_display_source_bucket(array $row): string
{
    $authorityTier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    $authorityBucket = strtolower(trim((string)($row['authority_bucket'] ?? '')));
    $typeSource = strtolower(trim((string)($row['type_slug_source'] ?? '')));

    if ($authorityTier === 'persisted_authority_fact') {
        return 'persisted_authority_fact';
    }
    if ($authorityTier === 'derived_authority_projection') {
        return 'authority_projection';
    }
    if ($authorityTier === 'generic_token_policy_hold') {
        return 'generic_policy_hold';
    }
    if ($authorityTier === 'conflict_case') {
        return 'governance_conflict_case';
    }
    if ($authorityTier === 'unresolved_authority' && $authorityBucket === 'resolved_unknown') {
        return 'unknown_family_hold';
    }
    if ($typeSource === 'vt_popular_threat_category') {
        return 'proposal_only';
    }
    if (in_array($typeSource, ['classification_subtype', 'classification_primary'], true)) {
        return 'fallback_review';
    }
    return 'unresolved_authority';
}

function db_dataset_readiness_display_confidence_bucket(array $row): string
{
    $authorityTier = strtolower(trim((string)($row['authority_tier'] ?? '')));
    $authorityBucket = strtolower(trim((string)($row['authority_bucket'] ?? '')));
    $typeSource = strtolower(trim((string)($row['type_slug_source'] ?? '')));

    if ($authorityTier === 'persisted_authority_fact') {
        return 'authority_resolved';
    }
    if ($authorityTier === 'derived_authority_projection') {
        return 'projection_review';
    }
    if ($authorityTier === 'generic_token_policy_hold') {
        return 'policy_hold';
    }
    if ($authorityTier === 'conflict_case') {
        return 'conflict_review';
    }
    if ($authorityTier === 'unresolved_authority' && $authorityBucket === 'resolved_unknown') {
        return 'unknown_placeholder_hold';
    }
    if ($typeSource === 'vt_popular_threat_category') {
        return 'proposal_only';
    }
    if (in_array($typeSource, ['classification_subtype', 'classification_primary'], true)) {
        return 'fallback_review';
    }
    return 'unresolved';
}

function db_dataset_readiness_is_benchmark_eligible(array $row): bool
{
    if (db_dataset_readiness_is_authority_consistency_debt_row($row)) {
        return false;
    }

    return strtolower(trim((string)($row['authority_tier'] ?? ''))) === 'persisted_authority_fact'
        && trim((string)($row['type_slug'] ?? '')) !== '';
}

function db_dataset_readiness_is_authority_consistency_debt_row(array $row): bool
{
    static $debtFamilies = [
        'devixor' => true,
        'pixpirate' => true,
        'joker' => true,
        'tanglebot' => true,
        'rewardsteal' => true,
        'promptspy' => true,
        'gravityrat' => true,
    ];

    if (strtolower(trim((string)($row['authority_tier'] ?? ''))) !== 'persisted_authority_fact') {
        return false;
    }

    $familyCandidates = [
        strtolower(trim((string)($row['persisted_governed_family_slug'] ?? ''))),
        strtolower(trim((string)($row['governed_family_slug'] ?? ''))),
    ];

    foreach ($familyCandidates as $slug) {
        if ($slug !== '' && isset($debtFamilies[$slug])) {
            $persistedType = strtolower(trim((string)($row['persisted_governed_type_slug'] ?? '')));
            $familyAuthorityType = strtolower(trim((string)($row['family_authority_type_slug'] ?? '')));
            if ($persistedType === '' || $familyAuthorityType === '') {
                return false;
            }
            return $persistedType !== $familyAuthorityType;
        }
    }

    return false;
}

function db_dataset_readiness_row_matches_filters(array $row, array $filters): bool
{
    $typeSlug = trim((string)($row['type_slug'] ?? ''));
    $source = trim((string)($row['type_slug_source'] ?? ''));
    $confidence = trim((string)($row['type_slug_confidence'] ?? ''));
    $recommendedUse = trim((string)($row['recommended_use'] ?? ''));
    $conflict = (bool)($row['conflict_case_flag'] ?? false);
    $benchmarkEligible = db_dataset_readiness_is_benchmark_eligible($row);

    $filterTypeSlug = trim((string)($filters['type_slug'] ?? ''));
    if ($filterTypeSlug !== '' && $typeSlug !== $filterTypeSlug) {
        return false;
    }

    $filterSource = trim((string)($filters['type_slug_source'] ?? ''));
    if ($filterSource !== '' && $source !== $filterSource) {
        return false;
    }

    $filterConfidence = trim((string)($filters['type_slug_confidence'] ?? ''));
    if ($filterConfidence !== '' && $confidence !== $filterConfidence) {
        return false;
    }

    $filterUse = trim((string)($filters['recommended_use'] ?? ''));
    if ($filterUse !== '' && $recommendedUse !== $filterUse) {
        return false;
    }

    if ((string)($filters['conflict_only'] ?? '') === '1' && !$conflict) {
        return false;
    }
    if ((string)($filters['benchmark_only'] ?? '') === '1' && !$benchmarkEligible) {
        return false;
    }
    if ((string)($filters['review_only'] ?? '') === '1' && $benchmarkEligible) {
        return false;
    }

    return true;
}

function db_dataset_readiness_label_surfaces_fast_path_eligible(array $filters): bool
{
    $typeSource = trim((string)($filters['type_slug_source'] ?? ''));
    return trim((string)($filters['type_slug'] ?? '')) === ''
        && ($typeSource === '' || $typeSource === 'family_type_authority')
        && trim((string)($filters['type_slug_confidence'] ?? '')) === ''
        && trim((string)($filters['recommended_use'] ?? '')) === ''
        && (string)($filters['conflict_only'] ?? '') !== '1'
        && (string)($filters['benchmark_only'] ?? '') !== '1'
        && (string)($filters['review_only'] ?? '') !== '1';
}

function db_dataset_readiness_label_surface_filters(
    array $filters,
    bool $includeLabelAuthority = false,
    bool $includeTypeAuthority = false,
    bool $fastBrowseMode = false
): array
{
    $where = [];
    $params = [];

    $query = trim((string)($filters['q'] ?? ''));
    if ($query !== '') {
        if ($fastBrowseMode) {
            $searchClauses = [
                "CONVERT(COALESCE(c.family_label, '') USING utf8mb4) = :q_exact_family",
                "CONVERT(COALESCE(c.classification_primary, '') USING utf8mb4) = :q_exact_primary",
                "CONVERT(COALESCE(c.classification_subtype, '') USING utf8mb4) = :q_exact_subtype",
                "CONVERT(COALESCE(sig.popular_threat_name, '') USING utf8mb4) = :q_exact_vt_family",
                "CONVERT(COALESCE(sig.popular_threat_category, '') USING utf8mb4) = :q_exact_vt_category",
                "CONVERT(COALESCE(c.android_package_name, '') USING utf8mb4) = :q_exact_package",
                "CONVERT(COALESCE(c.sample_label, '') USING utf8mb4) LIKE :q_prefix_sample",
                "CONVERT(COALESCE(c.android_package_name, '') USING utf8mb4) LIKE :q_prefix_package",
            ];
            $params['q_exact_family'] = $query;
            $params['q_exact_primary'] = $query;
            $params['q_exact_subtype'] = $query;
            $params['q_exact_vt_family'] = $query;
            $params['q_exact_vt_category'] = $query;
            $params['q_exact_package'] = $query;
            if (preg_match('/^[A-Fa-f0-9]{64}$/', $query) === 1) {
                $searchClauses[] = 'LOWER(HEX(c.sha256)) = :q_sha_hex';
                $params['q_sha_hex'] = strtolower($query);
            }
            if ($includeLabelAuthority) {
                $searchClauses[] = "CONVERT(COALESCE(auth.governed_type_slug, '') USING utf8mb4) = :q_exact_auth_governed";
                $searchClauses[] = "CONVERT(COALESCE(auth.effective_type_slug, '') USING utf8mb4) = :q_exact_auth_effective";
                $params['q_exact_auth_governed'] = $query;
                $params['q_exact_auth_effective'] = $query;
            }
            if ($includeTypeAuthority) {
                $searchClauses[] = "CONVERT(COALESCE(fta.type_slug, '') USING utf8mb4) = :q_exact_family_type";
                $params['q_exact_family_type'] = $query;
            }
            $where[] = '(
                ' . implode("\n                OR ", $searchClauses) . '
            )';
            $params['q_prefix_sample'] = $query . '%';
            $params['q_prefix_package'] = $query . '%';
        } else {
            $params['q_exact'] = $query;
            $searchClauses = [
                'c.sha256 = :q_exact',
                'c.sha256 LIKE :q_like_sha',
                "CONVERT(COALESCE(c.sample_label, '') USING utf8mb4) LIKE :q_like_sample",
                "CONVERT(COALESCE(c.family_label, '') USING utf8mb4) LIKE :q_like_family",
                "CONVERT(COALESCE(c.classification_primary, '') USING utf8mb4) LIKE :q_like_primary",
                "CONVERT(COALESCE(c.classification_subtype, '') USING utf8mb4) LIKE :q_like_subtype",
                "CONVERT(COALESCE(sig.popular_threat_name, '') USING utf8mb4) LIKE :q_like_vt_family",
                "CONVERT(COALESCE(sig.popular_threat_category, '') USING utf8mb4) LIKE :q_like_vt_category",
                "CONVERT(COALESCE(c.android_package_name, '') USING utf8mb4) LIKE :q_like_package",
            ];
            if ($includeLabelAuthority) {
                $searchClauses[] = "CONVERT(COALESCE(auth.governed_type_slug, '') USING utf8mb4) LIKE :q_like_auth_governed";
                $searchClauses[] = "CONVERT(COALESCE(auth.effective_type_slug, '') USING utf8mb4) LIKE :q_like_auth_effective";
            }
            if ($includeTypeAuthority) {
                $searchClauses[] = "CONVERT(COALESCE(fta.type_slug, '') USING utf8mb4) LIKE :q_like_family_type";
            }

            $where[] = '(
                ' . implode("\n            OR ", $searchClauses) . '
            )';
            $like = '%' . $query . '%';
            $params['q_like_sha'] = $like;
            $params['q_like_sample'] = $like;
            $params['q_like_family'] = $like;
            $params['q_like_primary'] = $like;
            $params['q_like_subtype'] = $like;
            $params['q_like_vt_family'] = $like;
            $params['q_like_vt_category'] = $like;
            $params['q_like_package'] = $like;
            if ($includeLabelAuthority) {
                $params['q_like_auth_governed'] = $like;
                $params['q_like_auth_effective'] = $like;
            }
            if ($includeTypeAuthority) {
                $params['q_like_family_type'] = $like;
            }
        }
    }

    return ['where' => $where, 'params' => $params];
}

function db_dataset_label_surfaces_page(array $filters = []): array
{
    $schema = db_dataset_readiness_schema_status();
    $page = max(1, (int)($filters['page'] ?? 1));
    $pageSize = max(1, min((int)($filters['page_size'] ?? 50), 200));
    $offset = max(0, ($page - 1) * $pageSize);
    $includeConfidenceCounts = ((string)($filters['advanced'] ?? '0')) === '1';
    $includeResolution = db_dataset_readiness_include_resolution_surface();
    $includeLabelAuthority = db_dataset_readiness_include_label_authority_surface();
    $includeTypeAuthority = db_dataset_readiness_include_type_authority_surface();
    $includePersistedAuthorityFact = db_dataset_readiness_include_persisted_authority_surface();

    if (!$schema['ok']) {
        return [
            'ok' => false,
            'rows' => [],
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => 0,
            'total_pages' => 1,
            'has_more' => false,
            'schema_missing' => $schema['missing'],
            'include_resolution_surface' => $includeResolution,
            'include_label_authority_surface' => $includeLabelAuthority,
            'include_type_authority_surface' => $includeTypeAuthority,
            'include_persisted_authority_surface' => $includePersistedAuthorityFact,
        ];
    }

    $alignmentExpr = db_dataset_readiness_alignment_expr();
    $genericExpr = db_dataset_readiness_generic_family_expr();
    $canonicalFamilyExpr = db_dataset_readiness_canonical_family_expr($includeResolution);
    $baseSql = sql_dataset_readiness_label_surfaces_base(
        $alignmentExpr,
        $genericExpr,
        $canonicalFamilyExpr,
        $includeResolution,
        $includeLabelAuthority,
        $includeTypeAuthority,
        $includePersistedAuthorityFact
    );
    $countSql = sql_dataset_readiness_label_surfaces_count_base($includeLabelAuthority, $includeTypeAuthority, $includePersistedAuthorityFact);

    $fastPathEligible = db_dataset_readiness_label_surfaces_fast_path_eligible($filters);
    $filterData = db_dataset_readiness_label_surface_filters($filters, $includeLabelAuthority, $includeTypeAuthority, $fastPathEligible);
    $whereSql = $filterData['where'] ? (' AND ' . implode(' AND ', $filterData['where'])) : '';
    $params = $filterData['params'];

    if ($fastPathEligible) {
        $fastTypeSource = trim((string)($filters['type_slug_source'] ?? ''));
        $needsFastTypeAuthority = $fastTypeSource === 'family_type_authority';
        $lightCountSql = sql_dataset_readiness_label_surfaces_count_base(false, $needsFastTypeAuthority, false);
        $lightFilterData = db_dataset_readiness_label_surface_filters($filters, false, $needsFastTypeAuthority, true);
        $lightWhere = $lightFilterData['where'];
        if ($needsFastTypeAuthority) {
            $lightWhere[] = "NULLIF(TRIM(COALESCE(fta.type_slug, '')), '') IS NOT NULL";
        }
        $lightWhereSql = $lightWhere ? (' AND ' . implode(' AND ', $lightWhere)) : '';
        $lightParams = $lightFilterData['params'];

        $countStmt = db()->prepare($lightCountSql . $lightWhereSql);
        foreach ($lightParams as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $countRow = $countStmt->fetch() ?: [];
        $totalCount = (int)($countRow['total_count'] ?? 0);

        $idSql = "
            SELECT c.sample_id
            FROM " . db_catalog_table('malware_sample_catalog') . " c
            LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
            " . ($needsFastTypeAuthority
                ? "LEFT JOIN " . db_catalog_table('v_android_sample_family_type_authority') . " fta ON fta.sample_id = c.sample_id"
                : '') . "
            WHERE LOWER(COALESCE(c.platform, '')) = 'android'
            {$lightWhereSql}
            ORDER BY c.sample_id DESC
            LIMIT :limit OFFSET :offset";
        $idStmt = db()->prepare($idSql);
        foreach ($lightParams as $key => $value) {
            $idStmt->bindValue(':' . $key, $value);
        }
        $idStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $idStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $idStmt->execute();

        $sampleIds = [];
        while (($idRow = $idStmt->fetch()) !== false) {
            $sampleIds[] = (int)($idRow['sample_id'] ?? 0);
        }

        $rows = [];
        $sourceCounts = [];
        $confidenceCounts = [];
        $benchmarkEligibleCount = 0;
        $reviewOnlyCount = 0;
        $conflictCount = 0;
        if ($sampleIds !== []) {
            $idPlaceholders = [];
            $detailParams = [];
            foreach ($sampleIds as $index => $sampleId) {
                $placeholder = 'sample_id_' . $index;
                $idPlaceholders[] = ':' . $placeholder;
                $detailParams[$placeholder] = $sampleId;
            }

            $sql = $baseSql . '
                AND c.sample_id IN (' . implode(', ', $idPlaceholders) . ')
                ORDER BY c.sample_id DESC';
            $stmt = db()->prepare($sql);
            foreach ($detailParams as $key => $value) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
            }
            $stmt->execute();

            while (($row = $stmt->fetch()) !== false) {
                $row = db_family_taxonomy_enrich_rows([$row])[0];
                $derived = db_dataset_readiness_type_derivation($row);
                $authorityTier = db_dataset_readiness_authority_tier($row);
                $row = array_merge($row, $derived, [
                    'family_resolution_status' => db_dataset_readiness_family_resolution_status($row),
                ], $authorityTier);
                $row['has_persisted_authority_fact'] = db_dataset_readiness_has_persisted_authority_fact($row);
                $row['governed_family_slug'] = trim((string)($row['persisted_governed_family_slug'] ?? '')) !== ''
                    ? $row['persisted_governed_family_slug']
                    : ($row['governed_family_slug'] ?? ($row['family_authority_family_slug'] ?? ''));
                $row['governed_type_slug'] = trim((string)($row['persisted_governed_type_slug'] ?? '')) !== ''
                    ? $row['persisted_governed_type_slug']
                    : ($row['type_slug'] ?? '');
                $row['authority_resolution_method'] = trim((string)($row['persisted_authority_resolution_method'] ?? '')) !== ''
                    ? $row['persisted_authority_resolution_method']
                    : (trim((string)($row['authority_resolution_method'] ?? '')) !== '' ? $row['authority_resolution_method'] : ($row['type_slug_source'] ?? ''));
                $row['review_status'] = trim((string)($row['persisted_authority_review_status'] ?? '')) !== ''
                    ? $row['persisted_authority_review_status']
                    : (trim((string)($row['authority_review_status'] ?? '')) !== '' ? $row['authority_review_status'] : ($row['resolution_review_status'] ?? ''));
                $row['mismatch_bucket'] = db_dataset_readiness_mismatch_bucket($row);
                $row['conflict_case_flag'] = db_dataset_readiness_row_is_conflict_case($row);
                $row['type_slug_candidate_disagreement_flag'] = (bool)($row['type_slug_conflict_flag'] ?? false);
                $row['type_slug_candidate_disagreement_reason'] = $row['type_slug_conflict_reason'] ?? null;
                $realConflict = db_dataset_readiness_real_type_conflict($row);
                $row['type_slug_conflict_flag'] = (bool)($realConflict['flag'] ?? false);
                $row['type_slug_conflict_reason'] = $realConflict['reason'] ?? null;
                $row['recommended_use'] = db_dataset_readiness_recommended_row_use($row);
                $row = db_dataset_readiness_apply_edge_case_display_policy($row);
                $source = db_dataset_readiness_display_source_bucket($row);
                $sourceCounts[$source] = (int)($sourceCounts[$source] ?? 0) + 1;
                if ($includeConfidenceCounts) {
                    $confidence = db_dataset_readiness_display_confidence_bucket($row);
                    $confidenceCounts[$confidence] = (int)($confidenceCounts[$confidence] ?? 0) + 1;
                }
                if (db_dataset_readiness_is_benchmark_eligible($row)) {
                    $benchmarkEligibleCount++;
                } else {
                    $reviewOnlyCount++;
                }
                if ((bool)($row['conflict_case_flag'] ?? false)) {
                    $conflictCount++;
                }
                $rows[] = $row;
            }
        }

        arsort($sourceCounts);
        if ($includeConfidenceCounts) {
            arsort($confidenceCounts);
        }
        $totalPages = max(1, (int)ceil($totalCount / $pageSize));

        return [
            'ok' => true,
            'rows' => $rows,
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $totalCount,
            'total_pages' => $totalPages,
            'has_more' => ($offset + $pageSize) < $totalCount,
            'include_resolution_surface' => $includeResolution,
            'include_label_authority_surface' => $includeLabelAuthority,
            'include_type_authority_surface' => $includeTypeAuthority,
            'include_persisted_authority_surface' => $includePersistedAuthorityFact,
            'source_counts' => $sourceCounts,
            'confidence_counts' => $confidenceCounts,
            'benchmark_eligible_count' => $benchmarkEligibleCount,
            'review_only_count' => $reviewOnlyCount,
            'conflict_count' => $conflictCount,
            'schema_missing' => [],
            'fast_page_scope_counts' => true,
        ];
    }

    $sql = $baseSql . $whereSql . '
        ORDER BY c.sample_id DESC';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    $rows = [];
    $totalCount = 0;
    $pageStart = $offset;
    $pageEnd = $offset + $pageSize;
    $sourceCounts = [];
    $confidenceCounts = [];
    $benchmarkEligibleCount = 0;
    $reviewOnlyCount = 0;
    $conflictCount = 0;

    while (($row = $stmt->fetch()) !== false) {
        $row = db_family_taxonomy_enrich_rows([$row])[0];
        $derived = db_dataset_readiness_type_derivation($row);
        $authorityTier = db_dataset_readiness_authority_tier($row);
        $row = array_merge($row, $derived, [
            'family_resolution_status' => db_dataset_readiness_family_resolution_status($row),
        ], $authorityTier);
        $row['has_persisted_authority_fact'] = db_dataset_readiness_has_persisted_authority_fact($row);
        $row['governed_family_slug'] = trim((string)($row['persisted_governed_family_slug'] ?? '')) !== ''
            ? $row['persisted_governed_family_slug']
            : ($row['governed_family_slug'] ?? ($row['family_authority_family_slug'] ?? ''));
        $row['governed_type_slug'] = trim((string)($row['persisted_governed_type_slug'] ?? '')) !== ''
            ? $row['persisted_governed_type_slug']
            : ($row['type_slug'] ?? '');
        $row['authority_resolution_method'] = trim((string)($row['persisted_authority_resolution_method'] ?? '')) !== ''
            ? $row['persisted_authority_resolution_method']
            : (trim((string)($row['authority_resolution_method'] ?? '')) !== '' ? $row['authority_resolution_method'] : ($row['type_slug_source'] ?? ''));
        $row['review_status'] = trim((string)($row['persisted_authority_review_status'] ?? '')) !== ''
            ? $row['persisted_authority_review_status']
            : (trim((string)($row['authority_review_status'] ?? '')) !== '' ? $row['authority_review_status'] : ($row['resolution_review_status'] ?? ''));
        $row['mismatch_bucket'] = db_dataset_readiness_mismatch_bucket($row);
        $row['conflict_case_flag'] = db_dataset_readiness_row_is_conflict_case($row);
        $row['type_slug_candidate_disagreement_flag'] = (bool)($row['type_slug_conflict_flag'] ?? false);
        $row['type_slug_candidate_disagreement_reason'] = $row['type_slug_conflict_reason'] ?? null;
        $realConflict = db_dataset_readiness_real_type_conflict($row);
        $row['type_slug_conflict_flag'] = (bool)($realConflict['flag'] ?? false);
        $row['type_slug_conflict_reason'] = $realConflict['reason'] ?? null;
        $row['recommended_use'] = db_dataset_readiness_recommended_row_use($row);
        $row = db_dataset_readiness_apply_edge_case_display_policy($row);
        if (!db_dataset_readiness_row_matches_filters($row, $filters)) {
            continue;
        }

        $totalCount++;
        $source = db_dataset_readiness_display_source_bucket($row);
        $sourceCounts[$source] = (int)($sourceCounts[$source] ?? 0) + 1;
        if ($includeConfidenceCounts) {
            $confidence = db_dataset_readiness_display_confidence_bucket($row);
            $confidenceCounts[$confidence] = (int)($confidenceCounts[$confidence] ?? 0) + 1;
        }
        $isBenchmarkEligible = db_dataset_readiness_is_benchmark_eligible($row);
        if ($isBenchmarkEligible) {
            $benchmarkEligibleCount++;
        } else {
            $reviewOnlyCount++;
        }
        if ((bool)($row['conflict_case_flag'] ?? false)) {
            $conflictCount++;
        }

        $matchedIndex = $totalCount - 1;
        if ($matchedIndex < $pageStart || $matchedIndex >= $pageEnd) {
            continue;
        }
        $rows[] = $row;
    }

    arsort($sourceCounts);
    if ($includeConfidenceCounts) {
        arsort($confidenceCounts);
    }
    $totalPages = max(1, (int)ceil($totalCount / $pageSize));

    return [
        'ok' => true,
        'rows' => $rows,
        'page' => $page,
        'page_size' => $pageSize,
        'total_count' => $totalCount,
        'total_pages' => $totalPages,
        'has_more' => ($offset + $pageSize) < $totalCount,
        'include_resolution_surface' => $includeResolution,
        'include_label_authority_surface' => $includeLabelAuthority,
        'include_type_authority_surface' => $includeTypeAuthority,
        'include_persisted_authority_surface' => $includePersistedAuthorityFact,
        'source_counts' => $sourceCounts,
        'confidence_counts' => $confidenceCounts,
        'benchmark_eligible_count' => $benchmarkEligibleCount,
        'review_only_count' => $reviewOnlyCount,
        'conflict_count' => $conflictCount,
        'schema_missing' => [],
        'fast_page_scope_counts' => false,
    ];
}

function db_dataset_readiness_type_row_stmt(): PDOStatement
{
    $schema = db_dataset_readiness_schema_status();
    $includeResolution = db_dataset_readiness_include_resolution_surface();
    $includeLabelAuthority = db_dataset_readiness_include_label_authority_surface();
    $includeTypeAuthority = db_dataset_readiness_include_type_authority_surface();
    $includePersistedAuthorityFact = db_dataset_readiness_include_persisted_authority_surface();

    if (!$schema['ok']) {
        throw new RuntimeException('Dataset readiness schema requirements are not met.');
    }

    $alignmentExpr = db_dataset_readiness_alignment_expr();
    $genericExpr = db_dataset_readiness_generic_family_expr();
    $canonicalFamilyExpr = db_dataset_readiness_canonical_family_expr($includeResolution);
    $sql = sql_dataset_readiness_type_benchmark_rows_base(
        $alignmentExpr,
        $genericExpr,
        $canonicalFamilyExpr,
        $includeResolution,
        $includeLabelAuthority,
        $includeTypeAuthority,
        $includePersistedAuthorityFact
    );
    $stmt = db()->prepare($sql);
    $stmt->execute();
    return $stmt;
}

function db_dataset_readiness_type_rows_cache(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $rows = [];
    $stmt = db_dataset_readiness_type_row_stmt();
    while (($row = $stmt->fetch()) !== false) {
        $row = db_dataset_readiness_lightweight_taxonomy_enrich_row($row);
        $row = array_merge($row, db_dataset_readiness_type_derivation($row));
        $row = array_merge($row, db_dataset_readiness_authority_tier($row));
        $row['has_persisted_authority_fact'] = db_dataset_readiness_has_persisted_authority_fact($row);
        $row['governed_family_slug'] = trim((string)($row['persisted_governed_family_slug'] ?? '')) !== ''
            ? $row['persisted_governed_family_slug']
            : ($row['governed_family_slug'] ?? ($row['family_authority_family_slug'] ?? ''));
        $row['governed_type_slug'] = trim((string)($row['persisted_governed_type_slug'] ?? '')) !== ''
            ? $row['persisted_governed_type_slug']
            : ($row['type_slug'] ?? '');
        $row['authority_resolution_method'] = trim((string)($row['persisted_authority_resolution_method'] ?? '')) !== ''
            ? $row['persisted_authority_resolution_method']
            : (trim((string)($row['authority_resolution_method'] ?? '')) !== '' ? $row['authority_resolution_method'] : ($row['type_slug_source'] ?? ''));
        $row['review_status'] = trim((string)($row['persisted_authority_review_status'] ?? '')) !== ''
            ? $row['persisted_authority_review_status']
            : (trim((string)($row['authority_review_status'] ?? '')) !== '' ? $row['authority_review_status'] : ($row['resolution_review_status'] ?? ''));
        $row['mismatch_bucket'] = db_dataset_readiness_mismatch_bucket($row);
        $row['conflict_case_flag'] = db_dataset_readiness_row_is_conflict_case($row);
        $row['type_slug_candidate_disagreement_flag'] = (bool)($row['type_slug_conflict_flag'] ?? false);
        $row['type_slug_candidate_disagreement_reason'] = $row['type_slug_conflict_reason'] ?? null;
        $realConflict = db_dataset_readiness_real_type_conflict($row);
        $row['type_slug_conflict_flag'] = (bool)($realConflict['flag'] ?? false);
        $row['type_slug_conflict_reason'] = $realConflict['reason'] ?? null;
        $row['recommended_use'] = db_dataset_readiness_recommended_row_use($row);
        $row = db_dataset_readiness_apply_edge_case_display_policy($row);
        $rows[] = $row;
    }
    $cache = $rows;
    return $cache;
}

function db_dataset_readiness_type_rows_by_sample_id(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    foreach (db_dataset_readiness_type_rows_cache() as $row) {
        $sampleId = (int)($row['sample_id'] ?? 0);
        if ($sampleId > 0) {
            $cache[$sampleId] = $row;
        }
    }

    return $cache;
}

function db_dataset_readiness_scope_counts_from_sample_ids(array $sampleIds): array
{
    $rowsById = db_dataset_readiness_type_rows_by_sample_id();
    $sourceCounts = [];
    $confidenceCounts = [];
    $benchmarkEligibleCount = 0;
    $reviewOnlyCount = 0;
    $conflictCount = 0;
    $matchedCount = 0;

    foreach ($sampleIds as $sampleId) {
        $sampleId = (int)$sampleId;
        if ($sampleId <= 0 || !isset($rowsById[$sampleId])) {
            continue;
        }

        $row = $rowsById[$sampleId];
        $matchedCount++;
        $source = db_dataset_readiness_display_source_bucket($row);
        $confidence = db_dataset_readiness_display_confidence_bucket($row);
        $sourceCounts[$source] = (int)($sourceCounts[$source] ?? 0) + 1;
        $confidenceCounts[$confidence] = (int)($confidenceCounts[$confidence] ?? 0) + 1;
        if (db_dataset_readiness_is_benchmark_eligible($row)) {
            $benchmarkEligibleCount++;
        } else {
            $reviewOnlyCount++;
        }
        if ((bool)($row['conflict_case_flag'] ?? false)) {
            $conflictCount++;
        }
    }

    arsort($sourceCounts);
    arsort($confidenceCounts);

    return [
        'matched_count' => $matchedCount,
        'source_counts' => $sourceCounts,
        'confidence_counts' => $confidenceCounts,
        'benchmark_eligible_count' => $benchmarkEligibleCount,
        'review_only_count' => $reviewOnlyCount,
        'conflict_count' => $conflictCount,
    ];
}

function db_dataset_readiness_lightweight_issue(array $row): array
{
    $resolvedFamilyName = trim((string)($row['resolved_family_name'] ?? ''));
    $resolutionReviewStatus = strtolower(trim((string)($row['resolution_review_status'] ?? '')));
    $mappingRows = (int)($row['mapping_rows'] ?? 0);
    $acceptedMappingRows = (int)($row['accepted_mapping_rows'] ?? 0);
    $distinctFamilies = (int)($row['distinct_families'] ?? 0);
    $acceptedDistinctFamilies = (int)($row['accepted_distinct_families'] ?? 0);
    $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
    $catalog = trim((string)($row['family_label'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $vtSuggestedLabel = trim((string)($row['vt_suggested_label'] ?? ''));
    $sourceBatchLabel = trim((string)($row['source_batch_label'] ?? ''));
    $catalogNorm = db_family_taxonomy_normalize_token($catalog);
    $signalNorm = db_family_taxonomy_normalize_token($signal);
    $generic = db_family_taxonomy_generic_tokens();
    $governedTargets = db_family_taxonomy_governed_signal_targets($signal);

    if (
        $resolvedFamilyName !== ''
        && $resolutionReviewStatus === 'accepted'
        && $mappingRows === 1
        && $acceptedMappingRows === 1
        && $distinctFamilies === 1
        && $acceptedDistinctFamilies === 1
    ) {
        return [
            'issue_kind' => 'alias_resolved',
            'issue_reason' => 'Resolved family view already has a single accepted mapping.',
        ];
    }

    if ($alignment === 'unlabeled') {
        return ['issue_kind' => 'unlabeled', 'issue_reason' => 'Neither catalog family nor VT signal family is present.'];
    }
    if ($alignment === 'signal_only') {
        return ['issue_kind' => 'catalog_missing', 'issue_reason' => 'VT signal exists but the catalog family label is empty.'];
    }
    if ($alignment === 'catalog_only') {
        return ['issue_kind' => 'signal_gap', 'issue_reason' => 'Catalog family label exists but VT signal family is missing.'];
    }
    if ($alignment === 'aligned') {
        if (in_array($catalogNorm, $generic, true)) {
            return ['issue_kind' => 'weak_generic_alignment', 'issue_reason' => 'Catalog and VT align only on a generic family token.'];
        }
        return ['issue_kind' => 'aligned', 'issue_reason' => 'Catalog family and VT signal family align.'];
    }

    if ($catalogNorm !== '' && $signalNorm !== '' && !in_array($catalogNorm, $generic, true) && !in_array($signalNorm, $generic, true)) {
        if (in_array($catalogNorm, $governedTargets, true)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'VT signal family token is a governed alias of the current catalog family.'];
        }
        if (db_family_taxonomy_signal_secondary_tokens_include_catalog_family($catalog, $vtSuggestedLabel, $signal)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'VT label already includes the catalog family as a secondary token.'];
        }
        if (db_family_taxonomy_source_batch_supports_catalog_family($sourceBatchLabel, $catalog)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'Source-batch provenance supports the current catalog family.'];
        }
    }

    if (in_array($catalogNorm, $generic, true) && in_array($signalNorm, $generic, true)) {
        return ['issue_kind' => 'generic_signal', 'issue_reason' => 'Catalog family and VT signal are both generic.'];
    }
    if (in_array($catalogNorm, $generic, true)) {
        return ['issue_kind' => 'placeholder_catalog', 'issue_reason' => 'Catalog family uses a placeholder or generic token.'];
    }
    if (db_family_taxonomy_signal_token_is_unstable($signalNorm)) {
        return ['issue_kind' => 'generic_signal', 'issue_reason' => 'VT signal family token is unstable or generic.'];
    }
    if (db_family_taxonomy_signal_has_noisy_secondary_tokens($signal, $vtSuggestedLabel)) {
        return ['issue_kind' => 'signal_overlap', 'issue_reason' => 'VT label combines the family token with noisy secondary tokens.'];
    }
    if (db_family_taxonomy_signal_token_is_weak_short($signalNorm)) {
        return ['issue_kind' => 'short_signal_token', 'issue_reason' => 'VT signal family token is very short.'];
    }

    return ['issue_kind' => 'semantic_conflict', 'issue_reason' => 'Catalog family and VT signal remain unresolved.'];
}

function db_dataset_readiness_lightweight_taxonomy_enrich_row(array $row): array
{
    $issue = db_dataset_readiness_lightweight_issue($row);
    $row['issue_kind'] = $issue['issue_kind'];
    $row['issue_reason'] = $issue['issue_reason'];
    $row['family_resolution_status'] = db_dataset_readiness_family_resolution_status($row);
    return $row;
}

function db_dataset_readiness_authority_materialization_debt(array $rows): array
{
    $pairs = [];
    foreach ($rows as $row) {
        if (strtolower(trim((string)($row['authority_tier'] ?? ''))) !== 'derived_authority_projection') {
            continue;
        }
        $familySlug = trim((string)($row['governed_family_slug'] ?? ''));
        $typeSlug = trim((string)($row['governed_type_slug'] ?? ''));
        if ($familySlug === '' || $typeSlug === '') {
            continue;
        }
        $key = strtolower($familySlug) . '|' . strtolower($typeSlug);
        if (!isset($pairs[$key])) {
            $pairs[$key] = [
                'governed_family_slug' => $familySlug,
                'governed_type_slug' => $typeSlug,
                'row_count' => 0,
                'highlight' => in_array(strtolower($familySlug), ['godfather', 'arsinkrat', 'smsspy', 'fakecop'], true),
            ];
        }
        $pairs[$key]['row_count']++;
    }

    $pairs = array_values($pairs);
    usort($pairs, static function (array $a, array $b): int {
        $cmp = ((int)($b['row_count'] ?? 0)) <=> ((int)($a['row_count'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)($a['governed_family_slug'] ?? ''), (string)($b['governed_family_slug'] ?? ''));
    });
    return $pairs;
}

function db_dataset_readiness_mismatch_summary(array $rows): array
{
    $summary = [
        'resolved_catalog_truth_vs_noisy_signal' => 0,
        'unresolved_governance_gap' => 0,
        'true_semantic_conflict' => 0,
        'generic_signal_token' => 0,
        'projection_without_persisted_fact' => 0,
    ];
    foreach ($rows as $row) {
        $bucket = db_dataset_readiness_mismatch_bucket($row);
        if ($bucket !== null && isset($summary[$bucket])) {
            $summary[$bucket]++;
        }
    }
    return $summary;
}

function db_dataset_readiness_v2_governance_queue_summary(): array
{
    if (!db_dataset_readiness_include_v2_governance_queue_surface()) {
        return [
            'available' => false,
            'total_queue_rows' => 0,
            'governance_queue_state_counts' => [],
            'record_repair_state_counts' => [],
            'governance_and_repair_state_counts' => [],
            'open_conflict_case_rows' => 0,
            'missing_alias_surface_rows' => 0,
            'missing_structural_assertion_rows' => 0,
            'missing_external_mapping_rows' => 0,
        ];
    }

    $governanceStateRows = db_all(
        'SELECT governance_queue_state, COUNT(*) AS row_count
         FROM ' . db_catalog_table('vw_android_family_governance_queue') . '
         GROUP BY governance_queue_state
         ORDER BY row_count DESC, governance_queue_state ASC'
    );
    $recordRepairRows = db_all(
        'SELECT v2_record_repair_state, COUNT(*) AS row_count
         FROM ' . db_catalog_table('vw_android_family_v2_record_repair_queue') . '
         GROUP BY v2_record_repair_state
         ORDER BY row_count DESC, v2_record_repair_state ASC'
    );
    $governanceRepairRows = db_all(
        'SELECT v2_governance_and_repair_state, COUNT(*) AS row_count
         FROM ' . db_catalog_table('vw_android_family_v2_conflict_and_repair_queue') . '
         GROUP BY v2_governance_and_repair_state
         ORDER BY row_count DESC, v2_governance_and_repair_state ASC'
    );
    $headlineRow = db_one(
        'SELECT
            COUNT(*) AS total_queue_rows,
            SUM(CASE WHEN COALESCE(open_conflict_case_rows, 0) > 0 THEN 1 ELSE 0 END) AS open_conflict_case_rows,
            SUM(CASE WHEN COALESCE(v2_governance_and_repair_state, "") = "missing_structural_assertions" THEN 1 ELSE 0 END) AS missing_structural_assertion_rows,
            SUM(CASE WHEN COALESCE(v2_governance_and_repair_state, "") = "missing_external_mappings" THEN 1 ELSE 0 END) AS missing_external_mapping_rows
         FROM ' . db_catalog_table('vw_android_family_v2_conflict_and_repair_queue')
    ) ?: [];
    $missingAliasRow = db_one(
        'SELECT COUNT(*) AS row_count
         FROM ' . db_catalog_table('vw_android_family_governance_queue') . '
         WHERE COALESCE(governance_queue_state, "") = "missing_alias_surface"'
    ) ?: [];

    $toMap = static function (array $rows, string $key): array {
        $map = [];
        foreach ($rows as $row) {
            $label = trim((string)($row[$key] ?? ''));
            if ($label === '') {
                $label = '(blank)';
            }
            $map[$label] = (int)($row['row_count'] ?? 0);
        }
        return $map;
    };

    return [
        'available' => true,
        'total_queue_rows' => (int)($headlineRow['total_queue_rows'] ?? 0),
        'governance_queue_state_counts' => $toMap($governanceStateRows, 'governance_queue_state'),
        'record_repair_state_counts' => $toMap($recordRepairRows, 'v2_record_repair_state'),
        'governance_and_repair_state_counts' => $toMap($governanceRepairRows, 'v2_governance_and_repair_state'),
        'open_conflict_case_rows' => (int)($headlineRow['open_conflict_case_rows'] ?? 0),
        'missing_alias_surface_rows' => (int)($missingAliasRow['row_count'] ?? 0),
        'missing_structural_assertion_rows' => (int)($headlineRow['missing_structural_assertion_rows'] ?? 0),
        'missing_external_mapping_rows' => (int)($headlineRow['missing_external_mapping_rows'] ?? 0),
    ];
}

function db_dataset_readiness_force_refresh_requested(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }

    $refresh = strtolower(trim((string)($_GET['refresh'] ?? '')));
    return in_array($refresh, ['1', 'true', 'yes', 'y'], true);
}

function db_dataset_type_benchmark(): array
{
    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }

    $schema = db_dataset_readiness_schema_status();
    $includeResolution = db_dataset_readiness_include_resolution_surface();
    $includeLabelAuthority = db_dataset_readiness_include_label_authority_surface();
    $includeTypeAuthority = db_dataset_readiness_include_type_authority_surface();

    if (!$schema['ok']) {
        $requestCache = [
            'ok' => false,
            'summary' => [],
            'classes' => [],
            'schema_missing' => $schema['missing'],
            'include_resolution_surface' => $includeResolution,
            'include_label_authority_surface' => $includeLabelAuthority,
            'include_type_authority_surface' => $includeTypeAuthority,
        ];
        return $requestCache;
    }

    $transientKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'include_resolution' => $includeResolution,
        'include_label_authority' => $includeLabelAuthority,
        'include_type_authority' => $includeTypeAuthority,
        'readiness_model' => 'authority_tiers_v2_android_scope_clean_benchmark_v4',
        'version' => APP_VERSION,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $cacheTtlSeconds = 21600;
    $forceRefresh = db_dataset_readiness_force_refresh_requested();
    $cacheNamespace = 'dataset_type_benchmark_clean_v3';
    $cached = $forceRefresh ? null : app_transient_cache_read($cacheNamespace, $transientKey, $cacheTtlSeconds);
    if (is_array($cached)) {
        $requestCache = $cached;
        return $requestCache;
    }
    if (!$forceRefresh) {
        $staleCached = app_transient_cache_read_stale($cacheNamespace, $transientKey);
        if (is_array($staleCached)) {
            $requestCache = $staleCached;
            return $requestCache;
        }
    }

    // The aggregate SQL prototype is kept behind a hard-off switch for now.
    // On the current MariaDB dataset it was slower than the legacy row scan on a cold cache miss.
    $useAggregateBenchmark = false;
    if ($useAggregateBenchmark) {
        try {
            $requestCache = db_dataset_type_benchmark_aggregate_payload(
                $includeResolution,
                $includeLabelAuthority,
                $includeTypeAuthority
            );
            app_transient_cache_write($cacheNamespace, $transientKey, $requestCache);
            return $requestCache;
        } catch (Throwable $e) {
            log_exception($e, null, 'app', 'WARN_TYPE_BENCHMARK_AGG_FALLBACK', [
                'include_resolution_surface' => $includeResolution,
                'include_label_authority_surface' => $includeLabelAuthority,
                'include_type_authority_surface' => $includeTypeAuthority,
            ], 'app');
        }
    }

    $classRows = [];
    $benchmarkClassRows = [];
    $familiesByType = [];
    $benchmarkFamiliesByType = [];
    $resolvedRows = 0;
    $benchmarkEligibleRows = 0;
    $proposalOnlyCount = 0;
    $unresolvedCount = 0;
    $genericLabelCount = 0;
    $taxonomyMismatchCount = 0;
    $conflictCount = 0;
    $authorityResolvedCount = 0;
    $subtypeFallbackCount = 0;
    $primaryFallbackCount = 0;
    $highConfidenceCount = 0;
    $persistedAuthorityFactCount = 0;
    $cleanBenchmarkRows = 0;
    $heldPersistedDebtCount = 0;
    $derivedAuthorityProjectionCount = 0;
    $projectionWithoutPersistedFactCount = 0;
    $genericTokenPolicyHoldCount = 0;
    $unresolvedAuthorityCount = 0;
    $typedWithoutFactCount = 0;

    foreach (db_dataset_readiness_type_rows_cache() as $row) {
        $type = trim((string)($row['type_slug'] ?? ''));
        $source = (string)($row['type_slug_source'] ?? 'unresolved');
        $confidence = (string)($row['type_slug_confidence'] ?? 'none');
        $family = trim((string)($row['canonical_family_label'] ?? ''));
        $isGeneric = (int)($row['generic_family_flag'] ?? 0) === 1;
        // row_conflict_review on the benchmark page should reflect the same
        // real held-row semantics used by label_surfaces, not the narrower
        // conflict_case_flag that only tracks open governance/semantic cases.
        $isConflict = (bool)($row['type_slug_conflict_flag'] ?? false);
        $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
        $authorityTier = strtolower(trim((string)($row['authority_tier'] ?? '')));

        if ($isGeneric) {
            $genericLabelCount++;
        }
        if ($alignment === 'mismatch') {
            $taxonomyMismatchCount++;
        }
        if ($isConflict) {
            $conflictCount++;
        }
        if ($authorityTier === 'persisted_authority_fact') {
            $persistedAuthorityFactCount++;
            if (db_dataset_readiness_is_authority_consistency_debt_row($row)) {
                $heldPersistedDebtCount++;
            }
        } elseif ($authorityTier === 'derived_authority_projection') {
            $derivedAuthorityProjectionCount++;
            $projectionWithoutPersistedFactCount++;
            if ($type !== '') {
                $typedWithoutFactCount++;
            }
        } elseif ($authorityTier === 'generic_token_policy_hold') {
            $genericTokenPolicyHoldCount++;
        } elseif ($authorityTier === 'unresolved_authority') {
            $unresolvedAuthorityCount++;
        } elseif ($authorityTier === 'conflict_case') {
            $unresolvedAuthorityCount++;
        }

        if ($source === 'vt_popular_threat_category') {
            $proposalOnlyCount++;
            continue;
        }
        if ($type === '') {
            $unresolvedCount++;
            continue;
        }

        $resolvedRows++;
        $isAuthorityRow = in_array($source, ['governed_type_authority', 'catalog_family_type', 'family_type_authority'], true);
        $isBenchmarkEligible = db_dataset_readiness_is_benchmark_eligible($row);
        if ($isAuthorityRow) {
            $authorityResolvedCount++;
        } elseif ($source === 'classification_subtype') {
            $subtypeFallbackCount++;
        } elseif ($source === 'classification_primary') {
            $primaryFallbackCount++;
        }
        if ($confidence === 'high') {
            $highConfidenceCount++;
        }
        if ($isBenchmarkEligible) {
            $benchmarkEligibleRows++;
            $cleanBenchmarkRows++;
        }

        if (!isset($classRows[$type])) {
            $classRows[$type] = [
                'governed_type_slug' => $type,
                'sample_count' => 0,
                'family_count' => 0,
                'top_family' => null,
                'top_family_count' => 0,
                'top_family_share' => null,
                'generic_label_count' => 0,
                'taxonomy_mismatch_count' => 0,
                'unresolved_family_count' => 0,
                'high_confidence_count' => 0,
                'conflict_count' => 0,
                'persisted_fact_count' => 0,
                'projection_count' => 0,
                'generic_policy_hold_count' => 0,
                'unresolved_authority_count' => 0,
                'trainable_n3' => false,
                'trainable_n10' => false,
                'recommended_use' => 'insufficient_support',
            ];
            $familiesByType[$type] = [];
        }
        if (!isset($benchmarkClassRows[$type])) {
            $benchmarkClassRows[$type] = [
                'governed_type_slug' => $type,
                'sample_count' => 0,
                'family_count' => 0,
                'top_family' => null,
                'top_family_count' => 0,
                'top_family_share' => null,
                'generic_label_count' => 0,
                'taxonomy_mismatch_count' => 0,
                'unresolved_family_count' => 0,
                'high_confidence_count' => 0,
                'conflict_count' => 0,
                'persisted_fact_count' => 0,
                'projection_count' => 0,
                'generic_policy_hold_count' => 0,
                'unresolved_authority_count' => 0,
                'trainable_n3' => false,
                'trainable_n10' => false,
                'recommended_use' => 'insufficient_support',
            ];
            $benchmarkFamiliesByType[$type] = [];
        }

        $classRows[$type]['sample_count']++;
        if ($isGeneric) {
            $classRows[$type]['generic_label_count']++;
        }
        if ($alignment === 'mismatch') {
            $classRows[$type]['taxonomy_mismatch_count']++;
        }
        if ($family === '') {
            $classRows[$type]['unresolved_family_count']++;
        } else {
            $familiesByType[$type][$family] = (int)($familiesByType[$type][$family] ?? 0) + 1;
        }
        if ($confidence === 'high') {
            $classRows[$type]['high_confidence_count']++;
        }
        if ($isConflict) {
            $classRows[$type]['conflict_count']++;
        }
        if ($authorityTier === 'persisted_authority_fact') {
            $classRows[$type]['persisted_fact_count']++;
        } elseif ($authorityTier === 'derived_authority_projection') {
            $classRows[$type]['projection_count']++;
        } elseif ($authorityTier === 'generic_token_policy_hold') {
            $classRows[$type]['generic_policy_hold_count']++;
        } else {
            $classRows[$type]['unresolved_authority_count']++;
        }
        if (!$isBenchmarkEligible) {
            continue;
        }

        $benchmarkClassRows[$type]['sample_count']++;
        if ($isGeneric) {
            $benchmarkClassRows[$type]['generic_label_count']++;
        }
        if ($alignment === 'mismatch') {
            $benchmarkClassRows[$type]['taxonomy_mismatch_count']++;
        }
        if ($family === '') {
            $benchmarkClassRows[$type]['unresolved_family_count']++;
        } else {
            $benchmarkFamiliesByType[$type][$family] = (int)($benchmarkFamiliesByType[$type][$family] ?? 0) + 1;
        }
        $benchmarkClassRows[$type]['high_confidence_count']++;
        if ($isConflict) {
            $benchmarkClassRows[$type]['conflict_count']++;
        }
        $benchmarkClassRows[$type]['persisted_fact_count']++;
    }

    foreach ($classRows as $type => &$row) {
        arsort($familiesByType[$type]);
        $row['family_count'] = count($familiesByType[$type]);
        if ($familiesByType[$type] !== []) {
            $row['top_family'] = (string)array_key_first($familiesByType[$type]);
            $row['top_family_count'] = (int)reset($familiesByType[$type]);
        }
        $sampleCount = (int)($row['sample_count'] ?? 0);
        $topFamilyCount = (int)($row['top_family_count'] ?? 0);
        $row['top_family_share'] = $sampleCount > 0 ? round(($topFamilyCount / $sampleCount) * 100, 2) : null;
        $row['trainable_n3'] = $sampleCount >= 3;
        $row['trainable_n10'] = $sampleCount >= 10;
        if ($sampleCount >= 10 && (int)($row['high_confidence_count'] ?? 0) >= max(3, (int)floor($sampleCount / 2))) {
            $row['recommended_use'] = 'trainable_n10';
        } elseif ($sampleCount >= 3) {
            $row['recommended_use'] = 'trainable_n3_review';
        }
    }
    unset($row);
    foreach ($benchmarkClassRows as $type => &$row) {
        arsort($benchmarkFamiliesByType[$type]);
        $row['family_count'] = count($benchmarkFamiliesByType[$type]);
        if ($benchmarkFamiliesByType[$type] !== []) {
            $row['top_family'] = (string)array_key_first($benchmarkFamiliesByType[$type]);
            $row['top_family_count'] = (int)reset($benchmarkFamiliesByType[$type]);
        }
        $sampleCount = (int)($row['sample_count'] ?? 0);
        $topFamilyCount = (int)($row['top_family_count'] ?? 0);
        $row['top_family_share'] = $sampleCount > 0 ? round(($topFamilyCount / $sampleCount) * 100, 2) : null;
        $row['trainable_n3'] = $sampleCount >= 3;
        $row['trainable_n10'] = $sampleCount >= 10;
        if ($sampleCount >= 10) {
            $row['recommended_use'] = 'trainable_n10';
        } elseif ($sampleCount >= 3) {
            $row['recommended_use'] = 'trainable_n3_review';
        }
    }
    unset($row);

    $classRows = array_values($classRows);
    $benchmarkClassRows = array_values(array_filter(
        $benchmarkClassRows,
        static fn(array $row): bool => (int)($row['sample_count'] ?? 0) > 0
    ));
    usort($classRows, static function (array $a, array $b): int {
        $countCmp = ((int)($b['sample_count'] ?? 0)) <=> ((int)($a['sample_count'] ?? 0));
        if ($countCmp !== 0) {
            return $countCmp;
        }
        return strcmp((string)($a['governed_type_slug'] ?? ''), (string)($b['governed_type_slug'] ?? ''));
    });
    usort($benchmarkClassRows, static function (array $a, array $b): int {
        $countCmp = ((int)($b['sample_count'] ?? 0)) <=> ((int)($a['sample_count'] ?? 0));
        if ($countCmp !== 0) {
            return $countCmp;
        }
        return strcmp((string)($a['governed_type_slug'] ?? ''), (string)($b['governed_type_slug'] ?? ''));
    });

    $sampleCount = $benchmarkEligibleRows;
    $classCount = count($benchmarkClassRows);
    $topClass = $benchmarkClassRows[0]['governed_type_slug'] ?? null;
    $topClassCount = (int)($benchmarkClassRows[0]['sample_count'] ?? 0);
    $top5Count = 0;
    $trainableN3 = 0;
    $trainableN10 = 0;
    foreach ($benchmarkClassRows as $index => $row) {
        $rowCount = (int)($row['sample_count'] ?? 0);
        if ($rowCount >= 3) {
            $trainableN3++;
        }
        if ($rowCount >= 10) {
            $trainableN10++;
        }
        if ($index < 5) {
            $top5Count += $rowCount;
        }
    }

    $materializationDebt = db_dataset_readiness_authority_materialization_debt(db_dataset_readiness_type_rows_cache());
    $mismatchSummary = db_dataset_readiness_mismatch_summary(db_dataset_readiness_type_rows_cache());
    $governanceQueueSummary = db_dataset_readiness_v2_governance_queue_summary();
    $authorityConsistencyDebt = db_dataset_authority_consistency_debt();
    $authorityConsistencySummary = is_array($authorityConsistencyDebt['summary'] ?? null) ? $authorityConsistencyDebt['summary'] : [];

    $requestCache = [
        'ok' => true,
        'summary' => [
            'sample_count' => $sampleCount,
            'class_count' => $classCount,
            'trainable_class_count_n3' => $trainableN3,
            'trainable_class_count_n10' => $trainableN10,
            'top_class' => $topClass,
            'top_class_count' => $topClassCount,
            'top_class_share' => $sampleCount > 0 ? round(($topClassCount / $sampleCount) * 100, 2) : null,
            'top_5_count' => $top5Count,
            'top_5_share' => $sampleCount > 0 ? round(($top5Count / $sampleCount) * 100, 2) : null,
            'generic_label_count' => $genericLabelCount,
            'unresolved_count' => $unresolvedCount,
            'taxonomy_mismatch_count' => $taxonomyMismatchCount,
            'proposed_only_count' => $proposalOnlyCount,
            'conflict_count' => $conflictCount,
            'resolved_typed_count' => $resolvedRows,
            'benchmark_eligible_count' => $benchmarkEligibleRows,
            'review_required_count' => max(0, $resolvedRows - $benchmarkEligibleRows),
            'authority_resolved_count' => $authorityResolvedCount,
            'subtype_fallback_count' => $subtypeFallbackCount,
            'primary_fallback_count' => $primaryFallbackCount,
            'high_confidence_count' => $highConfidenceCount,
            'clean_benchmark_rows' => $cleanBenchmarkRows,
            'persisted_authority_fact_count' => $persistedAuthorityFactCount,
            'held_persisted_authority_consistency_debt_count' => $heldPersistedDebtCount,
            'derived_authority_projection_count' => $derivedAuthorityProjectionCount,
            'projection_without_persisted_fact_count' => $projectionWithoutPersistedFactCount,
            'generic_token_policy_hold_count' => $genericTokenPolicyHoldCount,
            'unresolved_authority_count' => $unresolvedAuthorityCount,
            'typed_without_fact_count' => $typedWithoutFactCount,
            'conflict_case_count' => (int)($governanceQueueSummary['open_conflict_case_rows'] ?? 0),
            'recommended_use' => 'Primary type target. Benchmark metrics below are restricted to clean aligned persisted authority rows; projection and fallback rows remain visible for curation and review.',
        ],
        'classes' => $benchmarkClassRows,
        'all_typed_classes' => $classRows,
        'authority_materialization_debt' => array_slice($materializationDebt, 0, 20),
        'authority_consistency_summary' => $authorityConsistencySummary,
        'mismatch_summary' => $mismatchSummary,
        'v2_governance_queue' => $governanceQueueSummary,
        'schema_missing' => [],
        'include_resolution_surface' => $includeResolution,
        'include_label_authority_surface' => $includeLabelAuthority,
        'include_type_authority_surface' => $includeTypeAuthority,
        'include_persisted_authority_surface' => db_dataset_readiness_include_persisted_authority_surface(),
    ];
    app_transient_cache_write($cacheNamespace, $transientKey, $requestCache);
    return $requestCache;
}

function db_dataset_type_slug_audit(): array
{
    $fieldsFound = [
        'classification_primary' => true,
        'classification_subtype' => true,
        'popular_threat_category' => true,
        'popular_threat_name' => true,
        'family_label' => true,
        'canonical_family_label' => db_dataset_readiness_include_resolution_surface(),
        'label_authority_resolution_view' => db_dataset_readiness_include_label_authority_surface(),
        'v_android_sample_family_type_authority' => db_dataset_readiness_include_type_authority_surface(),
        'android_malware_type' => db_dataset_readiness_surface_available('android_malware_type'),
        'malware_family_authority_fact' => db_dataset_readiness_surface_available('malware_family_authority_fact'),
    ];

    $distributions = [
        'classification_primary' => [],
        'classification_subtype' => [],
        'popular_threat_category' => [],
        'derived_type_slug' => [],
        'type_slug_source' => [],
        'type_slug_confidence' => [],
        'null_unknown_generic' => [
            'classification_primary_null' => 0,
            'classification_subtype_null' => 0,
            'popular_threat_category_null' => 0,
            'derived_type_slug_null' => 0,
        ],
    ];
    $mismatches = [
        'classification_primary_vs_classification_subtype' => 0,
        'classification_primary_vs_vt_popular_threat_category' => 0,
        'classification_subtype_vs_vt_popular_threat_category' => 0,
        'family_derived_type_vs_classification_derived_type' => 0,
        'current_derived_type_slug_vs_existing_governed_authority' => 0,
    ];

    foreach (db_dataset_readiness_type_rows_cache() as $row) {
        $primaryRaw = trim((string)($row['classification_primary'] ?? ''));
        $subtypeRaw = trim((string)($row['classification_subtype'] ?? ''));
        $vtRaw = trim((string)($row['popular_threat_category'] ?? ''));
        $derivedType = trim((string)($row['type_slug'] ?? ''));
        $source = trim((string)($row['type_slug_source'] ?? 'unresolved'));
        $confidence = trim((string)($row['type_slug_confidence'] ?? 'none'));
        $familyAuthority = trim((string)($row['type_candidate_effective_authority'] ?? ($row['type_candidate_governed_authority'] ?? $row['type_candidate_family_authority'] ?? '')));
        $primaryNorm = trim((string)($row['type_candidate_primary'] ?? ''));
        $subtypeNorm = trim((string)($row['type_candidate_subtype'] ?? ''));
        $vtNorm = trim((string)($row['type_candidate_vt_category'] ?? ''));
        $classificationDerived = $subtypeNorm !== '' ? $subtypeNorm : $primaryNorm;

        $primaryKey = $primaryRaw !== '' ? $primaryRaw : '(null)';
        $subtypeKey = $subtypeRaw !== '' ? $subtypeRaw : '(null)';
        $vtKey = $vtRaw !== '' ? $vtRaw : '(null)';
        $derivedKey = $derivedType !== '' ? $derivedType : '(null)';
        $distributions['classification_primary'][$primaryKey] = (int)($distributions['classification_primary'][$primaryKey] ?? 0) + 1;
        $distributions['classification_subtype'][$subtypeKey] = (int)($distributions['classification_subtype'][$subtypeKey] ?? 0) + 1;
        $distributions['popular_threat_category'][$vtKey] = (int)($distributions['popular_threat_category'][$vtKey] ?? 0) + 1;
        $distributions['derived_type_slug'][$derivedKey] = (int)($distributions['derived_type_slug'][$derivedKey] ?? 0) + 1;
        $distributions['type_slug_source'][$source] = (int)($distributions['type_slug_source'][$source] ?? 0) + 1;
        $distributions['type_slug_confidence'][$confidence] = (int)($distributions['type_slug_confidence'][$confidence] ?? 0) + 1;

        if ($primaryRaw === '') {
            $distributions['null_unknown_generic']['classification_primary_null']++;
        }
        if ($subtypeRaw === '') {
            $distributions['null_unknown_generic']['classification_subtype_null']++;
        }
        if ($vtRaw === '') {
            $distributions['null_unknown_generic']['popular_threat_category_null']++;
        }
        if ($derivedType === '') {
            $distributions['null_unknown_generic']['derived_type_slug_null']++;
        }

        if ($primaryNorm !== '' && $subtypeNorm !== '' && $primaryNorm !== $subtypeNorm) {
            $mismatches['classification_primary_vs_classification_subtype']++;
        }
        if ($primaryNorm !== '' && $vtNorm !== '' && $primaryNorm !== $vtNorm) {
            $mismatches['classification_primary_vs_vt_popular_threat_category']++;
        }
        if ($subtypeNorm !== '' && $vtNorm !== '' && $subtypeNorm !== $vtNorm) {
            $mismatches['classification_subtype_vs_vt_popular_threat_category']++;
        }
        if ($familyAuthority !== '' && $classificationDerived !== '' && $familyAuthority !== $classificationDerived) {
            $mismatches['family_derived_type_vs_classification_derived_type']++;
        }
        if ($familyAuthority !== '' && $derivedType !== '' && $familyAuthority !== $derivedType) {
            $mismatches['current_derived_type_slug_vs_existing_governed_authority']++;
        }
    }

    foreach (['classification_primary', 'classification_subtype', 'popular_threat_category', 'derived_type_slug', 'type_slug_source', 'type_slug_confidence'] as $key) {
        arsort($distributions[$key]);
    }

    return [
        'fields_found' => $fieldsFound,
        'distributions' => $distributions,
        'mismatch_counts' => $mismatches,
        'derivation_policy' => [
            'A' => 'Use dedicated family-to-type authority from v_android_sample_family_type_authority when present.',
            'B' => 'Otherwise use governed type authority from label_authority_resolution_view, then catalog family type if available.',
            'C' => 'Treat effective_type authority as reviewable support, not first-class benchmark truth when stronger family authority disagrees.',
            'D' => 'Otherwise use normalized classification_subtype, then classification_primary, only when they map cleanly to governed type values.',
            'E' => 'Use VT popular_threat_category as proposal-only fallback; otherwise leave type_slug unresolved.',
        ],
    ];
}

function db_dataset_readiness_overview(bool $includeTypeBenchmark = false): array
{
    $benchmark = $includeTypeBenchmark ? db_dataset_type_benchmark() : ['ok' => false, 'summary' => []];
    $typeSummary = $benchmark['summary'] ?? [];
    $mismatchSummary = is_array($benchmark['mismatch_summary'] ?? null) ? $benchmark['mismatch_summary'] : [];
    $materializationDebt = is_array($benchmark['authority_materialization_debt'] ?? null) ? $benchmark['authority_materialization_debt'] : [];
    $governanceQueueSummary = is_array($benchmark['v2_governance_queue'] ?? null) ? $benchmark['v2_governance_queue'] : [];
    $surfaces = [
        [
            'surface_key' => 'type_slug',
            'label' => 'type_slug',
            'status' => $includeTypeBenchmark ? (($benchmark['ok'] ?? false) ? 'ready_mvp' : 'blocked') : 'ready_mvp',
            'sample_count' => $includeTypeBenchmark ? ($typeSummary['sample_count'] ?? null) : null,
            'class_count' => $includeTypeBenchmark ? ($typeSummary['class_count'] ?? null) : null,
            'trainable_class_count_n3' => $includeTypeBenchmark ? ($typeSummary['trainable_class_count_n3'] ?? null) : null,
            'trainable_class_count_n10' => $includeTypeBenchmark ? ($typeSummary['trainable_class_count_n10'] ?? null) : null,
            'top_class' => $includeTypeBenchmark ? ($typeSummary['top_class'] ?? null) : null,
            'top_class_share' => $includeTypeBenchmark ? ($typeSummary['top_class_share'] ?? null) : null,
            'unresolved_count' => $includeTypeBenchmark ? ($typeSummary['unresolved_count'] ?? null) : null,
            'persisted_authority_fact_count' => $includeTypeBenchmark ? ($typeSummary['persisted_authority_fact_count'] ?? null) : null,
            'derived_authority_projection_count' => $includeTypeBenchmark ? ($typeSummary['derived_authority_projection_count'] ?? null) : null,
            'projection_without_persisted_fact_count' => $includeTypeBenchmark ? ($typeSummary['projection_without_persisted_fact_count'] ?? null) : null,
            'generic_token_policy_hold_count' => $includeTypeBenchmark ? ($typeSummary['generic_token_policy_hold_count'] ?? null) : null,
            'unresolved_authority_count' => $includeTypeBenchmark ? ($typeSummary['unresolved_authority_count'] ?? null) : null,
            'conflict_case_count' => $includeTypeBenchmark ? ($typeSummary['conflict_case_count'] ?? null) : null,
            'typed_without_fact_count' => $includeTypeBenchmark ? ($typeSummary['typed_without_fact_count'] ?? null) : null,
            'recommended_use' => $includeTypeBenchmark
                ? ($typeSummary['recommended_use'] ?? 'Primary type target.')
                : 'Primary type target. Open Type Benchmark for live metrics.',
            'notes' => $includeTypeBenchmark
                ? 'Real metrics are benchmark-restricted to authority-resolved high-confidence rows. Subtype and primary fallback rows remain visible for review only.'
                : 'Overview stays lightweight by default. Open Type Benchmark when you need live corpus metrics.',
        ],
        [
            'surface_key' => 'major_family_benchmark',
            'label' => 'major_family_benchmark',
            'status' => 'pending',
            'recommended_use' => 'Separate benchmark surface planned after type_slug MVP.',
            'notes' => 'Keep separate from broad family census.',
        ],
        [
            'surface_key' => 'family_within_type',
            'label' => 'family_within_type',
            'status' => 'pending',
            'recommended_use' => 'Planned after governed within-type family rules exist.',
            'notes' => 'Not exposed as a stable benchmark surface yet.',
        ],
        [
            'surface_key' => 'expanded_family',
            'label' => 'expanded_family',
            'status' => 'pending',
            'recommended_use' => 'Exploratory until long-tail family policy is defined.',
            'notes' => 'Minor-family modeling is not implemented in MVP.',
        ],
        [
            'surface_key' => 'all_current_family',
            'label' => 'all_current_family',
            'status' => 'pending',
            'recommended_use' => 'Exploratory census only, not benchmark-ready.',
            'notes' => 'Long-tail imbalance and taxonomy mismatch handling still pending.',
        ],
        [
            'surface_key' => 'category_subtype',
            'label' => 'category_subtype',
            'status' => 'pending',
            'recommended_use' => 'Auxiliary only.',
            'notes' => 'Displayed in label surfaces, but not promoted as a primary target.',
        ],
        [
            'surface_key' => 'category_primary',
            'label' => 'category_primary',
            'status' => 'pending',
            'recommended_use' => 'Not a scientific target.',
            'notes' => 'Current column is only a low-confidence fallback when stronger authority surfaces are absent.',
        ],
    ];

    return [
        'type_benchmark' => $benchmark,
        'mismatch_summary' => $mismatchSummary,
        'authority_materialization_debt' => $materializationDebt,
        'v2_governance_queue' => $governanceQueueSummary,
        'surfaces' => $surfaces,
        'includes_type_benchmark' => $includeTypeBenchmark,
    ];
}

function db_dataset_export_readiness(): array
{
    $artifacts = [
        'samples.csv',
        'labels.csv',
        'permissions_matrix.csv',
        'av_consensus_matrix.csv',
        'metadata_features.csv',
        'splits.csv',
        'exclusion_report.csv',
        'dataset_card.md',
        'manifest.json',
    ];

    return array_map(static function (string $name): array {
        return [
            'artifact_name' => $name,
            'status' => 'not_implemented',
            'notes' => 'Read-only placeholder in MVP. Export generation is intentionally deferred.',
        ];
    }, $artifacts);
}

function db_dataset_authority_consistency_debt(): array
{
    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }

    $transientKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'readiness_model' => 'authority_consistency_debt_v1',
        'version' => APP_VERSION,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $cacheTtlSeconds = 21600;
    $forceRefresh = db_dataset_readiness_force_refresh_requested();
    $cacheNamespace = 'dataset_authority_consistency_debt_v1';
    $cached = $forceRefresh ? null : app_transient_cache_read($cacheNamespace, $transientKey, $cacheTtlSeconds);
    if (is_array($cached)) {
        $requestCache = $cached;
        return $requestCache;
    }
    if (!$forceRefresh) {
        $staleCached = app_transient_cache_read_stale($cacheNamespace, $transientKey);
        if (is_array($staleCached)) {
            $requestCache = $staleCached;
            return $requestCache;
        }
    }

    $definitions = [
        'devixor' => [
            'migration_basis' => '0207_primary_android_family_authority_repairs_v1.sql reclassified family primary type to banker from dominant Android corpus semantics and source-backed banking-fraud behavior.',
            'evidence_summary' => 'V2 canonical type is banker; Cyble mapping accepted; accepted assertions include banking-trojan, RAT, ransomware, accessibility abuse, SMS fraud, and overlay ODF; raw subtype is Banker across the affected corpus.',
            'recommended_winner' => 'banker',
            'confidence' => 'medium-high',
            'recommended_action' => 'update persisted facts',
        ],
        'pixpirate' => [
            'migration_basis' => '0207_primary_android_family_authority_repairs_v1.sql reclassified family primary type to banker from dominant Android corpus semantics and source-backed banking-fraud behavior.',
            'evidence_summary' => 'V2 canonical type is banker; Cleafy mapping accepted; accepted assertions include banking-trojan, ATS, accessibility abuse, and SMS fraud; raw subtype is Banker across the affected corpus.',
            'recommended_winner' => 'banker',
            'confidence' => 'high',
            'recommended_action' => 'update persisted facts',
        ],
        'joker' => [
            'migration_basis' => '0207_primary_android_family_authority_repairs_v1.sql reclassified family primary type to banker; older Joker lineage and fraud migrations emphasize premium billing and SMS fraud.',
            'evidence_summary' => 'V2 canonical type is banker, but accepted assertions emphasize wap-billing-fraud, SMS fraud, and notification theft; raw subtype is Banker for nearly all rows while the persisted type is sms-trojan.',
            'recommended_winner' => 'sms-trojan',
            'confidence' => 'medium',
            'recommended_action' => 'add explicit reviewed override',
        ],
        'tanglebot' => [
            'migration_basis' => '0207_primary_android_family_authority_repairs_v1.sql reclassified family primary type to spyware from source-backed surveillance and smishing behavior.',
            'evidence_summary' => 'V2 canonical type is spyware; Cloudmark mapping accepted; accepted assertions include smishing-distribution, RAT, overlay ODF, and spyware; raw subtype is Spyware across the affected corpus.',
            'recommended_winner' => 'spyware',
            'confidence' => 'high',
            'recommended_action' => 'update persisted facts',
        ],
        'rewardsteal' => [
            'migration_basis' => '0214_primary_android_family_authority_repairs_v3.sql reclassified family primary type to banker from dominant Android corpus semantics and Microsoft banking-fraud rationale.',
            'evidence_summary' => 'V2 canonical type is banker, but current v2 support is thin: the strongest accepted capability is infostealer and the external mapping still needs review; raw subtype is Banker while persisted type is rat.',
            'recommended_winner' => 'banker',
            'confidence' => 'low',
            'recommended_action' => 'hold for manual review',
        ],
        'promptspy' => [
            'migration_basis' => '0211_primary_android_family_authority_repairs_v2.sql reclassified family primary type to spyware from surveillance and remote-control behavior.',
            'evidence_summary' => 'V2 canonical type is spyware; ESET mapping accepted; accepted assertions include VNC remote control, spyware, RAT, and screen streaming; raw subtype is Spyware across the affected corpus.',
            'recommended_winner' => 'spyware',
            'confidence' => 'medium',
            'recommended_action' => 'add explicit reviewed override',
        ],
        'gravityrat' => [
            'migration_basis' => '0079_primary_android_family_type_reclassification_v1.sql and 0211_primary_android_family_authority_repairs_v2.sql moved the family from earlier RAT-oriented handling to current spyware policy.',
            'evidence_summary' => 'V2 canonical type is spyware; Securelist mapping accepted; accepted assertions include both spyware and RAT at similar confidence; raw subtype is Spyware across the affected corpus.',
            'recommended_winner' => 'spyware',
            'confidence' => 'medium',
            'recommended_action' => 'add explicit reviewed override',
        ],
    ];

    $familySql = [];
    foreach (array_keys($definitions) as $slug) {
        $familySql[] = 'SELECT ' . db()->quote($slug) . ' AS family_slug';
    }
    $familyScopeSql = implode(' UNION ALL ', $familySql);

    $rows = db_all(
        "WITH family_scope AS (
            {$familyScopeSql}
        ),
        current_types AS (
            SELECT
                fs.family_slug,
                f.family_name,
                LOWER(TRIM(COALESCE(tt.type_slug, ''))) COLLATE utf8mb4_unicode_ci AS family_table_type_slug,
                LOWER(TRIM(COALESCE(v2t.type_slug, ''))) COLLATE utf8mb4_unicode_ci AS v2_canonical_type_slug
            FROM family_scope fs
            LEFT JOIN " . db_catalog_table('android_malware_family') . " f
              ON LOWER(TRIM(COALESCE(f.family_slug, ''))) COLLATE utf8mb4_unicode_ci = fs.family_slug COLLATE utf8mb4_unicode_ci
             AND f.is_active = 1
            LEFT JOIN " . db_catalog_table('android_malware_type') . " tt
              ON tt.type_id = f.primary_type_id
            LEFT JOIN " . db_catalog_table('android_family_entity_v2') . " e
              ON LOWER(TRIM(COALESCE(e.family_slug, ''))) COLLATE utf8mb4_unicode_ci = fs.family_slug COLLATE utf8mb4_unicode_ci
             AND e.is_active = 1
            LEFT JOIN " . db_catalog_table('android_malware_type') . " v2t
              ON v2t.type_id = e.canonical_type_id
        ),
        affected AS (
            SELECT
                LOWER(TRIM(COALESCE(maf.governed_family_slug, ''))) COLLATE utf8mb4_unicode_ci AS family_slug,
                LOWER(TRIM(COALESCE(maf.governed_type_slug, ''))) COLLATE utf8mb4_unicode_ci AS persisted_fact_type_slug,
                COUNT(*) AS row_count_affected
            FROM " . db_catalog_table('malware_family_authority_fact') . " maf
            JOIN " . db_catalog_table('malware_sample_catalog') . " c
              ON c.sample_id = maf.sample_id
            JOIN current_types ct
              ON ct.family_slug COLLATE utf8mb4_unicode_ci = LOWER(TRIM(COALESCE(maf.governed_family_slug, ''))) COLLATE utf8mb4_unicode_ci
            WHERE maf.is_active = 1
              AND LOWER(COALESCE(c.platform, '')) = 'android'
              AND LOWER(COALESCE(c.file_extension, '')) = 'apk'
              AND LOWER(TRIM(COALESCE(maf.governed_type_slug, ''))) COLLATE utf8mb4_unicode_ci <> COALESCE(ct.family_table_type_slug, '')
            GROUP BY family_slug, persisted_fact_type_slug
        )
        SELECT
            ct.family_slug,
            ct.family_name,
            ct.family_table_type_slug,
            a.persisted_fact_type_slug,
            ct.v2_canonical_type_slug,
            COALESCE(a.row_count_affected, 0) AS row_count_affected
        FROM current_types ct
        LEFT JOIN affected a
          ON a.family_slug = ct.family_slug COLLATE utf8mb4_unicode_ci
        ORDER BY row_count_affected DESC, ct.family_slug ASC"
    );

    $resultRows = [];
    $totalAffectedRows = 0;
    foreach ($rows as $row) {
        $slug = strtolower(trim((string)($row['family_slug'] ?? '')));
        if ($slug === '' || !isset($definitions[$slug])) {
            continue;
        }
        $rowCount = (int)($row['row_count_affected'] ?? 0);
        $totalAffectedRows += $rowCount;
        $resultRows[] = [
            'family' => $slug,
            'family_name' => (string)($row['family_name'] ?? ucfirst($slug)),
            'row_count_affected' => $rowCount,
            'current_family_table_type' => (string)($row['family_table_type_slug'] ?? ''),
            'persisted_fact_type' => (string)($row['persisted_fact_type_slug'] ?? ''),
            'v2_canonical_type' => (string)($row['v2_canonical_type_slug'] ?? ''),
            'migration_basis' => $definitions[$slug]['migration_basis'],
            'evidence_summary' => $definitions[$slug]['evidence_summary'],
            'recommended_winner' => $definitions[$slug]['recommended_winner'],
            'confidence' => $definitions[$slug]['confidence'],
            'recommended_action' => $definitions[$slug]['recommended_action'],
        ];
    }

    $payload = [
        'ok' => true,
        'rows' => $resultRows,
        'summary' => [
            'families_count' => count($resultRows),
            'affected_rows_count' => $totalAffectedRows,
            'persisted_auto_policy_note' => 'malware_family_authority_fact is persisted but currently auto-materialized.',
            'strongest_authority_note' => 'Persisted facts should be strongest only when they agree with current governed family/type policy or have explicit reviewed status.',
            'hold_note' => 'Rows with persisted-vs-current-policy disagreement are authority consistency debt and should be held from clean benchmark counts until adjudicated.',
        ],
    ];

    app_transient_cache_write($cacheNamespace, $transientKey, $payload);
    $requestCache = $payload;
    return $requestCache;
}
