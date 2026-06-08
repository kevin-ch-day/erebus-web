<?php
// app/lib/permissions.php
// Permission taxonomy helpers.

declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Return the configured permission bucket definitions.
 */
function perm_bucket_definitions(): array
{
    return defined('PERM_TAXONOMY_BUCKETS') ? PERM_TAXONOMY_BUCKETS : [];
}

/**
 * Permission classification definitions.
 */
function perm_classifications(): array
{
    return defined('PERM_CLASSIFICATIONS') ? PERM_CLASSIFICATIONS : [];
}

/**
 * Permission triage statuses.
 */
function perm_triage_statuses(): array
{
    return defined('PERM_TRIAGE_STATUSES') ? PERM_TRIAGE_STATUSES : [];
}

/**
 * Triage statuses safe to present in operator-facing LOVs.
 *
 * @return array<int,array<string,mixed>>
 */
function perm_operator_triage_statuses(): array
{
    return perm_triage_statuses();
}

/**
 * Operator-facing triage vocabulary metadata keyed by stored status key.
 *
 * This is the web-contract meaning layer for status concepts. Stored keys remain
 * stable for compatibility, while labels/hints/workflow intent stay centralized.
 *
 * @return array<string,array<string,mixed>>
 */
function perm_triage_status_metadata_map(): array
{
    return [
        'new' => [
            'concept_label' => 'Active review',
            'backlog_effect' => 'Stays in active review backlog',
            'workflow_role' => 'active_review',
            'recommended_quick_action' => true,
        ],
        'in_review' => [
            'concept_label' => 'Active review',
            'backlog_effect' => 'Stays in active review backlog',
            'workflow_role' => 'active_review',
        ],
        'deferred' => [
            'concept_label' => 'Needs more evidence',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'deferred_review',
            'help_text' => 'Use when you need more evidence before deciding.',
            'recommended_quick_action' => true,
        ],
        'aosp_missing' => [
            'concept_label' => 'AOSP governance candidate',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'governance_candidate',
            'help_text' => 'Use for Android permissions in the AOSP namespace that are missing from the public SDK or docs baseline.',
            'suggested_queue_action' => 'aosp',
            'suggested_queue_bucket' => 'AOSP_EXACT',
            'suggested_queue_classification' => 'AOSP',
            'recommended_quick_action' => true,
        ],
        'gms_known' => [
            'concept_label' => 'Google-governed',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'governed_known',
            'help_text' => 'Use for Google-defined permissions, including Play Services or GMS cases.',
            'suggested_queue_action' => 'google',
            'suggested_queue_bucket' => 'GOOGLE_GMS',
            'suggested_queue_classification' => 'GOOGLE',
            'recommended_quick_action' => true,
        ],
        'oem_candidate' => [
            'concept_label' => 'OEM governance candidate',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'governance_candidate',
            'help_text' => 'Use for vendor-specific namespaces that need OEM review.',
            'suggested_queue_action' => 'oem',
            'suggested_queue_bucket' => 'OEM_EXACT',
            'suggested_queue_classification' => 'OEM',
            'recommended_quick_action' => true,
        ],
        'launcher_ecosystem' => [
            'concept_label' => 'Governed launcher ecosystem',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'governed_known',
            'help_text' => 'Use for launcher or platform ecosystem permissions that are known but not dictionary-worthy.',
        ],
        'app_defined' => [
            'concept_label' => 'App-defined resolved',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'resolved',
            'help_text' => 'Use when the permission is app-defined and should not remain in the unknown workflow backlog.',
            'suggested_queue_action' => 'app_defined',
            'suggested_queue_bucket' => 'APP_DEFINED_OTHER',
            'suggested_queue_classification' => 'APP_DEFINED',
        ],
        'malformed' => [
            'concept_label' => 'Malformed / invalid',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'rejected_or_invalid',
            'help_text' => 'Use when the permission string is invalid or non-standard.',
            'suggested_queue_action' => 'reject',
        ],
        'brand_spoof' => [
            'concept_label' => 'Escalated suspicious',
            'backlog_effect' => 'Stays in active review backlog',
            'workflow_role' => 'escalated_review',
        ],
        'malicious_dga' => [
            'concept_label' => 'Escalated suspicious',
            'backlog_effect' => 'Stays in active review backlog',
            'workflow_role' => 'escalated_review',
        ],
        'resolved_aosp' => [
            'concept_label' => 'Resolved in AOSP dictionary',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'resolved',
            'help_text' => 'Use after the dictionary or registry workflow has already resolved the classification through AOSP.',
        ],
        'resolved_oem' => [
            'concept_label' => 'Resolved in OEM dictionary',
            'backlog_effect' => 'Leaves default review backlog',
            'workflow_role' => 'resolved',
            'help_text' => 'Use after the dictionary or registry workflow has already resolved the classification through OEM governance.',
        ],
    ];
}

