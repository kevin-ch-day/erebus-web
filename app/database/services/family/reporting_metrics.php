<?php
declare(strict_types=1);

function db_family_taxonomy_math_summary(array $scorecard, array $mismatchPairs = []): array
{
    $counts = [
        'aligned' => (int)($scorecard['aligned_rows'] ?? 0),
        'mismatch' => (int)($scorecard['mismatch_rows'] ?? 0),
        'signal_only' => (int)($scorecard['signal_only_rows'] ?? 0),
        'catalog_only' => (int)($scorecard['catalog_only_rows'] ?? 0),
        'unlabeled' => (int)($scorecard['unlabeled_rows'] ?? 0),
    ];
    $total = max(0, (int)($scorecard['total_rows'] ?? 0));
    $entropy = 0.0;
    $hhi = 0.0;
    $nonZeroBuckets = 0;

    foreach ($counts as $count) {
        if ($count <= 0 || $total <= 0) {
            continue;
        }
        $p = $count / $total;
        $entropy += -$p * log($p, 2);
        $hhi += $p * $p;
        $nonZeroBuckets++;
    }

    $pairMass = 0;
    foreach ($mismatchPairs as $pair) {
        $pairMass += (int)($pair['row_count'] ?? 0);
    }
    $topPairMass = (int)($mismatchPairs[0]['row_count'] ?? 0);

    $aligned = $counts['aligned'];
    $nonAligned = max(0, $total - $aligned);
    $signalOnly = $counts['signal_only'];
    $catalogOnly = $counts['catalog_only'];
    $unlabeled = $counts['unlabeled'];
    $mismatch = $counts['mismatch'];
    $highConflict = (int)($scorecard['high_conflict_rows'] ?? 0);
    $generic = (int)($scorecard['generic_label_rows'] ?? 0);

    return [
        'entropy_bits' => round($entropy, 4),
        'normalized_entropy' => $nonZeroBuckets > 1 ? round($entropy / log($nonZeroBuckets, 2), 4) : null,
        'hhi' => round($hhi, 4),
        'non_aligned_rows' => $nonAligned,
        'resolvable_visibility_gap_rows' => $signalOnly + $catalogOnly + $unlabeled,
        'resolvable_visibility_gap_pct' => $total > 0 ? round((($signalOnly + $catalogOnly + $unlabeled) / $total) * 100, 2) : null,
        'true_naming_conflict_pct' => $total > 0 ? round(($mismatch / $total) * 100, 2) : null,
        'high_conflict_within_non_aligned_pct' => $nonAligned > 0 ? round(($highConflict / $nonAligned) * 100, 2) : null,
        'generic_within_catalog_visible_pct' => ($mismatch + $catalogOnly + $aligned) > 0 ? round(($generic / ($mismatch + $catalogOnly + $aligned)) * 100, 2) : null,
        'top_pair_share_of_sampled_mismatch_mass' => $pairMass > 0 ? round(($topPairMass / $pairMass) * 100, 2) : null,
    ];
}

