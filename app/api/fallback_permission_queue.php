<?php
// app/api/fallback_permission_queue.php
// Read-only DB-backed permission-queue contract.
// Keep this aligned with canonical queue vocabulary and Python apply semantics.
// Must remain parity with the live permission-queue payload (fields, UTC formats, nulls).

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../database/db_engine.php';

require_get();

function iso_utc(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s\Z');
    } catch (Throwable $e) {
        return null;
    }
}

function normalize_queue_action(string $raw): string
{
    $normalized = perm_normalize_queue_action($raw);
    if ($normalized === '') return '';
    $known = array_map('strtolower', perm_extract_keys(perm_queue_actions()));
    $known = array_values(array_unique(array_merge($known, ['skip', 'apply'])));
    if (in_array($normalized, $known, true)) {
        return $normalized;
    }
    return 'unknown:' . $raw;
}

function normalize_status(string $raw): string
{
    $key = perm_normalize_queue_status($raw);
    if ($key === '') {
        return '';
    }

    $known = ['queued', 'claimed', 'applied', 'error', 'rejected', 'skipped'];
    if (in_array($key, $known, true)) {
        return $key;
    }

    return 'unknown:' . $raw;
}

function active_queue_status_sql_case(string $expr): string
{
    return "CASE
        WHEN LOWER(TRIM({$expr})) = 'pending' THEN 'queued'
        WHEN LOWER(TRIM({$expr})) = 'done' THEN 'applied'
        WHEN LOWER(TRIM({$expr})) = 'failed' THEN 'error'
        ELSE LOWER(TRIM({$expr}))
    END";
}

function canonical_queue_action_sql_case(string $expr): string
{
    return "LOWER(TRIM({$expr}))";
}

function classify_queue_population_fields(
    string $status,
    string $queueAction,
    string $sourceSystem,
    ?string $dictUnknownTriageStatus,
    bool $hasLedgerAnchor,
    bool $hasObsSample,
    bool $hasVtEvent,
    bool $alreadyInAosp
): string
{
    $normalizedStatus = normalize_status($status);
    $normalizedAction = normalize_queue_action($queueAction);
    $sourceSystem = strtolower(trim($sourceSystem));
    $dictUnknownTriageStatus = strtolower(trim((string)$dictUnknownTriageStatus));

    if ($normalizedStatus !== 'queued') {
        return 'other_queue_state';
    }
    if ($sourceSystem === 'web') {
        return 'web_triage_queue';
    }
    if (
        $normalizedAction === 'aosp'
        && $sourceSystem === 'static-analysis'
        && !$hasLedgerAnchor
        && !$hasObsSample
        && !$hasVtEvent
        && !$alreadyInAosp
    ) {
        return 'imported_static_candidate_no_anchor';
    }
    if ($normalizedAction === 'aosp' && $alreadyInAosp && $dictUnknownTriageStatus === 'resolved_aosp') {
        return 'already_resolved_aosp_duplicate';
    }
    if ($normalizedAction === 'aosp' && $dictUnknownTriageStatus === 'malformed') {
        return 'malformed_ledger_conflict';
    }
    if ($hasLedgerAnchor && ($hasObsSample || $hasVtEvent)) {
        return 'evidence_backed_queue_work';
    }
    return 'other_queue_state';
}

