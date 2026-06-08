<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/database/db_func.php';

final class StackServiceTest extends TestCase
{
    public function testStackAuditHasExpectedTopLevelSections(): void
    {
        $audit = db_stack_audit();

        $this->assertIsArray($audit);
        $this->assertArrayHasKey('runtime', $audit);
        $this->assertArrayHasKey('capabilities', $audit);
        $this->assertArrayHasKey('architecture_profile', $audit);
        $this->assertArrayHasKey('gap_inventory', $audit);
        $this->assertArrayHasKey('upgrade_tracks', $audit);
        $this->assertArrayHasKey('research_anchors', $audit);
        $this->assertArrayHasKey('cli_entrypoints', $audit);
        $this->assertTrue((bool)($audit['capabilities']['openapi_present'] ?? false));
    }

    public function testCliEntrypointsExposeFamilyAndStackCommands(): void
    {
        $audit = db_stack_audit();
        $entrypoints = $audit['cli_entrypoints'] ?? [];

        $this->assertIsArray($entrypoints);
        $commands = array_map(static fn(array $row): string => (string)($row['command'] ?? ''), $entrypoints);

        $this->assertContains('php bin/erebus_console.php family:summary --format=table', $commands);
        $this->assertContains('php bin/erebus_console.php family:export --decision-mode=repair_after_alias_review --format=csv', $commands);
        $this->assertContains('php bin/erebus_console.php family:apply-plan --decision-mode=repair_after_alias_review --format=sql', $commands);
        $this->assertContains('php bin/erebus_console.php family:pairs --format=table', $commands);
        $this->assertContains('php bin/erebus_console.php family:drivers --format=table', $commands);
        $this->assertContains('php bin/erebus_console.php family:governance --format=table', $commands);
        $this->assertContains('php bin/erebus_console.php stack:audit --format=json', $commands);
    }

    public function testStackAuditGapInventoryNoLongerFlagsUiCoverageOrOpenApiMissing(): void
    {
        $audit = db_stack_audit();
        $gaps = $audit['gap_inventory'] ?? [];

        $this->assertIsArray($gaps);
        $gapKeys = array_map(static fn(array $row): string => (string)($row['key'] ?? ''), $gaps);

        $this->assertNotContains('ui_coverage_thin', $gapKeys);
        $this->assertNotContains('openapi_contract_missing', $gapKeys);
    }
}
