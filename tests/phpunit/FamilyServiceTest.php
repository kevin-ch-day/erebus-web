<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/database/db_func.php';

final class FamilyServiceTest extends TestCase
{
    public function testGovernedSignalAliasMapIncludesKnownResolvedVariants(): void
    {
        $aliases = db_family_taxonomy_signal_alias_map();

        $this->assertSame('hiddenads', $aliases['cloput'] ?? null);
        $this->assertSame('actionspy', $aliases['axespy'] ?? null);
        $this->assertSame('infostealer', $aliases['codoor'] ?? null);
        $this->assertSame('projectspy', $aliases['covidspy'] ?? null);
        $this->assertSame('fakecall', $aliases['fakecalls'] ?? null);
        $this->assertSame('coyote', $aliases['boxter'] ?? null);
        $this->assertSame('irata', $aliases['realrat'] ?? null);
        $this->assertSame('darkcomet', $aliases['darkkomet'] ?? null);
        $this->assertSame('gravityrat', $aliases['gravity'] ?? null);
        $this->assertSame('monokle', $aliases['monocle'] ?? null);
        $this->assertSame('rafel', $aliases['refalrat'] ?? null);
        $this->assertSame('slockerwannacry', $aliases['wannalocker'] ?? null);
        $this->assertSame('bluetraveller', $aliases['albaniiutas'] ?? null);
    }

    public function testStackFloorHeuristicsRecognizeGenericTokens(): void
    {
        $generic = db_family_taxonomy_generic_tokens();

        $this->assertContains('andr', $generic);
        $this->assertContains('msil', $generic);
        $this->assertContains('bankbot', $generic);
        $this->assertContains('fakeapp', $generic);
        $this->assertContains('genericfca', $generic);
        $this->assertContains('java', $generic);
        $this->assertContains('spyware', $generic);
        $this->assertContains('masqueradingmalware', $generic);
        $this->assertContains('rootkit', $generic);
        $this->assertContains('vbransom', $generic);
        $this->assertContains('w97m', $generic);
        $this->assertContains('o97m', $generic);
    }