function queue_population_case_sql(bool $exact): string
{
    $triageExpr = $exact
        ? "(
                SELECT u2.triage_status
                FROM " . db_catalog_table('android_permission_dict_unknown') . " u2
                WHERE BINARY u2.permission_string = BINARY q.permission_string
                LIMIT 1
            )"
        : "(
                SELECT u2.triage_status
                FROM " . db_catalog_table('android_permission_dict_unknown') . " u2
                WHERE LOWER(TRIM(u2.permission_string)) = LOWER(TRIM(q.permission_string))
                ORDER BY
                    CASE WHEN BINARY u2.permission_string = BINARY q.permission_string THEN 0 ELSE 1 END ASC,
                    u2.permission_string ASC
                LIMIT 1
            )";
    $anchorExistsExpr = $exact
        ? "EXISTS (
                SELECT 1
                FROM " . db_catalog_table('android_permission_dict_unknown') . " u2
                WHERE BINARY u2.permission_string = BINARY q.permission_string
                LIMIT 1
            )"
        : "EXISTS (
                SELECT 1
                FROM " . db_catalog_table('android_permission_dict_unknown') . " u2
                WHERE LOWER(TRIM(u2.permission_string)) = LOWER(TRIM(q.permission_string))
                LIMIT 1
            )";
    $obsMatchExpr = $exact
        ? "BINARY o2.permission_string = BINARY q.permission_string"
        : "LOWER(TRIM(o2.permission_string)) = LOWER(TRIM(q.permission_string))";
    $vtMatchExpr = $exact
        ? "BINARY e2.permission_string = BINARY q.permission_string"
        : "LOWER(TRIM(e2.permission_string)) = LOWER(TRIM(q.permission_string))";
    $aospMatchExpr = $exact
        ? "BINARY a2.constant_value = BINARY q.permission_string"
        : "LOWER(TRIM(a2.constant_value)) = LOWER(TRIM(q.permission_string))";

    return "
        CASE
            WHEN " . active_queue_status_sql_case('q.status') . " <> 'queued' THEN 'other_queue_state'
            WHEN LOWER(TRIM(COALESCE(q.source_system, ''))) = 'web' THEN 'web_triage_queue'
            WHEN
                " . canonical_queue_action_sql_case('q.queue_action') . " = 'aosp'
                AND LOWER(TRIM(COALESCE(q.source_system, ''))) = 'static-analysis'
                AND NOT {$anchorExistsExpr}
                AND NOT EXISTS (
                    SELECT 1
                    FROM " . db_catalog_table('android_permission_obs_sample') . " o2
                    WHERE {$obsMatchExpr}
                    LIMIT 1
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM " . db_catalog_table('android_permission_enrich_vt_event') . " e2
                    WHERE {$vtMatchExpr}
                    LIMIT 1
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM " . db_catalog_table('android_permission_dict_aosp') . " a2
                    WHERE {$aospMatchExpr}
                    LIMIT 1
                )
            THEN 'imported_static_candidate_no_anchor'
            WHEN
                " . canonical_queue_action_sql_case('q.queue_action') . " = 'aosp'
                AND EXISTS (
                    SELECT 1
                    FROM " . db_catalog_table('android_permission_dict_aosp') . " a2
                    WHERE {$aospMatchExpr}
                    LIMIT 1
                )
                AND LOWER(TRIM(COALESCE({$triageExpr}, ''))) = 'resolved_aosp'
            THEN 'already_resolved_aosp_duplicate'
            WHEN
                " . canonical_queue_action_sql_case('q.queue_action') . " = 'aosp'
                AND LOWER(TRIM(COALESCE({$triageExpr}, ''))) = 'malformed'
            THEN 'malformed_ledger_conflict'
            WHEN
                {$anchorExistsExpr}
                AND (
                    EXISTS (
                        SELECT 1
                        FROM " . db_catalog_table('android_permission_obs_sample') . " o2
                        WHERE {$obsMatchExpr}
                        LIMIT 1
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM " . db_catalog_table('android_permission_enrich_vt_event') . " e2
                        WHERE {$vtMatchExpr}
                        LIMIT 1
                    )
                )
            THEN 'evidence_backed_queue_work'
            ELSE 'other_queue_state'
        END
    ";
}

function classify_queue_population(array $row): string
{
    return classify_queue_population_fields(
        (string)($row['status'] ?? ''),
        (string)($row['queue_action'] ?? ''),
        (string)($row['source_system'] ?? ''),
        isset($row['dict_unknown_triage_status']) ? (string)$row['dict_unknown_triage_status'] : null,
        !empty($row['has_ledger_anchor']),
        !empty($row['has_obs_sample']),
        !empty($row['has_vt_event']),
        !empty($row['already_in_aosp'])
    );
}