function db_family_taxonomy_row_pattern_summary(array $rows): array
{
    $summary = [
        'generic_catalog_rows' => 0,
        'generic_signal_rows' => 0,
        'signal_overlap_rows' => 0,
        'short_signal_token_rows' => 0,
        'allcaps_catalog_rows' => 0,
        'mixed_case_catalog_rows' => 0,
        'spy_bank_loader_signal_rows' => 0,
        'unknown_catalog_rows' => 0,
        'issue_kind_counts' => [],
        'top_catalog_labels' => [],
        'top_signal_labels' => [],
    ];

    foreach ($rows as $row) {
        $catalog = trim((string)($row['family_label'] ?? ''));
        $signal = trim((string)($row['popular_threat_name'] ?? ''));
        $catalogNorm = db_family_taxonomy_normalize_token($catalog);
        $issueKind = trim((string)($row['issue_kind'] ?? ''));
        if ($issueKind === '') {
            $issue = db_family_taxonomy_row_issue($row);
            $issueKind = (string)($issue['issue_kind'] ?? 'unknown');
        }

        if ($catalog !== '') {
            $summary['top_catalog_labels'][$catalog] = ($summary['top_catalog_labels'][$catalog] ?? 0) + 1;
        }
        if ($signal !== '') {
            $summary['top_signal_labels'][$signal] = ($summary['top_signal_labels'][$signal] ?? 0) + 1;
        }

        if (in_array($catalogNorm, db_family_taxonomy_generic_tokens(), true) && $catalogNorm !== 'unknown') {
            $summary['generic_catalog_rows']++;
        }
        if ($issueKind === 'generic_signal') {
            $summary['generic_signal_rows']++;
        }
        if ($issueKind === 'signal_overlap') {
            $summary['signal_overlap_rows']++;
        }
        if ($issueKind === 'short_signal_token') {
            $summary['short_signal_token_rows']++;
        }
        if ($catalog !== '' && preg_match('/^[A-Z0-9_\-]+$/', $catalog)) {
            $summary['allcaps_catalog_rows']++;
        }
        if ($catalog !== '' && preg_match('/[A-Z].*[a-z]|[a-z].*[A-Z]/', $catalog)) {
            $summary['mixed_case_catalog_rows']++;
        }
        if (
            $signal !== ''
            && $issueKind !== 'generic_signal'
            && $issueKind !== 'signal_overlap'
            && $issueKind !== 'short_signal_token'
            && preg_match('/(spy|bank|loader|drop|rat|bot|steal|door)/i', $signal) === 1
            && !db_family_taxonomy_signal_token_is_unstable(db_family_taxonomy_normalize_token($signal))
        ) {
            $summary['spy_bank_loader_signal_rows']++;
        }
        if (strtolower($catalog) === 'unknown') {
            $summary['unknown_catalog_rows']++;
        }
        $summary['issue_kind_counts'][$issueKind] = ($summary['issue_kind_counts'][$issueKind] ?? 0) + 1;
    }

    arsort($summary['top_catalog_labels']);
    arsort($summary['top_signal_labels']);
    $summary['top_catalog_labels'] = array_slice($summary['top_catalog_labels'], 0, 10, true);
    $summary['top_signal_labels'] = array_slice($summary['top_signal_labels'], 0, 10, true);

    return $summary;
}

function db_family_taxonomy_row_matches_pattern(array $row, string $pattern): bool
{
    $pattern = strtolower(trim($pattern));
    $catalog = trim((string)($row['family_label'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $catalogNorm = db_family_taxonomy_normalize_token($catalog);
    $signalNorm = db_family_taxonomy_normalize_token($signal);
    $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));
    if ($issueKind === '') {
        $issue = db_family_taxonomy_row_issue($row);
        $issueKind = strtolower(trim((string)($issue['issue_kind'] ?? 'unknown')));
    }

    return match ($pattern) {
        'unknown_catalog' => $catalogNorm === 'unknown',
        'generic_catalog' => in_array($catalogNorm, db_family_taxonomy_generic_tokens(), true) && $catalogNorm !== 'unknown',
        'generic_signal' => $issueKind === 'generic_signal',
        'signal_overlap' => $issueKind === 'signal_overlap',
        'short_signal' => $issueKind === 'short_signal_token',
        'spy_bank_loader_signal' => (
            $signal !== ''
            && $issueKind !== 'generic_signal'
            && $issueKind !== 'signal_overlap'
            && $issueKind !== 'short_signal_token'
            && preg_match('/(spy|bank|loader|drop|rat|bot|steal|door)/i', $signal) === 1
            && !db_family_taxonomy_signal_token_is_unstable($signalNorm)
        ),
        'alias_candidate', 'alias_resolved', 'semantic_conflict', 'placeholder_catalog' => $issueKind === $pattern,
        default => false,
    };
}

function db_family_taxonomy_mismatch_pairs_from_rows(array $rows, int $limit = 20): array
{
    $limit = max(1, min($limit, 50));
    $groups = [];

    foreach ($rows as $row) {
        $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
        if ($alignment !== 'mismatch') {
            continue;
        }

        $decisionMode = strtolower(trim((string)($row['decision_mode'] ?? '')));
        if ($decisionMode === 'keep_as_is') {
            continue;
        }

        $issueKind = strtolower(trim((string)($row['issue_kind'] ?? '')));
        if ($issueKind === 'alias_resolved') {
            continue;
        }

        $catalogLabel = trim((string)($row['family_label'] ?? ''));
        $signalLabel = trim((string)($row['popular_threat_name'] ?? ''));
        $catalogKey = $catalogLabel !== '' ? $catalogLabel : '(empty)';
        $signalKey = $signalLabel !== '' ? $signalLabel : '(empty)';
        $key = strtolower($catalogKey) . '|' . strtolower($signalKey);

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'catalog_family_label' => $catalogKey,
                'signal_family_name' => $signalKey,
                'row_count' => 0,
            ];
        }
        $groups[$key]['row_count']++;
    }

    $pairs = array_values($groups);
    usort($pairs, static function (array $a, array $b): int {
        $countCmp = ((int)($b['row_count'] ?? 0)) <=> ((int)($a['row_count'] ?? 0));
        if ($countCmp !== 0) {
            return $countCmp;
        }
        $catalogCmp = strcmp((string)($a['catalog_family_label'] ?? ''), (string)($b['catalog_family_label'] ?? ''));
        if ($catalogCmp !== 0) {
            return $catalogCmp;
        }
        return strcmp((string)($a['signal_family_name'] ?? ''), (string)($b['signal_family_name'] ?? ''));
    });
    $pairs = array_slice($pairs, 0, $limit);

    $signalDistribution = db_family_taxonomy_signal_family_distribution(array_map(
        static fn(array $pair): string => (string)($pair['signal_family_name'] ?? ''),
        $pairs
    ));

    foreach ($pairs as &$pair) {
        $resolution = db_family_taxonomy_pair_resolution(
            (string)($pair['catalog_family_label'] ?? ''),
            (string)($pair['signal_family_name'] ?? ''),
            $signalDistribution
        );
        $pair['pair_kind'] = db_family_taxonomy_pair_kind(
            (string)($pair['catalog_family_label'] ?? ''),
            (string)($pair['signal_family_name'] ?? '')
        );
        $pair['resolution_action'] = $resolution['resolution_action'];
        $pair['resolution_confidence'] = $resolution['resolution_confidence'];
        $pair['resolution_target_family'] = $resolution['resolution_target_family'];
        $pair['resolution_reason'] = $resolution['resolution_reason'];
    }
    unset($pair);

    return $pairs;
}

