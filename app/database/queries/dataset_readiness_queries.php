<?php
declare(strict_types=1);

function sql_dataset_readiness_label_surfaces_base(
    string $alignmentExpr,
    string $genericFamilyExpr,
    string $resolvedFamilyExpr,
    bool $includeResolution,
    bool $includeLabelAuthority,
    bool $includeTypeAuthority,
    bool $includePersistedAuthorityFact
): string {
    $resolutionSelect = '';
    $resolutionJoin = '';
    $labelAuthoritySelect = '';
    $labelAuthorityJoin = '';
    $typeAuthoritySelect = '';
    $typeAuthorityJoin = '';
    $persistedAuthoritySelect = '';
    $persistedAuthorityJoin = '';
    if ($includeResolution) {
        $resolutionSelect = ",
            res.resolved_family_name,
            res.resolution_review_status,
            res.mapping_rows,
            res.accepted_mapping_rows,
            res.distinct_families,
            res.accepted_distinct_families";
        $resolutionJoin = '
        LEFT JOIN ' . db_catalog_table('vw_malware_sample_catalog_family_resolution') . ' res ON res.sample_id = c.sample_id';
    }
    if ($includeLabelAuthority) {
        $labelAuthoritySelect = ",
            auth.catalog_family_slug,
            auth.catalog_family_name,
            auth.catalog_type_slug,
            auth.governed_family_slug,
            auth.governed_family_name,
            auth.governed_type_slug,
            auth.effective_family_slug,
            auth.effective_family_name,
            auth.effective_type_slug,
            auth.explicit_authority_override_flag,
            auth.authority_source_table,
            auth.authority_resolution_method,
            auth.review_status AS authority_review_status";
        $labelAuthorityJoin = '
        LEFT JOIN ' . db_catalog_table('label_authority_resolution_view') . ' auth ON auth.sample_id = c.sample_id';
    }
    if ($includeTypeAuthority) {
        $typeAuthoritySelect = ",
            fta.type_slug AS family_authority_type_slug,
            fta.family_slug AS family_authority_family_slug,
            fta.family_name AS family_authority_family_name,
            fta.authority_bucket,
            fta.authority_gap_reason,
            fta.raw_vs_authority_status,
            fta.generic_token_kind,
            fta.vt_tail_token_kind";
        $typeAuthorityJoin = '
        LEFT JOIN ' . db_catalog_table('v_android_sample_family_type_authority') . ' fta ON fta.sample_id = c.sample_id';
    }
    if ($includePersistedAuthorityFact) {
        $persistedAuthoritySelect = ",
            af.authority_id AS persisted_authority_id,
            af.governed_family_slug AS persisted_governed_family_slug,
            af.governed_family_name AS persisted_governed_family_name,
            af.governed_type_slug AS persisted_governed_type_slug,
            af.authority_source_system AS persisted_authority_source_system,
            af.authority_source_table AS persisted_authority_source_table,
            af.authority_resolution_method AS persisted_authority_resolution_method,
            af.review_status AS persisted_authority_review_status,
            af.is_active AS persisted_authority_is_active";
        $persistedAuthorityJoin = '
        LEFT JOIN ' . db_catalog_table('malware_family_authority_fact') . ' af
               ON af.sample_id = c.sample_id
              AND af.is_active = 1';
    }

    return "
        SELECT
            c.sample_id,
            c.sha256,
            c.sample_label,
            c.family_label,
            c.classification_primary,
            c.classification_subtype,
            c.platform,
            c.android_package_name,
            c.vt_suggested_label,
            sig.popular_threat_name,
            sig.popular_threat_label,
            sig.popular_threat_category,
            {$resolvedFamilyExpr} AS canonical_family_label,
            {$alignmentExpr} AS alignment_status,
            {$genericFamilyExpr} AS generic_family_flag
            {$labelAuthoritySelect}
            {$typeAuthoritySelect}
            {$persistedAuthoritySelect}
            {$resolutionSelect}
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        {$labelAuthorityJoin}
        {$typeAuthorityJoin}
        {$persistedAuthorityJoin}
        {$resolutionJoin}
        WHERE LOWER(COALESCE(c.platform, '')) = 'android'
    ";
}

