#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/database/db_func.php';

function qc_usage(): void
{
    echo <<<TXT
Erebus Console

Usage:
  php bin/erebus_console.php help
  php bin/erebus_console.php family:summary [--format=table|json]
  php bin/erebus_console.php family:export [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=csv|json|table]
  php bin/erebus_console.php family:apply-plan [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=table|json|sql]
  php bin/erebus_console.php family:pairs [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=table|json|csv]
  php bin/erebus_console.php family:drivers [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=table|json]
  php bin/erebus_console.php family:governance [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=table|json|csv]
  php bin/erebus_console.php family:opportunities [--limit=25] [--alignment=...] [--pattern=...] [--decision-mode=...] [--format=table|json|csv]
  php bin/erebus_console.php family:rows [--limit=50] [--alignment=...] [--pattern=...] [--q=...] [--pair-catalog=...] [--pair-signal=...] [--fix-action=...] [--target-family=...] [--decision-mode=...] [--format=table|json|csv]
  php bin/erebus_console.php stack:audit [--format=table|json]

Examples:
  php bin/erebus_console.php family:summary --format=table
  php bin/erebus_console.php family:export --decision-mode=repair_after_alias_review --format=csv
  php bin/erebus_console.php family:apply-plan --decision-mode=repair_after_alias_review --format=sql
  php bin/erebus_console.php family:pairs --format=table
  php bin/erebus_console.php family:drivers --format=table
  php bin/erebus_console.php family:governance --format=table
  php bin/erebus_console.php family:opportunities --limit=15 --format=table
  php bin/erebus_console.php family:rows --decision-mode=ask_why_first --limit=25 --format=table
  php bin/erebus_console.php stack:audit --format=json

TXT;
}

function qc_parse_options(array $argv): array
{
    $options = [];
    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        if ($arg === '') {
            continue;
        }
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = '1';
        }
    }
    return $options;
}

function qc_get(array $options, string $key, string $default = ''): string
{
    return trim((string)($options[$key] ?? $default));
}

function qc_int(array $options, string $key, int $default, int $min, int $max): int
{
    $raw = $options[$key] ?? $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }
    return max($min, min($max, (int)$value));
}

function qc_print_json($data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

function qc_print_csv(array $rows, array $columns): void
{
    $out = fopen('php://output', 'wb');
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
}

function qc_width(string $value, int $max = 40): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    if (mb_strlen($value) <= $max) {
        return $value;
    }
    return mb_substr($value, 0, max(0, $max - 3)) . '...';
}

function qc_print_table(array $rows, array $columns): void
{
    if ($rows === []) {
        echo "(no rows)\n";
        return;
    }

    $widths = [];
    foreach ($columns as $key => $label) {
        $widths[$key] = mb_strlen($label);
    }
    foreach ($rows as $row) {
        foreach ($columns as $key => $label) {
            $widths[$key] = max($widths[$key], mb_strlen(qc_width((string)($row[$key] ?? ''))));
        }
    }

    $parts = [];
    foreach ($columns as $key => $label) {
        $parts[] = str_pad($label, $widths[$key]);
    }
    echo implode(' | ', $parts), PHP_EOL;
    echo implode('-+-', array_map(static fn($key) => str_repeat('-', $widths[$key]), array_keys($columns))), PHP_EOL;

    foreach ($rows as $row) {
        $parts = [];
        foreach ($columns as $key => $label) {
            $parts[] = str_pad(qc_width((string)($row[$key] ?? '')), $widths[$key]);
        }
        echo implode(' | ', $parts), PHP_EOL;
    }
}

function qc_family_payload(array $options): array
{
    return db_family_taxonomy_check(
        qc_int($options, 'limit', 50, 1, 250),
        qc_get($options, 'alignment'),
        qc_get($options, 'q'),
        qc_get($options, 'pattern'),
        qc_get($options, 'pair-catalog'),
        qc_get($options, 'pair-signal'),
        qc_get($options, 'fix-action'),
        qc_get($options, 'target-family'),
        qc_get($options, 'decision-mode')
    );
}

