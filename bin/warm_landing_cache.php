#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Warm shared transient caches used by the Home control deck.
 *
 * Landing itself never cold-computes these (proxy timeout / php-fpm memory).
 * Run after deploy or when Home shows "partial (... cache warming)".
 *
 *   php bin/warm_landing_cache.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/lib/app_config.php';
require_once $root . '/app/database/db_func.php';
require_once $root . '/app/lib/transient_cache.php';

@ini_set('memory_limit', '1024M');
@set_time_limit(0);

echo 'cache_dir=' . app_transient_cache_dir() . PHP_EOL;

require_once $root . '/app/lib/taxonomy_view_data.php';

$steps = [
    'family_scorecard' => static function (): string {
        $summary = db_health_family_taxonomy_scorecard_cached();
        return 'mismatch_rows=' . (int)($summary['mismatch_rows'] ?? 0);
    },
    'family_mismatch_pairs' => static function (): string {
        $rows = db_family_taxonomy_top_mismatch_pairs_cached(6, true);
        return 'rows=' . count($rows);
    },
    'type_benchmark' => static function (): string {
        $benchmark = db_dataset_type_benchmark();
        $summary = is_array($benchmark['summary'] ?? null) ? $benchmark['summary'] : [];
        return 'clean_benchmark_rows=' . (int)($summary['clean_benchmark_rows'] ?? 0);
    },
    'taxonomy_check_android' => static function (): string {
        $payload = db_family_taxonomy_check(
            limit: 100,
            platform: 'android',
            includeRows: false,
        );
        $summary = is_array($payload['data']['summary'] ?? null) ? $payload['data']['summary'] : [];
        // Also populate the API-layer cache key used by family_taxonomy_check.php.
        $apiCacheKey = hash('sha256', json_encode([
            'limit' => 100,
            'alignment' => '',
            'platform' => 'android',
            'query' => '',
            'pattern' => '',
            'pair_catalog' => '',
            'pair_signal' => '',
            'fix_action' => '',
            'target_family' => '',
            'decision_mode' => '',
            'include_rows' => '0',
        ], JSON_UNESCAPED_SLASHES));
        app_transient_cache_write('family_taxonomy_check', $apiCacheKey, $payload);
        // Common SSR view filters.
        taxonomy_view_fetch(25, ['platform' => 'android', 'include_rows' => false]);
        taxonomy_view_fetch(25, ['alignment' => 'signal_only', 'platform' => 'android', 'include_rows' => false]);
        taxonomy_view_fetch(25, ['alignment' => 'catalog_only', 'platform' => 'android', 'include_rows' => false]);
        taxonomy_view_fetch(25, ['decision_mode' => 'ask_why_first', 'platform' => 'android', 'include_rows' => false]);
        taxonomy_view_fetch(25, ['pattern' => 'semantic_conflict', 'platform' => 'android', 'include_rows' => false]);
        taxonomy_view_catalog_only_authority_summary('android');
        taxonomy_view_catalog_only_anchor_families('android', 10);
        return 'summary_rows=' . count($summary);
    },
];

foreach ($steps as $name => $fn) {
    $t0 = microtime(true);
    try {
        $detail = $fn();
        $ms = (int)round((microtime(true) - $t0) * 1000);
        echo sprintf("ok  %-24s %6d ms  %s\n", $name, $ms, $detail);
    } catch (Throwable $e) {
        $ms = (int)round((microtime(true) - $t0) * 1000);
        echo sprintf("ERR %-24s %6d ms  %s\n", $name, $ms, $e->getMessage());
    }
}

$t0 = microtime(true);
$snap = db_landing_snapshot();
$ms = (int)round((microtime(true) - $t0) * 1000);
$degraded = is_array($snap['degraded'] ?? null) ? $snap['degraded'] : [];
echo sprintf(
    "landing_snapshot              %6d ms  degraded=%s\n",
    $ms,
    $degraded === [] ? '[]' : json_encode($degraded)
);

exit($degraded === [] ? 0 : 1);
