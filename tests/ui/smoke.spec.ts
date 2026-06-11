import { expect, test, type Page } from '@playwright/test';

const healthPayload = {
  ok: true,
  data: {
    system_control: {
      hold_until_utc: '',
      hold_reason_code: '',
    },
    metrics: {
      eligible_now: 4,
      processing_now: 1,
      error_count: 0,
      retry_wait_count: 2,
      stale_claims: 0,
      reason_breakdown: [
        { reason_code: 'RATE_LIMIT', count: 3 },
        { reason_code: 'MAINTENANCE', count: 1 },
      ],
    },
  },
  meta: {
    generated_at_utc: '2026-05-25 04:00:00',
    schema_surface: 'health_v1',
    request_id: 'deadbeef',
    server_utc_now: '2026-05-25 04:00:01 UTC',
  },
};

function trackConsoleErrors(page: Page) {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  page.on('pageerror', (error) => {
    errors.push(error.message);
  });
  return errors;
}

const permissionIntelPayload = {
  ok: true,
  data: {
    taxonomy: {
      version: '1.0',
      updated_at_utc: '2026-05-17 12:00:00',
      buckets: [],
    },
    health: {
      known_count: 90,
      unknown_count: 2,
      total_count: 92,
      known_pct: 97.8,
      unknown_pct: 2.2,
      current_unknown_obs_rows: 2,
      current_unknown_samples: 2,
      current_unknown_permissions: 1,
      unknown_dict_count: 7,
      ledger_inventory_rows: 7,
      actionable_review_backlog: 2,
      ledger_actionable_status_rows: 2,
      workflow_unknown_backlog: 7,
      ledger_unresolved_compat_rows: 7,
      raw_unknown: 7,
      effective_unknown: 7,
      effective_unknown_compat_legacy: 7,
      resolved_oem_count: 0,
      oem_already_resolved_not_retagged: 0,
      last_observed_at_utc: '2026-05-17 15:00:00',
      last_classified_at_utc: '2026-05-17 12:00:00',
      unknown_pct_7d: 2.0,
      unknown_pct_prev_7d: 1.8,
      unknown_pct_delta: 0.2,
      total_7d: 10,
      unknown_7d: 1,
    },
    bucket_distribution: [],
    unknown_permissions: [
      {
        permission_string: 'android.permission.CAMERA',
        namespace: 'android',
        triage_status: 'new',
        triage_status_display: 'new',
        queue_status: '',
        queue_action: '',
        queue_updated_at_utc: null,
        queue_processed_at_utc: null,
        queue_error_message: '',
        seen_count: 5,
        first_seen_at_utc: '2026-05-16 09:00:00',
        last_seen_at_utc: '2026-05-17 15:00:00',
        risk_hint: 'high',
      },
    ],
    unknown_page: {
      page: 1,
      page_size: 25,
      total_count: 1,
      total_pages: 1,
      has_more: false,
      sort: 'seen_desc',
    },
    actionable_review_rows: [],
    actionable_review_page: {
      page: 1,
      page_size: 25,
      total_count: 0,
      total_pages: 1,
      has_more: false,
      sort: 'seen_desc',
    },
    current_evidence_review_rows: [
      {
        permission_string: 'android.permission.CAMERA',
        namespace: 'android',
        current_unknown_samples: 12,
        current_unknown_obs_rows: 18,
        current_total_samples: 18,
        vt_event_count: 9,
        dict_unknown_triage_status: 'new',
        review_lane_label: 'active_review_candidate',
        first_observed_at_utc: '2026-05-16 09:00:00',
        last_observed_at_utc: '2026-05-17 15:00:00',
        historical_ledger_seen_count: 5,
        queue_status: '',
      },
      {
        permission_string: 'android.permission.READ_SMS',
        namespace: 'android',
        current_unknown_samples: 2,
        current_unknown_obs_rows: 3,
        current_total_samples: 3,
        vt_event_count: 1,
        dict_unknown_triage_status: 'new',
        review_lane_label: 'active_review_candidate',
        first_observed_at_utc: '2026-05-15 09:00:00',
        last_observed_at_utc: '2026-05-16 15:00:00',
        historical_ledger_seen_count: 9999,
        queue_status: '',
      },
    ],
    current_evidence_review_page: {
      page: 1,
      page_size: 25,
      total_count: 2,
      total_pages: 1,
      has_more: false,
      lane_scope: 'active',
      sort: 'current_unknown_samples_desc',
    },
    governed_current_unknown_rows: [
      {
        permission_string: 'com.example.launcher.PERMISSION',
        namespace: 'com.example.launcher',
        current_unknown_samples: 1,
        current_unknown_obs_rows: 1,
        current_total_samples: 1,
        vt_event_count: 1,
        dict_unknown_triage_status: 'launcher_ecosystem',
        review_lane_label: 'governed_launcher_ecosystem',
        first_observed_at_utc: '2026-05-14 08:00:00',
        last_observed_at_utc: '2026-05-15 15:00:00',
        historical_ledger_seen_count: 777,
        queue_status: '',
      },
    ],
    governed_current_unknown_page: {
      page: 1,
      page_size: 25,
      total_count: 1,
      total_pages: 1,
      has_more: false,
      lane_scope: 'governed',
      sort: 'current_unknown_samples_desc',
    },
    ledger_diagnostic_rows: [
      {
        permission_string: 'com.oplus.ocs.permission.third',
        namespace: 'com.oplus.ocs',
        risk_hint: 'medium',
        triage_status: 'oem_candidate',
        historical_ledger_seen_count: 18548,
        first_seen_at_utc: '2025-01-01 00:00:00',
        last_seen_at_utc: '2026-05-14 18:20:20',
        has_obs_sample: false,
        has_vt_event: false,
        current_unknown_samples: 0,
        diagnostic_label: 'ledger_only_no_evidence',
        queue_status: '',
        queue_action: '',
        queue_updated_at_utc: null,
        queue_processed_at_utc: null,
        queue_error_message: '',
      },
    ],
    ledger_diagnostic_page: {
      page: 1,
      page_size: 25,
      total_count: 1,
      total_pages: 1,
      has_more: false,
      sort: 'seen_desc',
    },
    triage_status_counts: {
      new: 2,
      launcher_ecosystem: 1,
      resolved_aosp: 1,
    },
    triage_status_counts_display: {
      new: 2,
      launcher_ecosystem: 1,
      aosp_resolved: 1,
    },
    metrics: {
      current_unknown_obs_rows: 2,
      current_unknown_samples: 2,
      current_unknown_permissions: 1,
      ledger_inventory_rows: 7,
      ledger_actionable_status_rows: 2,
      ledger_unresolved_compat_rows: 7,
      raw_unknown: 7,
      actionable_review_backlog: 2,
      workflow_unknown_backlog: 7,
      effective_unknown: 7,
      effective_unknown_compat_legacy: 7,
      resolved_oem_count: 0,
      resolved_aosp_count: 1,
      oem_already_resolved_not_retagged: 0,
      triage_status_counts: {
        new: 2,
        launcher_ecosystem: 1,
        resolved_aosp: 1,
      },
      triage_status_counts_display: {
        new: 2,
        launcher_ecosystem: 1,
        aosp_resolved: 1,
      },
      queue_counts: {
        queued: 0,
        applied: 0,
        error: 0,
        rejected: 0,
        skipped: 0,
      },
      queue_action_counts: {},
      current_evidence_review_backlog: 2,
      governed_current_unknown_backlog: 1,
      ledger_diagnostic_backlog: 1,
      current_evidence_risk_counts: {
        high: 1,
        medium: 1,
        low: 0,
      },
    },
    operator_summary: {
      actionable_review_backlog: 2,
      workflow_unknown_backlog: 7,
      workflow_unknown_backlog_raw: 7,
      unknown_ledger_entries: 7,
      actionable_workflow_unknowns: 2,
      explained_workflow_unknowns: 5,
      current_evidence_review_backlog: 2,
      governed_current_unknown_backlog: 1,
      ledger_diagnostic_backlog: 1,
      launcher_ecosystem_unknowns: 1,
      launcher_ecosystem_explained: 1,
      app_defined_unknowns: 0,
      resolved_unknowns: 1,
      resolved_aosp_unknowns: 1,
      resolved_oem_unknowns: 0,
      triage_status_counts_display: {
        new: 2,
        launcher_ecosystem: 1,
        aosp_resolved: 1,
      },
      queued_dict_decisions: 0,
      applied_dict_decisions: 0,
      error_dict_decisions: 0,
      current_evidence_risk_counts: {
        high: 1,
        medium: 1,
        low: 0,
      },
      effective_unknown_compat_legacy: 7,
    },
    session: {
      unknown_total: 7,
      unknown_total_raw: 7,
      unknown_total_effective: 7,
      actionable_review_backlog: 2,
      current_evidence_review_backlog: 2,
      governed_current_unknown_backlog: 1,
      ledger_diagnostic_backlog: 1,
      resolved_oem_count: 0,
      new_risk_counts: {
        high: 1,
        medium: 1,
        low: 0,
      },
      workflow_unknown_backlog: 7,
      actionable_workflow_unknowns: 2,
      current_evidence_risk_counts: {
        high: 1,
        medium: 1,
        low: 0,
      },
    },
    contract: {
      permission_metrics_version: '2026-05-16b',
      unknown_total_source: 'workflow_unknown_backlog',
      operator_summary_source: 'workflow_ledger_plus_event_volume',
    },
    status_model: {
      configured_triage_statuses: ['new', 'launcher_ecosystem', 'resolved_aosp'],
      live_triage_statuses: ['new', 'launcher_ecosystem', 'resolved_aosp'],
      unexpected_live_triage_statuses: [],
      raw_queue_action_counts: {},
      normalized_queue_action_counts: {},
      legacy_queue_actions_active: [],
    },
    namespace_drift: [],
    maintenance: {
      new_unknowns_24h: 0,
      new_namespaces_7d: 0,
      security_sensitive_unknowns: 0,
    },
    queue: {
      queued_count: 0,
      applied_count: 0,
      error_count: 0,
      rejected_count: 0,
      skipped_count: 0,
      last_queued_at_utc: null,
      last_applied_at_utc: null,
      last_error_at_utc: null,
    },
    rollup_guard: {
      stale_permissions_count: 0,
      stale_count_mismatch_count: 0,
      max_lag_seconds: 0,
      max_lag_days: 0,
      sample: [],
      sample_limit: 10,
    },
  },
  meta: {
    generated_at_utc: '2026-05-17 18:00:00',
    unknown_limit: 25,
    namespace_limit: 25,
    warnings: [],
    namespace_drift_source: 'vt_event',
    namespace_drift_reason: null,
  },
};

