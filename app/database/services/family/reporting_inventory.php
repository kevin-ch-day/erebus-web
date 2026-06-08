<?php
declare(strict_types=1);

function db_family_taxonomy_issue_inventory(array $rows): array
{
    $issueCounts = [];
    $catalogCounts = [];
    $signalCounts = [];

    foreach ($rows as $row) {
        $issueKind = trim((string)($row['issue_kind'] ?? ''));
        if ($issueKind === '') {
            $issue = db_family_taxonomy_row_issue($row);
            $issueKind = (string)($issue['issue_kind'] ?? 'unknown');
        }
        $issueCounts[$issueKind] = ($issueCounts[$issueKind] ?? 0) + 1;

        $catalogLabel = trim((string)($row['family_label'] ?? ''));
        $catalogKey = $catalogLabel !== '' ? $catalogLabel : '(empty)';
        $catalogCounts[$catalogKey] = ($catalogCounts[$catalogKey] ?? 0) + 1;

        $signalLabel = trim((string)($row['popular_threat_name'] ?? ''));
        $signalKey = $signalLabel !== '' ? $signalLabel : '(empty)';
        $signalCounts[$signalKey] = ($signalCounts[$signalKey] ?? 0) + 1;
    }

    arsort($issueCounts);
    arsort($catalogCounts);
    arsort($signalCounts);

    return [
        'total_rows' => count($rows),
        'issue_kind_counts' => $issueCounts,
        'top_catalog_labels' => array_slice($catalogCounts, 0, 10, true),
        'top_signal_labels' => array_slice($signalCounts, 0, 10, true),
    ];
}

function db_family_taxonomy_decision_model(array $row): array
{
    $action = strtolower(trim((string)($row['suggested_fix_action'] ?? 'monitor')));
    $confidence = strtolower(trim((string)($row['suggested_fix_confidence'] ?? 'low')));
    $issueKind = strtolower(trim((string)($row['issue_kind'] ?? 'unknown')));

    if (in_array($action, ['fill_catalog_from_signal', 'adopt_signal_family'], true)) {
        return [
            'decision_mode' => 'repair_now_candidate',
            'decision_priority' => $confidence === 'high' ? 'high' : 'medium',
            'decision_why' => 'This row has a concrete canonical target family and is a strong candidate for controlled catalog repair.',
        ];
    }

    if (in_array($action, ['canonicalize_catalog_alias', 'canonicalize_to_signal_family'], true)) {
        return [
            'decision_mode' => 'repair_after_alias_review',
            'decision_priority' => $confidence === 'high' ? 'medium' : 'low',
            'decision_why' => 'This row looks batch-repairable, but it should pass a short canonical-label review before changing catalog truth.',
        ];
    }

    if ($action === 'replace_generic_alignment') {
        return [
            'decision_mode' => 'monitor_only',
            'decision_priority' => 'low',
            'decision_why' => 'Catalog and VT only agree on a generic family token, but this is not direct repair debt; keep it visible until a stronger governed family target exists.',
        ];
    }

    if ($action === 'keep_catalog_use_alias_map') {
        return [
            'decision_mode' => 'keep_as_is',
            'decision_priority' => 'low',
            'decision_why' => 'The current catalog family already matches the governed alias target, so no catalog rewrite is needed.',
        ];
    }

    if ($action === 'hold_generic_signal') {
        return [
            'decision_mode' => 'hold_generic_signal',
            'decision_priority' => 'medium',
            'decision_why' => 'The VT family token is too generic or overloaded to promote safely, so hold the catalog family and avoid auto-repair.',
        ];
    }

    if ($action === 'hold_signal_overlap') {
        return [
            'decision_mode' => 'hold_signal_overlap',
            'decision_priority' => 'medium',
            'decision_why' => 'The full VT label reflects overlapping family and detector-style tokens, so keep this row visible but out of the hard naming-conflict queue.',
        ];
    }

    if (in_array($action, ['manual_family_adjudication', 'needs_family_governance', 'manual_alias_review'], true)) {
        return [
            'decision_mode' => 'ask_why_first',
            'decision_priority' => in_array($issueKind, ['semantic_conflict', 'placeholder_catalog', 'catalog_missing'], true) ? 'high' : 'medium',
            'decision_why' => 'This row needs analyst or governance review because the evidence is incomplete, conflicted, or lacks a stable canonical target.',
        ];
    }

    return [
        'decision_mode' => 'monitor_only',
        'decision_priority' => 'low',
        'decision_why' => 'No direct repair path is recommended yet; keep the row visible but do not mutate catalog truth.',
    ];
}

