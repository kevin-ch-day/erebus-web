<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/database/db_func.php';

final class PermissionIntelContractTest extends TestCase
{
    public function testEffectiveUnknownTriageStatusesExcludeGovernedResolvedResidue(): void
    {
        $keys = perm_effective_unknown_triage_status_keys();

        $this->assertSame(
            ['new', 'in_review', 'aosp_missing', 'oem_candidate', 'brand_spoof', 'malicious_dga'],
            $keys
        );
        $this->assertNotContains('app_defined', $keys);
        $this->assertNotContains('launcher_ecosystem', $keys);
        $this->assertNotContains('resolved_aosp', $keys);
        $this->assertNotContains('resolved_oem', $keys);
        $this->assertNotContains('gms_known', $keys);
        $this->assertNotContains('malformed', $keys);
    }

    public function testActionableWorkflowUnknownCountUsesEffectiveUnknownTruthSurface(): void
    {
        $this->assertSame(63, perm_actionable_workflow_unknown_count(63));
        $this->assertSame(0, perm_actionable_workflow_unknown_count(-12));
    }

    public function testPageTotalMetaRewritesPreviewCountsToAuthoritativeTotal(): void
    {
        $page = [
            'rows' => [['permission_string' => 'a'], ['permission_string' => 'b']],
            'meta' => [
                'page' => 1,
                'page_size' => 10,
                'total_count' => 10,
                'total_pages' => 1,
                'has_more' => false,
            ],
        ];

        $updated = db_android_permission_page_total_meta($page, 23);

        $this->assertSame(23, $updated['meta']['total_count']);
        $this->assertSame(3, $updated['meta']['total_pages']);
        $this->assertTrue($updated['meta']['has_more']);
    }

    public function testEffectiveUnknownMetricsQueryUsesEffectiveStatusSet(): void
    {
        $sql = sql_android_permission_effective_unknown_metrics();

        $this->assertStringContainsString("u.triage_status NOT IN ('new', 'in_review', 'aosp_missing', 'oem_candidate', 'brand_spoof', 'malicious_dga')", $sql);
        $this->assertStringNotContainsString("u.triage_status IN ('resolved_aosp', 'resolved_oem')", $sql);
    }

    public function testObservationMaterializationMapCoversGovernedResolvedStatuses(): void
    {
        $this->assertSame(
            ['classification' => 'OEM', 'bucket' => 'OEM_EXACT', 'rule_fired' => 'oem_dict'],
            perm_obs_materialization_for_triage_status('resolved_oem')
        );
        $this->assertSame(
            ['classification' => 'GOOGLE', 'bucket' => 'GOOGLE_GMS', 'rule_fired' => 'gms_namespace'],
            perm_obs_materialization_for_triage_status('gms_known')
        );
        $this->assertSame(
            ['classification' => 'OEM', 'bucket' => 'OEM_LAUNCHER_ECOSYSTEM', 'rule_fired' => 'launcher_ecosystem'],
            perm_obs_materialization_for_triage_status('launcher_ecosystem')
        );
        $this->assertSame(
            ['classification' => 'APP_DEFINED', 'bucket' => 'APP_DEFINED_OTHER', 'rule_fired' => 'default'],
            perm_obs_materialization_for_triage_status('app_defined')
        );
        $this->assertNull(perm_obs_materialization_for_triage_status('resolved_aosp'));
    }

