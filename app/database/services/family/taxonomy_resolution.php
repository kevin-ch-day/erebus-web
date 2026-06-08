<?php
declare(strict_types=1);

function db_family_taxonomy_pair_kind(string $catalogLabel, string $signalLabel, ?string $vtSuggestedLabel = null): string
{
    $catalogNorm = db_family_taxonomy_normalize_token($catalogLabel);
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    $generic = db_family_taxonomy_generic_tokens();
    $governedTargets = db_family_taxonomy_governed_signal_targets($signalLabel);
    $dominance = db_family_taxonomy_signal_catalog_dominance($signalLabel);

    if ($catalogNorm === '' || $signalNorm === '') {
        return 'incomplete_pair';
    }
    if (in_array($catalogNorm, $generic, true)) {
        return 'generic_catalog';
    }
    if (db_family_taxonomy_signal_token_is_unstable($signalNorm)) {
        return 'generic_signal';
    }
    if (in_array($catalogNorm, $governedTargets, true)) {
        return 'alias_resolved';
    }
    if (db_family_taxonomy_signal_secondary_tokens_include_catalog_family($catalogLabel, $vtSuggestedLabel, $signalLabel)) {
        return 'alias_resolved';
    }
    if (
        ($dominance['top_family_norm'] ?? '') === $catalogNorm
        && (int)($dominance['top_count'] ?? 0) >= 5
        && (float)($dominance['dominance'] ?? 0.0) >= 0.80
    ) {
        return 'alias_candidate';
    }
    if (db_family_taxonomy_has_distinct_inventory_anchors($catalogNorm, $signalNorm)) {
        return 'semantic_conflict';
    }
    if ($catalogNorm === $signalNorm) {
        return 'alias_candidate';
    }
    if (str_contains($catalogNorm, $signalNorm) || str_contains($signalNorm, $catalogNorm)) {
        return 'alias_candidate';
    }
    if (levenshtein($catalogNorm, $signalNorm) <= 2) {
        return 'alias_candidate';
    }
    return 'semantic_conflict';
}