function db_family_taxonomy_decision_inventory(array $rows): array
{
    $decisionCounts = [];
    $priorityCounts = [];

    foreach ($rows as $row) {
        $decision = trim((string)($row['decision_mode'] ?? 'monitor_only'));
        $priority = trim((string)($row['decision_priority'] ?? 'low'));
        $decisionCounts[$decision] = ($decisionCounts[$decision] ?? 0) + 1;
        $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
    }

    arsort($decisionCounts);
    arsort($priorityCounts);

    return [
        'total_rows' => count($rows),
        'decision_mode_counts' => $decisionCounts,
        'decision_priority_counts' => $priorityCounts,
    ];
}

function db_family_taxonomy_decision_issue_inventory(array $rows, string $decisionMode = 'ask_why_first'): array
{
    $decisionMode = strtolower(trim($decisionMode));
    $issueCounts = [];
    $platformCounts = [];
    $issuePlatformCounts = [];

    foreach ($rows as $row) {
        $rowDecisionMode = strtolower(trim((string)($row['decision_mode'] ?? '')));
        if ($rowDecisionMode !== $decisionMode) {
            continue;
        }

        $issue = trim((string)($row['issue_kind'] ?? 'unknown'));
        if ($issue === '') {
            $issue = 'unknown';
        }
        $platform = strtolower(trim((string)($row['platform'] ?? 'unknown')));
        if ($platform === '') {
            $platform = 'unknown';
        }

        $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
        $platformCounts[$platform] = ($platformCounts[$platform] ?? 0) + 1;
        if (!isset($issuePlatformCounts[$issue])) {
            $issuePlatformCounts[$issue] = [];
        }
        $issuePlatformCounts[$issue][$platform] = ($issuePlatformCounts[$issue][$platform] ?? 0) + 1;
    }

    arsort($issueCounts);
    arsort($platformCounts);
    foreach ($issuePlatformCounts as &$counts) {
        arsort($counts);
    }
    unset($counts);

    return [
        'decision_mode' => $decisionMode,
        'total_rows' => array_sum($issueCounts),
        'issue_kind_counts' => $issueCounts,
        'platform_counts' => $platformCounts,
        'issue_platform_counts' => $issuePlatformCounts,
    ];
}

function db_family_taxonomy_apply_plan_supported_actions(): array
{
    return [
        'adopt_signal_family',
        'fill_catalog_from_signal',
        'canonicalize_catalog_alias',
        'canonicalize_to_signal_family',
    ];
}

function db_family_taxonomy_sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function db_family_taxonomy_row_already_at_target_family(array $row): bool
{
    $catalogLabel = trim((string)($row['family_label'] ?? ''));
    $targetFamily = trim((string)($row['suggested_target_family'] ?? ''));

    return $catalogLabel !== '' && $targetFamily !== '' && $catalogLabel === $targetFamily;
}

