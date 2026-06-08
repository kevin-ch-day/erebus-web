<?php
// app/api/artifact_ingest_queue.php
// Queue artifact ingestion (write endpoint, localhost/token only).

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/artifact_sources.php';
require_once __DIR__ . '/../database/db_engine.php';

require_post();
require_write_access();

$raw = file_get_contents('php://input');
$body = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($body)) {
    $body = $_POST;
}

$items = [];
if (isset($body['items']) && is_array($body['items'])) {
    $items = $body['items'];
} elseif (!empty($body)) {
    $items = [$body];
}

if (!$items) {
    api_error('No items provided.', 400, 'ERR_EMPTY_ITEMS');
    exit;
}

function normalize_nullable_text($value, int $maxLen): ?string
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLen);
    }
    return substr($text, 0, $maxLen);
}

function normalize_hash_type(string $hash): ?string
{
    $len = strlen($hash);
    if ($len === 32) return 'md5';
    if ($len === 40) return 'sha1';
    if ($len === 64) return 'sha256';
    return null;
}

$dedupeRegistrySql = "
    SELECT 1 AS ok
    FROM malware_artifact_hash_registry
    WHERE %s = :hash
    LIMIT 1
";

$dedupeQueueSql = "
    SELECT queue_status
    FROM malware_artifact_ingest_queue
    WHERE queue_status IN ('PENDING','PROCESSING')
      AND (
        (artifact_hash_type = :hash_type AND artifact_hash_norm = :hash_norm)
        OR %s = :hash_legacy
      )
    ORDER BY ingest_id DESC
    LIMIT 1
";

$insertSql = "
    INSERT INTO malware_artifact_ingest_queue (
        artifact_hash_raw,
        artifact_hash_norm,
        artifact_hash_type,
        artifact_hash_md5,
        artifact_hash_sha1,
        artifact_hash_sha256,
        artifact_name,
        artifact_family,
        artifact_category,
        artifact_subtype,
        artifact_source
    ) VALUES (
        :hash_raw, :hash_norm, :hash_type,
        :md5, :sha1, :sha256,
        :name, :family, :category, :subtype, :source
    )
";

$accepted = 0;
$failed = 0;
$errors = [];
$ingestIds = [];
$duplicatesKnown = [];
$duplicatesQueued = [];
$warnings = [];
$rowResults = [];

db_tx(function () use (
    $items,
    $insertSql,
    $dedupeRegistrySql,
    $dedupeQueueSql,
    &$accepted,
    &$failed,
    &$errors,
    &$ingestIds,
    &$duplicatesKnown,
    &$duplicatesQueued,
    &$warnings,
    &$rowResults
) {
    foreach ($items as $idx => $item) {
        $rowNo = $idx + 1;
        $hash = trim((string)($item['artifact_hash'] ?? ''));
        if ($hash === '' || !preg_match('/^[a-f0-9]+$/i', $hash)) {
            $failed++;
            $message = "Row {$rowNo}: invalid hash.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'invalid_hash', 'message' => $message];
            continue;
        }

        $type = normalize_hash_type($hash);
        if ($type === null) {
            $failed++;
            $message = "Row {$rowNo}: hash length must be 32/40/64.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'invalid_hash_length', 'message' => $message];
            continue;
        }

        $column = $type === 'md5' ? 'artifact_hash_md5' : ($type === 'sha1' ? 'artifact_hash_sha1' : 'artifact_hash_sha256');
        $hashNorm = strtolower($hash);

        $known = db_one(sprintf($dedupeRegistrySql, $type), ['hash' => $hash]);
        if ($known) {
            $failed++;
            $duplicatesKnown[] = $hash;
            $message = "Row {$rowNo}: already known in registry.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'duplicate_known', 'message' => $message];
            continue;
        }

        $queued = db_one(sprintf($dedupeQueueSql, $column), [
            'hash_type' => $type,
            'hash_norm' => $hashNorm,
            'hash_legacy' => $hash,
        ]);
        if ($queued) {
            $failed++;
            $duplicatesQueued[] = $hash;
            $message = "Row {$rowNo}: already queued (" . ($queued['queue_status'] ?? 'PENDING') . ").";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'duplicate_queued', 'message' => $message];
            continue;
        }

        $source = trim((string)($item['artifact_source'] ?? ''));
        $sourceOther = trim((string)($item['artifact_source_other'] ?? ''));
        if ($source === '') {
            $failed++;
            $message = "Row {$rowNo}: missing artifact_source.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'missing_source', 'message' => $message];
            continue;
        }
        if (!artifact_source_is_valid($source)) {
            $failed++;
            $message = "Row {$rowNo}: invalid artifact_source.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'invalid_source', 'message' => $message];
            continue;
        }
        if ($source === 'other') {
            if ($sourceOther !== '') {
                if (strlen($sourceOther) > 120) {
                    $warnings[] = "Row {$rowNo}: artifact_source_other truncated to 120 characters.";
                }
                $sourceOther = function_exists('mb_substr') ? mb_substr($sourceOther, 0, 120) : substr($sourceOther, 0, 120);
                $source = 'other:' . $sourceOther;
            } else {
                $source = 'other';
            }
        }

        $name = normalize_nullable_text($item['artifact_name'] ?? '', 255);
        $family = normalize_nullable_text($item['artifact_family'] ?? '', 100);
        $category = normalize_nullable_text($item['artifact_category'] ?? '', 100);
        $subtype = normalize_nullable_text($item['artifact_subtype'] ?? '', 100);
        $source = normalize_nullable_text($source, 255);

        foreach ([
            'artifact_name' => [trim((string)($item['artifact_name'] ?? '')), $name, 255],
            'artifact_family' => [trim((string)($item['artifact_family'] ?? '')), $family, 100],
            'artifact_category' => [trim((string)($item['artifact_category'] ?? '')), $category, 100],
            'artifact_subtype' => [trim((string)($item['artifact_subtype'] ?? '')), $subtype, 100],
        ] as $field => [$rawValue, $normalizedValue, $maxLen]) {
            if ($rawValue !== '' && $normalizedValue !== null && strlen($rawValue) > strlen($normalizedValue)) {
                $warnings[] = "Row {$rowNo}: {$field} truncated to {$maxLen} characters.";
            }
        }

        $params = [
            'hash_raw' => $hash,
            'hash_norm' => $hashNorm,
            'hash_type' => $type,
            'md5' => $type === 'md5' ? $hash : null,
            'sha1' => $type === 'sha1' ? $hash : null,
            'sha256' => $type === 'sha256' ? $hash : null,
            'name' => $name,
            'family' => $family,
            'category' => $category,
            'subtype' => $subtype,
            'source' => $source,
        ];

        $count = db_exec($insertSql, $params);
        if ($count > 0) {
            $accepted++;
            $ingestIds[] = db()->lastInsertId();
            $rowResults[] = ['row' => $rowNo, 'status' => 'accepted', 'message' => "Row {$rowNo}: queued for ingestion."];
        } else {
            $failed++;
            $message = "Row {$rowNo}: insert failed.";
            $errors[] = $message;
            $rowResults[] = ['row' => $rowNo, 'status' => 'insert_failed', 'message' => $message];
        }
    }
});

api_ok([
    'accepted' => $accepted,
    'failed' => $failed,
    'ingest_ids' => $ingestIds,
    'errors' => $errors,
    'duplicates_known' => $duplicatesKnown,
    'duplicates_queued' => $duplicatesQueued,
    'warnings' => $warnings,
    'row_results' => $rowResults,
]);