function sql_dataset_readiness_label_surfaces_count_base(bool $includeLabelAuthority, bool $includeTypeAuthority, bool $includePersistedAuthorityFact): string
{
    $labelAuthorityJoin = $includeLabelAuthority
        ? '
        LEFT JOIN ' . db_catalog_table('label_authority_resolution_view') . ' auth ON auth.sample_id = c.sample_id'
        : '';
    $typeAuthorityJoin = $includeTypeAuthority
        ? '
        LEFT JOIN ' . db_catalog_table('v_android_sample_family_type_authority') . ' fta ON fta.sample_id = c.sample_id'
        : '';
    $persistedAuthorityJoin = $includePersistedAuthorityFact
        ? '
        LEFT JOIN ' . db_catalog_table('malware_family_authority_fact') . ' af
               ON af.sample_id = c.sample_id
              AND af.is_active = 1'
        : '';

    return "
        SELECT COUNT(*) AS total_count
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        {$labelAuthorityJoin}
        {$typeAuthorityJoin}
        {$persistedAuthorityJoin}
        WHERE LOWER(COALESCE(c.platform, '')) = 'android'
    ";
}

function sql_dataset_readiness_type_rows_base(
    string $alignmentExpr,
    string $genericFamilyExpr,
    string $resolvedFamilyExpr,
    bool $includeResolution,
    bool $includeLabelAuthority,
    bool $includeTypeAuthority,
    bool $includePersistedAuthorityFact
): string {
    return sql_dataset_readiness_label_surfaces_base(
        $alignmentExpr,
        $genericFamilyExpr,
        $resolvedFamilyExpr,
        $includeResolution,
        $includeLabelAuthority,
        $includeTypeAuthority,
        $includePersistedAuthorityFact
    );
}

function sql_dataset_readiness_type_benchmark_rows_base(
    string $alignmentExpr,
    string $genericFamilyExpr,
    string $resolvedFamilyExpr,
    bool $includeResolution,
    bool $includeLabelAuthority,
    bool $includeTypeAuthority,
    bool $includePersistedAuthorityFact
): string {
    return sql_dataset_readiness_label_surfaces_base(
        $alignmentExpr,
        $genericFamilyExpr,
        $resolvedFamilyExpr,
        $includeResolution,
        $includeLabelAuthority,
        $includeTypeAuthority,
        $includePersistedAuthorityFact
    );
}

function sql_dataset_readiness_type_class_counts(
    string $governedTypeExpr,
    string $alignmentExpr,
    string $genericFamilyExpr,
    string $canonicalFamilyExpr,
    bool $includeResolution
): string {
    $resolutionJoin = $includeResolution
        ? '
        LEFT JOIN ' . db_catalog_table('vw_malware_sample_catalog_family_resolution') . ' res ON res.sample_id = c.sample_id'
        : '';

    return "
        SELECT
            {$governedTypeExpr} AS governed_type_slug,
            COUNT(*) AS sample_count,
            SUM(CASE WHEN {$genericFamilyExpr} = 1 THEN 1 ELSE 0 END) AS generic_label_count,
            SUM(CASE WHEN {$alignmentExpr} = 'mismatch' THEN 1 ELSE 0 END) AS taxonomy_mismatch_count,
            SUM(CASE WHEN NULLIF(TRIM(COALESCE({$canonicalFamilyExpr}, '')), '') IS NULL THEN 1 ELSE 0 END) AS unresolved_family_count
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        {$resolutionJoin}
        WHERE {$governedTypeExpr} IS NOT NULL
          AND {$governedTypeExpr} <> ''
        GROUP BY governed_type_slug
        ORDER BY sample_count DESC, governed_type_slug ASC
    ";
}

function sql_dataset_readiness_type_family_counts(
    string $governedTypeExpr,
    string $canonicalFamilyExpr,
    bool $includeResolution
): string {
    $resolutionJoin = $includeResolution
        ? '
        LEFT JOIN ' . db_catalog_table('vw_malware_sample_catalog_family_resolution') . ' res ON res.sample_id = c.sample_id'
        : '';

    return "
        SELECT
            {$governedTypeExpr} AS governed_type_slug,
            NULLIF(TRIM(COALESCE({$canonicalFamilyExpr}, '')), '') AS canonical_family_label,
            COUNT(*) AS sample_count
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
        {$resolutionJoin}
        WHERE {$governedTypeExpr} IS NOT NULL
          AND {$governedTypeExpr} <> ''
        GROUP BY governed_type_slug, canonical_family_label
        ORDER BY governed_type_slug ASC, sample_count DESC, canonical_family_label ASC
    ";
}