function db_family_taxonomy_apply_plan_from_rows(array $rows): array
{
    $supportedActions = db_family_taxonomy_apply_plan_supported_actions();
    $groups = [];
    $excluded = [
        'unsupported_action' => 0,
        'missing_target_family' => 0,
        'already_at_target_family' => 0,
    ];

    foreach ($rows as $row) {
        $action = strtolower(trim((string)($row['suggested_fix_action'] ?? '')));
        $targetFamily = trim((string)($row['suggested_target_family'] ?? ''));

        if ($action === '' || !in_array($action, $supportedActions, true)) {
            $excluded['unsupported_action']++;
            continue;
        }

        if ($targetFamily === '') {
            $excluded['missing_target_family']++;
            continue;
        }

        if (db_family_taxonomy_row_already_at_target_family($row)) {
            $excluded['already_at_target_family']++;
            continue;
        }

        $groupKey = $action . '|' . strtolower($targetFamily);
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'plan_action' => $action,
                'target_family' => $targetFamily,
                'row_count' => 0,
                'sample_ids' => [],
                'current_catalog_labels' => [],
                'vt_signal_labels' => [],
                'decision_modes' => [],
                'confidence_buckets' => [],
            ];
        }

        $sampleId = (int)($row['sample_id'] ?? 0);
        if ($sampleId > 0) {
            $groups[$groupKey]['sample_ids'][] = $sampleId;
        }

        $groups[$groupKey]['row_count']++;

        $catalogLabel = trim((string)($row['family_label'] ?? ''));
        $signalLabel = trim((string)($row['popular_threat_name'] ?? ''));
        $decisionMode = trim((string)($row['decision_mode'] ?? ''));
        $confidenceBucket = trim((string)($row['confidence_bucket'] ?? ''));

        if ($catalogLabel !== '') {
            $groups[$groupKey]['current_catalog_labels'][$catalogLabel] = true;
        }
        if ($signalLabel !== '') {
            $groups[$groupKey]['vt_signal_labels'][$signalLabel] = true;
        }
        if ($decisionMode !== '') {
            $groups[$groupKey]['decision_modes'][$decisionMode] = true;
        }
        if ($confidenceBucket !== '') {
            $groups[$groupKey]['confidence_buckets'][$confidenceBucket] = true;
        }
    }

    $tableName = db_catalog_table('malware_sample_catalog');
    $planRows = [];
    foreach ($groups as $group) {
        sort($group['sample_ids']);
        $sampleIds = array_values(array_unique($group['sample_ids']));
        $catalogLabels = array_keys($group['current_catalog_labels']);
        $signalLabels = array_keys($group['vt_signal_labels']);
        $decisionModes = array_keys($group['decision_modes']);
        $confidenceBuckets = array_keys($group['confidence_buckets']);

        $sqlPreview = '-- DRY RUN ONLY' . PHP_EOL
            . '-- Action: ' . $group['plan_action'] . PHP_EOL
            . '-- Target family: ' . $group['target_family'] . PHP_EOL
            . 'UPDATE ' . $tableName
            . ' SET family_label = ' . db_family_taxonomy_sql_quote($group['target_family'])
            . ' WHERE sample_id IN (' . implode(', ', $sampleIds) . ');';

        $planRows[] = [
            'plan_action' => $group['plan_action'],
            'target_family' => $group['target_family'],
            'row_count' => $group['row_count'],
            'sample_ids' => $sampleIds,
            'sample_id_count' => count($sampleIds),
            'current_catalog_labels' => $catalogLabels,
            'vt_signal_labels' => $signalLabels,
            'decision_modes' => $decisionModes,
            'confidence_buckets' => $confidenceBuckets,
            'sql_preview' => $sqlPreview,
        ];
    }

    usort($planRows, static function (array $a, array $b): int {
        $rowDiff = (int)($b['row_count'] ?? 0) <=> (int)($a['row_count'] ?? 0);
        if ($rowDiff !== 0) {
            return $rowDiff;
        }
        return strcmp((string)($a['target_family'] ?? ''), (string)($b['target_family'] ?? ''));
    });

    return [
        'dry_run' => true,
        'supported_actions' => $supportedActions,
        'plan_rows' => $planRows,
        'summary' => [
            'candidate_rows' => array_sum(array_map(static fn(array $row): int => (int)($row['row_count'] ?? 0), $planRows)),
            'plan_group_count' => count($planRows),
            'excluded_rows' => array_sum($excluded),
            'excluded_reasons' => $excluded,
        ],
    ];
}

function db_family_taxonomy_fix_action_inventory(array $rows): array
{
    $actionCounts = [];
    $targetCounts = [];

    foreach ($rows as $row) {
        $action = trim((string)($row['suggested_fix_action'] ?? 'monitor'));
        $target = trim((string)($row['suggested_target_family'] ?? ''));
        $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
        if ($target !== '') {
            $targetCounts[$target] = ($targetCounts[$target] ?? 0) + 1;
        }
    }

    arsort($actionCounts);
    arsort($targetCounts);

    return [
        'total_rows' => count($rows),
        'action_counts' => $actionCounts,
        'top_target_families' => array_slice($targetCounts, 0, 10, true),
    ];
}