function qc_run_family_summary(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $data = $payload['data'];
    $meta = $payload['meta'];

    if ($format === 'json') {
        qc_print_json([
            'meta' => $meta,
            'summary' => $data['summary'] ?? [],
            'issue_inventory' => $data['issue_inventory'] ?? [],
            'decision_inventory' => $data['decision_inventory'] ?? [],
        ]);
        return 0;
    }

    echo "Family summary\n";
    echo "Primary DB: ", ($meta['primary_database'] ?? '--'), PHP_EOL;
    echo "Generated: ", ($meta['generated_at_utc'] ?? '--'), " UTC", PHP_EOL, PHP_EOL;

    echo "Alignment summary\n";
    qc_print_table($data['summary'] ?? [], [
        'alignment_status' => 'Alignment',
        'row_count' => 'Rows',
        'generic_label_count' => 'Generic labels',
    ]);
    echo PHP_EOL;

    $issueCounts = [];
    foreach (($data['issue_inventory']['issue_kind_counts'] ?? []) as $issue => $count) {
        $issueCounts[] = ['issue_kind' => (string)$issue, 'row_count' => (string)$count];
    }
    echo "Issue inventory\n";
    qc_print_table($issueCounts, [
        'issue_kind' => 'Issue',
        'row_count' => 'Rows',
    ]);
    echo PHP_EOL;

    $decisionCounts = [];
    foreach (($data['decision_inventory']['decision_mode_counts'] ?? []) as $mode => $count) {
        $decisionCounts[] = ['decision_mode' => (string)$mode, 'row_count' => (string)$count];
    }
    echo "Decision lanes\n";
    qc_print_table($decisionCounts, [
        'decision_mode' => 'Decision mode',
        'row_count' => 'Rows',
    ]);
    return 0;
}

function qc_run_family_opportunities(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $rows = $payload['data']['repair_opportunities'] ?? [];

    if ($format === 'json') {
        qc_print_json($rows);
        return 0;
    }
    if ($format === 'csv') {
        qc_print_csv($rows, [
            'suggested_fix_action',
            'suggested_target_family',
            'row_count',
            'high_confidence_rows',
            'dominant_issue_kind',
            'decision_mode',
            'decision_priority',
            'suggested_fix_reason',
            'decision_why',
        ]);
        return 0;
    }

    qc_print_table($rows, [
        'suggested_fix_action' => 'Action',
        'suggested_target_family' => 'Target',
        'row_count' => 'Rows',
        'high_confidence_rows' => 'High conf',
        'dominant_issue_kind' => 'Issue',
        'decision_mode' => 'Decision',
        'decision_priority' => 'Priority',
    ]);
    return 0;
}

function qc_run_family_pairs(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $rows = $payload['data']['remediation_summary']['top_mismatch_pairs'] ?? [];

    if ($format === 'json') {
        qc_print_json($rows);
        return 0;
    }
    if ($format === 'csv') {
        qc_print_csv($rows, [
            'catalog_family_label',
            'signal_family_name',
            'row_count',
            'pair_kind',
            'resolution_action',
            'resolution_target_family',
            'resolution_reason',
        ]);
        return 0;
    }

    qc_print_table($rows, [
        'catalog_family_label' => 'Catalog family',
        'signal_family_name' => 'VT signal',
        'row_count' => 'Rows',
        'pair_kind' => 'Pair kind',
        'resolution_action' => 'Resolution',
        'resolution_target_family' => 'Target',
    ]);
    return 0;
}

function qc_rows_from_map(array $map, string $keyLabel, string $valueLabel): array
{
    $rows = [];
    foreach ($map as $key => $value) {
        $rows[] = [
            $keyLabel => (string)$key,
            $valueLabel => (string)$value,
        ];
    }
    return $rows;
}

function qc_run_family_drivers(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $data = $payload['data'] ?? [];

    $issueInventory = $data['issue_inventory'] ?? [];
    $fixInventory = $data['fix_action_inventory'] ?? [];
    $decisionInventory = $data['decision_inventory'] ?? [];

    $driverPayload = [
        'issue_kind_counts' => $issueInventory['issue_kind_counts'] ?? [],
        'top_catalog_labels' => $issueInventory['top_catalog_labels'] ?? [],
        'top_signal_labels' => $issueInventory['top_signal_labels'] ?? [],
        'action_counts' => $fixInventory['action_counts'] ?? [],
        'top_target_families' => $fixInventory['top_target_families'] ?? [],
        'decision_mode_counts' => $decisionInventory['decision_mode_counts'] ?? [],
        'decision_priority_counts' => $decisionInventory['decision_priority_counts'] ?? [],
    ];

    if ($format === 'json') {
        qc_print_json($driverPayload);
        return 0;
    }

    echo "Issue kinds\n";
    qc_print_table(
        qc_rows_from_map($driverPayload['issue_kind_counts'], 'issue_kind', 'row_count'),
        ['issue_kind' => 'Issue', 'row_count' => 'Rows']
    );
    echo PHP_EOL;

    echo "Top catalog labels\n";
    qc_print_table(
        qc_rows_from_map($driverPayload['top_catalog_labels'], 'catalog_label', 'row_count'),
        ['catalog_label' => 'Catalog label', 'row_count' => 'Rows']
    );
    echo PHP_EOL;

    echo "Top VT signal labels\n";
    qc_print_table(
        qc_rows_from_map($driverPayload['top_signal_labels'], 'signal_label', 'row_count'),
        ['signal_label' => 'VT signal', 'row_count' => 'Rows']
    );
    echo PHP_EOL;

    echo "Fix actions\n";
    qc_print_table(
        qc_rows_from_map($driverPayload['action_counts'], 'fix_action', 'row_count'),
        ['fix_action' => 'Fix action', 'row_count' => 'Rows']
    );
    echo PHP_EOL;

    echo "Decision lanes\n";
    qc_print_table(
        qc_rows_from_map($driverPayload['decision_mode_counts'], 'decision_mode', 'row_count'),
        ['decision_mode' => 'Decision mode', 'row_count' => 'Rows']
    );

    return 0;
}