function classify_queue_population_exact(array $row): string
{
    return classify_queue_population_fields(
        (string)($row['status'] ?? ''),
        (string)($row['queue_action'] ?? ''),
        (string)($row['source_system'] ?? ''),
        isset($row['dict_unknown_triage_status_exact']) ? (string)$row['dict_unknown_triage_status_exact'] : null,
        !empty($row['has_ledger_anchor_exact']),
        !empty($row['has_obs_sample_exact']),
        !empty($row['has_vt_event_exact']),
        !empty($row['already_in_aosp_exact'])
    );
}

function classify_queue_population_normalized(array $row): string
{
    return classify_queue_population_fields(
        (string)($row['status'] ?? ''),
        (string)($row['queue_action'] ?? ''),
        (string)($row['source_system'] ?? ''),
        isset($row['dict_unknown_triage_status_normalized']) ? (string)$row['dict_unknown_triage_status_normalized'] : null,
        !empty($row['has_ledger_anchor_normalized']),
        !empty($row['has_obs_sample_normalized']),
        !empty($row['has_vt_event_normalized']),
        !empty($row['already_in_aosp_normalized'])
    );
}

function match_semantics(bool $exactCaseMatch, bool $normalizedCaseMatch): string
{
    if ($exactCaseMatch) return 'exact';
    if ($normalizedCaseMatch) return 'normalized_only';
    return 'none';
}

function match_warning(string $matchSemantics): ?string
{
    if ($matchSemantics === 'normalized_only') {
        return 'case_form_drift';
    }
    return null;
}

function match_semantics_label(string $matchSemantics): string
{
    if ($matchSemantics === 'exact') return 'Exact match';
    if ($matchSemantics === 'normalized_only') return 'Normalized-only';
    return 'No match';
}

function permission_key(?string $value): string
{
    return strtolower(trim((string)$value));
}

function build_in_clause(string $prefix, array $values, array &$params): string
{
    $placeholders = [];
    $index = 0;
    foreach ($values as $value) {
        $key = $prefix . $index++;
        $placeholders[] = ':' . $key;
        $params[$key] = $value;
    }
    return implode(', ', $placeholders);
}

function fetch_exact_presence_map(string $table, string $column, array $exactValues): array
{
    $exactValues = array_values(array_unique(array_filter($exactValues, static fn($v): bool => (string)$v !== '')));
    if ($exactValues === []) {
        return [];
    }

    $params = [];
    $inClause = build_in_clause('exact_', $exactValues, $params);
    $rows = db_all("SELECT DISTINCT {$column} AS value FROM {$table} WHERE {$column} IN ({$inClause})", $params);

    $map = [];
    foreach ($rows as $row) {
        $value = (string)($row['value'] ?? '');
        if ($value !== '') {
            $map[$value] = true;
        }
    }
    return $map;
}

function fetch_normalized_presence_map(string $table, string $column, array $normalizedValues): array
{
    $normalizedValues = array_values(array_unique(array_filter($normalizedValues, static fn($v): bool => (string)$v !== '')));
    if ($normalizedValues === []) {
        return [];
    }

    $params = [];
    $inClause = build_in_clause('norm_', $normalizedValues, $params);
    $rows = db_all(
        "SELECT DISTINCT LOWER(TRIM({$column})) AS normalized_value
         FROM {$table}
         WHERE LOWER(TRIM({$column})) IN ({$inClause})",
        $params
    );

    $map = [];
    foreach ($rows as $row) {
        $value = permission_key($row['normalized_value'] ?? '');
        if ($value !== '') {
            $map[$value] = true;
        }
    }
    return $map;
}