const permissionLovPayload = {
  ok: true,
  data: {
    triage_statuses: [
      {
        key: 'new',
        label: 'New',
        help_text: 'Requires review.',
        backlog_effect: 'Stays in active review backlog',
        recommended_quick_action: true,
      },
      {
        key: 'launcher_ecosystem',
        label: 'Launcher ecosystem',
        help_text: 'Known launcher ecosystem residue.',
        backlog_effect: 'Leaves default review backlog',
      },
      {
        key: 'resolved_aosp',
        label: 'Resolved AOSP',
        help_text: 'Known Android platform permission.',
        backlog_effect: 'Leaves default review backlog',
      },
    ],
    buckets: [
      { key: 'UNKNOWN', label: 'Unknown / Unclassified' },
      { key: 'OEM_CANDIDATE', label: 'Unknown / OEM Candidate' },
    ],
    classifications: [
      { key: 'UNKNOWN', label: 'Unknown' },
      { key: 'APP_DEFINED', label: 'App-defined' },
    ],
    queue_actions: [
      { key: '', label: 'No action' },
      { key: 'queue', label: 'Queue update' },
    ],
  },
  meta: {
    generated_at_utc: '2026-05-17 18:00:00',
  },
};

const classificationGapsPayload = {
  ok: true,
  data: {
    summary: [
      {
        classification_gap_reason: 'missing_vt_confidence_for_strong_attack_surface',
        workflow_state: 'behavior_strong_vt_missing',
        workflow_label: 'Behavior strong, VT evidence missing',
        review_priority: 'high',
        sample_count: 2,
        attack_row_count: 4,
      },
    ],
    gaps: [
      {
        sample_id: 39470,
        sha256: null,
        package_name: 'com.redx.user',
        attack_technique_id: 'T1636.002',
        attack_name: 'Protected User Data: Call Log',
        permissions: 'android.permission.READ_CALL_LOG, android.permission.WRITE_CALL_LOG',
        max_mapping_strength_rank: 3,
        sample_strong_attack_surface_rows: 4,
        confidence_bucket: null,
        recommended_action: null,
        vt_malicious_count: null,
        vt_harmless_count: null,
        classification_gap_reason: 'missing_vt_confidence_for_strong_attack_surface',
        workflow_state: 'behavior_strong_vt_missing',
        workflow_label: 'Behavior strong, VT evidence missing',
        workflow_reason_label: 'Missing VT confidence for strong behavior',
        review_priority: 'high',
      },
      {
        sample_id: 39470,
        sha256: null,
        package_name: 'com.redx.user',
        attack_technique_id: 'T1636.004',
        attack_name: 'Protected User Data: SMS Messages',
        permissions: 'android.permission.READ_SMS, android.permission.RECEIVE_SMS',
        max_mapping_strength_rank: 3,
        sample_strong_attack_surface_rows: 4,
        confidence_bucket: null,
        recommended_action: null,
        vt_malicious_count: null,
        vt_harmless_count: null,
        classification_gap_reason: 'missing_vt_confidence_for_strong_attack_surface',
        workflow_state: 'behavior_strong_vt_missing',
        workflow_label: 'Behavior strong, VT evidence missing',
        workflow_reason_label: 'Missing VT confidence for strong behavior',
        review_priority: 'high',
      },
      {
        sample_id: 39329,
        sha256: null,
        package_name: 'remove.clothes.com',
        attack_technique_id: 'T1582',
        attack_name: 'SMS Control',
        permissions: 'android.permission.SEND_SMS, android.permission.WRITE_SMS',
        max_mapping_strength_rank: 3,
        sample_strong_attack_surface_rows: 3,
        confidence_bucket: null,
        recommended_action: null,
        vt_malicious_count: null,
        vt_harmless_count: null,
        classification_gap_reason: 'missing_vt_confidence_for_strong_attack_surface',
        workflow_state: 'behavior_strong_vt_missing',
        workflow_label: 'Behavior strong, VT evidence missing',
        workflow_reason_label: 'Missing VT confidence for strong behavior',
        review_priority: 'high',
      },
      {
        sample_id: 39329,
        sha256: null,
        package_name: 'remove.clothes.com',
        attack_technique_id: 'T1636.004',
        attack_name: 'Protected User Data: SMS Messages',
        permissions: 'android.permission.READ_SMS, android.permission.RECEIVE_SMS',
        max_mapping_strength_rank: 3,
        sample_strong_attack_surface_rows: 3,
        confidence_bucket: null,
        recommended_action: null,
        vt_malicious_count: null,
        vt_harmless_count: null,
        classification_gap_reason: 'missing_vt_confidence_for_strong_attack_surface',
        workflow_state: 'behavior_strong_vt_missing',
        workflow_label: 'Behavior strong, VT evidence missing',
        workflow_reason_label: 'Missing VT confidence for strong behavior',
        review_priority: 'high',
      },
    ],
  },
  meta: {
    schema_available: true,
    primary_database: 'erebus_threat_intel_prod',
    permission_intel_database: 'android_permission_intel',
    permission_intel_split: true,
    limit: 10,
  },
};