function db_family_taxonomy_platform_inventory(array $rows): array
{
    $platformCounts = [];
    $platformAlignmentCounts = [];
    $platformDecisionCounts = [];
    $platformHeldMismatchCounts = [];
    $platformRepairNowCounts = [];

    foreach ($rows as $row) {
        $platform = strtolower(trim((string)($row['platform'] ?? '')));
        if ($platform === '') {
            $platform = 'unknown';
        }

        $alignment = strtolower(trim((string)($row['alignment_status'] ?? 'unknown')));
        $decisionMode = strtolower(trim((string)($row['decision_mode'] ?? 'monitor_only')));
        $fixAction = strtolower(trim((string)($row['suggested_fix_action'] ?? 'monitor')));

        $platformCounts[$platform] = ($platformCounts[$platform] ?? 0) + 1;
        $platformAlignmentCounts[$platform][$alignment] = ($platformAlignmentCounts[$platform][$alignment] ?? 0) + 1;
        $platformDecisionCounts[$platform][$decisionMode] = ($platformDecisionCounts[$platform][$decisionMode] ?? 0) + 1;

        if ($alignment === 'mismatch' && in_array($fixAction, ['hold_generic_signal', 'hold_signal_overlap'], true)) {
            $platformHeldMismatchCounts[$platform] = ($platformHeldMismatchCounts[$platform] ?? 0) + 1;
        }

        if ($decisionMode === 'repair_now_candidate') {
            $platformRepairNowCounts[$platform] = ($platformRepairNowCounts[$platform] ?? 0) + 1;
        }
    }

    arsort($platformCounts);
    foreach ($platformAlignmentCounts as &$counts) {
        arsort($counts);
    }
    unset($counts);
    foreach ($platformDecisionCounts as &$counts) {
        arsort($counts);
    }
    unset($counts);
    arsort($platformHeldMismatchCounts);
    arsort($platformRepairNowCounts);

    return [
        'total_rows' => count($rows),
        'platform_counts' => $platformCounts,
        'platform_alignment_counts' => $platformAlignmentCounts,
        'platform_decision_counts' => $platformDecisionCounts,
        'platform_held_mismatch_counts' => $platformHeldMismatchCounts,
        'platform_repair_now_counts' => $platformRepairNowCounts,
    ];
}

function db_family_taxonomy_repair_opportunities(array $rows): array
{
    $groups = [];
    $priorityMap = [
        'fill_catalog_from_signal' => 100,
        'adopt_signal_family' => 95,
        'canonicalize_to_signal_family' => 90,
        'canonicalize_catalog_alias' => 85,
        'replace_generic_alignment' => 80,
        'manual_alias_review' => 60,
        'manual_family_adjudication' => 50,
        'needs_family_governance' => 45,
        'hold_signal_overlap' => 35,
        'hold_generic_signal' => 30,
        'keep_catalog_use_alias_map' => 20,
        'monitor' => 10,
    ];

    foreach ($rows as $row) {
        $action = trim((string)($row['suggested_fix_action'] ?? 'monitor'));
        $target = trim((string)($row['suggested_target_family'] ?? ''));
        $issueKind = trim((string)($row['issue_kind'] ?? 'unknown'));
        $confidence = strtolower(trim((string)($row['confidence_bucket'] ?? '')));
        $highConfidence = in_array($confidence, ['high', 'strong'], true);

        if (in_array(strtolower($action), db_family_taxonomy_apply_plan_supported_actions(), true)
            && db_family_taxonomy_row_already_at_target_family($row)) {
            continue;
        }

        $key = $action . '|' . strtolower($target);

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'suggested_fix_action' => $action,
                'suggested_target_family' => $target,
                'row_count' => 0,
                'high_confidence_rows' => 0,
                'issue_kind_counts' => [],
                'catalog_label_examples' => [],
                'signal_label_examples' => [],
                'sample_id_preview' => [],
                'suggested_fix_reason' => trim((string)($row['suggested_fix_reason'] ?? '')),
                'decision_mode' => trim((string)($row['decision_mode'] ?? 'monitor_only')),
                'decision_priority' => trim((string)($row['decision_priority'] ?? 'low')),
                'decision_why' => trim((string)($row['decision_why'] ?? '')),
                'priority_score' => $priorityMap[$action] ?? 0,
            ];
        }

        $groups[$key]['row_count']++;
        if ($highConfidence) {
            $groups[$key]['high_confidence_rows']++;
        }
        $groups[$key]['issue_kind_counts'][$issueKind] = ($groups[$key]['issue_kind_counts'][$issueKind] ?? 0) + 1;

        $catalogLabel = trim((string)($row['family_label'] ?? ''));
        $signalLabel = trim((string)($row['popular_threat_name'] ?? ''));
        if ($catalogLabel !== '') {
            $groups[$key]['catalog_label_examples'][$catalogLabel] = ($groups[$key]['catalog_label_examples'][$catalogLabel] ?? 0) + 1;
        }
        if ($signalLabel !== '') {
            $groups[$key]['signal_label_examples'][$signalLabel] = ($groups[$key]['signal_label_examples'][$signalLabel] ?? 0) + 1;
        }
        if (count($groups[$key]['sample_id_preview']) < 5 && isset($row['sample_id'])) {
            $groups[$key]['sample_id_preview'][] = (int)$row['sample_id'];
        }
    }

    foreach ($groups as &$group) {
        arsort($group['issue_kind_counts']);
        arsort($group['catalog_label_examples']);
        arsort($group['signal_label_examples']);
        $group['dominant_issue_kind'] = (string)(array_key_first($group['issue_kind_counts']) ?? 'unknown');
        $group['catalog_label_examples'] = array_keys(array_slice($group['catalog_label_examples'], 0, 3, true));
        $group['signal_label_examples'] = array_keys(array_slice($group['signal_label_examples'], 0, 3, true));
    }
    unset($group);

    $groups = array_values($groups);
    usort($groups, static function (array $a, array $b): int {
        $priorityCmp = (($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));
        if ($priorityCmp !== 0) {
            return $priorityCmp;
        }
        $confidenceCmp = (($b['high_confidence_rows'] ?? 0) <=> ($a['high_confidence_rows'] ?? 0));
        if ($confidenceCmp !== 0) {
            return $confidenceCmp;
        }
        return (($b['row_count'] ?? 0) <=> ($a['row_count'] ?? 0));
    });

    return array_slice($groups, 0, 12);
}