function db_family_taxonomy_remediation_summary(array $scorecard, array $mismatchPairs, array $rows = []): array
{
    $math = db_family_taxonomy_math_summary($scorecard, $mismatchPairs);
    $rowPatterns = db_family_taxonomy_row_pattern_summary($rows);
    $signalDistribution = db_family_taxonomy_signal_family_distribution(array_map(
        static fn(array $pair): string => (string)($pair['signal_family_name'] ?? ''),
        $mismatchPairs
    ));
    $aliasCandidateRows = 0;
    $genericSignalRows = 0;
    $signalOverlapRows = 0;
    $genericCatalogRows = 0;
    $semanticConflictRows = 0;
    $projectionWithoutPersistedFactRows = 0;
    $genericPolicyHoldRows = 0;

    foreach ($mismatchPairs as &$pair) {
        $pairKind = db_family_taxonomy_pair_kind(
            (string)($pair['catalog_family_label'] ?? ''),
            (string)($pair['signal_family_name'] ?? '')
        );
        $pair['pair_kind'] = $pairKind;
        $count = (int)($pair['row_count'] ?? 0);
        $resolution = db_family_taxonomy_pair_resolution(
            (string)($pair['catalog_family_label'] ?? ''),
            (string)($pair['signal_family_name'] ?? ''),
            $signalDistribution
        );
        $pair['resolution_action'] = $resolution['resolution_action'];
        $pair['resolution_confidence'] = $resolution['resolution_confidence'];
        $pair['resolution_target_family'] = $resolution['resolution_target_family'];
        $pair['resolution_reason'] = $resolution['resolution_reason'];
        if ($pairKind === 'alias_candidate') {
            $aliasCandidateRows += $count;
        } elseif ($pairKind === 'generic_signal') {
            $genericSignalRows += $count;
        } elseif ($pairKind === 'generic_catalog') {
            $genericCatalogRows += $count;
        } else {
            $semanticConflictRows += $count;
        }
    }
    unset($pair);

    if ($rows !== []) {
        $aliasCandidateRows = 0;
        $genericSignalRows = 0;
        $signalOverlapRows = 0;
        $genericCatalogRows = 0;
        $semanticConflictRows = 0;
        foreach ($rows as $row) {
            $issueKind = trim((string)($row['issue_kind'] ?? ''));
            $authorityTier = trim((string)($row['authority_tier'] ?? ''));
            if ($issueKind === '') {
                $issue = db_family_taxonomy_row_issue($row);
                $issueKind = (string)($issue['issue_kind'] ?? '');
            }
            if ($issueKind === 'alias_candidate') {
                $aliasCandidateRows += 1;
            } elseif ($issueKind === 'generic_signal' || $issueKind === 'short_signal_token') {
                $genericSignalRows += 1;
            } elseif ($issueKind === 'signal_overlap') {
                $signalOverlapRows += 1;
            } elseif ($issueKind === 'placeholder_catalog') {
                $genericCatalogRows += 1;
            } elseif ($issueKind === 'semantic_conflict') {
                $semanticConflictRows += 1;
            }
            if ($authorityTier === 'derived_authority_projection') {
                $projectionWithoutPersistedFactRows += 1;
            }
            if ($authorityTier === 'generic_token_policy_hold') {
                $genericPolicyHoldRows += 1;
            }
        }
    }

    $normalizedNamingConflictRows = $semanticConflictRows;
    $totalRows = max(0, (int)($scorecard['total_rows'] ?? 0));
    $math['raw_label_disagreement_rows'] = (int)($scorecard['mismatch_rows'] ?? 0);
    $math['raw_label_disagreement_pct'] = $scorecard['mismatch_pct'] ?? null;
    $math['true_naming_conflict_rows'] = $normalizedNamingConflictRows;
    $math['true_naming_conflict_pct'] = $totalRows > 0 ? round(($normalizedNamingConflictRows / $totalRows) * 100, 2) : null;

    $priority = [];

    $priority[] = [
        'lane' => 'visibility_gap',
        'severity' => (($math['resolvable_visibility_gap_pct'] ?? 0.0) >= 50.0) ? 'critical' : 'warn',
        'rows' => (int)($math['resolvable_visibility_gap_rows'] ?? 0),
        'pct' => $math['resolvable_visibility_gap_pct'],
        'title' => 'Visibility and completeness gap',
        'why' => 'Signal-only, catalog-only, and unlabeled rows dominate the family-taxonomy debt.',
        'next_path' => 'Add or reconcile family labels before treating the catalog as taxonomy truth.',
        'alignment' => '',
        'pattern' => '',
        'query' => '',
        'page' => 'family_taxonomy_check',
    ];

    $priority[] = [
        'lane' => 'generic_label_cleanup',
        'severity' => (($scorecard['generic_label_pct'] ?? 0.0) >= 5.0) ? 'warn' : 'info',
        'rows' => (int)($scorecard['generic_label_rows'] ?? 0),
        'pct' => $scorecard['generic_label_pct'] ?? null,
        'title' => 'Generic family label cleanup',
        'why' => 'Generic labels obscure real families and inflate fake agreement.',
        'next_path' => 'Prioritize generic catalog labels with strong VT signal or high confidence.',
        'alignment' => 'generic_label',
        'pattern' => 'generic_catalog',
        'query' => '',
        'page' => 'family_taxonomy_check',
    ];

    if (($rowPatterns['unknown_catalog_rows'] ?? 0) >= 25) {
        $priority[] = [
            'lane' => 'unknown_placeholder_cleanup',
            'severity' => (($rowPatterns['unknown_catalog_rows'] ?? 0) >= 100) ? 'critical' : 'warn',
            'rows' => (int)($rowPatterns['unknown_catalog_rows'] ?? 0),
            'pct' => count($rows) > 0 ? round(((int)$rowPatterns['unknown_catalog_rows'] / count($rows)) * 100, 2) : null,
            'title' => 'Unknown placeholder dominance',
            'why' => 'A large part of the live mismatch queue is not a nuanced dispute. It is catalog placeholder debt.',
            'next_path' => 'Bulk-review `Unknown` catalog labels with high-confidence VT family signals before deeper taxonomy debates.',
            'alignment' => 'mismatch',
            'pattern' => 'unknown_catalog',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    if ($genericSignalRows >= 50) {
        $priority[] = [
            'lane' => 'generic_signal_contamination',
            'severity' => $genericSignalRows >= 150 ? 'warn' : 'info',
            'rows' => $genericSignalRows,
            'pct' => null,
            'title' => 'Generic signal contamination',
            'why' => 'A material share of mismatch mass comes from generic VT signal names like `andr`, `msil`, `drop`, or `fakeapp`.',
            'next_path' => 'Separate generic VT signal tokens from real family names before measuring family disagreement.',
            'alignment' => 'mismatch',
            'pattern' => 'generic_signal',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    if ($projectionWithoutPersistedFactRows >= 25) {
        $priority[] = [
            'lane' => 'projection_materialization_debt',
            'severity' => $projectionWithoutPersistedFactRows >= 250 ? 'warn' : 'info',
            'rows' => $projectionWithoutPersistedFactRows,
            'pct' => count($rows) > 0 ? round(($projectionWithoutPersistedFactRows / count($rows)) * 100, 2) : null,
            'title' => 'Projection materialization debt',
            'why' => 'These rows already look typeable from the authority projection, but the governed family/type fact is not materialized yet.',
            'next_path' => 'Prioritize governed family/type pairs with the most projection-only rows and backfill persisted authority facts before treating them as benchmark truth.',
            'alignment' => 'mismatch',
            'pattern' => '',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    if ($signalOverlapRows >= 25) {
        $priority[] = [
            'lane' => 'signal_overlap_backlog',
            'severity' => $signalOverlapRows >= 100 ? 'warn' : 'info',
            'rows' => $signalOverlapRows,
            'pct' => null,
            'title' => 'Composite signal overlap',
            'why' => 'A material slice of mismatch rows uses VT labels that combine a family token with alias-style or detector-style secondary tokens, which looks like co-label overlap rather than clean family disagreement.',
            'next_path' => 'Keep these rows out of the hard conflict queue and only revisit them when a stable canonical family target is governed.',
            'alignment' => 'mismatch',
            'pattern' => 'signal_overlap',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    if ($normalizedNamingConflictRows > 0) {
        $priority[] = [
            'lane' => 'naming_conflict',
            'severity' => ($normalizedNamingConflictRows >= 250 || (($math['true_naming_conflict_pct'] ?? 0.0) >= 5.0)) ? 'critical' : 'warn',
            'rows' => $normalizedNamingConflictRows,
            'pct' => $math['true_naming_conflict_pct'] ?? null,
            'title' => 'Direct naming conflict',
            'why' => 'Only a small residue of rows still looks like true family disagreement after generic-token stripping, overlap handling, and alias normalization.',
            'next_path' => 'Work the unresolved semantic-conflict queue last, after visibility debt, placeholder cleanup, generic-signal holds, and projection materialization debt.',
            'alignment' => 'mismatch',
            'pattern' => 'semantic_conflict',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    if ($aliasCandidateRows > 0) {
        $priority[] = [
            'lane' => 'alias_normalization',
            'severity' => $aliasCandidateRows >= 50 ? 'warn' : 'info',
            'rows' => $aliasCandidateRows,
            'pct' => null,
            'title' => 'Alias and normalization candidates',
            'why' => 'These rows still look like spelling drift, token drift, or alias normalization rather than true semantic disagreement.',
            'next_path' => 'Review alias-like pairs and normalize them into one governed family token.',
            'alignment' => 'mismatch',
            'pattern' => 'alias_candidate',
            'query' => '',
            'page' => 'family_taxonomy_check',
        ];
    }

    usort($priority, static function (array $a, array $b): int {
        $severityRank = ['critical' => 0, 'warn' => 1, 'info' => 2];
        $aRank = $severityRank[(string)($a['severity'] ?? 'info')] ?? 3;
        $bRank = $severityRank[(string)($b['severity'] ?? 'info')] ?? 3;
        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }
        return ((int)($b['rows'] ?? 0)) <=> ((int)($a['rows'] ?? 0));
    });

    return [
        'math' => $math,
        'priority_lanes' => $priority,
        'mismatch_pair_classes' => [
            'alias_candidate_rows' => $aliasCandidateRows,
            'generic_signal_rows' => $genericSignalRows,
            'signal_overlap_rows' => $signalOverlapRows,
            'generic_catalog_rows' => $genericCatalogRows,
            'semantic_conflict_rows' => $semanticConflictRows,
            'projection_without_persisted_fact_rows' => $projectionWithoutPersistedFactRows,
            'generic_policy_hold_rows' => $genericPolicyHoldRows,
        ],
        'row_pattern_summary' => $rowPatterns,
        'top_mismatch_pairs' => $mismatchPairs,
    ];
}