function sql_dataset_readiness_type_overall_counts(
    string $governedTypeExpr,
    string $proposedTypeExpr,
    string $alignmentExpr,
    string $genericFamilyExpr
): string {
    return "
        SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN {$governedTypeExpr} IS NOT NULL AND {$governedTypeExpr} <> '' THEN 1 ELSE 0 END) AS governed_type_rows,
            SUM(CASE WHEN ({$governedTypeExpr} IS NULL OR {$governedTypeExpr} = '') AND {$proposedTypeExpr} IS NOT NULL AND {$proposedTypeExpr} <> '' THEN 1 ELSE 0 END) AS proposed_only_rows,
            SUM(CASE WHEN ({$governedTypeExpr} IS NULL OR {$governedTypeExpr} = '') AND ({$proposedTypeExpr} IS NULL OR {$proposedTypeExpr} = '') THEN 1 ELSE 0 END) AS unresolved_count,
            SUM(CASE WHEN {$genericFamilyExpr} = 1 THEN 1 ELSE 0 END) AS generic_label_count,
            SUM(CASE WHEN {$alignmentExpr} = 'mismatch' THEN 1 ELSE 0 END) AS taxonomy_mismatch_count
        FROM " . db_catalog_table('malware_sample_catalog') . " c
        LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
    ";
}