function db_family_taxonomy_governance_inventory(array $rows): array
{
    $governanceActions = [
        'needs_family_governance',
        'manual_family_adjudication',
        'manual_alias_review',
    ];

    $groups = [];
    $untargetedPairs = [];
    $untargetedSignals = [];
    $untargetedCatalogs = [];
    $totalRows = 0;
    $targetedRows = 0;
    $untargetedRows = 0;

    foreach ($rows as $row) {
        $action = trim((string)($row['suggested_fix_action'] ?? ''));
        if (!in_array($action, $governanceActions, true)) {
            continue;
        }

        $totalRows++;
        $target = trim((string)($row['suggested_target_family'] ?? ''));
        if ($target !== '') {
            $targetedRows++;
        } else {
            $untargetedRows++;
            $pairCatalog = trim((string)($row['family_label'] ?? ''));
            $pairSignal = trim((string)($row['popular_threat_name'] ?? ''));
            $pairKey = strtolower($pairCatalog) . '|' . strtolower($pairSignal);
            if (!isset($untargetedPairs[$pairKey])) {
                $untargetedPairs[$pairKey] = [
                    'catalog_family' => $pairCatalog,
                    'signal_family' => $pairSignal,
                    'row_count' => 0,
                    'high_confidence_rows' => 0,
                    'issue_kind_counts' => [],
                    'action_counts' => [],
                    'sample_id_preview' => [],
                    'decision_mode' => trim((string)($row['decision_mode'] ?? 'ask_why_first')),
                    'decision_priority' => trim((string)($row['decision_priority'] ?? 'high')),
                ];
            }
            $untargetedPairs[$pairKey]['row_count']++;
            if ($pairSignal !== '') {
                $untargetedSignals[$pairSignal] = ($untargetedSignals[$pairSignal] ?? 0) + 1;
            }
            if ($pairCatalog !== '') {
                $untargetedCatalogs[$pairCatalog] = ($untargetedCatalogs[$pairCatalog] ?? 0) + 1;
            }
        }

        $groupKey = strtolower($target !== '' ? $target : '(no target hint)');
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'target_family' => $target,
                'row_count' => 0,
                'high_confidence_rows' => 0,
                'action_counts' => [],
                'issue_kind_counts' => [],
                'signal_label_examples' => [],
                'catalog_label_examples' => [],
                'sample_id_preview' => [],
                'decision_mode' => trim((string)($row['decision_mode'] ?? 'ask_why_first')),
                'decision_priority' => trim((string)($row['decision_priority'] ?? 'high')),
            ];
        }

        $groups[$groupKey]['row_count']++;
        $confidence = strtolower(trim((string)($row['confidence_bucket'] ?? '')));
        if (in_array($confidence, ['high', 'strong'], true)) {
            $groups[$groupKey]['high_confidence_rows']++;
        }

        $issueKind = trim((string)($row['issue_kind'] ?? 'unknown'));
        $groups[$groupKey]['action_counts'][$action] = ($groups[$groupKey]['action_counts'][$action] ?? 0) + 1;
        $groups[$groupKey]['issue_kind_counts'][$issueKind] = ($groups[$groupKey]['issue_kind_counts'][$issueKind] ?? 0) + 1;
        if ($target === '') {
            $untargetedPairs[$pairKey]['action_counts'][$action] = ($untargetedPairs[$pairKey]['action_counts'][$action] ?? 0) + 1;
            $untargetedPairs[$pairKey]['issue_kind_counts'][$issueKind] = ($untargetedPairs[$pairKey]['issue_kind_counts'][$issueKind] ?? 0) + 1;
        }

        $signalLabel = trim((string)($row['popular_threat_name'] ?? ''));
        if ($signalLabel !== '') {
            $groups[$groupKey]['signal_label_examples'][$signalLabel] = ($groups[$groupKey]['signal_label_examples'][$signalLabel] ?? 0) + 1;
        }

        $catalogLabel = trim((string)($row['family_label'] ?? ''));
        if ($catalogLabel !== '') {
            $groups[$groupKey]['catalog_label_examples'][$catalogLabel] = ($groups[$groupKey]['catalog_label_examples'][$catalogLabel] ?? 0) + 1;
        }

        if (count($groups[$groupKey]['sample_id_preview']) < 5 && isset($row['sample_id'])) {
            $groups[$groupKey]['sample_id_preview'][] = (int)$row['sample_id'];
        }
        if ($target === '' && count($untargetedPairs[$pairKey]['sample_id_preview']) < 5 && isset($row['sample_id'])) {
            $untargetedPairs[$pairKey]['sample_id_preview'][] = (int)$row['sample_id'];
        }
    }

    foreach ($groups as &$group) {
        arsort($group['action_counts']);
        arsort($group['issue_kind_counts']);
        arsort($group['signal_label_examples']);
        arsort($group['catalog_label_examples']);
        $group['dominant_issue_kind'] = (string)(array_key_first($group['issue_kind_counts']) ?? 'unknown');
        $group['dominant_action'] = (string)(array_key_first($group['action_counts']) ?? 'needs_family_governance');
        $group['action_labels'] = array_keys($group['action_counts']);
        $group['signal_label_examples'] = array_keys(array_slice($group['signal_label_examples'], 0, 3, true));
        $group['catalog_label_examples'] = array_keys(array_slice($group['catalog_label_examples'], 0, 3, true));
    }
    unset($group);

    foreach ($untargetedPairs as &$pair) {
        arsort($pair['action_counts']);
        arsort($pair['issue_kind_counts']);
        $pair['dominant_issue_kind'] = (string)(array_key_first($pair['issue_kind_counts']) ?? 'unknown');
        $pair['dominant_action'] = (string)(array_key_first($pair['action_counts']) ?? 'needs_family_governance');
    }
    unset($pair);

    $groupRows = array_values($groups);
    usort($groupRows, static function (array $a, array $b): int {
        $highCmp = (($b['high_confidence_rows'] ?? 0) <=> ($a['high_confidence_rows'] ?? 0));
        if ($highCmp !== 0) {
            return $highCmp;
        }
        $rowCmp = (($b['row_count'] ?? 0) <=> ($a['row_count'] ?? 0));
        if ($rowCmp !== 0) {
            return $rowCmp;
        }
        return strcmp((string)($a['target_family'] ?? ''), (string)($b['target_family'] ?? ''));
    });

    $untargetedPairRows = array_values($untargetedPairs);
    usort($untargetedPairRows, static function (array $a, array $b): int {
        $highCmp = (($b['high_confidence_rows'] ?? 0) <=> ($a['high_confidence_rows'] ?? 0));
        if ($highCmp !== 0) {
            return $highCmp;
        }
        $rowCmp = (($b['row_count'] ?? 0) <=> ($a['row_count'] ?? 0));
        if ($rowCmp !== 0) {
            return $rowCmp;
        }
        $catalogCmp = strcmp((string)($a['catalog_family'] ?? ''), (string)($b['catalog_family'] ?? ''));
        if ($catalogCmp !== 0) {
            return $catalogCmp;
        }
        return strcmp((string)($a['signal_family'] ?? ''), (string)($b['signal_family'] ?? ''));
    });

    arsort($untargetedSignals);
    arsort($untargetedCatalogs);

    return [
        'total_rows' => $totalRows,
        'targeted_rows' => $targetedRows,
        'untargeted_rows' => $untargetedRows,
        'target_groups' => array_slice($groupRows, 0, 12),
        'untargeted_pair_groups' => array_slice($untargetedPairRows, 0, 12),
        'untargeted_top_signal_labels' => array_slice($untargetedSignals, 0, 8, true),
        'untargeted_top_catalog_labels' => array_slice($untargetedCatalogs, 0, 8, true),
    ];
}