function db_family_taxonomy_row_issue(array $row): array
{
    $resolvedFamilyName = trim((string)($row['resolved_family_name'] ?? ''));
    $resolutionReviewStatus = strtolower(trim((string)($row['resolution_review_status'] ?? '')));
    $mappingRows = (int)($row['mapping_rows'] ?? 0);
    $acceptedMappingRows = (int)($row['accepted_mapping_rows'] ?? 0);
    $distinctFamilies = (int)($row['distinct_families'] ?? 0);
    $acceptedDistinctFamilies = (int)($row['accepted_distinct_families'] ?? 0);
    $alignment = strtolower(trim((string)($row['alignment_status'] ?? '')));
    $catalog = trim((string)($row['family_label'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $vtSuggestedLabel = trim((string)($row['vt_suggested_label'] ?? ''));
    $sourceBatchLabel = trim((string)($row['source_batch_label'] ?? ''));
    $catalogNorm = db_family_taxonomy_normalize_token($catalog);
    $signalNorm = db_family_taxonomy_normalize_token($signal);
    $generic = db_family_taxonomy_generic_tokens();
    $governedTargets = db_family_taxonomy_governed_signal_targets($signal);
    $dominance = db_family_taxonomy_signal_catalog_dominance($signal);

    if (
        $resolvedFamilyName !== ''
        && $resolutionReviewStatus === 'accepted'
        && $mappingRows === 1
        && $acceptedMappingRows === 1
        && $distinctFamilies === 1
        && $acceptedDistinctFamilies === 1
    ) {
        return [
            'issue_kind' => 'alias_resolved',
            'issue_reason' => 'Resolved family view already has a single accepted mapping for this row, so it is settled alias normalization rather than an open taxonomy conflict.',
        ];
    }

    if ($catalogNorm !== '' && $signalNorm !== '' && !in_array($catalogNorm, $generic, true) && !in_array($signalNorm, $generic, true)) {
        if (in_array($catalogNorm, $governedTargets, true)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'VT signal family token is a governed alias of the current catalog family.'];
        }
        if (db_family_taxonomy_signal_secondary_tokens_include_catalog_family($catalog, $vtSuggestedLabel, $signal)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'Full VT label already includes the catalog family as a secondary token, so this row is better treated as co-label drift than a true family conflict.'];
        }
        if (db_family_taxonomy_source_batch_supports_catalog_family($sourceBatchLabel, $catalog)) {
            return ['issue_kind' => 'alias_resolved', 'issue_reason' => 'External source-batch provenance already supports the current catalog family, so a one-off VT token disagreement should not override that governed batch truth.'];
        }
        if (
            ($dominance['top_family_norm'] ?? '') === $catalogNorm
            && (int)($dominance['top_count'] ?? 0) >= 5
            && (float)($dominance['dominance'] ?? 0.0) >= 0.80
        ) {
            return ['issue_kind' => 'alias_candidate', 'issue_reason' => 'The VT signal overwhelmingly maps back to this same catalog family in the live inventory, so this looks more like alias or co-label drift than a true semantic conflict.'];
        }
    }

    if ($alignment === 'unlabeled') {
        return ['issue_kind' => 'unlabeled', 'issue_reason' => 'Neither catalog family nor VT signal family is present.'];
    }
    if ($alignment === 'signal_only') {
        return ['issue_kind' => 'catalog_missing', 'issue_reason' => 'VT signal exists but the catalog family label is empty.'];
    }
    if ($alignment === 'catalog_only') {
        return ['issue_kind' => 'signal_gap', 'issue_reason' => 'Catalog family label exists but VT signal family is missing.'];
    }
    if ($alignment === 'aligned') {
        if (in_array($catalogNorm, $generic, true)) {
            return ['issue_kind' => 'weak_generic_alignment', 'issue_reason' => 'Catalog and VT align, but only on a generic family token.'];
        }
        return ['issue_kind' => 'aligned', 'issue_reason' => 'Catalog family and VT signal family align.'];
    }

    if (in_array($catalogNorm, $generic, true) && in_array($signalNorm, $generic, true)) {
        return ['issue_kind' => 'generic_signal', 'issue_reason' => 'Catalog family is placeholder/generic and the VT signal is also generic, so this row should be held rather than promoted.'];
    }
    if (in_array($catalogNorm, $generic, true)) {
        return ['issue_kind' => 'placeholder_catalog', 'issue_reason' => 'Catalog family uses a placeholder or generic token while VT provides a more specific family signal.'];
    }
    if (db_family_taxonomy_signal_token_is_unstable($signalNorm)) {
        return ['issue_kind' => 'generic_signal', 'issue_reason' => 'VT signal family token is generic, dropper-side, or behavior-style and should not be treated as stable family truth.'];
    }
    if (db_family_taxonomy_signal_has_noisy_secondary_tokens($signal, $vtSuggestedLabel)) {
        return ['issue_kind' => 'signal_overlap', 'issue_reason' => 'Full VT label combines the family token with generic, alias-style, or detector-noise secondary tokens, so this row is better treated as co-label overlap than as a clean direct naming conflict.'];
    }
    if (db_family_taxonomy_signal_token_is_weak_short($signalNorm)) {
        return ['issue_kind' => 'short_signal_token', 'issue_reason' => 'VT signal family token is very short and likely needs extra scrutiny before reuse.'];
    }
    $pairKind = db_family_taxonomy_pair_kind($catalog, $signal, $vtSuggestedLabel);
    if ($pairKind === 'semantic_conflict') {
        return ['issue_kind' => 'semantic_conflict', 'issue_reason' => 'Catalog family and VT signal represent a real unresolved family disagreement.'];
    }
    if ($pairKind === 'alias_candidate') {
        return ['issue_kind' => 'alias_candidate', 'issue_reason' => 'Catalog family and VT signal look lexically close enough to be an alias or spelling-normalization problem.'];
    }
    return ['issue_kind' => 'semantic_conflict', 'issue_reason' => 'Catalog family and VT signal represent a real unresolved family disagreement.'];
}

function db_family_taxonomy_catalog_label_inventory(): array
{
    static $inventory = null;
    if (is_array($inventory)) {
        return $inventory;
    }

    $rows = db_all(
        'SELECT LOWER(TRIM(family_label)) AS family_norm, family_label, COUNT(*) AS row_count
         FROM ' . db_catalog_table('malware_sample_catalog') . "
         WHERE NULLIF(TRIM(COALESCE(family_label, '')), '') IS NOT NULL
         GROUP BY family_norm, family_label
         ORDER BY family_norm ASC, row_count DESC, family_label ASC"
    );

    $inventory = [];
    foreach ($rows as $row) {
        $norm = strtolower(trim((string)($row['family_norm'] ?? '')));
        if ($norm === '' || isset($inventory[$norm])) {
            continue;
        }
        $inventory[$norm] = [
            'label' => (string)($row['family_label'] ?? ''),
            'count' => (int)($row['row_count'] ?? 0),
        ];
    }

    return $inventory;
}

function db_family_taxonomy_signal_family_distribution(array $signalFamilies): array
{
    static $cache = [];

    $signalFamilies = array_values(array_unique(array_filter(array_map(
        static fn($value): string => strtolower(trim((string)$value)),
        $signalFamilies
    ))));
    if ($signalFamilies === []) {
        return [];
    }

    $missing = [];
    foreach ($signalFamilies as $signalFamily) {
        if (!array_key_exists($signalFamily, $cache)) {
            $missing[] = $signalFamily;
        }
    }

    if ($missing !== []) {
        $quoted = implode(', ', array_map(
            static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $missing
        ));

        $sql = 'SELECT LOWER(TRIM(sig.popular_threat_name)) AS signal_family, c.family_label, COUNT(*) AS row_count
                FROM ' . db_catalog_table('malware_sample_catalog') . ' c
                JOIN ' . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
                WHERE LOWER(TRIM(COALESCE(sig.popular_threat_name, ''))) IN ({$quoted})
                GROUP BY signal_family, c.family_label
                ORDER BY signal_family ASC, row_count DESC, c.family_label ASC";
        $rows = db_all($sql);

        foreach ($missing as $signalFamily) {
            $cache[$signalFamily] = [];
        }
        foreach ($rows as $row) {
            $signalFamily = strtolower(trim((string)($row['signal_family'] ?? '')));
            if ($signalFamily === '') {
                continue;
            }
            $cache[$signalFamily][] = [
                'catalog_family' => (string)($row['family_label'] ?? ''),
                'row_count' => (int)($row['row_count'] ?? 0),
            ];
        }
    }

    $distribution = [];
    foreach ($signalFamilies as $signalFamily) {
        $distribution[$signalFamily] = $cache[$signalFamily] ?? [];
    }

    return $distribution;
}

function db_family_taxonomy_pair_resolution(string $catalogLabel, string $signalLabel, array $signalDistribution = []): array
{
    $pairKind = db_family_taxonomy_pair_kind($catalogLabel, $signalLabel);
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    $catalogNorm = db_family_taxonomy_normalize_token($catalogLabel);

    if (db_family_taxonomy_signal_token_is_unstable($signalNorm)) {
        return [
            'resolution_action' => 'hold_catalog_generic_signal',
            'resolution_confidence' => 'high',
            'resolution_target_family' => $catalogNorm !== '' ? $catalogLabel : '',
            'resolution_reason' => 'VT signal token is unstable, generic, or detector-side and should not drive catalog family repair on its own.',
        ];
    }

    if ($pairKind === 'generic_catalog') {
        return [
            'resolution_action' => 'promote_specific_family_if_governed',
            'resolution_confidence' => 'medium',
            'resolution_target_family' => '',
            'resolution_reason' => 'Catalog family is placeholder/generic. Promote only after selecting or establishing a governed canonical family label.',
        ];
    }

    if ($pairKind === 'alias_candidate') {
        return [
            'resolution_action' => 'normalize_alias_to_catalog',
            'resolution_confidence' => 'high',
            'resolution_target_family' => $catalogLabel,
            'resolution_reason' => 'Catalog family and VT signal are lexically close enough to treat this as alias or spelling drift.',
        ];
    }

    $familyRows = $signalDistribution[$signalNorm] ?? [];
    $total = 0;
    $topFamily = '';
    $topCount = 0;
    foreach ($familyRows as $row) {
        $count = (int)($row['row_count'] ?? 0);
        $family = (string)($row['catalog_family'] ?? '');
        $total += $count;
        if ($count > $topCount) {
            $topCount = $count;
            $topFamily = $family;
        }
    }
    $dominance = $total > 0 ? ($topCount / $total) : 0.0;

    if ($topFamily !== '' && strcasecmp($topFamily, $catalogLabel) === 0 && $topCount >= 10 && $dominance >= 0.8) {
        return [
            'resolution_action' => 'treat_signal_as_catalog_alias',
            'resolution_confidence' => 'medium',
            'resolution_target_family' => $catalogLabel,
            'resolution_reason' => 'This VT signal maps overwhelmingly back to the same catalog family, so it is a good alias candidate for governed normalization.',
        ];
    }

    if ($topFamily !== '' && $topCount >= 10 && $dominance >= 0.8) {
        return [
            'resolution_action' => 'candidate_relabel_to_dominant_family',
            'resolution_confidence' => 'medium',
            'resolution_target_family' => $topFamily,
            'resolution_reason' => 'This VT signal mostly maps to one other catalog family, so the current catalog label is likely inconsistent and needs targeted review.',
        ];
    }

    return [
        'resolution_action' => 'manual_family_governance',
        'resolution_confidence' => 'low',
        'resolution_target_family' => '',
        'resolution_reason' => 'This pair is a real semantic conflict or an unstable VT family cluster and should not be auto-fixed.',
    ];
}

function db_family_taxonomy_dominant_existing_family_target(string $signalLabel): array
{
    static $cache = [];

    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return [];
    }
    if (isset($cache[$signalNorm])) {
        return $cache[$signalNorm];
    }

    $distribution = db_family_taxonomy_signal_family_distribution([$signalNorm]);
    $familyRows = $distribution[$signalNorm] ?? [];
    if ($familyRows === []) {
        $cache[$signalNorm] = [];
        return [];
    }

    $generic = db_family_taxonomy_generic_tokens();
    $total = 0;
    $topFamily = '';
    $topCount = 0;
    $runnerUpCount = 0;
    $specificFamilyCount = 0;
    foreach ($familyRows as $row) {
        $count = (int)($row['row_count'] ?? 0);
        $family = trim((string)($row['catalog_family'] ?? ''));
        $familyNorm = db_family_taxonomy_normalize_token($family);
        if ($familyNorm === '' || in_array($familyNorm, $generic, true)) {
            continue;
        }
        $specificFamilyCount += 1;
        $total += $count;
        if ($count > $topCount) {
            $runnerUpCount = $topCount;
            $topCount = $count;
            $topFamily = $family;
        } elseif ($count > $runnerUpCount) {
            $runnerUpCount = $count;
        }
    }

    if ($topFamily === '' || $total <= 0) {
        $cache[$signalNorm] = [];
        return [];
    }

    $dominance = $topCount / $total;
    if ($topCount < 2 || $dominance < 0.65) {
        $cache[$signalNorm] = [];
        return [];
    }

    $cache[$signalNorm] = [
        'label' => $topFamily,
        'top_count' => $topCount,
        'total' => $total,
        'dominance' => $dominance,
        'runner_up_count' => $runnerUpCount,
        'specific_family_count' => $specificFamilyCount,
    ];

    return $cache[$signalNorm];
}

