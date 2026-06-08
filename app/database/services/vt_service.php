<?php
// app/database/services/vt_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../../lib/transient_cache.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/schema_service.php';
require_once __DIR__ . '/family_service.php';

function db_vt_summary_cache_key(string $scope): string
{
    return md5(json_encode([
        'scope' => $scope,
        'primary_catalog' => db_primary_catalog_name(),
        'version' => defined('APP_VERSION') ? APP_VERSION : 'dev',
    ], JSON_UNESCAPED_SLASHES) ?: '');
}

function db_vt_summary_cached(string $namespace, string $scope, int $ttlSeconds, callable $loader): array
{
    static $requestCache = [];

    $cacheKey = db_vt_summary_cache_key($scope);
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

function db_vt_false_positive_review_view_name(): string
{
    if (db_schema_surface_present('v_vt_false_positive_review_candidates_effective')) {
        return 'v_vt_false_positive_review_candidates_effective';
    }
    return 'v_vt_false_positive_review_candidates';
}

function db_vt_confidence_schema_status(): array
{
    return db_schema_requirements_status(db_known_schema_requirements([
        'v_vt_evidence_confidence_summary',
        'v_vt_false_positive_review_candidates',
    ]));
}

function db_vt_confidence(int $limit = 25): array
{
    $limit = max(1, min($limit, 250));
    $schema = db_vt_confidence_schema_status();
    $vendorModelSummary = db_vt_vendor_model_summary();
    $signalSurfaceSummary = db_vt_signal_surface_summary();
    if (!$schema['ok']) {
        return [
            'data' => [
                'summary' => [],
                'false_positive_review_summary' => [],
                'false_positive_review_candidates' => [],
                'vendor_model_summary' => $vendorModelSummary,
                'signal_surface_summary' => $signalSurfaceSummary,
                'schema_missing' => $schema['missing'],
            ],
            'meta' => [
                'schema_available' => false,
                'primary_database' => db_primary_catalog_name(),
                'limit' => $limit,
            ],
        ];
    }

    $summaryView = db_catalog_table('v_vt_evidence_confidence_summary');
    $fpView = db_catalog_table(db_vt_false_positive_review_view_name());

    $summary = db_all("
        SELECT
            confidence_bucket,
            recommended_action,
            sample_count,
            min_confidence_score,
            avg_confidence_score,
            max_confidence_score
        FROM {$summaryView}
        ORDER BY FIELD(confidence_bucket, 'high','strong','moderate','review','weak','none'),
                 recommended_action
    ");

    $fpSummary = db_all("
        SELECT review_reason, COUNT(*) AS sample_count
        FROM {$fpView}
        GROUP BY review_reason
        ORDER BY sample_count DESC, review_reason ASC
    ");

    $stmt = db()->prepare("
        SELECT
            sample_id,
            sha256,
            sample_label,
            family_label,
            platform,
            android_package_name,
            vt_malicious_count,
            vt_suspicious_count,
            vt_harmless_count,
            vt_total_engines,
            raw_detection_ratio,
            confidence_score,
            confidence_bucket,
            recommended_action,
            review_reason
        FROM {$fpView}
        ORDER BY confidence_score ASC, vt_malicious_count ASC,
                 vt_harmless_count DESC, sample_id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'data' => [
            'summary' => $summary,
            'false_positive_review_summary' => $fpSummary,
            'false_positive_review_candidates' => $stmt->fetchAll(),
            'vendor_model_summary' => $vendorModelSummary,
            'signal_surface_summary' => $signalSurfaceSummary,
        ],
        'meta' => [
            'schema_available' => true,
            'primary_database' => db_primary_catalog_name(),
            'schema_surface' => '0057_vt_evidence_confidence_layer',
            'false_positive_review_view' => db_vt_false_positive_review_view_name(),
            'limit' => $limit,
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        ],
    ];
}

function db_vt_supports_key_leases(): bool
{
    $row = db_one("
        SELECT COUNT(*) AS c
        FROM information_schema.columns
        WHERE table_schema = :schema
          AND table_name = 'virustotal_api_keys'
          AND column_name IN ('lease_until_utc', 'lease_owner')
    ", ['schema' => db_primary_catalog_name()]);
    return (int)($row['c'] ?? 0) === 2;
}

function db_vt_system_hold_summary(): array
{
    $control = db_one("
        SELECT
            hold_until_utc,
            hold_reason_code,
            last_429_key_id,
            last_429_endpoint,
            last_429_retry_after_seconds
        FROM virustotal_system_control
        WHERE control_id = 1
        LIMIT 1
    ") ?? [];

    $holdUntil = isset($control['hold_until_utc']) ? trim((string)$control['hold_until_utc']) : '';
    $holdTs = $holdUntil !== '' ? strtotime($holdUntil . ' UTC') : false;
    $activeHold = $holdTs !== false && $holdTs > time();

    return [
        'active_hold' => $activeHold,
        'hold_until_utc' => $holdUntil !== '' ? $holdUntil : null,
        'hold_reason_code' => $control['hold_reason_code'] ?? null,
        'last_429_key_id' => $control['last_429_key_id'] ?? null,
        'last_429_endpoint' => $control['last_429_endpoint'] ?? null,
        'last_429_retry_after_seconds' => $control['last_429_retry_after_seconds'] ?? null,
    ];
}

function db_vt_key_rows_enriched(): array
{
    $supportsLeases = db_vt_supports_key_leases();
    $leaseCols = $supportsLeases
        ? 'lease_until_utc, lease_owner'
        : 'NULL AS lease_until_utc, NULL AS lease_owner';

    $keys = db_all("
        SELECT
            api_key_id,
            RIGHT(api_key, 6) AS last6,
            is_enabled,
            is_visible,
            daily_quota_limit,
            daily_quota_used,
            quota_day_utc,
            cooldown_until_utc,
            last_429_at_utc,
            last_429_retry_after_seconds,
            rate_limit_429_count,
            {$leaseCols}
        FROM virustotal_api_keys
        ORDER BY api_key_id ASC
    ");

    $nowRow = db_one("SELECT UTC_TIMESTAMP() AS now_utc, UTC_DATE() AS today_utc") ?? [];
    $now = (string)($nowRow['now_utc'] ?? gmdate('Y-m-d H:i:s'));
    $today = (string)($nowRow['today_utc'] ?? gmdate('Y-m-d'));
    $nowTs = strtotime($now . ' UTC') ?: time();

    foreach ($keys as &$row) {
        $enabled = ((int)($row['is_enabled'] ?? 0)) === 1;
        $visible = ((int)($row['is_visible'] ?? 0)) === 1;
        $quotaDay = (string)($row['quota_day_utc'] ?? '');
        $limitRaw = $row['daily_quota_limit'] ?? null;
        $usedRaw = $row['daily_quota_used'] ?? null;
        $limit = $limitRaw === null ? null : (int)$limitRaw;
        $used = $usedRaw === null ? 0 : (int)$usedRaw;
        $remainingQuota = null;
        if ($limit !== null && $limit > 0 && $quotaDay === $today) {
            $remainingQuota = max(0, $limit - $used);
        }

        $quotaBlocked = false;
        if ($quotaDay === $today && $limit !== null && $limit > 0) {
            $quotaBlocked = $used >= $limit;
        }

        $coolingNow = false;
        if (!empty($row['cooldown_until_utc'])) {
            $cooldownTs = strtotime((string)$row['cooldown_until_utc'] . ' UTC');
            $coolingNow = $cooldownTs !== false && $cooldownTs > $nowTs;
        }

        $leasedNow = false;
        if ($supportsLeases && !empty($row['lease_until_utc'])) {
            $leaseTs = strtotime((string)$row['lease_until_utc'] . ' UTC');
            $leasedNow = $leaseTs !== false && $leaseTs > $nowTs;
        }

        $operatorStatus = 'eligible';
        if (!$enabled) {
            $operatorStatus = 'disabled';
        } elseif (!$visible) {
            $operatorStatus = 'hidden';
        } elseif ($quotaBlocked) {
            $operatorStatus = 'quota_blocked';
        } elseif ($coolingNow) {
            $operatorStatus = 'cooling';
        } elseif ($leasedNow) {
            $operatorStatus = 'leased';
        }

        $row['remaining_quota'] = $remainingQuota;
        $row['quota_blocked'] = $quotaBlocked ? 1 : 0;
        $row['cooling_now'] = $coolingNow ? 1 : 0;
        $row['leased_now'] = $leasedNow ? 1 : 0;
        $row['is_eligible_now'] = ($enabled && $visible && !$quotaBlocked && !$coolingNow && !$leasedNow) ? 1 : 0;
        $row['operator_status'] = $operatorStatus;
    }
    unset($row);

    usort($keys, static function (array $a, array $b): int {
        $order = [
            'eligible' => 0,
            'cooling' => 1,
            'leased' => 2,
            'quota_blocked' => 3,
            'hidden' => 4,
            'disabled' => 5,
        ];
        $aStatus = (string)($a['operator_status'] ?? '');
        $bStatus = (string)($b['operator_status'] ?? '');
        $aOrder = $order[$aStatus] ?? 99;
        $bOrder = $order[$bStatus] ?? 99;
        if ($aOrder !== $bOrder) {
            return $aOrder <=> $bOrder;
        }

        $aRemaining = (int)($a['remaining_quota'] ?? -1);
        $bRemaining = (int)($b['remaining_quota'] ?? -1);
        if ($aRemaining !== $bRemaining) {
            return $bRemaining <=> $aRemaining;
        }

        return ((int)($a['api_key_id'] ?? 0)) <=> ((int)($b['api_key_id'] ?? 0));
    });

    return [
        'supports_leases' => $supportsLeases,
        'keys' => $keys,
    ];
}

function db_vt_key_status_snapshot(): array
{
    $snapshot = db_vt_key_rows_enriched();

    return [
        'generated_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'supports_leases' => (bool)($snapshot['supports_leases'] ?? false),
        'hold' => db_vt_system_hold_summary(),
        'key_posture' => db_vt_key_posture_summary(),
        'keys' => $snapshot['keys'] ?? [],
    ];
}

function db_vt_key_posture_summary(): array
{
    $snapshot = db_vt_key_rows_enriched();
    $supportsLeases = (bool)($snapshot['supports_leases'] ?? false);
    $keys = is_array($snapshot['keys'] ?? null) ? $snapshot['keys'] : [];

    $total = count($keys);
    $enabledVisible = 0;
    $eligible = 0;
    $cooling = 0;
    $leased = 0;
    $quotaBlocked = 0;
    $totalRemainingQuota = 0;
    $eligibleRemainingQuota = 0;

    foreach ($keys as $row) {
        $enabled = ((int)($row['is_enabled'] ?? 0)) === 1;
        $visible = ((int)($row['is_visible'] ?? 0)) === 1;
        if (!$enabled || !$visible) {
            continue;
        }
        $enabledVisible++;
        $remainingQuota = $row['remaining_quota'] ?? null;
        if ($remainingQuota !== null) {
            $totalRemainingQuota += (int)$remainingQuota;
        }

        if (((int)($row['quota_blocked'] ?? 0)) === 1) {
            $quotaBlocked++;
        }
        if (((int)($row['cooling_now'] ?? 0)) === 1) {
            $cooling++;
        }
        if (((int)($row['leased_now'] ?? 0)) === 1) {
            $leased++;
        }
        if (((int)($row['is_eligible_now'] ?? 0)) === 1) {
            $eligible++;
            if ($remainingQuota !== null) {
                $eligibleRemainingQuota += (int)$remainingQuota;
            }
        }
    }

    return [
        'supports_leases' => $supportsLeases,
        'total_keys' => $total,
        'enabled_visible_keys' => $enabledVisible,
        'eligible_keys' => $eligible,
        'cooling_keys' => $cooling,
        'leased_keys' => $supportsLeases ? $leased : null,
        'quota_blocked_keys' => $quotaBlocked,
        'total_remaining_quota' => $totalRemainingQuota,
        'eligible_remaining_quota' => $eligibleRemainingQuota,
    ];
}

function db_vt_ops_summary(): array
{
    $health = db_health();
    $keyPosture = db_vt_key_posture_summary();
    $confidenceSchema = db_vt_confidence_schema_status();
    $vtSurfaceSummary = $health['vt_surface_summary'] ?? db_vt_surface_inventory_summary();
    $vendorModelSummary = db_vt_vendor_model_summary();
    $signalSurfaceSummary = db_vt_signal_surface_summary();

    return [
        'data' => [
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
            'catalogs' => $health['catalogs'] ?? [],
            'schema_heads' => $health['schema_heads'] ?? [],
            'system_control' => $health['system_control'] ?? [],
            'metrics' => $health['metrics'] ?? [],
            'vt_surface_summary' => $vtSurfaceSummary,
            'confidence_schema' => [
                'available' => (bool)($confidenceSchema['ok'] ?? false),
                'missing' => $confidenceSchema['missing'] ?? [],
                'missing_count' => (int)($confidenceSchema['missing_count'] ?? 0),
            ],
            'key_posture' => $keyPosture,
            'vendor_model_summary' => $vendorModelSummary,
            'signal_surface_summary' => $signalSurfaceSummary,
            'family_taxonomy_summary' => function_exists('db_health_family_taxonomy_scorecard_cached')
                ? db_health_family_taxonomy_scorecard_cached()
                : db_family_taxonomy_scorecard(),
        ],
        'meta' => [
            'primary_database' => db_primary_catalog_name(),
            'permission_intel_database' => db_permission_intel_catalog_name(),
            'permission_intel_split' => db_permission_intel_split_enabled(),
        ],
    ];
}

function db_vt_vendor_model_summary(): array
{
    return db_vt_summary_cached(
        'vt_vendor_model_summary',
        'vendor_model_summary',
        300,
        static function (): array {
            $rowCounts = [
                'canonical_vendor_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM virustotal_sample_vendor_verdicts")['c'] ?? 0)),
                'projection_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM virustotal_sample_vendor_engine_verdicts")['c'] ?? 0)),
                'collision_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM vt_vendor_engine_name_collision_log")['c'] ?? 0)),
                'reliability_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM vt_vendor_reliability_profile")['c'] ?? 0)),
                'projection_profile_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM vt_vendor_projection_profile")['c'] ?? 0)),
                'delta_rows_30d' => (int)((db_one("SELECT COUNT(*) AS c FROM vt_vendor_delta WHERE fetched_at_utc >= UTC_TIMESTAMP() - INTERVAL 30 DAY")['c'] ?? 0)),
            ];

            $reliability = db_one("
                SELECT
                    AVG(reliability_weight) AS avg_weight,
                    AVG(false_positive_tendency) AS avg_fp_tendency,
                    AVG(instability_score) AS avg_instability
                FROM vt_vendor_reliability_profile
            ") ?? [];

            $projection = db_one("
                SELECT
                    SUM(CASE WHEN low_fill_candidate_flag = 1 THEN 1 ELSE 0 END) AS low_fill_candidates,
                    AVG(wide_populated_ratio) AS avg_populated_ratio
                FROM vt_vendor_projection_profile
            ") ?? [];

            $delta = db_one("
                SELECT
                    SUM(changed_engines_count) AS changed_engines_sum_30d,
                    SUM(engines_new_count) AS engines_new_sum_30d,
                    SUM(engines_removed_count) AS engines_removed_sum_30d,
                    SUM(labels_changed_count) AS labels_changed_sum_30d,
                    SUM(categories_changed_count) AS categories_changed_sum_30d
                FROM vt_vendor_delta
                WHERE fetched_at_utc >= UTC_TIMESTAMP() - INTERVAL 30 DAY
            ") ?? [];

            return array_merge($rowCounts, [
                'avg_reliability_weight' => $reliability['avg_weight'] !== null ? (float)$reliability['avg_weight'] : null,
                'avg_false_positive_tendency' => $reliability['avg_fp_tendency'] !== null ? (float)$reliability['avg_fp_tendency'] : null,
                'avg_instability_score' => $reliability['avg_instability'] !== null ? (float)$reliability['avg_instability'] : null,
                'low_fill_candidates' => (int)($projection['low_fill_candidates'] ?? 0),
                'avg_projection_populated_ratio' => $projection['avg_populated_ratio'] !== null ? (float)$projection['avg_populated_ratio'] : null,
                'changed_engines_sum_30d' => (int)($delta['changed_engines_sum_30d'] ?? 0),
                'engines_new_sum_30d' => (int)($delta['engines_new_sum_30d'] ?? 0),
                'engines_removed_sum_30d' => (int)($delta['engines_removed_sum_30d'] ?? 0),
                'labels_changed_sum_30d' => (int)($delta['labels_changed_sum_30d'] ?? 0),
                'categories_changed_sum_30d' => (int)($delta['categories_changed_sum_30d'] ?? 0),
            ]);
        }
    );
}

function db_vt_signal_surface_summary(): array
{
    return db_vt_summary_cached(
        'vt_signal_surface_summary',
        'signal_surface_summary',
        300,
        static function (): array {
            $rowCounts = [
                'signal_current_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM virustotal_sample_signal_current")['c'] ?? 0)),
                'confidence_rows' => (int)((db_one("SELECT COUNT(*) AS c FROM vt_sample_verdict_confidence_current")['c'] ?? 0)),
            ];

            $parseRows = db_all("
                SELECT parse_version, COUNT(*) AS row_count
                FROM virustotal_sample_signal_current
                GROUP BY parse_version
                ORDER BY row_count DESC, parse_version ASC
            ");

            return array_merge($rowCounts, [
                'parse_versions' => array_map(
                    static fn($row): array => [
                        'parse_version' => (string)($row['parse_version'] ?? ''),
                        'row_count' => (int)($row['row_count'] ?? 0),
                    ],
                    $parseRows
                ),
            ]);
        }
    );
}