/**
 * Operator statuses enriched with current vocabulary metadata.
 *
 * @return array<int,array<string,mixed>>
 */
function perm_operator_triage_statuses_with_metadata(): array
{
    $metaMap = perm_triage_status_metadata_map();
    $enriched = [];
    foreach (perm_operator_triage_statuses() as $def) {
        $key = strtolower(trim((string)($def['key'] ?? '')));
        if ($key === '') {
            continue;
        }
        $enriched[] = array_merge($def, $metaMap[$key] ?? []);
    }
    return $enriched;
}

/**
 * Namespace class definitions (for drift/OEM guidance).
 */
function perm_namespace_classes(): array
{
    return defined('PERM_NAMESPACE_CLASSES') ? PERM_NAMESPACE_CLASSES : [];
}

/**
 * OEM review outcomes (read-only until workflow exists).
 */
function perm_oem_review_outcomes(): array
{
    return defined('PERM_OEM_REVIEW_OUTCOMES') ? PERM_OEM_REVIEW_OUTCOMES : [];
}

/**
 * Queue actions for dictionary maintenance.
 */
function perm_queue_actions(): array
{
    return defined('PERM_QUEUE_ACTIONS') ? PERM_QUEUE_ACTIONS : [];
}

/**
 * Queue lifecycle status definitions.
 */
function perm_queue_statuses(): array
{
    return defined('PERM_QUEUE_STATUSES') ? PERM_QUEUE_STATUSES : [];
}

/**
 * Extract keys from a list of key/label definitions.
 */
function perm_extract_keys(array $defs): array
{
    $keys = [];
    foreach ($defs as $def) {
        $key = trim((string)($def['key'] ?? ''));
        if ($key !== '') $keys[] = $key;
    }
    return $keys;
}

/**
 * Bucket keys (normalized).
 */
function perm_bucket_keys(): array
{
    $keys = [];
    foreach (perm_bucket_definitions() as $def) {
        $key = (string)($def['key'] ?? '');
        if ($key !== '') $keys[] = perm_bucket_key($key);
    }
    return array_values(array_unique($keys));
}

/**
 * Triage status keys.
 */
function perm_triage_status_keys(): array
{
    return perm_extract_keys(perm_triage_statuses());
}

/**
 * Triage status display labels keyed by stored triage key.
 */
function perm_triage_status_label_map(): array
{
    $map = [];
    foreach (perm_triage_statuses() as $def) {
        $key = strtolower(trim((string)($def['key'] ?? '')));
        $label = trim((string)($def['label'] ?? ''));
        if ($key === '' || $label === '') {
            continue;
        }
        $map[$key] = $label;
    }
    foreach (perm_deprecated_triage_status_label_map() as $key => $label) {
        if (!isset($map[$key])) {
            $map[$key] = $label;
        }
    }
    return $map;
}

/**
 * Deprecated triage labels preserved for historical read compatibility.
 *
 * These keys are no longer part of the active configured workflow contract, but
 * some older rows may still need a human-readable label when rendered.
 *
 * @return array<string,string>
 */