function db_family_taxonomy_suggested_fix(array $row): array
{
    $issue = db_family_taxonomy_row_issue($row);
    $issueKind = $issue['issue_kind'] ?? 'unknown';
    $catalog = trim((string)($row['family_label'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $catalogNorm = db_family_taxonomy_normalize_token($catalog);
    $signalNorm = db_family_taxonomy_normalize_token($signal);
    $inventory = db_family_taxonomy_catalog_label_inventory();
    $confidence = strtolower(trim((string)($row['confidence_bucket'] ?? '')));
    $highConfidence = in_array($confidence, ['high', 'strong'], true);

    if ($issueKind === 'placeholder_catalog') {
        $governedTarget = db_family_taxonomy_governed_signal_target($signal);
        $governedFamilyLabel = db_family_taxonomy_governed_signal_family_label($signal);
        $catalogIsUnknownPlaceholder = in_array($catalogNorm, ['', 'unknown'], true);
        if ($signalNorm !== '' && isset($inventory[$signalNorm]) && $highConfidence) {
            return [
                'suggested_fix_action' => 'adopt_signal_family',
                'suggested_target_family' => $inventory[$signalNorm]['label'],
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is placeholder/generic and VT signal matches an existing catalog family label.',
            ];
        }
        if ($governedTarget !== '' && isset($inventory[$governedTarget]) && $highConfidence) {
            return [
                'suggested_fix_action' => 'adopt_signal_family',
                'suggested_target_family' => $inventory[$governedTarget]['label'],
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is placeholder/generic and the VT signal is a governed alias of an existing canonical family.',
            ];
        }
        if ($governedFamilyLabel !== '' && $highConfidence) {
            return [
                'suggested_fix_action' => 'adopt_signal_family',
                'suggested_target_family' => $governedFamilyLabel,
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is placeholder/generic and the VT signal already matches a governed canonical family label.',
            ];
        }
        if (db_family_taxonomy_signal_token_is_unstable($signalNorm) || ($signalNorm !== '' && strlen($signalNorm) <= 5)) {
            return [
                'suggested_fix_action' => 'hold_generic_signal',
                'suggested_target_family' => '',
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is placeholder/generic, but the VT signal is generic, loader-side, behavior-style, or too short to promote safely.',
            ];
        }
        $dominantTarget = db_family_taxonomy_dominant_existing_family_target($signal);
        if ($dominantTarget !== [] && $highConfidence) {
            $targetLabel = (string)($dominantTarget['label'] ?? '');
            $topCount = (int)($dominantTarget['top_count'] ?? 0);
            $total = (int)($dominantTarget['total'] ?? 0);
            $dominance = (float)($dominantTarget['dominance'] ?? 0.0);
            $runnerUpCount = (int)($dominantTarget['runner_up_count'] ?? 0);
            $specificFamilyCount = (int)($dominantTarget['specific_family_count'] ?? 0);
            if ($catalogIsUnknownPlaceholder && $targetLabel !== '' && ($specificFamilyCount === 1 || ($dominance >= 0.85 && $runnerUpCount <= 1))) {
                return [
                    'suggested_fix_action' => 'adopt_signal_family',
                    'suggested_target_family' => $targetLabel,
                    'suggested_fix_confidence' => 'high',
                    'suggested_fix_reason' => "Catalog family is placeholder/generic and this VT signal already collapses to existing family {$targetLabel} in current catalog usage.",
                ];
            }
            return [
                'suggested_fix_action' => 'needs_family_governance',
                'suggested_target_family' => $targetLabel,
                'suggested_fix_confidence' => 'medium',
                'suggested_fix_reason' => "VT signal most often maps to existing family {$targetLabel} ({$topCount}/{$total} rows), so this placeholder row has a governed review target.",
            ];
        }
        return [
            'suggested_fix_action' => 'needs_family_governance',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => $highConfidence ? 'medium' : 'low',
            'suggested_fix_reason' => 'Catalog family is placeholder/generic but no stable existing catalog family target is available.',
        ];
    }

    if ($issueKind === 'alias_resolved') {
        return [
            'suggested_fix_action' => 'keep_catalog_use_alias_map',
            'suggested_target_family' => $catalog,
            'suggested_fix_confidence' => 'high',
            'suggested_fix_reason' => 'Catalog family already matches the governed canonical target for this VT signal alias.',
        ];
    }

    if ($issueKind === 'catalog_missing') {
        $governedFamilyLabel = db_family_taxonomy_governed_signal_family_label($signal);
        if ($signalNorm !== '' && isset($inventory[$signalNorm]) && $highConfidence) {
            return [
                'suggested_fix_action' => 'fill_catalog_from_signal',
                'suggested_target_family' => $inventory[$signalNorm]['label'],
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is missing and VT signal matches an existing catalog family label.',
            ];
        }
        if ($governedFamilyLabel !== '' && $highConfidence) {
            return [
                'suggested_fix_action' => 'fill_catalog_from_signal',
                'suggested_target_family' => $governedFamilyLabel,
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is missing and the VT signal already matches a governed canonical family label.',
            ];
        }
        if (db_family_taxonomy_signal_token_is_unstable($signalNorm) || db_family_taxonomy_signal_token_is_weak_short($signalNorm)) {
            return [
                'suggested_fix_action' => 'hold_generic_signal',
                'suggested_target_family' => '',
                'suggested_fix_confidence' => 'high',
                'suggested_fix_reason' => 'Catalog family is missing, but the VT signal is generic, packer-side, detector-side, or too weak to promote into catalog family truth.',
            ];
        }
        return [
            'suggested_fix_action' => 'needs_family_governance',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => $highConfidence ? 'medium' : 'low',
            'suggested_fix_reason' => 'Catalog family is missing but the VT signal is not yet a stable catalog family target.',
        ];
    }

    if ($issueKind === 'alias_candidate') {
        if ($catalogNorm !== '' && isset($inventory[$catalogNorm])) {
            return [
                'suggested_fix_action' => 'canonicalize_catalog_alias',
                'suggested_target_family' => $inventory[$catalogNorm]['label'],
                'suggested_fix_confidence' => 'medium',
                'suggested_fix_reason' => 'Catalog family already maps to an existing canonical family spelling; treat the VT signal as alias drift.',
            ];
        }
        if ($signalNorm !== '' && isset($inventory[$signalNorm])) {
            return [
                'suggested_fix_action' => 'canonicalize_to_signal_family',
                'suggested_target_family' => $inventory[$signalNorm]['label'],
                'suggested_fix_confidence' => 'medium',
                'suggested_fix_reason' => 'VT signal maps to an existing canonical family spelling; catalog family likely needs normalization.',
            ];
        }
        return [
            'suggested_fix_action' => 'manual_alias_review',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => 'low',
            'suggested_fix_reason' => 'Likely alias drift, but no canonical label inventory anchor is available.',
        ];
    }

    if ($issueKind === 'generic_signal' || $issueKind === 'short_signal_token' || $issueKind === 'signal_overlap') {
        return [
            'suggested_fix_action' => $issueKind === 'signal_overlap' ? 'hold_signal_overlap' : 'hold_generic_signal',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => 'high',
            'suggested_fix_reason' => $issueKind === 'signal_overlap'
                ? 'Full VT label contains overlapping family and detector-style secondary tokens, so this row should stay out of the hard naming-conflict queue.'
                : 'VT signal token is too generic or too short to promote into catalog family truth directly.',
        ];
    }

    if ($issueKind === 'semantic_conflict') {
        return [
            'suggested_fix_action' => 'manual_family_adjudication',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => $highConfidence ? 'medium' : 'low',
            'suggested_fix_reason' => 'Catalog family and VT signal disagree semantically; needs analyst or governance review.',
        ];
    }

    if ($issueKind === 'weak_generic_alignment') {
        return [
            'suggested_fix_action' => 'replace_generic_alignment',
            'suggested_target_family' => '',
            'suggested_fix_confidence' => $highConfidence ? 'medium' : 'low',
            'suggested_fix_reason' => 'Catalog and VT agree only on a generic token; look for a more specific governed family.',
        ];
    }

    return [
        'suggested_fix_action' => 'monitor',
        'suggested_target_family' => '',
        'suggested_fix_confidence' => 'low',
        'suggested_fix_reason' => 'No immediate catalog repair suggested.',
    ];
}

function db_family_taxonomy_family_label_state(array $row): string
{
    $label = trim((string)($row['family_label'] ?? ''));
    $labelNorm = db_family_taxonomy_normalize_token($label);
    if ($labelNorm === '') {
        return 'blank_family_label';
    }
    if (in_array($labelNorm, db_family_taxonomy_generic_tokens(), true)) {
        return 'generic_family_label';
    }
    return 'specific_family_label';
}

function db_family_taxonomy_queue_type_context(array $row): array
{
    if (function_exists('db_dataset_readiness_type_derivation')) {
        $derived = db_dataset_readiness_type_derivation($row);
        return [
            'effective_type_slug' => (string)($derived['type_slug'] ?? ''),
            'effective_type_source' => (string)($derived['type_slug_source'] ?? 'unresolved'),
            'effective_type_confidence' => (string)($derived['type_slug_confidence'] ?? 'none'),
            'effective_type_status' => (string)($derived['type_slug_resolution_status'] ?? 'unresolved'),
            'type_slug_conflict_flag' => (bool)($derived['type_slug_conflict_flag'] ?? false),
            'type_slug_conflict_reason' => (string)($derived['type_slug_conflict_reason'] ?? ''),
        ];
    }

    return [
        'effective_type_slug' => '',
        'effective_type_source' => 'unresolved',
        'effective_type_confidence' => 'none',
        'effective_type_status' => 'unresolved',
        'type_slug_conflict_flag' => false,
        'type_slug_conflict_reason' => '',
    ];
}

function db_family_taxonomy_training_tier(array $row): string
{
    if (function_exists('db_dataset_readiness_type_derivation')) {
        $derived = db_dataset_readiness_type_derivation($row);
        $derivedRow = $row;
        $derivedRow['effective_family_resolved'] = in_array(
            strtolower(trim((string)($row['issue_kind'] ?? ''))),
            ['aligned', 'alias_resolved'],
            true
        ) ? 1 : 0;
        $derivedRow['effective_type_slug'] = (string)($derived['effective_type_slug'] ?? '');
        if (function_exists('db_dataset_readiness_family_resolution_status')) {
            $derivedRow['family_resolution_status'] = db_dataset_readiness_family_resolution_status($row);
        }
        if (function_exists('db_dataset_readiness_recommended_row_use')) {
            // no-op here, but keeps row shape close to the readiness service
        }
        if (function_exists('db_dataset_readiness_type_derivation') && function_exists('db_dataset_readiness_is_benchmark_eligible')) {
            // fall through; taxonomy tier is still computed locally below because the readiness
            // export expects different row shape, but we now have the correct derivation fields.
        }
    }

    $issueKind = trim((string)($row['issue_kind'] ?? ''));
    $typeSlug = trim((string)($row['effective_type_slug'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $familyState = db_family_taxonomy_family_label_state($row);

    if (in_array($issueKind, ['aligned', 'alias_resolved'], true) && $typeSlug !== '') {
        return 'authority_family_typed';
    }
    if ($typeSlug !== '') {
        return 'type_or_subtype_only';
    }
    if ($signal !== '') {
        return 'token_hint_only';
    }
    if ($familyState === 'blank_family_label') {
        return 'blank_family_label';
    }
    if ($familyState === 'generic_family_label') {
        return 'generic_family_label';
    }
    return 'specific_label_unresolved';
}

function db_family_taxonomy_recommended_supervision_regime(array $row): string
{
    $tier = trim((string)($row['taxonomy_training_tier'] ?? ''));
    if ($tier === 'authority_family_typed') {
        return 'closed_set_family_and_type';
    }
    if ($tier === 'type_or_subtype_only') {
        return 'coarse_taxonomy_only';
    }
    if ($tier === 'token_hint_only') {
        return 'partial_family_candidate';
    }
    if ($tier === 'specific_label_unresolved') {
        return 'open_family_candidate';
    }
    return 'weak_label_aux_only';
}

function db_family_taxonomy_recommended_ml_use(array $row): string
{
    if (function_exists('db_dataset_readiness_recommended_row_use') && function_exists('db_dataset_readiness_type_derivation')) {
        $derived = db_dataset_readiness_type_derivation($row);
        $derivedRow = array_merge($row, $derived, [
            'recommended_use' => '',
        ]);
        return (string)db_dataset_readiness_recommended_row_use($derivedRow);
    }

    $regime = trim((string)($row['recommended_supervision_regime'] ?? ''));
    $issueKind = trim((string)($row['issue_kind'] ?? ''));
    $signal = trim((string)($row['popular_threat_name'] ?? ''));
    $familyState = db_family_taxonomy_family_label_state($row);

    if ($regime === 'closed_set_family_and_type') {
        return 'closed_set_family_and_type';
    }
    if ($regime === 'coarse_taxonomy_only') {
        return 'coarse_taxonomy_only_not_family_training';
    }
    if ($regime === 'partial_family_candidate') {
        if ($familyState === 'specific_family_label' && $signal !== '') {
            return 'partial_label_family_candidate';
        }
        return 'open_set_eval_or_weak_review';
    }
    if ($regime === 'open_family_candidate') {
        if ($issueKind === 'alias_candidate') {
            return 'seed_alias_review_then_closed_set_family';
        }
        return 'manual_validation_then_seed_review';
    }
    return 'triage_only';
}

function db_family_taxonomy_has_persisted_authority_fact_local(array $row): bool
{
    return (int)($row['persisted_authority_id'] ?? 0) > 0
        || trim((string)($row['persisted_governed_type_slug'] ?? '')) !== ''
        || trim((string)($row['persisted_governed_family_slug'] ?? '')) !== '';
}

function db_family_taxonomy_projection_is_typed_local(array $row): bool
{
    return strtolower(trim((string)($row['authority_bucket'] ?? ''))) === 'authority_family_typed'
        && trim((string)($row['family_authority_type_slug'] ?? '')) !== '';
}

function db_family_taxonomy_open_conflict_family_slugs_local(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $table = db_catalog_table('vw_android_family_v2_conflict_and_repair_queue');
    $present = db_one(
        'SELECT COUNT(*) AS row_count
         FROM information_schema.tables
         WHERE table_schema = :table_schema
           AND table_name = :table_name',
        [
            'table_schema' => db_primary_catalog_name(),
            'table_name' => 'vw_android_family_v2_conflict_and_repair_queue',
        ]
    ) ?: [];
    if ((int)($present['row_count'] ?? 0) <= 0) {
        $cache = [];
        return $cache;
    }

    $rows = db_all(
        'SELECT DISTINCT LOWER(TRIM(COALESCE(family_slug, ""))) AS family_slug
         FROM ' . $table . '
         WHERE COALESCE(open_conflict_case_rows, 0) > 0'
    );
    $cache = [];
    foreach ($rows as $row) {
        $slug = trim((string)($row['family_slug'] ?? ''));
        if ($slug !== '') {
            $cache[$slug] = true;
        }
    }
    return $cache;
}

function db_family_taxonomy_row_has_open_conflict_case_local(array $row): bool
{
    $familySlugs = db_family_taxonomy_open_conflict_family_slugs_local();
    if ($familySlugs === []) {
        return false;
    }

    $candidates = [
        strtolower(trim((string)($row['persisted_governed_family_slug'] ?? ''))),
        strtolower(trim((string)($row['family_authority_family_slug'] ?? ''))),
        strtolower(trim((string)($row['governed_family_slug'] ?? ''))),
        strtolower(trim((string)($row['effective_family_slug'] ?? ''))),
    ];
    foreach ($candidates as $slug) {
        if ($slug !== '' && isset($familySlugs[$slug])) {
            return true;
        }
    }
    return false;
}

function db_family_taxonomy_authority_tier_local(array $row): array
{
    $hasFact = db_family_taxonomy_has_persisted_authority_fact_local($row);
    $projectionTyped = db_family_taxonomy_projection_is_typed_local($row);
    $authorityBucket = strtolower(trim((string)($row['authority_bucket'] ?? '')));
    $gapReason = strtolower(trim((string)($row['authority_gap_reason'] ?? '')));

    if ($hasFact) {
        return [
            'authority_tier' => 'persisted_authority_fact',
            'authority_source' => trim((string)($row['persisted_authority_source_table'] ?? '')) !== ''
                ? trim((string)($row['persisted_authority_source_table'] ?? ''))
                : trim((string)($row['persisted_authority_source_system'] ?? '')),
        ];
    }
    if (db_family_taxonomy_row_has_open_conflict_case_local($row)) {
        return [
            'authority_tier' => 'conflict_case',
            'authority_source' => 'vw_android_family_v2_conflict_and_repair_queue',
        ];
    }
    if ($projectionTyped) {
        return [
            'authority_tier' => 'derived_authority_projection',
            'authority_source' => 'v_android_sample_family_type_authority',
        ];
    }
    if (in_array($authorityBucket, ['generic_label_candidate', 'vt_tail_policy_hold_review'], true)
        || in_array($gapReason, ['resolved_token_policy_held_not_family', 'vt_tail_token_policy_held_not_family', 'resolved_token_coarse_behavior'], true)
    ) {
        return [
            'authority_tier' => 'generic_token_policy_hold',
            'authority_source' => 'v_android_sample_family_type_authority',
        ];
    }

    return [
        'authority_tier' => 'unresolved_authority',
        'authority_source' => $authorityBucket !== '' ? 'v_android_sample_family_type_authority' : 'unresolved',
    ];
}

function db_family_taxonomy_enrich_row(array $row): array
{
    $alignmentStatus = strtolower((string)($row['alignment_status'] ?? ''));
    $confidenceBucket = strtolower((string)($row['confidence_bucket'] ?? ''));
    $genericFlag = ((int)($row['generic_label_flag'] ?? 0)) === 1;
    $reviewLane = 'aligned_monitor';

    if ($alignmentStatus === 'mismatch') {
        $reviewLane = 'family_mismatch_review';
    } elseif ($alignmentStatus === 'signal_only') {
        $reviewLane = 'catalog_fill_from_signal';
    } elseif ($alignmentStatus === 'catalog_only') {
        $reviewLane = 'signal_gap_review';
    } elseif ($alignmentStatus === 'unlabeled') {
        $reviewLane = 'unlabeled_triage';
    }

    if ($genericFlag) {
        $reviewLane = 'generic_label_cleanup';
    }

    if (in_array($confidenceBucket, ['high', 'strong'], true) && $alignmentStatus !== 'aligned') {
        $reviewLane .= '_priority';
    }

    $issue = db_family_taxonomy_row_issue($row);
    $row['issue_kind'] = $issue['issue_kind'];
    $row['issue_reason'] = $issue['issue_reason'];
    $fix = db_family_taxonomy_suggested_fix($row);
    $row['suggested_fix_action'] = $fix['suggested_fix_action'];
    $row['suggested_target_family'] = $fix['suggested_target_family'];
    $row['suggested_fix_confidence'] = $fix['suggested_fix_confidence'];
    $row['suggested_fix_reason'] = $fix['suggested_fix_reason'];
    $row['review_lane'] = $reviewLane;

    $decision = db_family_taxonomy_decision_model($row);
    $row['decision_mode'] = $decision['decision_mode'];
    $row['decision_priority'] = $decision['decision_priority'];
    $row['decision_why'] = $decision['decision_why'];

    $typeContext = db_family_taxonomy_queue_type_context($row);
    $row['effective_type_slug'] = $typeContext['effective_type_slug'];
    $row['effective_type_source'] = $typeContext['effective_type_source'];
    $row['effective_type_confidence'] = $typeContext['effective_type_confidence'];
    $row['effective_type_status'] = $typeContext['effective_type_status'];
    $row['type_slug_conflict_flag'] = $typeContext['type_slug_conflict_flag'];
    $row['type_slug_conflict_reason'] = $typeContext['type_slug_conflict_reason'];
    if (function_exists('db_dataset_readiness_authority_tier')) {
        $authority = db_dataset_readiness_authority_tier($row);
        $row['authority_tier'] = (string)($authority['authority_tier'] ?? 'unresolved_authority');
        $row['authority_source'] = (string)($authority['authority_source'] ?? 'unresolved');
    } else {
        $authority = db_family_taxonomy_authority_tier_local($row);
        $row['authority_tier'] = (string)($authority['authority_tier'] ?? 'unresolved_authority');
        $row['authority_source'] = (string)($authority['authority_source'] ?? 'unresolved');
    }
    if (function_exists('db_dataset_readiness_has_persisted_authority_fact')) {
        $row['has_persisted_authority_fact'] = db_dataset_readiness_has_persisted_authority_fact($row);
    } else {
        $row['has_persisted_authority_fact'] = db_family_taxonomy_has_persisted_authority_fact_local($row);
    }
    $row['taxonomy_training_tier'] = db_family_taxonomy_training_tier($row);
    $row['recommended_supervision_regime'] = db_family_taxonomy_recommended_supervision_regime($row);
    $row['recommended_ml_use'] = db_family_taxonomy_recommended_ml_use($row);

    return $row;
}

function db_family_taxonomy_enrich_rows(array $rows): array
{
    $signalFamilies = [];
    foreach ($rows as $row) {
        $signalFamily = strtolower(trim((string)($row['popular_threat_name'] ?? '')));
        if ($signalFamily !== '') {
            $signalFamilies[] = $signalFamily;
        }
    }
    if ($signalFamilies !== []) {
        db_family_taxonomy_signal_family_distribution($signalFamilies);
    }

    foreach ($rows as &$row) {
        $row = db_family_taxonomy_enrich_row($row);
    }
    unset($row);
    return $rows;
}