function fetch_unknown_status_maps(array $exactValues, array $normalizedValues): array
{
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $exactMap = [];
    $normalizedMap = [];

    $exactValues = array_values(array_unique(array_filter($exactValues, static fn($v): bool => (string)$v !== '')));
    if ($exactValues !== []) {
        $params = [];
        $inClause = build_in_clause('unknown_exact_', $exactValues, $params);
        $rows = db_all(
            "SELECT permission_string, triage_status
             FROM {$dictUnknown}
             WHERE permission_string IN ({$inClause})
             ORDER BY permission_string ASC",
            $params
        );
        foreach ($rows as $row) {
            $permission = (string)($row['permission_string'] ?? '');
            if ($permission !== '' && !array_key_exists($permission, $exactMap)) {
                $exactMap[$permission] = $row['triage_status'] ?? null;
            }
        }
    }

    $normalizedValues = array_values(array_unique(array_filter($normalizedValues, static fn($v): bool => (string)$v !== '')));
    if ($normalizedValues !== []) {
        $params = [];
        $inClause = build_in_clause('unknown_norm_', $normalizedValues, $params);
        $rows = db_all(
            "SELECT LOWER(TRIM(permission_string)) AS normalized_permission, permission_string, triage_status
             FROM {$dictUnknown}
             WHERE LOWER(TRIM(permission_string)) IN ({$inClause})
             ORDER BY permission_string ASC",
            $params
        );
        foreach ($rows as $row) {
            $normalized = permission_key($row['normalized_permission'] ?? '');
            if ($normalized !== '' && !array_key_exists($normalized, $normalizedMap)) {
                $normalizedMap[$normalized] = $row['triage_status'] ?? null;
            }
        }
    }

    return [$exactMap, $normalizedMap];
}