function perm_deprecated_triage_status_label_map(): array
{
    return [
        'reviewed' => 'Reviewed',
        'classified' => 'Classified',
        'ignore' => 'Rejected / no action',
    ];
}

/**
 * Deprecated triage keys preserved for read-only compatibility checks.
 *
 * These keys are not part of the active operator workflow vocabulary.
 *
 * @return array<int,string>
 */
function perm_deprecated_triage_status_keys(): array
{
    return array_keys(perm_deprecated_triage_status_label_map());
}

/**
 * Resolve a stored triage status key into an operator-facing label.
 */
function perm_triage_status_label(?string $value): string
{
    $key = strtolower(trim((string)$value));
    if ($key === '') {
        return '-';
    }
    $map = perm_triage_status_label_map();
    return $map[$key] ?? $value ?? '-';
}

/**
 * Merge raw triage-status counts under operator-facing display labels.
 *
 * @param array<string,int> $raw
 * @return array<string,int>
 */
function perm_display_triage_status_counts(array $raw): array
{
    $merged = [];
    foreach ($raw as $key => $count) {
        $label = perm_triage_status_label((string)$key);
        $merged[$label] = (int)($merged[$label] ?? 0) + (int)$count;
    }
    return $merged;
}

/**
 * Actionable workflow statuses shown by default in triage.
 *
 * Note: `gms_known` is a legacy stored key that the UI now presents as
 * "Google-defined". Keep the stored key stable until the underlying data is migrated.
 */
function perm_actionable_triage_status_keys(): array
{
    return ['new', 'in_review', 'aosp_missing', 'oem_candidate', 'gms_known', 'malformed', 'brand_spoof', 'malicious_dga'];
}

/**
 * Review-workflow states that should remain visible in operator review queues.
 */
function perm_review_triage_status_keys(): array
{
    return ['new', 'in_review', 'aosp_missing', 'oem_candidate', 'gms_known', 'malformed', 'brand_spoof', 'malicious_dga'];
}

/**
 * Ledger states that still represent unresolved operator burden.
 *
 * This excludes governed/resolved residue and adjudicated diagnostic lanes such
 * as Google-known and malformed tokens. Those remain visible in review and
 * diagnostics surfaces, but they should not inflate the headline burden count.
 */
function perm_effective_unknown_triage_status_keys(): array
{
    return ['new', 'in_review', 'aosp_missing', 'oem_candidate', 'brand_spoof', 'malicious_dga'];
}

function perm_actionable_workflow_unknown_count(int $effectiveUnknown): int
{
    return max(0, $effectiveUnknown);
}

/**
 * Observation-table materialization for governed/resolved triage outcomes.
 *
 * Returns a normalized observation classification payload when a dictionary
 * triage status should also move matching observation rows out of UNKNOWN.
 *
 * @return array{classification:string,bucket:string,rule_fired:string}|null
 */
function perm_obs_materialization_for_triage_status(?string $status): ?array
{
    $key = strtolower(trim((string)$status));
    return match ($key) {
        'resolved_oem' => [
            'classification' => 'OEM',
            'bucket' => 'OEM_EXACT',
            'rule_fired' => 'oem_dict',
        ],
        'gms_known' => [
            'classification' => 'GOOGLE',
            'bucket' => 'GOOGLE_GMS',
            'rule_fired' => 'gms_namespace',
        ],
        'launcher_ecosystem' => [
            'classification' => 'OEM',
            'bucket' => 'OEM_LAUNCHER_ECOSYSTEM',
            'rule_fired' => 'launcher_ecosystem',
        ],
        'app_defined' => [
            'classification' => 'APP_DEFINED',
            'bucket' => 'APP_DEFINED_OTHER',
            'rule_fired' => 'default',
        ],
        default => null,
    };
}

/**
 * Resolved / terminal workflow states that are hidden by default in triage.
 */
function perm_resolved_triage_status_keys(): array
{
    return ['app_defined', 'resolved_aosp', 'resolved_oem'];
}

/**
 * Queue status keys.
 */
