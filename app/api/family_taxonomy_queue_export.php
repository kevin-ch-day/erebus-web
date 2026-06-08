<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method Not Allowed\n";
    exit;
}

try {
    $limit = get_int('limit', 100, 1, 250);
    $alignment = trim((string)($_GET['alignment'] ?? ''));
    $platform = strtolower(trim((string)($_GET['platform'] ?? '')));
    $query = trim((string)($_GET['q'] ?? ''));
    $pattern = trim((string)($_GET['pattern'] ?? ''));
    $pairCatalog = trim((string)($_GET['pair_catalog'] ?? ''));
    $pairSignal = trim((string)($_GET['pair_signal'] ?? ''));
    $fixAction = trim((string)($_GET['fix_action'] ?? ''));
    $targetFamily = trim((string)($_GET['target_family'] ?? ''));
    $decisionMode = trim((string)($_GET['decision_mode'] ?? ''));

    $payload = taxonomy_view_fetch($limit, [
        'alignment' => $alignment,
        'platform' => $platform,
        'query' => $query,
        'pattern' => $pattern,
        'pair_catalog' => $pairCatalog,
        'pair_signal' => $pairSignal,
        'fix_action' => $fixAction,
        'target_family' => $targetFamily,
        'decision_mode' => $decisionMode,
        'include_rows' => true,
    ]);

    $rows = $payload['data']['rows'] ?? [];
    $stamp = gmdate('Ymd_His');
    $filename = 'family_taxonomy_queue_' . $stamp . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        throw new RuntimeException('Failed to open CSV output stream.');
    }

    $headers = [
        'sample_id',
        'sha256',
        'sample_label',
        'android_package_name',
        'family_label',
        'vt_suggested_label',
        'popular_threat_name',
        'popular_threat_label',
        'popular_threat_category',
        'parse_version',
        'confidence_bucket',
        'confidence_score',
        'recommended_action',
        'alignment_status',
        'generic_label_flag',
        'issue_kind',
        'issue_reason',
        'suggested_fix_action',
        'suggested_target_family',
        'suggested_fix_confidence',
        'suggested_fix_reason',
        'decision_mode',
        'decision_priority',
        'decision_why',
        'review_lane',
    ];
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $key) {
            $line[] = $row[$key] ?? '';
        }
        fputcsv($out, $line);
    }

    fclose($out);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to export family taxonomy queue.\n";
}