function augment_queue_rows(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $exactPermissions = [];
    $normalizedPermissions = [];
    foreach ($rows as $row) {
        $permission = (string)($row['permission_string'] ?? '');
        if ($permission === '') {
            continue;
        }
        $exactPermissions[] = $permission;
        $normalizedPermissions[] = permission_key($permission);
    }

    [$unknownExactMap, $unknownNormalizedMap] = fetch_unknown_status_maps($exactPermissions, $normalizedPermissions);
    $obsExactMap = fetch_exact_presence_map(db_catalog_table('android_permission_obs_sample'), 'permission_string', $exactPermissions);
    $obsNormalizedMap = fetch_normalized_presence_map(db_catalog_table('android_permission_obs_sample'), 'permission_string', $normalizedPermissions);
    $vtExactMap = fetch_exact_presence_map(db_catalog_table('android_permission_enrich_vt_event'), 'permission_string', $exactPermissions);
    $vtNormalizedMap = fetch_normalized_presence_map(db_catalog_table('android_permission_enrich_vt_event'), 'permission_string', $normalizedPermissions);
    $aospExactMap = fetch_exact_presence_map(db_catalog_table('android_permission_dict_aosp'), 'constant_value', $exactPermissions);
    $aospNormalizedMap = fetch_normalized_presence_map(db_catalog_table('android_permission_dict_aosp'), 'constant_value', $normalizedPermissions);

    $outRows = [];
    foreach ($rows as $row) {
        $permission = (string)($row['permission_string'] ?? '');
        $normalizedPermission = permission_key($permission);
        $rawAction = (string)($row['queue_action'] ?? '');
        $rawStatus = (string)($row['status'] ?? '');
        $normalizedAction = $rawAction !== '' ? normalize_queue_action($rawAction) : null;
        $queueTriageStatus = $row['queue_triage_status'] ?? $row['triage_status'] ?? null;
        $dictUnknownTriageStatusExact = $unknownExactMap[$permission] ?? null;
        $dictUnknownTriageStatusNormalized = $unknownNormalizedMap[$normalizedPermission] ?? null;

        $hasLedgerAnchorExact = array_key_exists($permission, $unknownExactMap);
        $hasLedgerAnchorNormalized = array_key_exists($normalizedPermission, $unknownNormalizedMap);
        $hasObsSampleExact = !empty($obsExactMap[$permission]);
        $hasObsSampleNormalized = !empty($obsNormalizedMap[$normalizedPermission]);
        $hasVtEventExact = !empty($vtExactMap[$permission]);
        $hasVtEventNormalized = !empty($vtNormalizedMap[$normalizedPermission]);
        $alreadyInAospExact = !empty($aospExactMap[$permission]);
        $alreadyInAospNormalized = !empty($aospNormalizedMap[$normalizedPermission]);

        $populationLabelExact = classify_queue_population_fields(
            $rawStatus,
            $rawAction,
            (string)($row['source_system'] ?? ''),
            is_string($dictUnknownTriageStatusExact) ? $dictUnknownTriageStatusExact : null,
            $hasLedgerAnchorExact,
            $hasObsSampleExact,
            $hasVtEventExact,
            $alreadyInAospExact
        );
        $populationLabelNormalized = classify_queue_population_fields(
            $rawStatus,
            $rawAction,
            (string)($row['source_system'] ?? ''),
            is_string($dictUnknownTriageStatusNormalized) ? $dictUnknownTriageStatusNormalized : null,
            $hasLedgerAnchorNormalized,
            $hasObsSampleNormalized,
            $hasVtEventNormalized,
            $alreadyInAospNormalized
        );

        $exactCaseMatch = $hasLedgerAnchorExact || $hasObsSampleExact || $hasVtEventExact || $alreadyInAospExact;
        $normalizedCaseMatch = $hasLedgerAnchorNormalized || $hasObsSampleNormalized || $hasVtEventNormalized || $alreadyInAospNormalized;
        $matchSemantics = match_semantics($exactCaseMatch, $normalizedCaseMatch);

        $outRows[] = [
            'queue_id' => (int)($row['queue_id'] ?? 0),
            'permission_string' => $permission !== '' ? $permission : null,
            'queue_action_raw' => $rawAction !== '' ? $rawAction : null,
            'queue_action' => $normalizedAction,
            'normalized_action' => $normalizedAction,
            'status_raw' => $rawStatus !== '' ? $rawStatus : null,
            'status' => $rawStatus !== '' ? normalize_status($rawStatus) : null,
            'queued_at_utc' => iso_utc($row['queued_at_utc'] ?? null),
            'processed_at_utc' => iso_utc($row['processed_at_utc'] ?? null),
            'processed_by' => $row['processed_by'] ?? null,
            'error_message' => $row['error_message'] ?? null,
            'triage_status' => $row['triage_status'] ?? null,
            'triage_status_display' => perm_triage_status_label((string)($row['triage_status'] ?? '')),
            'queue_triage_status' => $queueTriageStatus,
            'queue_triage_status_display' => perm_triage_status_label((string)$queueTriageStatus),
            'dict_unknown_triage_status' => $dictUnknownTriageStatusNormalized,
            'dict_unknown_triage_status_display' => perm_triage_status_label((string)$dictUnknownTriageStatusNormalized),
            'dict_unknown_triage_status_exact' => $dictUnknownTriageStatusExact,
            'dict_unknown_triage_status_exact_display' => perm_triage_status_label((string)$dictUnknownTriageStatusExact),
            'dict_unknown_triage_status_normalized' => $dictUnknownTriageStatusNormalized,
            'dict_unknown_triage_status_normalized_display' => perm_triage_status_label((string)$dictUnknownTriageStatusNormalized),
            'proposed_classification' => $row['proposed_classification'] ?? null,
            'proposed_bucket' => $row['proposed_bucket'] ?? null,
            'updated_at_utc' => iso_utc($row['updated_at_utc'] ?? null),
            'source_system' => $row['source_system'] ?? null,
            'requested_by' => $row['requested_by'] ?? null,
            'has_ledger_anchor' => $hasLedgerAnchorNormalized,
            'has_obs_sample' => $hasObsSampleNormalized,
            'has_vt_event' => $hasVtEventNormalized,
            'already_in_aosp' => $alreadyInAospNormalized,
            'has_ledger_anchor_exact' => $hasLedgerAnchorExact,
            'has_ledger_anchor_normalized' => $hasLedgerAnchorNormalized,
            'has_obs_sample_exact' => $hasObsSampleExact,
            'has_obs_sample_normalized' => $hasObsSampleNormalized,
            'has_vt_event_exact' => $hasVtEventExact,
            'has_vt_event_normalized' => $hasVtEventNormalized,
            'already_in_aosp_exact' => $alreadyInAospExact,
            'already_in_aosp_normalized' => $alreadyInAospNormalized,
            'queue_population_label' => $populationLabelNormalized,
            'queue_population_label_exact' => $populationLabelExact,
            'queue_population_label_normalized' => $populationLabelNormalized,
            'queue_population_label_semantics' => 'normalized',
            'exact_case_match' => $exactCaseMatch,
            'normalized_case_match' => $normalizedCaseMatch,
            'normalized_only_match' => $normalizedCaseMatch && !$exactCaseMatch,
            'match_semantics' => $matchSemantics,
            'match_semantics_label' => match_semantics_label($matchSemantics),
            'match_warning' => match_warning($matchSemantics),
            'ledger_anchor_label' => ledger_anchor_label($hasLedgerAnchorExact, $hasLedgerAnchorNormalized),
            'evidence_label' => evidence_label($hasObsSampleExact, $hasObsSampleNormalized, $hasVtEventExact, $hasVtEventNormalized),
            'aosp_match_label' => aosp_match_label($alreadyInAospExact, $alreadyInAospNormalized),
            'conflict_label' => conflict_label($populationLabelNormalized, is_string($dictUnknownTriageStatusNormalized) ? $dictUnknownTriageStatusNormalized : null),
            'conflict_label_exact' => conflict_label($populationLabelExact, is_string($dictUnknownTriageStatusExact) ? $dictUnknownTriageStatusExact : null),
            'conflict_label_normalized' => conflict_label($populationLabelNormalized, is_string($dictUnknownTriageStatusNormalized) ? $dictUnknownTriageStatusNormalized : null),
        ];
    }

    return $outRows;
}