async function mockPermissionIntel(page: Page) {
  await page.route('**/api.php/android_permission_intelligence.php*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(permissionIntelPayload),
    });
  });
}

async function mockPermissionLov(page: Page) {
  await page.route('**/api.php/android_permission_lov.php*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(permissionLovPayload),
    });
  });
}

async function mockClassificationGaps(page: Page) {
  await page.route('**/api.php/android_permission_classification_gaps.php*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(classificationGapsPayload),
    });
  });
}

test('landing page renders without console errors', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=landing');
  await expect(page.locator('h1')).toContainText('Erebus Web');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('health page renders top reasons without console errors', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.route('**/api.php/health.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(healthPayload),
    });
  });

  await page.goto('/index.php?p=health');
  await expect(page.locator('h1')).toContainText('Pipeline Health');
  await expect(page.locator('#health-reasons-list li')).toHaveCount(2);

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('malware samples page renders without console errors', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=malware_samples');
  await expect(page.locator('h1')).toContainText('Malware Samples');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('permission triage defaults to current evidence and keeps ledger diagnostics separate', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await mockPermissionIntel(page);
  await page.goto('/index.php?p=permissions_triage');
  await expect(page.locator('h1')).toContainText('Permission Triage');
  await expect(page.locator('#perm-filter-summary')).toContainText('Mode: current evidence review');
  await expect(page.locator('#perm-unknown-body tr')).toHaveCount(2);
  await expect(page.locator('#perm-unknown-body tr').first()).toContainText('android.permission.CAMERA');
  await expect(page.locator('#perm-unknown-body tr').nth(1)).toContainText('android.permission.READ_SMS');
  await expect(page.locator('#perm-unknown-body')).not.toContainText('com.oplus.ocs.permission.third');

  await page.locator('#perm-review-lane').selectOption('governed');
  await expect(page.locator('#perm-unknown-body tr')).toHaveCount(1);
  await expect(page.locator('#perm-unknown-body')).toContainText('com.example.launcher.PERMISSION');
  await expect(page.locator('#perm-review-lane-note')).toContainText('current sample/observation footprint for governed residue');
  await expect(page.locator('#perm-unknown-table thead')).toContainText('Current governed samples');
  await expect(page.locator('#perm-unknown-table thead')).toContainText('Current governed obs');
  await expect(page.locator('#perm-unknown-body tr').first().locator('button[data-action="review"]')).toHaveText('Inspect');
  await expect(page.locator('#perm-unknown-body tr').first().locator('button[data-action="evidence"]')).toHaveText('View Evidence');

  await page.locator('#perm-review-lane').selectOption('ledger');
  await expect(page.locator('#perm-unknown-body tr')).toHaveCount(1);
  await expect(page.locator('#perm-unknown-body')).toContainText('com.oplus.ocs.permission.third');
  await expect(page.locator('#perm-unknown-body')).toContainText('Ledger only / no current UNKNOWNs');
  await expect(page.locator('#perm-review-lane-note')).toContainText('historical workflow context');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('permission triage disables empty active-lane session controls', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  const emptyActivePayload = JSON.parse(JSON.stringify(permissionIntelPayload));
  emptyActivePayload.data.current_evidence_review_rows = [];
  emptyActivePayload.data.current_evidence_review_page.total_count = 0;
  emptyActivePayload.data.current_evidence_review_page.total_pages = 1;
  emptyActivePayload.data.current_evidence_review_page.has_more = false;
  emptyActivePayload.data.metrics.current_evidence_review_backlog = 0;
  emptyActivePayload.data.metrics.current_evidence_risk_counts = { high: 0, medium: 0, low: 0 };
  emptyActivePayload.data.operator_summary.current_evidence_review_backlog = 0;
  emptyActivePayload.data.operator_summary.current_evidence_risk_counts = { high: 0, medium: 0, low: 0 };
  emptyActivePayload.data.session.current_evidence_review_backlog = 0;
  emptyActivePayload.data.session.current_evidence_risk_counts = { high: 0, medium: 0, low: 0 };
  emptyActivePayload.data.session.new_risk_counts = { high: 0, medium: 0, low: 0 };

  await page.route('**/api.php/android_permission_intelligence.php*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(emptyActivePayload),
    });
  });

  await page.goto('/index.php?p=permissions_triage');
  await expect(page.locator('#perm-session-total')).toContainText('0');
  await expect(page.locator('#perm-session-start-high')).toBeDisabled();
  await expect(page.locator('#perm-session-review-next')).toBeDisabled();
  await expect(page.locator('#perm-session-resume')).toBeDisabled();
  await expect(page.locator('#perm-unknown-body')).toContainText('No current evidence-backed review rows in the active lane.');
  await expect(page.locator('#perm-unknown-body')).toContainText('1 governed current UNKNOWN row remains; switch to Governed current UNKNOWNs.');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('permission review without a selected permission shows an empty-state message', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await mockPermissionLov(page);
  await page.goto('/index.php?p=permissions_review');
  await expect(page.locator('h1')).toContainText('Permission Review');
  await expect(page.locator('#review-loading-text')).toContainText('No permission selected. Return to Triage and choose a permission.');
  await expect(page.locator('#review-error')).toContainText('No permission selected. Return to Triage and choose a permission.');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('permission overview separates current evidence, governed rows, and ledger diagnostics', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await mockPermissionIntel(page);
  await mockClassificationGaps(page);
  await page.goto('/index.php?p=permissions_overview');
  await expect(page.locator('h1')).toContainText('Permission Overview');
  await expect(page.locator('#perm-top-unknown-body tr')).toHaveCount(2);
  await expect(page.locator('#perm-top-unknown-body tr').first()).toContainText('android.permission.CAMERA');
  await expect(page.locator('#perm-top-unknown-body tr').nth(1)).toContainText('android.permission.READ_SMS');
  await expect(page.locator('#perm-governed-unknown-body tr')).toHaveCount(1);
  await expect(page.locator('#perm-governed-unknown-body')).toContainText('com.example.launcher.PERMISSION');
  await expect(page.locator('#perm-ledger-diagnostics-body tr')).toHaveCount(1);
  await expect(page.locator('#perm-ledger-diagnostics-body')).toContainText('com.oplus.ocs.permission.third');
  await expect(page.locator('#perm-next-action')).toContainText('current evidence-backed UNKNOWNs first');
  await expect(page.locator('body')).toContainText('Governed current residue');
  await expect(page.locator('body')).toContainText('Current governed samples');
  await expect(page.locator('#perm-governed-unknown-body')).toContainText('Inspect');
  await expect(page.locator('#perm-governed-unknown-body')).toContainText('View Evidence');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});

test('permission overview classification gaps collapse repeated attack rows into sample-first priorities', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await mockPermissionIntel(page);
  await mockClassificationGaps(page);
  await page.goto('/index.php?p=permissions_overview');

  await page.locator('#perm-classification-gaps-filters button[data-gap-filter="behavior_strong_vt_missing"]').click();
  await expect(page.locator('#perm-classification-gaps-summary')).toContainText('Showing 2 samples');
  await expect(page.locator('#perm-classification-gaps-body tr')).toHaveCount(2);
  await expect(page.locator('#perm-classification-gaps-body')).toContainText('4 strong ATT&CK behavior rows');
  await expect(page.locator('#perm-classification-gaps-body')).toContainText('3 strong ATT&CK behavior rows');
  await expect(page.locator('#perm-classification-gaps-body')).not.toContainText('Showing 4 rows');

  expect(errors, `Console errors:\n${errors.join('\n')}`).toHaveLength(0);
});