function qc_run_family_governance(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $inventory = $payload['data']['governance_inventory'] ?? [];
    $rows = $inventory['target_groups'] ?? [];

    if ($format === 'json') {
        qc_print_json($inventory);
        return 0;
    }
    if ($format === 'csv') {
        $csvRows = array_map(static function (array $row): array {
            $row['action_labels'] = implode(', ', array_values($row['action_labels'] ?? []));
            $row['signal_label_examples'] = implode(', ', array_values($row['signal_label_examples'] ?? []));
            $row['catalog_label_examples'] = implode(', ', array_values($row['catalog_label_examples'] ?? []));
            $row['sample_id_preview'] = implode(', ', array_map('strval', $row['sample_id_preview'] ?? []));
            return $row;
        }, $rows);
        qc_print_csv($csvRows, [
            'target_family',
            'row_count',
            'high_confidence_rows',
            'dominant_issue_kind',
            'dominant_action',
            'decision_mode',
            'decision_priority',
            'action_labels',
            'signal_label_examples',
            'catalog_label_examples',
            'sample_id_preview',
        ]);
        return 0;
    }

    echo "Governance inventory\n";
    echo 'Total governance rows: ', (string)($inventory['total_rows'] ?? 0), PHP_EOL;
    echo 'Targeted rows: ', (string)($inventory['targeted_rows'] ?? 0), PHP_EOL;
    echo 'Untargeted rows: ', (string)($inventory['untargeted_rows'] ?? 0), PHP_EOL, PHP_EOL;

    $tableRows = array_map(static function (array $row): array {
        $row['action_labels'] = implode(', ', array_values($row['action_labels'] ?? []));
        $row['signal_label_examples'] = implode(', ', array_values($row['signal_label_examples'] ?? []));
        return $row;
    }, $rows);

    qc_print_table($tableRows, [
        'target_family' => 'Target hint',
        'row_count' => 'Rows',
        'high_confidence_rows' => 'High conf',
        'dominant_issue_kind' => 'Issue',
        'dominant_action' => 'Action',
        'signal_label_examples' => 'Signals',
    ]);
    echo PHP_EOL;

    echo "Untargeted conflict drivers\n";
    qc_print_table($inventory['untargeted_pair_groups'] ?? [], [
        'catalog_family' => 'Catalog',
        'signal_family' => 'VT signal',
        'row_count' => 'Rows',
        'high_confidence_rows' => 'High conf',
        'dominant_issue_kind' => 'Issue',
        'dominant_action' => 'Action',
    ]);
    return 0;
}

function qc_run_family_rows(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = qc_family_payload($options);
    $rows = $payload['data']['rows'] ?? [];

    if ($format === 'json') {
        qc_print_json($rows);
        return 0;
    }
    if ($format === 'csv') {
        qc_print_csv($rows, [
            'sample_id',
            'sha256',
            'sample_label',
            'android_package_name',
            'family_label',
            'popular_threat_name',
            'confidence_bucket',
            'confidence_score',
            'alignment_status',
            'issue_kind',
            'suggested_fix_action',
            'suggested_target_family',
            'decision_mode',
            'decision_priority',
            'review_lane',
        ]);
        return 0;
    }

    qc_print_table($rows, [
        'sample_id' => 'Sample',
        'family_label' => 'Catalog',
        'popular_threat_name' => 'VT signal',
        'confidence_bucket' => 'Confidence',
        'issue_kind' => 'Issue',
        'suggested_fix_action' => 'Fix',
        'decision_mode' => 'Decision',
        'review_lane' => 'Lane',
    ]);
    return 0;
}

