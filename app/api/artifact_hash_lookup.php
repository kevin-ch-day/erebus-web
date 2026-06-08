<?php
// app/api/artifact_hash_lookup.php
// Read-only hash lookup against malware_artifact_hash_registry.

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_engine.php';

require_get();

$hash = trim((string)($_GET['hash'] ?? ''));
if ($hash === '') {
    api_error('Missing hash parameter.', 400, 'ERR_HASH_MISSING');
    exit;
}

if (!preg_match('/^[a-f0-9]+$/i', $hash)) {
    api_error('Hash must be hexadecimal.', 400, 'ERR_HASH_INVALID');
    exit;
}

$len = strlen($hash);
$column = null;
if ($len === 32) {
    $column = 'md5';
} elseif ($len === 40) {
    $column = 'sha1';
} elseif ($len === 64) {
    $column = 'sha256';
}

if ($column === null) {
    api_error('Hash length must be 32, 40, or 64 characters.', 400, 'ERR_HASH_LENGTH');
    exit;
}

$queueColumn = $column === 'md5' ? 'artifact_hash_md5' : ($column === 'sha1' ? 'artifact_hash_sha1' : 'artifact_hash_sha256');
$hashNorm = strtolower($hash);

$compatRow = null;
try {
    $compatSql = "
        SELECT
            sha256,
            md5,
            sha1,
            record_created_at_utc,
            vt_status_code,
            next_eligible_at_utc,
            attempt_count,
            last_attempt_at_utc,
            last_http_status,
            last_error_category,
            last_error_message,
            last_run_id,
            source_url
        FROM v_registry_state_compat
        WHERE {$column} = :hash
        LIMIT 1
    ";
    $compatRow = db_one($compatSql, ['hash' => $hash]);
} catch (Throwable $e) {
    $compatRow = null;
}

$row = $compatRow;
if ($row === null) {
    $sql = "
        SELECT
            sha256,
            md5,
            sha1,
            record_created_at_utc
        FROM malware_artifact_hash_registry
        WHERE {$column} = :hash
        LIMIT 1
    ";
    $row = db_one($sql, ['hash' => $hash]);
    if ($row !== null) {
        $row = [
            'sha256' => $row['sha256'] ?? null,
            'md5' => $row['md5'] ?? null,
            'sha1' => $row['sha1'] ?? null,
            'record_created_at_utc' => $row['record_created_at_utc'] ?? null,
            'vt_status_code' => null,
            'next_eligible_at_utc' => null,
            'attempt_count' => null,
            'last_attempt_at_utc' => null,
            'last_http_status' => null,
            'last_error_category' => null,
            'last_error_message' => null,
            'last_run_id' => null,
            'source_url' => null,
        ];
    }
}

if ($row !== null) {
    $sampleRow = null;
    try {
        $sampleSql = "
            SELECT sample_id, sample_label, family_label
            FROM malware_sample_catalog
            WHERE {$column} = :hash
            ORDER BY sample_id DESC
            LIMIT 1
        ";
        $sampleRow = db_one($sampleSql, ['hash' => $hash]);
        if ($sampleRow === null && !empty($row['sha256'])) {
            $sampleRow = db_one(
                "
                    SELECT sample_id, sample_label, family_label
                    FROM malware_sample_catalog
                    WHERE sha256 = :sha256
                    ORDER BY sample_id DESC
                    LIMIT 1
                ",
                ['sha256' => (string)$row['sha256']]
            );
        }
    } catch (Throwable $e) {
        $sampleRow = null;
    }

    $row['sample_id'] = $sampleRow['sample_id'] ?? null;
    $row['sample_label'] = $sampleRow['sample_label'] ?? null;
    $row['family_label'] = $sampleRow['family_label'] ?? null;

    $needsStateFallback = (
        ($row['vt_status_code'] ?? null) === null
        && ($row['next_eligible_at_utc'] ?? null) === null
        && ($row['attempt_count'] ?? null) === null
        && ($row['last_attempt_at_utc'] ?? null) === null
        && ($row['last_http_status'] ?? null) === null
        && ($row['last_error_category'] ?? null) === null
        && ($row['last_error_message'] ?? null) === null
        && ($row['last_run_id'] ?? null) === null
    );

    if ($needsStateFallback) {
        $stateRow = null;
        try {
            if (!empty($row['sample_id'])) {
                $stateRow = db_one(
                    "
                        SELECT
                            vt_status_code,
                            next_eligible_at_utc,
                            attempt_count,
                            last_attempt_at_utc,
                            last_http_status,
                            last_error_category,
                            last_error_message,
                            last_run_id
                        FROM virustotal_sample_state
                        WHERE sample_id = :sample_id
                        LIMIT 1
                    ",
                    ['sample_id' => (int)$row['sample_id']]
                );
            }

            if ($stateRow === null && !empty($row['sha256'])) {
                $stateRow = db_one(
                    "
                        SELECT
                            vt_status_code,
                            next_eligible_at_utc,
                            attempt_count,
                            last_attempt_at_utc,
                            last_http_status,
                            last_error_category,
                            last_error_message,
                            last_run_id
                        FROM virustotal_sample_state
                        WHERE sha256 = :sha256
                        LIMIT 1
                    ",
                    ['sha256' => (string)$row['sha256']]
                );
            }
        } catch (Throwable $e) {
            $stateRow = null;
        }

        if ($stateRow !== null) {
            $row['vt_status_code'] = $stateRow['vt_status_code'] ?? $row['vt_status_code'];
            $row['next_eligible_at_utc'] = $stateRow['next_eligible_at_utc'] ?? $row['next_eligible_at_utc'];
            $row['attempt_count'] = $stateRow['attempt_count'] ?? $row['attempt_count'];
            $row['last_attempt_at_utc'] = $stateRow['last_attempt_at_utc'] ?? $row['last_attempt_at_utc'];
            $row['last_http_status'] = $stateRow['last_http_status'] ?? $row['last_http_status'];
            $row['last_error_category'] = $stateRow['last_error_category'] ?? $row['last_error_category'];
            $row['last_error_message'] = $stateRow['last_error_message'] ?? $row['last_error_message'];
            $row['last_run_id'] = $stateRow['last_run_id'] ?? $row['last_run_id'];
        }
    }
}

$queueSql = "
    SELECT queue_status, record_created_at_utc
    FROM malware_artifact_ingest_queue
    WHERE (
        (artifact_hash_type = :hash_type AND artifact_hash_norm = :hash_norm)
        OR {$queueColumn} = :hash_legacy
    )
    ORDER BY ingest_id DESC
    LIMIT 1
";
$queueRow = db_one($queueSql, [
    'hash_type' => $column,
    'hash_norm' => $hashNorm,
    'hash_legacy' => $hash,
]);

api_ok([
    'found' => $row !== null,
    'match_column' => $column,
    'record' => $row,
    'queue_status' => $queueRow['queue_status'] ?? null,
    'queued_at_utc' => $queueRow['record_created_at_utc'] ?? null,
]);