    public function testNamespaceClassificationCoversHuaweiPermissionAndKnownPlayNamespace(): void
    {
        $this->assertSame('oem', perm_namespace_class_for('huawei.permission.ACCESS_LOCATION_SERVICE')['key']);
        $this->assertSame('expected', perm_namespace_class_for('com.android.vending')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('me.everything.badger')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.nttdocomo.android')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.jb.gokeyboard')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('telecom.mdesk.permission')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.android.mylauncher')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.ebproductions.android')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('ir.devixor.app')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.scrap.praise')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('ir.shz.shzkisi')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.asus.msa')['key']);
        $this->assertSame('known_ecosystem', perm_namespace_class_for('com.moutai.mall')['key']);
    }

    public function testGovernedObservationReconcileQueryTargetsOnlyGovernedStatuses(): void
    {
        $sql = sql_android_permission_obs_reclassify_by_governed_status();

        $this->assertStringContainsString("IN ('resolved_oem', 'gms_known', 'launcher_ecosystem', 'app_defined')", $sql);
        $this->assertStringContainsString("THEN 'OEM_LAUNCHER_ECOSYSTEM'", $sql);
        $this->assertStringNotContainsString("= 'resolved_aosp' THEN 'AOSP'", $sql);
    }

    public function testLedgerDiagnosticsSuppressesExactGovernedResidueWithoutUnknownBurden(): void
    {
        require_once dirname(__DIR__, 2) . '/app/database/services/android_service_reporting.php';

        $fastReflection = new ReflectionFunction('db_android_permission_ledger_diagnostics_page_fast');
        $fastFile = file($fastReflection->getFileName());
        $fastBody = implode('', array_slice(
            $fastFile,
            $fastReflection->getStartLine() - 1,
            $fastReflection->getEndLine() - $fastReflection->getStartLine() + 1
        ));

        $overviewReflection = new ReflectionFunction('db_android_permission_ledger_diagnostics_overview');
        $overviewFile = file($overviewReflection->getFileName());
        $overviewBody = implode('', array_slice(
            $overviewFile,
            $overviewReflection->getStartLine() - 1,
            $overviewReflection->getEndLine() - $overviewReflection->getStartLine() + 1
        ));

        $this->assertStringContainsString("r.current_unknown_samples = 0", $fastBody);
        $this->assertStringContainsString("r.has_obs_sample = 1", $fastBody);
        $this->assertStringContainsString("IN ('resolved_aosp', 'resolved_oem', 'gms_known', 'launcher_ecosystem')", $fastBody);

        $this->assertStringContainsString("r.current_unknown_samples = 0", $overviewBody);
        $this->assertStringContainsString("r.current_total_samples > 0", $overviewBody);
        $this->assertStringContainsString("IN ('resolved_aosp', 'resolved_oem', 'gms_known', 'launcher_ecosystem')", $overviewBody);
    }

    public function testDynamicReceiverBatchRepairTargetsOnlyExactNewSuffixRows(): void
    {
        $sql = sql_unknown_permission_batch_promote_dynamic_receiver_tokens();

        $this->assertStringContainsString("triage_status = 'app_defined'", $sql);
        $this->assertStringContainsString("WHERE triage_status = 'new'", $sql);
        $this->assertStringContainsString("permission_string LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'", $sql);
    }

    public function testNotedResolvedOemBatchRepairTargetsOnlyRecordedOemPromotions(): void
    {
        $sql = sql_unknown_permission_batch_promote_noted_resolved_oem();

        $this->assertStringContainsString("SET triage_status = 'resolved_oem'", $sql);
        $this->assertStringContainsString("WHERE triage_status = 'oem_candidate'", $sql);
        $this->assertStringContainsString("notes LIKE '%promoted to resolved_oem%'", $sql);
    }

    public function testDynamicReceiverArtifactBatchRepairTargetsOnlyTrailingGarbageVariants(): void
    {
        $sql = sql_unknown_permission_batch_retire_dynamic_receiver_artifacts();

        $this->assertStringContainsString("SET\n            triage_status = 'malformed'", $sql);
        $this->assertStringContainsString("WHERE triage_status IN ('new', 'oem_candidate')", $sql);
        $this->assertStringContainsString("permission_string LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION%'", $sql);
        $this->assertStringContainsString("permission_string NOT LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'", $sql);
    }

    public function testZeroTelemetryNewAppDefinedBatchRepairExcludesGovernedPrefixes(): void
    {
        $sql = sql_unknown_permission_batch_promote_zero_telemetry_new_app_defined();

        $this->assertStringContainsString("WHERE triage_status = 'new'", $sql);
        $this->assertStringContainsString("seen_count = 0", $sql);
        $this->assertStringContainsString("(example_package_name IS NULL OR TRIM(example_package_name) = '')", $sql);
        $this->assertStringContainsString("permission_string NOT LIKE 'android.permission.%'", $sql);
        $this->assertStringContainsString("permission_string NOT LIKE 'com.huawei.%'", $sql);
        $this->assertStringContainsString("permission_string NOT LIKE 'com.google.%'", $sql);
    }

    public function testMisspelledAospFalsePositiveRepairTargetsOnlyKnownTypo(): void
    {
        $sql = sql_unknown_permission_batch_retire_misspelled_aosp_false_positives();

        $this->assertStringContainsString("triage_status = 'malformed'", $sql);
        $this->assertStringContainsString("WHERE triage_status = 'aosp_missing'", $sql);
        $this->assertStringContainsString("permission_string = 'android.permission.change_configuratison'", $sql);
        $this->assertStringContainsString("CHANGE_CONFIGURATION", $sql);
    }

    public function testObservedOemCandidatePromotionBuildsOemDictionaryRows(): void
    {
        $sql = sql_oem_candidate_batch_insert_into_oem_dict();

        $this->assertStringContainsString("INSERT INTO", $sql);
        $this->assertStringContainsString("WHERE u.triage_status = 'oem_candidate'", $sql);
        $this->assertStringContainsString("u.permission_string LIKE 'com.huawei.%'", $sql);
        $this->assertStringContainsString("u.permission_string LIKE 'com.samsung.%'", $sql);
        $this->assertStringContainsString("u.permission_string LIKE 'com.sonymobile.%'", $sql);
        $this->assertStringContainsString("d.permission_string IS NULL", $sql);
    }

    public function testObservedOemCandidatePromotionMarksUnknownRowsResolved(): void
    {
        $sql = sql_unknown_permission_batch_promote_oem_candidates_to_resolved();

        $this->assertStringContainsString("SET\n            triage_status = 'resolved_oem'", $sql);
        $this->assertStringContainsString("WHERE triage_status = 'oem_candidate'", $sql);
        $this->assertStringContainsString("permission_string LIKE 'com.huawei.%'", $sql);
    }

    public function testSuspiciousOemAutoseedCleanupTargetsOnlySuspiciousSeededRows(): void
    {
        $sql = sql_oem_dict_delete_suspicious_autoseed_rows();

        $this->assertStringContainsString("DELETE d", $sql);
        $this->assertStringContainsString("u.triage_status IN ('brand_spoof', 'malicious_dga')", $sql);
        $this->assertStringContainsString("d.notes LIKE '%[auto-seed] workflow oem_candidate; queued for apply%'", $sql);
    }

    public function testSuspiciousObsReclassifyUsesAppDefinedMaterialization(): void
    {
        $sql = sql_android_permission_obs_reclassify_suspicious_app_defined();

        $this->assertStringContainsString("o.classification = 'APP_DEFINED'", $sql);
        $this->assertStringContainsString("o.bucket = 'APP_DEFINED_OTHER'", $sql);
        $this->assertStringContainsString("o.rule_fired = 'suspicious_app_defined'", $sql);
        $this->assertStringContainsString("u.triage_status IN ('brand_spoof', 'malicious_dga')", $sql);
    }

    public function testDynamicReceiverArtifactObsReclassifyUsesAppDefinedMaterialization(): void
    {
        $sql = sql_android_permission_obs_reclassify_dynamic_receiver_artifacts();

        $this->assertStringContainsString("o.classification = 'APP_DEFINED'", $sql);
        $this->assertStringContainsString("o.bucket = 'APP_DEFINED_OTHER'", $sql);
        $this->assertStringContainsString("o.rule_fired = 'malformed_dynamic_receiver_artifact'", $sql);
        $this->assertStringContainsString("u.triage_status = 'malformed'", $sql);
        $this->assertStringContainsString("u.permission_string LIKE '%DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION%'", $sql);
        $this->assertStringContainsString("u.permission_string NOT LIKE '%.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION'", $sql);
    }

    public function testNewUnknown24hExcludesDynamicReceiverSuffixRecurrence(): void
    {
        $sql = sql_android_permission_new_unknowns_24h();

        $this->assertStringContainsString("NOT LIKE '%.dynamic_receiver_not_exported_permission'", $sql);
    }

    public function testEffectiveUnknownMetricsExcludeDynamicReceiverSuffixRecurrence(): void
    {
        $sql = sql_android_permission_effective_unknown_metrics();

        $this->assertStringContainsString("u.triage_status = 'new'", $sql);
        $this->assertStringContainsString("LOWER(TRIM(u.permission_string)) LIKE '%.dynamic_receiver_not_exported_permission'", $sql);
    }

    public function testCurrentUnknownReviewPageDemotesDynamicReceiverSuffixRecurrence(): void
    {
        $sql = sql_android_permission_current_unknown_review_page();

        $this->assertStringContainsString("THEN 'resolved_or_dictionary_known'", $sql);
        $this->assertStringContainsString("LOWER(TRIM(u.permission_string)) LIKE '%.dynamic_receiver_not_exported_permission'", $sql);
    }

    public function testSecuritySensitiveUnknownsExcludeDynamicReceiverSuffixRecurrence(): void
    {
        $sql = sql_android_permission_security_sensitive_unknowns();

        $this->assertStringContainsString("NOT LIKE '%.dynamic_receiver_not_exported_permission'", $sql);
    }

    public function testClassificationGapWorkflowStatesSeparateEvidenceMissingAndConflicts(): void
    {
        $this->assertSame(
            'behavior_strong_vt_missing',
            db_android_permission_gap_workflow_state('missing_vt_confidence_for_strong_attack_surface')
        );
        $this->assertSame(
            'evidence_missing',
            db_android_permission_gap_workflow_state('missing_vt_confidence_for_attack_surface')
        );
        $this->assertSame(
            'behavior_vt_conflict',
            db_android_permission_gap_workflow_state('strong_attack_surface_low_vt_action')
        );
        $this->assertSame(
            'behavior_vt_conflict',
            db_android_permission_gap_workflow_state('strong_attack_surface_weak_vt_confidence')
        );
    }

    public function testClassificationGapWorkflowLabelsAreOperatorReadable(): void
    {
        $this->assertSame(
            'Behavior strong, VT evidence missing',
            db_android_permission_gap_workflow_label('behavior_strong_vt_missing')
        );
        $this->assertSame(
            'Evidence missing',
            db_android_permission_gap_workflow_label('evidence_missing')
        );
        $this->assertSame(
            'Behavior/VT conflict',
            db_android_permission_gap_workflow_label('behavior_vt_conflict')
        );
    }

    public function testClassificationGapSampleKeyPrefersShaThenSampleId(): void
    {
        $this->assertSame(
            'sha:abcdef1234',
            db_android_permission_gap_sample_key(['sha256' => 'ABCDEF1234', 'sample_id' => 99])
        );
        $this->assertSame(
            'id:99',
            db_android_permission_gap_sample_key(['sample_id' => 99])
        );
        $this->assertSame(
            '',
            db_android_permission_gap_sample_key([])
        );
    }
}