function sql_dataset_readiness_type_derived_base(
    string $alignmentExpr,
    string $genericFamilyExpr,
    string $resolvedFamilyExpr,
    string $familyAuthorityTypeExpr,
    string $governedAuthorityTypeExpr,
    string $catalogAuthorityTypeExpr,
    string $effectiveAuthorityTypeExpr,
    string $classificationSubtypeTypeExpr,
    string $classificationPrimaryTypeExpr,
    string $vtCategoryTypeExpr,
    bool $includeResolution,
    bool $includeLabelAuthority,
    bool $includeTypeAuthority
): string {
    $resolutionJoin = $includeResolution
        ? '
        LEFT JOIN ' . db_catalog_table('vw_malware_sample_catalog_family_resolution') . ' res ON res.sample_id = c.sample_id'
        : '';
    $labelAuthorityJoin = $includeLabelAuthority
        ? '
        LEFT JOIN ' . db_catalog_table('label_authority_resolution_view') . ' auth ON auth.sample_id = c.sample_id'
        : '';
    $typeAuthorityJoin = $includeTypeAuthority
        ? '
        LEFT JOIN ' . db_catalog_table('v_android_sample_family_type_authority') . ' fta ON fta.sample_id = c.sample_id'
        : '';

    return "
        SELECT
            typed.*,
            CASE
                WHEN typed.type_slug IS NULL OR typed.type_slug = '' THEN 0
                WHEN (typed.candidate_governed_type_authority IS NOT NULL AND typed.candidate_governed_type_authority <> typed.type_slug)
                  OR (typed.candidate_effective_type_authority IS NOT NULL AND typed.candidate_effective_type_authority <> typed.type_slug)
                  OR (typed.candidate_catalog_type_authority IS NOT NULL AND typed.candidate_catalog_type_authority <> typed.type_slug)
                  OR (typed.candidate_family_type_authority IS NOT NULL AND typed.candidate_family_type_authority <> typed.type_slug)
                  OR (typed.candidate_classification_subtype IS NOT NULL AND typed.candidate_classification_subtype <> typed.type_slug)
                  OR (typed.candidate_classification_primary IS NOT NULL AND typed.candidate_classification_primary <> typed.type_slug)
                  OR (typed.candidate_vt_category IS NOT NULL AND typed.candidate_vt_category <> typed.type_slug)
                    THEN 1
                ELSE 0
            END AS type_slug_conflict_flag
        FROM (
            SELECT
                base.*,
                CASE
                    WHEN base.candidate_family_type_authority IS NOT NULL THEN base.candidate_family_type_authority
                    WHEN base.candidate_governed_type_authority IS NOT NULL THEN base.candidate_governed_type_authority
                    WHEN base.candidate_catalog_type_authority IS NOT NULL THEN base.candidate_catalog_type_authority
                    WHEN base.candidate_effective_type_authority IS NOT NULL THEN base.candidate_effective_type_authority
                    WHEN base.candidate_classification_subtype IS NOT NULL THEN base.candidate_classification_subtype
                    WHEN base.candidate_classification_primary IS NOT NULL THEN base.candidate_classification_primary
                    WHEN base.candidate_vt_category IS NOT NULL THEN base.candidate_vt_category
                    ELSE NULL
                END AS type_slug,
                CASE
                    WHEN base.candidate_family_type_authority IS NOT NULL THEN 'family_type_authority'
                    WHEN base.candidate_governed_type_authority IS NOT NULL THEN 'governed_type_authority'
                    WHEN base.candidate_catalog_type_authority IS NOT NULL THEN 'catalog_family_type'
                    WHEN base.candidate_effective_type_authority IS NOT NULL THEN 'effective_type_authority'
                    WHEN base.candidate_classification_subtype IS NOT NULL THEN 'classification_subtype'
                    WHEN base.candidate_classification_primary IS NOT NULL THEN 'classification_primary'
                    WHEN base.candidate_vt_category IS NOT NULL THEN 'vt_popular_threat_category'
                    ELSE 'unresolved'
                END AS type_slug_source,
                CASE
                    WHEN base.candidate_family_type_authority IS NOT NULL THEN 'high'
                    WHEN base.candidate_governed_type_authority IS NOT NULL THEN 'high'
                    WHEN base.candidate_catalog_type_authority IS NOT NULL THEN 'high'
                    WHEN base.candidate_effective_type_authority IS NOT NULL THEN 'high'
                    WHEN base.candidate_classification_subtype IS NOT NULL THEN 'medium'
                    WHEN base.candidate_classification_primary IS NOT NULL THEN 'low'
                    WHEN base.candidate_vt_category IS NOT NULL THEN 'proposal'
                    ELSE 'none'
                END AS type_slug_confidence,
                CASE
                    WHEN base.candidate_family_type_authority IS NOT NULL THEN 'authority_resolved'
                    WHEN base.candidate_governed_type_authority IS NOT NULL THEN 'authority_resolved'
                    WHEN base.candidate_catalog_type_authority IS NOT NULL THEN 'family_type_resolved'
                    WHEN base.candidate_effective_type_authority IS NOT NULL THEN 'family_type_resolved'
                    WHEN base.candidate_classification_subtype IS NOT NULL THEN 'subtype_fallback'
                    WHEN base.candidate_classification_primary IS NOT NULL THEN 'primary_fallback'
                    WHEN base.candidate_vt_category IS NOT NULL THEN 'proposal_only'
                    ELSE 'unresolved'
                END AS type_slug_resolution_status,
                CASE
                    WHEN base.candidate_family_type_authority IS NOT NULL THEN base.candidate_family_type_authority
                    WHEN base.candidate_governed_type_authority IS NOT NULL THEN base.candidate_governed_type_authority
                    WHEN base.candidate_catalog_type_authority IS NOT NULL THEN base.candidate_catalog_type_authority
                    ELSE NULL
                END AS governed_type_slug,
                base.candidate_vt_category AS proposed_type_slug
            FROM (
                SELECT
                    c.sample_id,
                    {$resolvedFamilyExpr} AS canonical_family_label,
                    {$alignmentExpr} AS alignment_status,
                    {$genericFamilyExpr} AS generic_family_flag,
                    {$familyAuthorityTypeExpr} AS candidate_family_type_authority,
                    {$governedAuthorityTypeExpr} AS candidate_governed_type_authority,
                    {$effectiveAuthorityTypeExpr} AS candidate_effective_type_authority,
                    {$catalogAuthorityTypeExpr} AS candidate_catalog_type_authority,
                    {$classificationSubtypeTypeExpr} AS candidate_classification_subtype,
                    {$classificationPrimaryTypeExpr} AS candidate_classification_primary,
                    {$vtCategoryTypeExpr} AS candidate_vt_category
                FROM " . db_catalog_table('malware_sample_catalog') . " c
                LEFT JOIN " . db_catalog_table('virustotal_sample_signal_current') . " sig ON sig.sample_id = c.sample_id
                {$labelAuthorityJoin}
                {$typeAuthorityJoin}
                {$resolutionJoin}
                WHERE LOWER(COALESCE(c.platform, '')) = 'android'
            ) base
        ) typed
    ";
}