function ledger_anchor_label(bool $exactMatch, bool $normalizedMatch): string
{
    if ($exactMatch) return 'Exact ledger anchor';
    if ($normalizedMatch) return 'Normalized ledger anchor';
    return 'No ledger anchor';
}

function evidence_label(bool $exactObs, bool $normalizedObs, bool $exactVt, bool $normalizedVt): string
{
    if ($exactObs || $exactVt) return 'Exact evidence';
    if ($normalizedObs || $normalizedVt) return 'Normalized evidence';
    return 'No evidence';
}

function aosp_match_label(bool $exactMatch, bool $normalizedMatch): string
{
    if ($exactMatch) return 'Exact AOSP';
    if ($normalizedMatch) return 'Normalized AOSP';
    return 'No AOSP match';
}

function conflict_label(string $populationLabel, ?string $dictUnknownTriageStatus): string
{
    if ($populationLabel === 'imported_static_candidate_no_anchor') {
        return 'missing_ledger_anchor';
    }
    if ($populationLabel === 'already_resolved_aosp_duplicate') {
        return 'already_resolved_duplicate';
    }
    if ($populationLabel === 'malformed_ledger_conflict') {
        return 'malformed_ledger';
    }
    if (strtolower(trim((string)$dictUnknownTriageStatus)) === 'malformed') {
        return 'malformed_ledger';
    }
    return 'none';
}