function perm_queue_status_keys(): array
{
    return perm_extract_keys(perm_queue_statuses());
}

/**
 * Canonical queue action aliases preserved for any remaining legacy rows.
 */
function perm_queue_action_aliases(): array
{
    return [];
}

function perm_normalize_queue_action(?string $value): string
{
    $key = strtolower(trim((string)$value));
    if ($key === '') {
        return '';
    }
    $aliases = perm_queue_action_aliases();
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }
    return $key;
}

function perm_valid_queue_action_keys_with_aliases(): array
{
    $keys = array_map('strtolower', perm_extract_keys(perm_queue_actions()));
    $aliases = array_keys(perm_queue_action_aliases());
    return array_values(array_unique(array_merge($keys, $aliases)));
}

function perm_normalize_queue_status(?string $value): string
{
    $key = strtolower(trim((string)$value));
    if ($key === '') {
        return '';
    }
    $aliases = [
        'pending' => 'queued',
        'done' => 'applied',
        'failed' => 'error',
    ];
    return $aliases[$key] ?? $key;
}

/**
 * Classification keys.
 */
function perm_classification_keys(): array
{
    return perm_extract_keys(perm_classifications());
}

/**
 * Namespace class keys.
 */
function perm_namespace_class_keys(): array
{
    return perm_extract_keys(perm_namespace_classes());
}

/**
 * Exact namespace override definitions keyed by stored namespace.
 *
 * @return array<string,array<string,mixed>>
 */
function perm_namespace_class_exact_overrides(): array
{
    return defined('PERM_NAMESPACE_CLASS_EXACT_OVERRIDES') && is_array(PERM_NAMESPACE_CLASS_EXACT_OVERRIDES)
        ? PERM_NAMESPACE_CLASS_EXACT_OVERRIDES
        : [];
}

/**
 * Exact override namespaces for a target class key.
 *
 * @return array<int,string>
 */
function perm_namespace_exact_override_values_for_class(string $classKey): array
{
    $target = strtolower(trim($classKey));
    $values = [];
    foreach (perm_namespace_class_exact_overrides() as $namespace => $override) {
        if (!is_array($override)) {
            continue;
        }
        $key = strtolower(trim((string)($override['key'] ?? '')));
        if ($key !== $target) {
            continue;
        }
        $ns = strtolower(trim((string)$namespace));
        if ($ns !== '') {
            $values[] = $ns;
        }
    }
    return array_values(array_unique($values));
}

/**
 * Exact override namespaces whose target class is not the provided one.
 *
 * @return array<int,string>
 */
function perm_namespace_exact_override_values_excluding_class(string $classKey): array
{
    $target = strtolower(trim($classKey));
    $values = [];
    foreach (perm_namespace_class_exact_overrides() as $namespace => $override) {
        if (!is_array($override)) {
            continue;
        }
        $key = strtolower(trim((string)($override['key'] ?? '')));
        $ns = strtolower(trim((string)$namespace));
        if ($ns === '' || $key === $target) {
            continue;
        }
        $values[] = $ns;
    }
    return array_values(array_unique($values));
}

/**
 * OEM namespace prefixes from config.
 */
function perm_oem_namespace_prefixes(): array
{
    foreach (perm_namespace_classes() as $def) {
        if (($def['key'] ?? '') === 'oem') {
            $prefixes = $def['prefixes'] ?? [];
            if (is_array($prefixes)) {
                return array_values(array_filter(array_map('strval', $prefixes)));
            }
        }
    }
    return [];
}

/**
 * Resolve a namespace into the configured namespace class definition.
 *
 * @return array{key:string,label:string,class_name:string,prefixes:array<int,string>}
 */