function sql_dataset_readiness_type_benchmark_summary_from_derived(string $derivedSql): string
{
    return "
        SELECT
            SUM(CASE WHEN type_slug_source IN ('family_type_authority', 'governed_type_authority', 'catalog_family_type') THEN 1 ELSE 0 END) AS authority_resolved_count,
            SUM(CASE WHEN type_slug_source = 'classification_subtype' THEN 1 ELSE 0 END) AS subtype_fallback_count,
            SUM(CASE WHEN type_slug_source = 'classification_primary' THEN 1 ELSE 0 END) AS primary_fallback_count,
            SUM(CASE WHEN type_slug_confidence = 'high' AND type_slug IS NOT NULL AND type_slug <> '' AND type_slug_source <> 'vt_popular_threat_category' THEN 1 ELSE 0 END) AS high_confidence_count,
            SUM(CASE WHEN type_slug_source = 'vt_popular_threat_category' THEN 1 ELSE 0 END) AS proposed_only_count,
            SUM(CASE WHEN type_slug IS NULL OR type_slug = '' THEN 1 ELSE 0 END) AS unresolved_count,
            SUM(CASE WHEN generic_family_flag = 1 THEN 1 ELSE 0 END) AS generic_label_count,
            SUM(CASE WHEN alignment_status = 'mismatch' THEN 1 ELSE 0 END) AS taxonomy_mismatch_count,
            SUM(CASE WHEN type_slug_conflict_flag = 1 THEN 1 ELSE 0 END) AS conflict_count,
            SUM(CASE WHEN type_slug IS NOT NULL AND type_slug <> '' AND type_slug_source <> 'vt_popular_threat_category' THEN 1 ELSE 0 END) AS resolved_typed_count,
            SUM(CASE WHEN type_slug_source IN ('family_type_authority', 'governed_type_authority', 'catalog_family_type') AND type_slug_confidence = 'high' AND type_slug IS NOT NULL AND type_slug <> '' THEN 1 ELSE 0 END) AS benchmark_eligible_count
        FROM ({$derivedSql}) typed
    ";
}

function sql_dataset_readiness_type_class_counts_from_derived(string $derivedSql, bool $benchmarkOnly): string
{
    $filter = $benchmarkOnly
        ? "typed.type_slug_source IN ('family_type_authority', 'governed_type_authority', 'catalog_family_type') AND typed.type_slug_confidence = 'high'"
        : "typed.type_slug_source <> 'vt_popular_threat_category'";

    return "
        SELECT
            typed.type_slug AS governed_type_slug,
            COUNT(*) AS sample_count,
            COUNT(DISTINCT NULLIF(TRIM(COALESCE(typed.canonical_family_label, '')), '')) AS family_count,
            SUM(CASE WHEN typed.generic_family_flag = 1 THEN 1 ELSE 0 END) AS generic_label_count,
            SUM(CASE WHEN typed.alignment_status = 'mismatch' THEN 1 ELSE 0 END) AS taxonomy_mismatch_count,
            SUM(CASE WHEN NULLIF(TRIM(COALESCE(typed.canonical_family_label, '')), '') IS NULL THEN 1 ELSE 0 END) AS unresolved_family_count,
            SUM(CASE WHEN typed.type_slug_confidence = 'high' THEN 1 ELSE 0 END) AS high_confidence_count,
            SUM(CASE WHEN typed.type_slug_conflict_flag = 1 THEN 1 ELSE 0 END) AS conflict_count
        FROM ({$derivedSql}) typed
        WHERE typed.type_slug IS NOT NULL
          AND typed.type_slug <> ''
          AND {$filter}
        GROUP BY typed.type_slug
        ORDER BY sample_count DESC, governed_type_slug ASC
    ";
}

function sql_dataset_readiness_type_family_counts_from_derived(string $derivedSql, bool $benchmarkOnly): string
{
    $filter = $benchmarkOnly
        ? "typed.type_slug_source IN ('family_type_authority', 'governed_type_authority', 'catalog_family_type') AND typed.type_slug_confidence = 'high'"
        : "typed.type_slug_source <> 'vt_popular_threat_category'";

    return "
        SELECT
            typed.type_slug AS governed_type_slug,
            NULLIF(TRIM(COALESCE(typed.canonical_family_label, '')), '') AS canonical_family_label,
            COUNT(*) AS sample_count
        FROM ({$derivedSql}) typed
        WHERE typed.type_slug IS NOT NULL
          AND typed.type_slug <> ''
          AND {$filter}
          AND NULLIF(TRIM(COALESCE(typed.canonical_family_label, '')), '') IS NOT NULL
        GROUP BY typed.type_slug, canonical_family_label
        ORDER BY governed_type_slug ASC, sample_count DESC, canonical_family_label ASC
    ";
}