function parse_cursor(?string $cursor): ?array
{
    if (!$cursor) return null;
    $parts = explode('|', $cursor);
    if (count($parts) !== 2) return null;
    [$ts, $id] = $parts;
    $ts = trim($ts);
    $id = (int)$id;
    if ($ts === '' || $id <= 0) return null;
    try {
        $dt = new DateTimeImmutable($ts, new DateTimeZone('UTC'));
        $dbTs = $dt->format('Y-m-d H:i:s');
        return [$dbTs, $id];
    } catch (Throwable $e) {
        return null;
    }
}

try {
    $dictQueue = db_catalog_table('android_permission_dict_queue');
    $dictUnknown = db_catalog_table('android_permission_dict_unknown');
    $canonicalActionFilterExpr = canonical_queue_action_sql_case('q.queue_action');
    $normalizedStatusFilterExpr = active_queue_status_sql_case('q.status');
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $action = trim((string)($_GET['queue_action'] ?? ''));
    $limit = get_int('limit', 50, 1, 500);
    $includePopulationCounts = get_bool('include_population_counts', false);
    $cursor = parse_cursor((string)($_GET['cursor'] ?? ''));

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'q.permission_string LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    if ($action !== '') {
        $actionKey = perm_normalize_queue_action($action);
        $where[] = "{$canonicalActionFilterExpr} = :action";
        $params['action'] = $actionKey;
    }

    if ($status !== '') {
        $where[] = "{$normalizedStatusFilterExpr} = :status";
        $params['status'] = perm_normalize_queue_status($status);
    }

    $paramsBase = $params;
    $whereBaseSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $wherePage = $where;
    if ($cursor) {
        [$cursorTs, $cursorId] = $cursor;
        $wherePage[] = '(q.updated_at_utc < :cursor_ts OR (q.updated_at_utc = :cursor_ts AND q.queue_id < :cursor_id))';
        $params['cursor_ts'] = $cursorTs;
        $params['cursor_id'] = $cursorId;
    }
    $wherePageSql = $wherePage ? ('WHERE ' . implode(' AND ', $wherePage)) : '';
    $normalizedStatusExpr = $normalizedStatusFilterExpr;
    $rowsSql = "
        SELECT
            q.queue_id,
            q.permission_string,
            q.queue_action,
            q.status,
            q.queued_at_utc,
            q.processed_at_utc,
            q.processed_by,
            q.error_message,
            q.triage_status,
            q.proposed_classification,
            q.proposed_bucket,
            q.updated_at_utc,
            q.source_system,
            q.requested_by,
            q.triage_status AS queue_triage_status
        FROM {$dictQueue} q
        {$whereBaseSql}
        ORDER BY q.updated_at_utc DESC, q.queue_id DESC
    ";
    $allRows = db_all($rowsSql, $paramsBase);

    $pageRows = [];
    foreach ($allRows as $row) {
        if ($cursor) {
            [$cursorTs, $cursorId] = $cursor;
            $rowTs = (string)($row['updated_at_utc'] ?? '');
            $rowId = (int)($row['queue_id'] ?? 0);
            $isBeforeCursor = $rowTs < $cursorTs || ($rowTs === $cursorTs && $rowId < $cursorId);
            if (!$isBeforeCursor) {
                continue;
            }
        }
        $pageRows[] = $row;
        if (count($pageRows) > $limit) {
            break;
        }
    }

    $hasMore = count($pageRows) > $limit;
    if ($hasMore) {
        $pageRows = array_slice($pageRows, 0, $limit);
    }

    $outRows = augment_queue_rows($pageRows);

    $totalCount = count($allRows);
    $countsByStatus = [];
    $countsByStatusActive = [];
    $countsByActionRaw = [];
    $countsByAction = [];
    $countsByActionActive = [];
    $legacyQueueActionsActiveMap = [];

    foreach ($allRows as $row) {
        $rawStatus = (string)($row['status'] ?? '');
        $normalizedStatus = normalize_status($rawStatus);
        if ($normalizedStatus !== '') {
            $countsByStatus[$normalizedStatus] = (int)($countsByStatus[$normalizedStatus] ?? 0) + 1;
            if (in_array($normalizedStatus, ['queued', 'claimed'], true)) {
                $countsByStatusActive[$normalizedStatus] = (int)($countsByStatusActive[$normalizedStatus] ?? 0) + 1;
            }
        }

        $rawAction = strtolower(trim((string)($row['queue_action'] ?? '')));
        if ($rawAction !== '') {
            $countsByActionRaw[$rawAction] = (int)($countsByActionRaw[$rawAction] ?? 0) + 1;
        }

        $normalizedAction = normalize_queue_action((string)($row['queue_action'] ?? ''));
        if ($normalizedAction !== '') {
            $countsByAction[$normalizedAction] = (int)($countsByAction[$normalizedAction] ?? 0) + 1;
            if (in_array($normalizedStatus, ['queued', 'claimed'], true)) {
                $countsByActionActive[$normalizedAction] = (int)($countsByActionActive[$normalizedAction] ?? 0) + 1;
                if ($rawAction !== '' && $rawAction !== $normalizedAction) {
                    $legacyKey = $rawAction . '|' . $normalizedAction;
                    $legacyQueueActionsActiveMap[$legacyKey] = [
                        'raw' => $rawAction,
                        'normalized' => $normalizedAction,
                        'count' => (int)(($legacyQueueActionsActiveMap[$legacyKey]['count'] ?? 0) + 1),
                    ];
                }
            }
        }
    }

    $legacyQueueActionsActive = array_values($legacyQueueActionsActiveMap);

    $countsByPopulation = null;
    $countsByPopulationExact = null;
    if ($includePopulationCounts) {
        $countsByPopulation = [
            'imported_static_candidate_no_anchor' => 0,
            'already_resolved_aosp_duplicate' => 0,
            'malformed_ledger_conflict' => 0,
            'evidence_backed_queue_work' => 0,
            'web_triage_queue' => 0,
            'other_queue_state' => 0,
        ];
        $countsByPopulationExact = $countsByPopulation;
        foreach (augment_queue_rows($allRows) as $row) {
            $normalizedLabel = (string)($row['queue_population_label_normalized'] ?? $row['queue_population_label'] ?? '');
            $exactLabel = (string)($row['queue_population_label_exact'] ?? '');
            if ($normalizedLabel !== '') {
                $countsByPopulation[$normalizedLabel] = (int)($countsByPopulation[$normalizedLabel] ?? 0) + 1;
            }
            if ($exactLabel !== '') {
                $countsByPopulationExact[$exactLabel] = (int)($countsByPopulationExact[$exactLabel] ?? 0) + 1;
            }
        }
    }

    $nextCursor = null;
    if ($hasMore && $pageRows) {
        $last = $pageRows[count($pageRows) - 1];
        $cursorTs = iso_utc($last['updated_at_utc'] ?? null);
        $cursorId = $last['queue_id'] ?? null;
        if ($cursorTs && $cursorId) {
            $nextCursor = $cursorTs . '|' . $cursorId;
        }
    }

    json_ok([
        'ok' => true,
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'rows' => $outRows,
        'has_more' => $hasMore,
        'next_cursor' => $nextCursor,
        'total_count' => $totalCount,
        'counts_by_status' => $countsByStatus,
        'counts_by_status_active' => $countsByStatusActive,
        'counts_by_action' => $countsByAction,
        'counts_by_action_active' => $countsByActionActive,
        'counts_by_action_raw' => $countsByActionRaw,
        'legacy_queue_actions_active' => $legacyQueueActionsActive,
        'counts_by_population' => $countsByPopulation,
        'counts_by_population_normalized' => $countsByPopulation,
        'counts_by_population_exact' => $countsByPopulationExact,
    ]);
} catch (Throwable $e) {
    api_error('Fallback permission queue failed.', 500, 'ERR_FALLBACK_QUEUE', [], $e);
}