function db_family_taxonomy_summary_from_rows(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $alignment = trim((string)($row['alignment_status'] ?? ''));
        if ($alignment === '') {
            $alignment = 'unknown';
        }
        if (!isset($groups[$alignment])) {
            $groups[$alignment] = [
                'alignment_status' => $alignment,
                'generic_label_count' => 0,
                'row_count' => 0,
            ];
        }
        $groups[$alignment]['row_count']++;
        if (((int)($row['generic_label_flag'] ?? 0)) === 1) {
            $groups[$alignment]['generic_label_count']++;
        }
    }

    $summary = array_values($groups);
    usort($summary, static function (array $a, array $b): int {
        $order = ['mismatch' => 0, 'signal_only' => 1, 'catalog_only' => 2, 'generic_label' => 3, 'unlabeled' => 4, 'aligned' => 5];
        $aOrder = $order[$a['alignment_status']] ?? 99;
        $bOrder = $order[$b['alignment_status']] ?? 99;
        if ($aOrder !== $bOrder) {
            return $aOrder <=> $bOrder;
        }
        return ($b['row_count'] ?? 0) <=> ($a['row_count'] ?? 0);
    });

    return $summary;
}

function db_family_taxonomy_scorecard_from_rows(array $rows): array
{
    $total = count($rows);
    $aligned = 0;
    $mismatch = 0;
    $signalOnly = 0;
    $catalogOnly = 0;
    $unlabeled = 0;
    $generic = 0;
    $highConflict = 0;
    $highMismatch = 0;

    foreach ($rows as $row) {
        $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
        $confidence = strtolower(trim((string)($row['confidence_bucket'] ?? '')));
        $isHighConfidence = in_array($confidence, ['high', 'strong'], true);
        $isGeneric = (int)($row['generic_label_flag'] ?? 0) === 1;

        if ($isGeneric) {
            $generic++;
        }

        if ($alignment === 'aligned') {
            $aligned++;
            continue;
        }
        if ($alignment === 'mismatch') {
            $mismatch++;
            if ($isHighConfidence) {
                $highMismatch++;
            }
        } elseif ($alignment === 'signal_only') {
            $signalOnly++;
        } elseif ($alignment === 'catalog_only') {
            $catalogOnly++;
        } elseif ($alignment === 'unlabeled') {
            $unlabeled++;
        }

        if ($alignment !== 'aligned' && $isHighConfidence) {
            $highConflict++;
        }
    }

    $pct = static function (int $count, int $base): ?float {
        if ($base <= 0) {
            return null;
        }
        return round(($count / $base) * 100, 2);
    };

    $riskClass = 'ok';
    if ($mismatch >= 1000 || $highConflict >= 500) {
        $riskClass = 'critical';
    } elseif ($mismatch >= 250 || $signalOnly >= 250 || $catalogOnly >= 250) {
        $riskClass = 'warn';
    }

    return [
        'available' => true,
        'total_rows' => $total,
        'aligned_rows' => $aligned,
        'mismatch_rows' => $mismatch,
        'signal_only_rows' => $signalOnly,
        'catalog_only_rows' => $catalogOnly,
        'unlabeled_rows' => $unlabeled,
        'generic_label_rows' => $generic,
        'high_conflict_rows' => $highConflict,
        'high_mismatch_rows' => $highMismatch,
        'aligned_pct' => $pct($aligned, $total),
        'mismatch_pct' => $pct($mismatch, $total),
        'signal_only_pct' => $pct($signalOnly, $total),
        'catalog_only_pct' => $pct($catalogOnly, $total),
        'unlabeled_pct' => $pct($unlabeled, $total),
        'generic_label_pct' => $pct($generic, $total),
        'risk_class' => $riskClass,
    ];
}