    public function testHoldSignalHeuristicsRecognizeNoisyWrapperAndSmsLabels(): void
    {
        $holdOnly = db_family_taxonomy_hold_signal_tokens();

        $this->assertContains('agentb', $holdOnly);
        $this->assertContains('hqwar', $holdOnly);
        $this->assertContains('smsspy', $holdOnly);
        $this->assertContains('metasploit', $holdOnly);
        $this->assertContains('meterpreter', $holdOnly);
        $this->assertContains('locker', $holdOnly);
        $this->assertContains('molerats', $holdOnly);
        $this->assertContains('blacklister', $holdOnly);
        $this->assertContains('donot', $holdOnly);
        $this->assertContains('dorxor', $holdOnly);
        $this->assertContains('penguin', $holdOnly);
        $this->assertContains('secimage', $holdOnly);
        $this->assertContains('knobot', $holdOnly);
        $this->assertContains('spyagent', $holdOnly);
        $this->assertContains('androrat', $holdOnly);
        $this->assertContains('subscriber', $holdOnly);
        $this->assertContains('hidden', $holdOnly);
        $this->assertContains('tencentprotect', $holdOnly);
        $this->assertContains('smssend', $holdOnly);
        $this->assertContains('lazarus', $holdOnly);
        $this->assertContains('clipbanker', $holdOnly);
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('agentb'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('hqwar'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('smsspy'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('metasploit'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('meterpreter'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('locker'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('molerats'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('blacklister'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('donot'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('dorxor'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('penguin'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('secimage'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('knobot'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('spyagent'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('androrat'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('subscriber'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('hidden'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('tencentprotect'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('smssend'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('lazarus'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('clipbanker'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('ajllv'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('ccikdn'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('dacic'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('sagnt'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('boogr'));
        $this->assertTrue(db_family_taxonomy_signal_token_is_unstable('brmon'));
    }

    public function testPlaceholderCatalogWithGenericSignalGetsHeld(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'andr',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('high', $fix['suggested_fix_confidence'] ?? null);
    }

    public function testDominantExistingFamilyTargetRequiresStrongExistingAnchor(): void
    {
        $result = db_family_taxonomy_dominant_existing_family_target('smsspy');

        $this->assertSame('IRATA', $result['label'] ?? null);
        $this->assertGreaterThanOrEqual(25, (int)($result['top_count'] ?? 0));
        $this->assertGreaterThan(0.65, (float)($result['dominance'] ?? 0.0));
        $this->assertGreaterThanOrEqual(2, (int)($result['specific_family_count'] ?? 0));
    }

    public function testHqwarIsHeldAsUnstableSignalInsteadOfSemanticConflict(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'AppLite',
            'popular_threat_name' => 'hqwar',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testRowPatternSummaryUsesDisjointGovernedBuckets(): void
    {
        $rows = [
            [
                'alignment_status' => 'mismatch',
                'family_label' => 'Unknown',
                'popular_threat_name' => 'andr',
            ],
            [
                'alignment_status' => 'mismatch',
                'family_label' => 'Trojan',
                'popular_threat_name' => 'RewardSteal',
            ],
            [
                'alignment_status' => 'mismatch',
                'family_label' => 'AppLite',
                'popular_threat_name' => 'boqx',
            ],
            [
                'alignment_status' => 'mismatch',
                'family_label' => 'IRATA',
                'popular_threat_name' => 'SpyNote',
            ],
            [
                'alignment_status' => 'mismatch',
                'family_label' => 'FatBoyPanel',
                'popular_threat_name' => 'rewardsteal',
                'vt_suggested_label' => 'trojan.rewardsteal/bankbot',
            ],
        ];

        $summary = db_family_taxonomy_row_pattern_summary($rows);

        $this->assertSame(1, $summary['unknown_catalog_rows']);
        $this->assertSame(1, $summary['generic_catalog_rows']);
        $this->assertSame(1, $summary['generic_signal_rows']);
        $this->assertSame(1, $summary['short_signal_token_rows']);
        $this->assertSame(1, $summary['signal_overlap_rows']);
        $this->assertSame(2, $summary['spy_bank_loader_signal_rows']);
    }

    public function testW97mIsHeldAsGenericSignalInsteadOfSemanticConflict(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Emotet',
            'popular_threat_name' => 'w97m',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
    }

    public function testWeakShortSignalTokenGetsItsOwnIssueLane(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'NukeSped',
            'popular_threat_name' => 'fjer',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('short_signal_token', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsRefalratToRafel(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Rafel',
            'popular_threat_name' => 'refalrat',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsAlbaniiutasToBlueTraveller(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'BlueTraveller',
            'popular_threat_name' => 'albaniiutas',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsAxespyToActionSpy(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'ActionSpy',
            'popular_threat_name' => 'axespy',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsWannalockerToSlockerWannacry(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Slocker Wannacry',
            'popular_threat_name' => 'wannalocker',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsWrobaToXloader(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Xloader',
            'popular_threat_name' => 'wroba',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testGovernedAliasResolutionAlignsCoperToOcto(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Octo',
            'popular_threat_name' => 'coper',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
    }

    public function testRowPatternMatcherTreatsGenericCatalogAsNonUnknownGenericOnly(): void
    {
        $unknownRow = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'RewardSteal',
        ];
        $genericRow = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Trojan',
            'popular_threat_name' => 'RewardSteal',
        ];

        $this->assertFalse(db_family_taxonomy_row_matches_pattern($unknownRow, 'generic_catalog'));
        $this->assertTrue(db_family_taxonomy_row_matches_pattern($genericRow, 'generic_catalog'));
        $this->assertTrue(db_family_taxonomy_row_matches_pattern($unknownRow, 'unknown_catalog'));
    }

    public function testMetasploitIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'metasploit',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testFakePlayerIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'fakeplayer',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('FakePlayer', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testFakeAdBlockerIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'fakeadblocker',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('FakeAdBlocker', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testRewardStealIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'rewardsteal',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('RewardSteal', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testPhoneSpyIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'phonespy',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('PhoneSpy', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testCovidSpyIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'covidspy',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('projectSpy', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testDwphonIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'dwphon',
            'confidence_bucket' => 'strong',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Dwphon', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testRootnikIsRecognizedAsGovernedCanonicalFamilyTarget(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'rootnik',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Rootnik', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testCodoorIsRecognizedAsGovernedAliasToExistingInfoStealerFamily(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'codoor',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('infoStealer', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testCloputIsRecognizedAsGovernedAliasToExistingHiddenAdsFamily(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'cloput',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('HiddenAds', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testSmsspyIsHeldAsUnstableSignalInsteadOfSemanticConflict(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'IRATA',
            'popular_threat_name' => 'smsspy',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testPlaceholderCatalogWithSingleExistingSpecificTargetBecomesRepairCandidate(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'refalrat',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Rafel', $fix['suggested_target_family'] ?? null);
        $this->assertSame('repair_now_candidate', $decision['decision_mode'] ?? null);
    }

    public function testTencentProtectIsHeldAsUnstableSignalInsteadOfGovernedPlaceholder(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Adware',
            'popular_threat_name' => 'tencentprotect',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
        $this->assertSame('hold_generic_signal', $decision['decision_mode'] ?? null);
    }

    public function testBlacklisterIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'blacklister',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
    }

    public function testSecimageIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'secimage',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
    }

    public function testSubscriberIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Adware',
            'popular_threat_name' => 'subscriber',
            'confidence_bucket' => 'high',
        ];

        $fix = db_family_taxonomy_suggested_fix($row);
        $decision = db_family_taxonomy_decision_model([
            'issue_kind' => 'placeholder_catalog',
            'suggested_fix_action' => $fix['suggested_fix_action'] ?? '',
            'suggested_fix_confidence' => $fix['suggested_fix_confidence'] ?? '',
        ]);

        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
        $this->assertSame('hold_generic_signal', $decision['decision_mode'] ?? null);
    }

    public function testHiddenIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'hidden',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
    }

    public function testPenguinIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'penguin',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
    }

    public function testDorxorAdoptsGovernedFamilyNowThatCatalogTruthExists(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'dorxor',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Dorxor', $fix['suggested_target_family'] ?? null);
    }

    public function testTencentProtectPairResolutionHoldsSignalInsteadOfSuggestingPromotion(): void
    {
        $resolution = db_family_taxonomy_pair_resolution('Adware', 'tencentprotect');

        $this->assertSame('hold_catalog_generic_signal', $resolution['resolution_action'] ?? null);
        $this->assertSame('high', $resolution['resolution_confidence'] ?? null);
        $this->assertSame('Adware', $resolution['resolution_target_family'] ?? null);
    }

    public function testMoleratsIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'molerats',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Molerats', $fix['suggested_target_family'] ?? null);
    }

    public function testSmssendIsHeldAsUnstableSignalInsteadOfPlaceholderGovernance(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Unknown',
            'popular_threat_name' => 'smssend',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testBankbotIsHeldAsUnstableSignalInsteadOfAliasCandidate(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'BlankBot',
            'popular_threat_name' => 'bankbot',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testFakewalletIsHeldAsUnstableSignalInsteadOfAliasCandidate(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'TrezorFakeWallet',
            'popular_threat_name' => 'fakewallet',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testDistinctInventoryAnchorsAreNotTreatedAsAliasCandidates(): void
    {
        $inventory = [
            'trickbot' => ['label' => 'Trickbot', 'count' => 10],
            'trickmo' => ['label' => 'TrickMo', 'count' => 6],
        ];

        $this->assertTrue(db_family_taxonomy_has_distinct_inventory_anchors('trickbot', 'trickmo', $inventory));
    }

    public function testTrickbotcryptResolvesToGovernedTrickbotAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Trickbot',
            'popular_threat_name' => 'trickbotcrypt',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Trickbot', $fix['suggested_target_family'] ?? null);
    }

    public function testGoodnewsResolvesToGovernedSmswormAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'SMSWorm',
            'popular_threat_name' => 'goodnews',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('SMSWorm', $fix['suggested_target_family'] ?? null);
    }

    public function testTeddadResolvesToGovernedScyllaAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Scylla',
            'popular_threat_name' => 'teddad',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Scylla', $fix['suggested_target_family'] ?? null);
    }

    public function testFhiiResolvesToGovernedTerracottaAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'TERRACOTTA',
            'popular_threat_name' => 'fhii',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('TERRACOTTA', $fix['suggested_target_family'] ?? null);
    }

    public function testPythonIsHeldAsUnstableSignalInsteadOfAliasCandidate(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Anubis',
            'popular_threat_name' => 'python',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testOfficeIsHeldAsUnstableSignalInsteadOfSemanticConflict(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'TrickBot',
            'popular_threat_name' => 'office',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testVncspyResolvesToGovernedPromptspyAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'PromptSpy',
            'popular_threat_name' => 'vncspy',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('PromptSpy', $fix['suggested_target_family'] ?? null);
    }

    public function testMalformedIsHeldAsUnstableSignalInsteadOfSemanticConflict(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'deVixor',
            'popular_threat_name' => 'malformed',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testAgNumericDetectionTokenIsHeldAsUnstableSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'deVixor',
            'popular_threat_name' => 'ag1557851',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testBankIsHeldAsGenericDetectorSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'FatBoyPanel',
            'popular_threat_name' => 'bank',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testOpaqueDetectorTokenIsHeldAsUnstableSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Donot',
            'popular_threat_name' => 'kylk',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testPackerStyleSignalIsHeldAsUnstableSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Gigabud',
            'popular_threat_name' => 'jiagu',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testAutopayIsHeldAsGenericLureSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'TangleBot',
            'popular_threat_name' => 'autopay',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testHiddenadIsHeldAsGenericConcealmentSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Joker',
            'popular_threat_name' => 'hiddenad',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testScamappIsHeldAsGenericSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Gigabud',
            'popular_threat_name' => 'scamapp',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
    }

    public function testHiddenadHrxjaIsTreatedAsPlaceholderCatalogDebt(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'HiddenAd.HRXJA',
            'popular_threat_name' => 'hiddad',
            'vt_suggested_label' => 'trojan.hiddad/hiddenads',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('placeholder_catalog', $issue['issue_kind'] ?? null);
        $this->assertSame('adopt_signal_family', $fix['suggested_fix_action'] ?? null);
    }

    public function testBoxterResolvesToCoyoteAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Coyote',
            'vt_suggested_label' => 'trojan.boxter/coyote',
            'popular_threat_name' => 'boxter',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('Coyote', $fix['suggested_target_family'] ?? null);
    }

    public function testSecondaryVtTokenMatchResolvesNukespedConflictSafely(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'NukeSped',
            'popular_threat_name' => 'tigerrat',
            'vt_suggested_label' => 'trojan.tigerrat/nukesped',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('NukeSped', $fix['suggested_target_family'] ?? null);
    }

    public function testPrimaryFamilyWithGenericSecondaryTokenIsHeldAsNoisySignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'GhostClicker',
            'popular_threat_name' => 'hiddad',
            'vt_suggested_label' => 'trojan.hiddad/andr',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('signal_overlap', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_signal_overlap', $fix['suggested_fix_action'] ?? null);
    }

    public function testCompositeSignalWithAliasStyleSecondaryTokenIsHeldAsNoisySignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Anubis',
            'popular_threat_name' => 'spynote',
            'vt_suggested_label' => 'trojan.spynote/spymax',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('signal_overlap', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_signal_overlap', $fix['suggested_fix_action'] ?? null);
    }

    public function testCompositeSignalWithGenericSecondaryTokenIsHeldAsNoisySignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'FatBoyPanel',
            'popular_threat_name' => 'rewardsteal',
            'vt_suggested_label' => 'trojan.rewardsteal/bankbot',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('signal_overlap', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_signal_overlap', $fix['suggested_fix_action'] ?? null);
    }

    public function testCompositeSignalWithCanonicalSecondaryAliasTokenIsHeldAsNoisySignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'AntiDot',
            'popular_threat_name' => 'jocker',
            'vt_suggested_label' => 'trojan.jocker/joker',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('signal_overlap', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_signal_overlap', $fix['suggested_fix_action'] ?? null);
    }

    public function testSpyagentIsHeldAsDetectorStyleSignal(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'Gigabud',
            'popular_threat_name' => 'spyagent',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('generic_signal', $issue['issue_kind'] ?? null);
        $this->assertSame('hold_generic_signal', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('', $fix['suggested_target_family'] ?? null);
    }

    public function testRealratResolvesToIrataAlias(): void
    {
        $row = [
            'alignment_status' => 'mismatch',
            'family_label' => 'IRATA',
            'popular_threat_name' => 'realrat',
            'confidence_bucket' => 'high',
        ];

        $issue = db_family_taxonomy_row_issue($row);
        $fix = db_family_taxonomy_suggested_fix($row);

        $this->assertSame('alias_resolved', $issue['issue_kind'] ?? null);
        $this->assertSame('keep_catalog_use_alias_map', $fix['suggested_fix_action'] ?? null);
        $this->assertSame('IRATA', $fix['suggested_target_family'] ?? null);
    }

    public function testApplyPlanGroupsSafeRepairRowsAndExcludesUnsupportedOnes(): void
    {
        $plan = db_family_taxonomy_apply_plan_from_rows([
            [
                'sample_id' => 101,
                'family_label' => 'TrickBot',
                'popular_threat_name' => 'trickbotcrypt',
                'confidence_bucket' => 'high',
                'decision_mode' => 'repair_after_alias_review',
                'suggested_fix_action' => 'canonicalize_catalog_alias',
                'suggested_target_family' => 'Trickbot',
            ],
            [
                'sample_id' => 102,
                'family_label' => 'Trickbot',
                'popular_threat_name' => 'trickmo',
                'confidence_bucket' => 'high',
                'decision_mode' => 'repair_after_alias_review',
                'suggested_fix_action' => 'canonicalize_catalog_alias',
                'suggested_target_family' => 'Trickbot',
            ],
            [
                'sample_id' => 103,
                'family_label' => 'Unknown',
                'popular_threat_name' => 'rewardsteal',
                'confidence_bucket' => 'high',
                'decision_mode' => 'ask_why_first',
                'suggested_fix_action' => 'needs_family_governance',
                'suggested_target_family' => '',
            ],
        ]);

        $this->assertTrue((bool)($plan['dry_run'] ?? false));
        $this->assertSame(1, (int)($plan['summary']['plan_group_count'] ?? 0));
        $this->assertSame(1, (int)($plan['summary']['candidate_rows'] ?? 0));
        $this->assertSame(2, (int)($plan['summary']['excluded_rows'] ?? 0));
        $this->assertSame(1, (int)($plan['summary']['excluded_reasons']['already_at_target_family'] ?? 0));

        $rows = $plan['plan_rows'] ?? [];
        $this->assertCount(1, $rows);
        $this->assertSame('canonicalize_catalog_alias', $rows[0]['plan_action'] ?? null);
        $this->assertSame('Trickbot', $rows[0]['target_family'] ?? null);
        $this->assertSame([101], $rows[0]['sample_ids'] ?? null);
        $this->assertStringContainsString('UPDATE', (string)($rows[0]['sql_preview'] ?? ''));
    }

    public function testGovernanceInventoryGroupsUntargetedConflictDriversByPair(): void
    {
        $inventory = db_family_taxonomy_governance_inventory([
            [
                'sample_id' => 2389,
                'family_label' => 'Gigabud',
                'popular_threat_name' => 'spyagent',
                'confidence_bucket' => 'high',
                'issue_kind' => 'semantic_conflict',
                'suggested_fix_action' => 'manual_family_adjudication',
                'suggested_target_family' => '',
                'decision_mode' => 'ask_why_first',
                'decision_priority' => 'high',
            ],
            [
                'sample_id' => 2354,
                'family_label' => 'Gigabud',
                'popular_threat_name' => 'spyagent',
                'confidence_bucket' => 'high',
                'issue_kind' => 'semantic_conflict',
                'suggested_fix_action' => 'manual_family_adjudication',
                'suggested_target_family' => '',
                'decision_mode' => 'ask_why_first',
                'decision_priority' => 'high',
            ],
            [
                'sample_id' => 796,
                'family_label' => 'IRATA',
                'popular_threat_name' => 'smsspy',
                'confidence_bucket' => 'high',
                'issue_kind' => 'placeholder_catalog',
                'suggested_fix_action' => 'needs_family_governance',
                'suggested_target_family' => 'IRATA',
                'decision_mode' => 'ask_why_first',
                'decision_priority' => 'high',
            ],
        ]);

        $this->assertSame(3, (int)($inventory['total_rows'] ?? 0));
        $this->assertSame(1, (int)($inventory['targeted_rows'] ?? 0));
        $this->assertSame(2, (int)($inventory['untargeted_rows'] ?? 0));
        $this->assertSame(2, (int)($inventory['untargeted_top_signal_labels']['spyagent'] ?? 0));
        $this->assertSame(2, (int)($inventory['untargeted_top_catalog_labels']['Gigabud'] ?? 0));

        $pairs = $inventory['untargeted_pair_groups'] ?? [];
        $this->assertCount(1, $pairs);
        $this->assertSame('Gigabud', $pairs[0]['catalog_family'] ?? null);
        $this->assertSame('spyagent', $pairs[0]['signal_family'] ?? null);
        $this->assertSame(2, (int)($pairs[0]['row_count'] ?? 0));
        $this->assertSame('semantic_conflict', $pairs[0]['dominant_issue_kind'] ?? null);
        $this->assertSame('manual_family_adjudication', $pairs[0]['dominant_action'] ?? null);
    }
}
