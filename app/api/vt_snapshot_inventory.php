<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $maxRows = get_int('max_rows', 5000, 1, 20000);
    $recentHours = get_int('recent_hours', 24, 1, 24 * 30);
    $snapshotTotal = 0;

    $statusMix = [
        '2XX' => 0,
        '404' => 0,
        '5XX' => 0,
        'OTHER' => 0,
    ];
    $sourceMix = [];
    $withAttrs = 0;
    $parseErrors = 0;
    $attrCounts = [];
    $cutoffTs = time() - ($recentHours * 3600);
    $recentAttrs = [];
    $olderAttrs = [];

    $pdo = db();
    $restoreBuffered = false;
    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        try {
            $restoreBuffered = (bool)$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (Throwable $e) {
            $restoreBuffered = false;
        }
    }

    $stmt = null;
    try {
        $stmt = $pdo->prepare(
            "
            SELECT event_id, op_source, http_status, fetched_at_utc, payload_json
            FROM virustotal_file_report_event
            ORDER BY event_id DESC
            LIMIT :limit
            "
        );
        $stmt->bindValue(':limit', $maxRows, PDO::PARAM_INT);
        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $snapshotTotal++;
            if (!is_array($row)) {
                continue;
            }
            $src = trim((string)($row['op_source'] ?? '-'));
            if ($src === '') {
                $src = '-';
            }
            $sourceMix[$src] = (int)($sourceMix[$src] ?? 0) + 1;

            $http = (int)($row['http_status'] ?? 0);
            if ($http >= 200 && $http <= 299) {
                $statusMix['2XX']++;
            } elseif ($http === 404) {
                $statusMix['404']++;
            } elseif ($http >= 500) {
                $statusMix['5XX']++;
            } else {
                $statusMix['OTHER']++;
            }

            $payload = (string)($row['payload_json'] ?? '');
            if ($payload === '') {
                continue;
            }
            try {
                $obj = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $parseErrors++;
                continue;
            }
            $attrs = $obj['data']['attributes'] ?? null;
            if (!is_array($attrs) || !$attrs) {
                continue;
            }
            $withAttrs++;

            $ts = strtotime((string)($row['fetched_at_utc'] ?? ''));
            $isRecent = ($ts !== false && $ts >= $cutoffTs);
            foreach ($attrs as $k => $_v) {
                $name = (string)$k;
                $attrCounts[$name] = (int)($attrCounts[$name] ?? 0) + 1;
                if ($isRecent) {
                    $recentAttrs[$name] = true;
                } else {
                    $olderAttrs[$name] = true;
                }
            }
        }
    } finally {
        if ($stmt instanceof PDOStatement) {
            $stmt->closeCursor();
        }
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            try {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $restoreBuffered);
            } catch (Throwable $e) {
                // ignore restore failures
            }
        }
    }

    arsort($sourceMix);
    arsort($attrCounts);
    $recentOnly = array_values(array_diff(array_keys($recentAttrs), array_keys($olderAttrs)));
    sort($recentOnly);

    api_ok([
        'ok' => true,
        'max_rows' => $maxRows,
        'recent_hours' => $recentHours,
        'snapshot_total' => $snapshotTotal,
        'with_attributes' => $withAttrs,
        'parse_errors' => $parseErrors,
        'status_mix' => $statusMix,
        'source_mix' => $sourceMix,
        'top_attributes' => array_slice(
            array_map(
                static fn($name, $count) => ['attribute' => (string)$name, 'count' => (int)$count],
                array_keys($attrCounts),
                array_values($attrCounts)
            ),
            0,
            25
        ),
        'recent_new_attributes' => $recentOnly,
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    api_error('Failed to load VT snapshot inventory.', 500, 'ERR_VT_SNAPSHOT_INVENTORY', [], $e);
}