function perm_namespace_class_for(string $namespace): array
{
    $value = strtolower(trim($namespace));
    $override = perm_namespace_class_exact_overrides()[$value] ?? null;
    if (is_array($override)) {
        return [
            'key' => strtolower(trim((string)($override['key'] ?? 'anomalous'))),
            'label' => trim((string)($override['label'] ?? 'Anomalous')),
            'class_name' => trim((string)($override['class_name'] ?? 'err')),
            'prefixes' => [],
        ];
    }
    foreach (perm_namespace_classes() as $def) {
        $prefixes = $def['prefixes'] ?? [];
        if (!is_array($prefixes) || !$prefixes) {
            continue;
        }
        foreach ($prefixes as $prefix) {
            $prefixValue = strtolower(trim((string)$prefix));
            if ($prefixValue === '') {
                continue;
            }
            if ($value === $prefixValue || str_starts_with($value, $prefixValue . '.')) {
                return [
                    'key' => strtolower(trim((string)($def['key'] ?? 'anomalous'))),
                    'label' => trim((string)($def['label'] ?? 'Anomalous')),
                    'class_name' => trim((string)($def['class_name'] ?? 'err')),
                    'prefixes' => array_values(array_map('strval', $prefixes)),
                ];
            }
        }
    }

    return [
        'key' => 'anomalous',
        'label' => 'Anomalous',
        'class_name' => 'err',
        'prefixes' => [],
    ];
}

/**
 * Review posture for namespace registry rows.
 *
 * @return array{
 *   review_bucket:string,
 *   validation_label:string,
 *   review_hint:string
 * }
 */
function perm_namespace_review_profile_for(string $namespace): array
{
    $value = strtolower(trim($namespace));
    $override = perm_namespace_class_exact_overrides()[$value] ?? null;
    if (is_array($override)) {
        return [
            'review_bucket' => trim((string)($override['review_bucket'] ?? 'needs_review')),
            'validation_label' => trim((string)($override['validation_label'] ?? 'Needs review')),
            'review_hint' => trim((string)($override['review_hint'] ?? 'Known namespace exception to broad prefix heuristics.')),
        ];
    }

    if ($value === '') {
        return [
            'review_bucket' => 'needs_review',
            'validation_label' => 'Needs review',
            'review_hint' => 'Empty or malformed namespace value.',
        ];
    }

    if (
        str_contains($value, '.launcher')
        || str_ends_with($value, '.home')
        || str_contains($value, '.launcher2')
        || str_contains($value, '.launcher3')
        || str_contains($value, '.qqlauncher')
    ) {
        if (
            str_starts_with($value, 'com.huawei')
            || str_starts_with($value, 'com.oppo')
            || str_starts_with($value, 'com.samsung')
            || str_starts_with($value, 'com.sec')
            || str_starts_with($value, 'com.heytap')
            || str_starts_with($value, 'com.vivo')
            || str_starts_with($value, 'com.bbk')
            || str_starts_with($value, 'com.sony')
            || str_starts_with($value, 'com.sonymobile')
            || str_starts_with($value, 'com.sonyericsson')
            || str_starts_with($value, 'com.htc')
            || str_starts_with($value, 'com.lge')
            || str_starts_with($value, 'com.meizu')
            || str_starts_with($value, 'com.asus')
            || str_starts_with($value, 'com.lenovo')
            || str_starts_with($value, 'com.motorola')
            || str_starts_with($value, 'com.zte')
            || str_starts_with($value, 'com.nubia')
        ) {
            return [
                'review_bucket' => 'oem_launcher_home',
                'validation_label' => 'Likely OEM-adjacent',
                'review_hint' => 'OEM launcher/home ecosystem namespace. Valid vendor family signal, but not itself a permission authority source.',
            ];
        }
        return [
            'review_bucket' => 'third_party_launcher',
            'validation_label' => 'Likely non-OEM',
            'review_hint' => 'Third-party launcher/home ecosystem namespace rather than OEM firmware authority.',
        ];
    }

    if (
        str_starts_with($value, 'huawei.permission.')
        || str_starts_with($value, 'huawei.android.permission.')
        || str_starts_with($value, 'oppo.permission.')
        || str_starts_with($value, 'oplus.permission.')
        || str_starts_with($value, 'heytap.permission.')
        || str_ends_with($value, '.permission')
    ) {
        return [
            'review_bucket' => 'oem_permission_space',
            'validation_label' => 'Likely OEM',
            'review_hint' => 'Vendor permission namespace. Strong OEM candidate; verify against vendor system components or device behavior if needed.',
        ];
    }

    if (
        str_starts_with($value, 'com.huawei')
        || str_starts_with($value, 'com.oppo')
        || str_starts_with($value, 'com.coloros')
        || str_starts_with($value, 'com.nearme')
        || str_starts_with($value, 'com.heytap')
        || str_starts_with($value, 'com.oplus')
        || str_starts_with($value, 'com.samsung')
        || str_starts_with($value, 'com.sec')
        || str_starts_with($value, 'com.miui')
        || str_starts_with($value, 'com.xiaomi')
        || str_starts_with($value, 'com.vivo')
        || str_starts_with($value, 'com.bbk')
        || str_starts_with($value, 'com.lenovo')
        || str_starts_with($value, 'com.motorola')
        || str_starts_with($value, 'com.lge')
        || str_starts_with($value, 'com.meizu')
        || str_starts_with($value, 'com.sony')
        || str_starts_with($value, 'com.sonymobile')
        || str_starts_with($value, 'com.sonyericsson')
        || str_starts_with($value, 'com.htc')
        || str_starts_with($value, 'com.asus')
        || str_starts_with($value, 'com.zte')
        || str_starts_with($value, 'com.nubia')
    ) {
        return [
            'review_bucket' => 'oem_service_or_account',
            'validation_label' => 'Likely OEM-adjacent',
            'review_hint' => 'Vendor service/account/app-market namespace. Likely part of the vendor ecosystem, but not necessarily a permission-definition namespace.',
        ];
    }

    if (
        str_starts_with($value, 'me.everything.badger')
        || str_starts_with($value, 'com.anddoes.launcher')
        || str_starts_with($value, 'com.majeur.launcher')
        || str_starts_with($value, 'com.fede.launcher')
        || str_starts_with($value, 'org.adw')
        || str_starts_with($value, 'net.qihoo.launcher')
        || str_starts_with($value, 'com.tencent.qqlauncher')
    ) {
        return [
            'review_bucket' => 'third_party_sdk_or_launcher',
            'validation_label' => 'Likely non-OEM',
            'review_hint' => 'Known third-party launcher or badge/library ecosystem namespace, not vendor firmware authority.',
        ];
    }

    return [
        'review_bucket' => 'needs_review',
        'validation_label' => 'Needs review',
        'review_hint' => 'Namespace is not in the core/Google/OEM prefix sets. Review as app-specific, library, or anomalous drift.',
    ];
}