function db_family_taxonomy_summary_from_scorecard(array $scorecard): array
{
    $summary = [
        [
            'alignment_status' => 'mismatch',
            'generic_label_count' => (int)($scorecard['generic_label_rows'] ?? 0),
            'row_count' => (int)($scorecard['mismatch_rows'] ?? 0),
        ],
        [
            'alignment_status' => 'signal_only',
            'generic_label_count' => 0,
            'row_count' => (int)($scorecard['signal_only_rows'] ?? 0),
        ],
        [
            'alignment_status' => 'catalog_only',
            'generic_label_count' => 0,
            'row_count' => (int)($scorecard['catalog_only_rows'] ?? 0),
        ],
        [
            'alignment_status' => 'unlabeled',
            'generic_label_count' => 0,
            'row_count' => (int)($scorecard['unlabeled_rows'] ?? 0),
        ],
        [
            'alignment_status' => 'aligned',
            'generic_label_count' => 0,
            'row_count' => (int)($scorecard['aligned_rows'] ?? 0),
        ],
    ];

    return array_values(array_filter($summary, static fn(array $row): bool => ((int)($row['row_count'] ?? 0)) > 0));
}

function db_family_taxonomy_queue_presets(array $issueInventory, array $decisionInventory = []): array
{
    $issueCounts = $issueInventory['issue_kind_counts'] ?? [];
    $count = static fn(string $key): int => (int)($issueCounts[$key] ?? 0);
    $decisionCounts = $decisionInventory['decision_mode_counts'] ?? [];
    $decisionCount = static fn(string $key): int => (int)($decisionCounts[$key] ?? 0);

    return [
        [
            'title' => 'Repair now candidates',
            'count' => $decisionCount('repair_now_candidate'),
            'description' => 'Rows with a concrete canonical family target that look safe for bounded repair batching.',
            'alignment' => '',
            'pattern' => '',
            'decision_mode' => 'repair_now_candidate',
            'button_label' => 'Open repair-now queue',
            'button_tone' => 'primary',
        ],
        [
            'title' => 'Ask why first',
            'count' => $decisionCount('ask_why_first'),
            'description' => 'Rows that need analyst or governance review before any catalog write because the evidence is conflicted or incomplete.',
            'alignment' => '',
            'pattern' => '',
            'decision_mode' => 'ask_why_first',
            'button_label' => 'Open why-first queue',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Placeholder cleanup',
            'count' => $count('placeholder_catalog'),
            'description' => 'Unknown or generic catalog family rows where VT already has a more specific family signal.',
            'alignment' => 'mismatch',
            'pattern' => 'placeholder_catalog',
            'decision_mode' => '',
            'button_label' => 'Open placeholder queue',
            'button_tone' => 'primary',
        ],
        [
            'title' => 'Generic signal noise',
            'count' => $count('generic_signal'),
            'description' => 'Rows where VT uses generic tokens like andr, msil, fakeapp, or drop that should not overwrite catalog family truth.',
            'alignment' => 'mismatch',
            'pattern' => 'generic_signal',
            'decision_mode' => 'hold_generic_signal',
            'button_label' => 'Open generic-signal queue',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Composite signal overlap',
            'count' => $count('signal_overlap'),
            'description' => 'Rows where the full VT label mixes a family token with generic, alias-style, or detector-noise secondary tokens.',
            'alignment' => 'mismatch',
            'pattern' => 'signal_overlap',
            'decision_mode' => 'hold_signal_overlap',
            'button_label' => 'Open overlap queue',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Alias review',
            'count' => $count('alias_candidate'),
            'description' => 'Rows where catalog family and VT signal are lexically close enough to suggest spelling drift or alias normalization.',
            'alignment' => '',
            'pattern' => 'alias_candidate',
            'decision_mode' => 'repair_after_alias_review',
            'button_label' => 'Open alias review',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Semantic conflicts',
            'count' => $count('semantic_conflict'),
            'description' => 'Rows that still need human or governance review because the catalog family and VT signal disagree materially.',
            'alignment' => 'mismatch',
            'pattern' => 'semantic_conflict',
            'decision_mode' => 'ask_why_first',
            'button_label' => 'Open conflict queue',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Signal-only fills',
            'count' => $count('catalog_missing'),
            'description' => 'Rows with no catalog family yet, but a VT family signal exists and may support catalog fill after review.',
            'alignment' => 'signal_only',
            'pattern' => '',
            'decision_mode' => '',
            'button_label' => 'Open signal-only queue',
            'button_tone' => 'default',
        ],
        [
            'title' => 'Governed alias resolved',
            'count' => $count('alias_resolved'),
            'description' => 'Rows where the catalog family already matches the governed canonical target and the VT signal only needs alias interpretation.',
            'alignment' => '',
            'pattern' => 'alias_resolved',
            'decision_mode' => 'keep_as_is',
            'button_label' => 'Open resolved-alias queue',
            'button_tone' => 'default',
        ],
    ];
}