function qc_run_family_export(array $options): int
{
    if (!isset($options['format']) || trim((string)$options['format']) === '') {
        $options['format'] = 'csv';
    }
    return qc_run_family_rows($options);
}

function qc_run_family_apply_plan(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = db_family_taxonomy_apply_plan(
        qc_int($options, 'limit', 50, 1, 250),
        qc_get($options, 'alignment'),
        qc_get($options, 'q'),
        qc_get($options, 'pattern'),
        qc_get($options, 'pair-catalog'),
        qc_get($options, 'pair-signal'),
        qc_get($options, 'fix-action'),
        qc_get($options, 'target-family'),
        qc_get($options, 'decision-mode')
    );

    $data = $payload['data'] ?? [];
    $rows = $data['plan_rows'] ?? [];

    if ($format === 'json') {
        qc_print_json([
            'meta' => $payload['meta'] ?? [],
            'summary' => $data['summary'] ?? [],
            'plan_rows' => $rows,
        ]);
        return 0;
    }

    if ($format === 'sql') {
        if ($rows === []) {
            echo "-- No dry-run plan rows found.\n";
            return 0;
        }
        foreach ($rows as $index => $row) {
            if ($index > 0) {
                echo PHP_EOL . PHP_EOL;
            }
            echo (string)($row['sql_preview'] ?? '-- No SQL preview available.');
        }
        echo PHP_EOL;
        return 0;
    }

    echo "Family apply plan (dry run)\n";
    $summary = $data['summary'] ?? [];
    echo 'Candidate rows: ', (string)($summary['candidate_rows'] ?? 0), PHP_EOL;
    echo 'Plan groups: ', (string)($summary['plan_group_count'] ?? 0), PHP_EOL;
    echo 'Excluded rows: ', (string)($summary['excluded_rows'] ?? 0), PHP_EOL, PHP_EOL;

    $tableRows = array_map(static function (array $row): array {
        $row['decision_modes'] = implode(', ', array_values($row['decision_modes'] ?? []));
        $row['confidence_buckets'] = implode(', ', array_values($row['confidence_buckets'] ?? []));
        return $row;
    }, $rows);

    qc_print_table($tableRows, [
        'plan_action' => 'Action',
        'target_family' => 'Target',
        'row_count' => 'Rows',
        'sample_id_count' => 'Samples',
        'decision_modes' => 'Decision modes',
        'confidence_buckets' => 'Confidence',
    ]);
    return 0;
}

function qc_run_stack_audit(array $options): int
{
    $format = strtolower(qc_get($options, 'format', 'table'));
    $payload = db_stack_audit();

    if ($format === 'json') {
        qc_print_json($payload);
        return 0;
    }

    echo "Stack runtime\n";
    qc_print_table([[
        'php_runtime' => (string)($payload['runtime']['php_runtime'] ?? '--'),
        'app_package' => trim((string)($payload['runtime']['app_package'] ?? '--') . ' ' . (string)($payload['runtime']['app_package_version'] ?? '')),
        'project_root' => (string)($payload['project_root'] ?? '--'),
    ]], [
        'php_runtime' => 'PHP',
        'app_package' => 'Package',
        'project_root' => 'Project root',
    ]);
    echo PHP_EOL;

    echo "Platform gaps\n";
    qc_print_table($payload['gap_inventory'] ?? [], [
        'severity' => 'Severity',
        'key' => 'Key',
        'title' => 'Title',
    ]);
    echo PHP_EOL;

    echo "CLI entrypoints\n";
    qc_print_table($payload['cli_entrypoints'] ?? [], [
        'label' => 'Label',
        'command' => 'Command',
        'why' => 'Why',
    ]);
    return 0;
}

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? 'help';
$options = qc_parse_options(array_slice($argv, 2));

try {
    switch ($command) {
        case 'help':
        case '--help':
        case '-h':
            qc_usage();
            exit(0);
        case 'family:summary':
            exit(qc_run_family_summary($options));
        case 'family:export':
            exit(qc_run_family_export($options));
        case 'family:apply-plan':
            exit(qc_run_family_apply_plan($options));
        case 'family:pairs':
            exit(qc_run_family_pairs($options));
        case 'family:drivers':
            exit(qc_run_family_drivers($options));
        case 'family:governance':
            exit(qc_run_family_governance($options));
        case 'family:opportunities':
            exit(qc_run_family_opportunities($options));
        case 'family:rows':
            exit(qc_run_family_rows($options));
        case 'stack:audit':
            exit(qc_run_stack_audit($options));
        default:
            fwrite(STDERR, "Unknown command: {$command}\n\n");
            qc_usage();
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