/**
 * Normalize a bucket value into a comparable key.
 */
function perm_bucket_key(?string $value): string
{
    $raw = strtoupper(trim((string)$value));
    if ($raw === '') return 'UNKNOWN';

    $raw = preg_replace('/[^A-Z0-9]+/', '_', $raw);
    $raw = preg_replace('/_+/', '_', $raw);
    return trim((string)$raw, '_');
}

/**
 * Map normalized bucket keys to display labels.
 */
function perm_bucket_label_map(): array
{
    $map = [];
    foreach (perm_bucket_definitions() as $def) {
        $label = (string)($def['label'] ?? '');
        $key = (string)($def['key'] ?? '');
        if ($label === '' || $key === '') continue;

        $map[perm_bucket_key($key)] = $label;
        $map[perm_bucket_key($label)] = $label;

        $aliases = $def['aliases'] ?? [];
        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                $alias = trim((string)$alias);
                if ($alias === '') continue;
                $map[perm_bucket_key($alias)] = $label;
            }
        }
    }
    return $map;
}

/**
 * Resolve a bucket value into a display label.
 */
function perm_bucket_label(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Unknown / Unclassified';
    }

    $map = perm_bucket_label_map();
    $key = perm_bucket_key($value);
    return $map[$key] ?? $value;
}
