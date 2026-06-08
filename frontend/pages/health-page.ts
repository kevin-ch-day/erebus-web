import type { AppSurface, JsonRecord } from '../types/app-globals';

type HealthReason = {
  reason_code?: unknown;
  count?: unknown;
};

type HealthSchemaGuard = {
  status?: unknown;
  missing?: unknown;
};

type SchemaInventory = {
  summary?: unknown;
};

type VtSurfaceSummary = {
  known_count?: unknown;
  available_count?: unknown;
  missing_count?: unknown;
  missing_names?: unknown;
};

type FamilyTaxonomySummary = {
  available?: unknown;
  mismatch_rows?: unknown;
  signal_only_rows?: unknown;
  catalog_only_rows?: unknown;
  high_conflict_rows?: unknown;
  risk_class?: unknown;
  aligned_pct?: unknown;
  mismatch_pct?: unknown;
  generic_label_pct?: unknown;
};

type WorkflowDebtRow = {
  key?: unknown;
  count?: unknown;
  raw?: unknown;
  normalized?: unknown;
  status?: unknown;
};

type WorkflowDebt = {
  deprecated_live_triage_statuses?: unknown;
  unexpected_live_triage_statuses?: unknown;
  legacy_queue_actions_active?: unknown;
};

type RollupSample = {
  permission_string?: unknown;
  lag_seconds?: unknown;
  count_delta?: unknown;
};

type RollupGuard = {
  stale_permissions_count?: unknown;
  stale_count_mismatch_count?: unknown;
  max_lag_days?: unknown;
  sample?: unknown;
};

const root = document.getElementById('health-page') as HTMLElement | null;

function asRows<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

function toRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}

if (root && window.App) {
  const pageRoot = root;
  const App = window.App as AppSurface;
  const endpoint = pageRoot.dataset.endpoint || '';
  const samplesBaseUrl = pageRoot.dataset.samplesBase || '';
  const refreshSeconds = Number(pageRoot.dataset.refreshSeconds || '15') || 15;
  const refreshMs = Math.max(5, refreshSeconds) * 1000;

  if (endpoint) {
    const stoplightEl = document.getElementById('health-stoplight');
    const stoplightSub = document.getElementById('health-stoplight-sub');
    const tileEligible = document.getElementById('tile-eligible');
    const tileProcessing = document.getElementById('tile-processing');
    const tileError = document.getElementById('tile-error');
    const tileRetry = document.getElementById('tile-retry');
    const tileStale = document.getElementById('tile-stale');
    const nextPathEl = document.getElementById('health-next-path');
    const reasonsList = document.getElementById('health-reasons-list');
    const reasonsEmpty = document.getElementById('health-reasons-empty');
    const metaEl = document.getElementById('health-meta');
    const errorEl = document.getElementById('health-error');
    const schemaSummaryEl = document.getElementById('schema-guard-summary');
    const schemaListEl = document.getElementById('schema-guard-list');
    const schemaInventorySummaryEl = document.getElementById('schema-inventory-summary');
    const schemaInventoryTotalEl = document.getElementById('schema-inventory-total');
    const schemaInventoryAvailableEl = document.getElementById('schema-inventory-available');
    const schemaInventoryMissingColumnsEl = document.getElementById('schema-inventory-missing-columns');
    const vtSurfaceSummaryEl = document.getElementById('vt-surface-summary');
    const vtSurfaceTotalEl = document.getElementById('vt-surface-total');
    const vtSurfaceAvailableEl = document.getElementById('vt-surface-available');
    const vtSurfaceMissingEl = document.getElementById('vt-surface-missing');
    const familyTaxonomySummaryEl = document.getElementById('family-taxonomy-summary');
    const familyTaxonomyMismatchEl = document.getElementById('family-taxonomy-mismatch');
    const familyTaxonomySignalOnlyEl = document.getElementById('family-taxonomy-signal-only');
    const familyTaxonomyCatalogOnlyEl = document.getElementById('family-taxonomy-catalog-only');
    const familyTaxonomyHighConflictEl = document.getElementById('family-taxonomy-high-conflict');
    const catalogPrimaryEl = document.getElementById('catalog-primary');
    const catalogPermissionIntelEl = document.getElementById('catalog-permission-intel');
    const catalogSplitModeEl = document.getElementById('catalog-split-mode');
    const schemaHeadPrimaryEl = document.getElementById('schema-head-primary');
    const schemaHeadPiEl = document.getElementById('schema-head-pi');
    const schemaHeadMatchEl = document.getElementById('schema-head-match');
    const rollupStatusEl = document.getElementById('rollup-guard-status');
    const rollupSummaryEl = document.getElementById('rollup-guard-summary');
    const rollupDetailsEl = document.getElementById('rollup-guard-details');
    const rollupListEl = document.getElementById('rollup-guard-list');
    const workflowDebtSummaryEl = document.getElementById('workflow-debt-summary');
    const workflowDebtListEl = document.getElementById('workflow-debt-list');
    const blockersGridEl = document.getElementById('health-blockers-grid');

    const esc = App.escapeHtml;
    const parseUtcToMs = App.parseUtcToMs;
    const formatUtc = App.formatUtc;
    const displayTz = App.getDisplayTz();
    const ingestBacklogUrl = App.pageUrl('ingest_backlog');
    const vtKeyDrilldownUrl = App.pageUrl('vt_key_controls');
    const permissionsOverviewUrl = App.pageUrl('permissions_overview');
    const familyTaxonomyUrl = App.pageUrl('family_taxonomy_check');

    function fmt(value: unknown, fallback = 'NULL'): string {
      return value === null || value === undefined || value === '' ? fallback : String(value);
    }

    function metricFmt(value: unknown, fallback = '--'): string {
      if (value === null || value === undefined || value === '') return fallback;
      const num = Number(value);
      if (Number.isFinite(num)) return num.toLocaleString();
      return String(value);
    }

    function renderBlockers(
      isHold: boolean,
      holdUntil: unknown,
      holdReason: unknown,
      metrics: JsonRecord,
      familySummary: FamilyTaxonomySummary | null,
      schemaHeads: JsonRecord | null,
      workflowDebt: WorkflowDebt | null,
    ): void {
      if (!blockersGridEl) return;

      const pendingLike = Number(metrics.retry_wait_count ?? 0) + Number(metrics.processing_now ?? 0);
      const taxonomyRisk = familySummary?.risk_class ? String(familySummary.risk_class).toUpperCase() : 'UNKNOWN';
      const taxonomyMismatch = metricFmt(familySummary?.mismatch_rows);
      const schemaSplit = schemaHeads?.heads_match ? 'Aligned' : 'Diverged';
      const primaryHead = fmt(schemaHeads?.primary_head, '--');
      const piHead = fmt(schemaHeads?.permission_intel_head, '--');
      const hasWorkflowDebt =
        asRows(workflowDebt?.deprecated_live_triage_statuses).length > 0 ||
        asRows(workflowDebt?.unexpected_live_triage_statuses).length > 0 ||
        asRows(workflowDebt?.legacy_queue_actions_active).length > 0;

      const cards = [
        {
          title: 'VT hold state',
          value: isHold ? 'Blocked' : 'Clear',
          tone: isHold ? 'warn' : 'ok',
          body: isHold
            ? `Hold until ${esc(`${formatUtc(holdUntil)} ${displayTz}`)} | reason ${esc(fmt(holdReason))}`
            : 'No active hold is blocking enrichment right now.',
          actionHref: vtKeyDrilldownUrl,
          actionLabel: 'Open VT Key Drilldown',
        },
        {
          title: 'Scheduler pressure',
          value: pendingLike > 0 ? metricFmt(pendingLike) : metricFmt(metrics.eligible_now),
          tone: pendingLike > 0 ? 'warn' : 'info',
          body: pendingLike > 0
            ? `Retry wait + processing pressure is visible. Check intake backlog before assuming the pipeline is quiet.`
            : `No queue-like residue is visible here. Eligible now: ${esc(metricFmt(metrics.eligible_now))}.`,
          actionHref: ingestBacklogUrl,
          actionLabel: 'Open Ingest Backlog',
        },
        {
          title: 'Taxonomy risk',
          value: taxonomyRisk,
          tone: taxonomyRisk === 'CRITICAL' ? 'error' : taxonomyRisk === 'WARN' ? 'warn' : 'info',
          body: `Mismatch rows: ${esc(taxonomyMismatch)} | High-conflict rows: ${esc(metricFmt(familySummary?.high_conflict_rows))}`,
          actionHref: familyTaxonomyUrl,
          actionLabel: 'Open Taxonomy Workspace',
        },
        {
          title: 'Catalog split',
          value: schemaSplit,
          tone: schemaHeads?.heads_match ? 'ok' : 'warn',
          body: `Primary head ${esc(primaryHead)} | PI head ${esc(piHead)}${hasWorkflowDebt ? ' | workflow debt visible' : ''}`,
          actionHref: permissionsOverviewUrl,
          actionLabel: 'Open Permission Overview',
        },
      ];

      blockersGridEl.innerHTML = cards.map((card) => `
        <div class="detail-card">
          <div class="detail-card-title">${esc(card.title)}</div>
          <div class="detail-value">${esc(card.value)}</div>
          <div class="muted" style="margin-top:8px;">${card.body}</div>
          <div style="margin-top:10px;">
            <a class="table-link" href="${esc(card.actionHref)}">${esc(card.actionLabel)}</a>
          </div>
        </div>
      `).join('');
    }

    function setStoplight(isHold: boolean, holdUntil: unknown, holdReason: unknown): void {
      if (!stoplightEl || !stoplightSub) return;
      const title = stoplightEl.querySelector('.health-stoplight-title');
      const statusText = isHold ? 'VT HOLD ACTIVE' : 'VT ENRICHMENT ALLOWED';
      if (title) title.textContent = statusText;
      stoplightEl.classList.toggle('health-stoplight-hold', isHold);
      stoplightEl.classList.toggle('health-stoplight-ok', !isHold);
      const holdLabel = holdUntil ? `${formatUtc(holdUntil)} ${displayTz}` : fmt(holdUntil);
      stoplightSub.textContent = isHold
        ? `Hold until: ${holdLabel} | reason: ${fmt(holdReason)}`
        : 'No active holds.';
    }

    function setNextPath(message: string, className = 'info'): void {
      if (!nextPathEl) return;
      nextPathEl.className = `notice ${className}`;
      nextPathEl.textContent = message;
    }

    function renderTile(el: HTMLElement | null, value: unknown): void {
      if (el) el.textContent = String(value);
    }

    function renderReasons(list: HealthReason[]): void {
      if (!reasonsList || !reasonsEmpty) return;
      reasonsList.innerHTML = '';
      if (!list.length) {
        reasonsEmpty.style.display = 'block';
        return;
      }
      reasonsEmpty.style.display = 'none';
      const sorted = [...list].sort((a, b) => Number(b.count || 0) - Number(a.count || 0));
      sorted.slice(0, 5).forEach((row) => {
        const li = document.createElement('li');
        const code = String(row.reason_code || 'UNKNOWN');
        const count = Number(row.count || 0);
        const link = document.createElement('a');
        link.className = 'health-reason-link';
        if (samplesBaseUrl) {
          link.href = `${samplesBaseUrl}${samplesBaseUrl.includes('?') ? '&' : '?'}reason=${encodeURIComponent(code)}`;
        }
        link.textContent = code;
        const badge = document.createElement('span');
        badge.className = 'badge';
        badge.textContent = String(count);
        li.appendChild(link);
        li.appendChild(badge);
        reasonsList.appendChild(li);
      });
    }

    function renderSchemaGuard(guard: HealthSchemaGuard | null): void {
      if (!schemaSummaryEl || !schemaListEl) return;
      schemaListEl.innerHTML = '';
      if (!guard) {
        schemaSummaryEl.textContent = 'Schema guard unavailable.';
        return;
      }
      if (guard.status === 'ok') {
        schemaSummaryEl.textContent = 'No schema issues detected.';
        return;
      }
      const missing = asRows<JsonRecord>(guard.missing);
      schemaSummaryEl.textContent = missing.length
        ? `Missing ${missing.length} required columns.`
        : 'Schema issues detected.';
      missing.slice(0, 6).forEach((item) => {
        const table = item.table ? String(item.table) : '--';
        const column = item.column ? String(item.column) : '--';
        const li = document.createElement('li');
        li.textContent = `${table}.${column}`;
        schemaListEl.appendChild(li);
      });
      if (missing.length > 6) {
        const li = document.createElement('li');
        li.textContent = `...and ${missing.length - 6} more`;
        schemaListEl.appendChild(li);
      }
    }

    function renderSchemaInventory(inventory: SchemaInventory | null): void {
      const summary = toRecord(inventory?.summary);
      if (!Object.keys(summary).length) {
        if (schemaInventorySummaryEl) schemaInventorySummaryEl.textContent = 'Schema inventory unavailable.';
        return;
      }
      const total = Number(summary.surface_count || 0);
      const available = Number(summary.available_count || 0);
      const missingSurfaces = Number(summary.missing_surface_count || 0);
      const missingColumns = Number(summary.missing_column_count || 0);
      if (schemaInventoryTotalEl) schemaInventoryTotalEl.textContent = String(total);
      if (schemaInventoryAvailableEl) schemaInventoryAvailableEl.textContent = String(available);
      if (schemaInventoryMissingColumnsEl) schemaInventoryMissingColumnsEl.textContent = String(missingColumns);
      if (schemaInventorySummaryEl) {
        schemaInventorySummaryEl.textContent = missingSurfaces || missingColumns
          ? `Web knows ${total} DB surfaces; ${missingSurfaces} missing surfaces and ${missingColumns} missing columns need attention.`
          : `Web knows ${total} DB surfaces; all expected columns are present.`;
      }
    }

    function renderVtSurfaceSummary(summary: VtSurfaceSummary | null): void {
      if (!vtSurfaceSummaryEl || !vtSurfaceTotalEl || !vtSurfaceAvailableEl || !vtSurfaceMissingEl) return;
      if (!summary) {
        vtSurfaceSummaryEl.textContent = 'VT surface summary unavailable.';
        return;
      }
      const known = Number(summary.known_count || 0);
      const available = Number(summary.available_count || 0);
      const missing = Number(summary.missing_count || 0);
      const missingNames = asRows<unknown>(summary.missing_names).map((name) => String(name));
      vtSurfaceTotalEl.textContent = String(known);
      vtSurfaceAvailableEl.textContent = String(available);
      vtSurfaceMissingEl.textContent = String(missing);
      vtSurfaceSummaryEl.textContent = missing > 0
        ? `Canonical vendor, projection, delta, and signal surfaces are incomplete: ${missingNames.slice(0, 4).join(', ')}${missingNames.length > 4 ? ' ...' : ''}.`
        : 'Canonical vendor, projection, delta, and signal surfaces are present.';
    }

    function renderFamilyTaxonomySummary(summary: FamilyTaxonomySummary | null): void {
      if (!familyTaxonomySummaryEl || !familyTaxonomyMismatchEl || !familyTaxonomySignalOnlyEl || !familyTaxonomyCatalogOnlyEl || !familyTaxonomyHighConflictEl) return;
      if (!summary || summary.available === false) {
        familyTaxonomySummaryEl.textContent = 'Family taxonomy summary unavailable.';
        return;
      }
      familyTaxonomyMismatchEl.textContent = fmt(summary.mismatch_rows);
      familyTaxonomySignalOnlyEl.textContent = fmt(summary.signal_only_rows);
      familyTaxonomyCatalogOnlyEl.textContent = fmt(summary.catalog_only_rows);
      familyTaxonomyHighConflictEl.textContent = fmt(summary.high_conflict_rows);
      familyTaxonomySummaryEl.textContent = `Risk: ${fmt(summary.risk_class)} | aligned ${fmt(summary.aligned_pct)}% | mismatch ${fmt(summary.mismatch_pct)}% | generic ${fmt(summary.generic_label_pct)}%`;
    }

    function renderCatalogs(catalogs: JsonRecord | null): void {
      if (!catalogPrimaryEl || !catalogPermissionIntelEl || !catalogSplitModeEl) return;
      catalogPrimaryEl.textContent = fmt(catalogs?.primary);
      catalogPermissionIntelEl.textContent = fmt(catalogs?.permission_intel);
      catalogSplitModeEl.textContent = catalogs?.split_enabled ? 'yes' : 'no';
    }

    function renderSchemaHeads(heads: JsonRecord | null): void {
      if (!schemaHeadPrimaryEl || !schemaHeadPiEl || !schemaHeadMatchEl) return;
      schemaHeadPrimaryEl.textContent = fmt(heads?.primary_head);
      schemaHeadPiEl.textContent = fmt(heads?.permission_intel_head);
      schemaHeadMatchEl.textContent = heads?.heads_match ? 'yes' : 'no';
    }

    function renderWorkflowDebt(workflowDebt: WorkflowDebt | null): void {
      if (!workflowDebtSummaryEl || !workflowDebtListEl) return;
      workflowDebtListEl.innerHTML = '';
      if (!workflowDebt) {
        workflowDebtSummaryEl.textContent = 'Workflow debt summary unavailable.';
        return;
      }
      const deprecatedLive = asRows<WorkflowDebtRow>(workflowDebt.deprecated_live_triage_statuses);
      const unexpectedLive = asRows<WorkflowDebtRow>(workflowDebt.unexpected_live_triage_statuses);
      const legacyQueueActive = asRows<WorkflowDebtRow>(workflowDebt.legacy_queue_actions_active);

      if (!deprecatedLive.length && !unexpectedLive.length && !legacyQueueActive.length) {
        workflowDebtSummaryEl.textContent = 'No live workflow vocabulary debt detected.';
        return;
      }

      workflowDebtSummaryEl.textContent =
        `Deprecated live statuses: ${deprecatedLive.length} | Unexpected live statuses: ${unexpectedLive.length} | Legacy active queue aliases: ${legacyQueueActive.length}`;

      deprecatedLive.forEach((row) => {
        const li = document.createElement('li');
        li.textContent = `Deprecated live triage status: ${fmt(row.key)} (${fmt(row.count, '0')} row(s))`;
        workflowDebtListEl.appendChild(li);
      });
      unexpectedLive.forEach((row) => {
        const li = document.createElement('li');
        li.textContent = `Unexpected live triage status: ${fmt(row.key)} (${fmt(row.count, '0')} row(s))`;
        workflowDebtListEl.appendChild(li);
      });
      legacyQueueActive.forEach((row) => {
        const li = document.createElement('li');
        li.textContent = `Legacy active queue alias: ${fmt(row.raw)} -> ${fmt(row.normalized)} (${fmt(row.count, '0')} row(s), status ${fmt(row.status)})`;
        workflowDebtListEl.appendChild(li);
      });
    }

    function renderRollupGuard(guard: RollupGuard | null): void {
      if (!rollupStatusEl || !rollupSummaryEl || !rollupListEl) return;
      rollupListEl.innerHTML = '';
      if (rollupDetailsEl) rollupDetailsEl.style.display = 'none';

      if (!guard) {
        rollupStatusEl.className = 'notice warn';
        rollupStatusEl.textContent = 'Rollup guard unavailable.';
        rollupSummaryEl.textContent = 'Unable to verify vt_current rollups.';
        return;
      }

      const stale = Number(guard.stale_permissions_count || 0);
      const mismatches = Number(guard.stale_count_mismatch_count || 0);
      const driftCount = Math.max(stale, mismatches);
      const maxLagDays = guard.max_lag_days;
      const maxLagLabel = maxLagDays === null || maxLagDays === undefined ? 'n/a' : `${Number(maxLagDays).toFixed(2)} days`;

      if (driftCount === 0) {
        rollupStatusEl.className = 'notice success';
        rollupStatusEl.textContent = 'No rollup drift detected.';
        rollupSummaryEl.textContent = 'vt_current matches vt_event for last seen and counts.';
        return;
      }

      rollupStatusEl.className = driftCount <= 10 ? 'notice warn' : 'notice error';
      rollupStatusEl.textContent = driftCount <= 10 ? 'Rollup drift detected (small).' : 'Rollup drift detected.';
      rollupSummaryEl.textContent = `Stale rows: ${stale} | Count mismatches: ${mismatches} | Max lag: ${maxLagLabel}`;

      const samples = asRows<RollupSample>(guard.sample);
      if (samples.length > 0 && rollupDetailsEl) {
        rollupDetailsEl.style.display = 'block';
        samples.slice(0, 10).forEach((row) => {
          const perm = row.permission_string || '--';
          const lag = row.lag_seconds ? `${Math.round(Number(row.lag_seconds) / 86400)}d` : '0d';
          const delta = row.count_delta !== undefined && row.count_delta !== null ? String(row.count_delta) : '0';
          const li = document.createElement('li');
          li.textContent = `${perm} (lag ${lag}, delta ${delta})`;
          rollupListEl.appendChild(li);
        });
      }
    }

    async function loadHealth(): Promise<void> {
      try {
        App.clearPageError(errorEl);
        const res = await App.fetchPayload(endpoint);
        if (!res.ok) {
          App.renderPageError(errorEl, {
            title: 'VT & pipeline health unavailable',
            summary: 'The health API did not return usable data, so the page cannot tell you whether enrichment should move or hold.',
            detail: res.error,
            status: res.status,
            raw: res.raw,
            hint: 'Confirm API and DB health first, then retry this page before interpreting VT or Permission Intel workflow counts.',
            primaryActionHref: App.currentPageUrl(),
            primaryActionLabel: 'Retry health page',
            secondaryActionHref: App.pageUrl('landing'),
            secondaryActionLabel: 'Back to landing',
          });
          return;
        }

        const data = toRecord(res.data);
        const control = toRecord(data.system_control);
        const metrics = toRecord(data.metrics);
        const holdUntil = control.hold_until_utc;
        const holdReason = control.hold_reason_code;
        const holdMs = parseUtcToMs(holdUntil);
        const isHold = holdMs !== null ? holdMs > Date.now() : !!(holdUntil && String(holdUntil).trim() !== '');
        const eligibleNow = Number(metrics.eligible_now ?? 0);
        const processingNow = Number(metrics.processing_now ?? 0);
        const errorCount = Number(metrics.error_count ?? 0);
        const retryCount = Number(metrics.retry_wait_count ?? 0);
        const staleClaims = Number(metrics.stale_claims ?? 0);

        setStoplight(isHold, holdUntil, holdReason);
        renderTile(tileEligible, eligibleNow);
        renderTile(tileProcessing, processingNow);
        renderTile(tileError, errorCount);
        renderTile(tileRetry, retryCount);
        renderTile(tileStale, staleClaims);
        renderReasons(asRows<HealthReason>(metrics.reason_breakdown));
        const catalogs = toRecord(data.catalogs);
        const schemaHeads = toRecord(data.schema_heads);
        const familySummary = toRecord(data.family_taxonomy_summary) as FamilyTaxonomySummary;
        renderCatalogs(catalogs);
        renderSchemaHeads(schemaHeads);
        renderSchemaGuard(toRecord(data.schema_guard) as HealthSchemaGuard);
        renderSchemaInventory(toRecord(data.schema_inventory) as SchemaInventory);
        renderVtSurfaceSummary(toRecord(data.vt_surface_summary) as VtSurfaceSummary);
        renderFamilyTaxonomySummary(familySummary);
        renderRollupGuard(toRecord(data.rollup_guard) as RollupGuard);
        renderWorkflowDebt(toRecord(data.workflow_debt) as WorkflowDebt);

        const workflowDebt = toRecord(data.workflow_debt);
        const hasWorkflowDebt =
          asRows(workflowDebt.deprecated_live_triage_statuses).length > 0 ||
          asRows(workflowDebt.unexpected_live_triage_statuses).length > 0 ||
          asRows(workflowDebt.legacy_queue_actions_active).length > 0;

        renderBlockers(isHold, holdUntil, holdReason, metrics, familySummary, schemaHeads, workflowDebt as WorkflowDebt);

        if (isHold) {
          setNextPath('Next path: VT is blocked by a hold. Check VT Key Drilldown first, then confirm whether queue pressure or retry residue is just a downstream symptom.', 'warn');
        } else if (!schemaHeads.heads_match) {
          setNextPath('Next path: primary and Permission Intel schema heads are diverged. Treat cross-surface comparisons cautiously until that split is understood.', 'warn');
        } else if (familySummary.risk_class && String(familySummary.risk_class).toLowerCase() === 'critical') {
          setNextPath('Next path: family-taxonomy risk is critical. Use the taxonomy overview and repair queue before treating family-level comparisons as stable evidence.', 'warn');
        } else if (hasWorkflowDebt) {
          setNextPath('Next path: platform vocabulary debt exists. Align live triage statuses and queue aliases before trusting page-level backlog comparisons across CLI and web.', 'warn');
        } else if (errorCount > 0 || retryCount > 0 || staleClaims > 0) {
          setNextPath('Next path: scheduler residue exists. Use Run Ledger, Drift Details, and Ingest Backlog to separate retry/error pressure from queue or catalog drift.', 'warn');
        } else if (eligibleNow > 0) {
          setNextPath('Next path: VT work is eligible now. If enrichment still is not moving, verify key readiness and recent run behavior before changing PI workflow decisions.', 'info');
        } else if (processingNow > 0) {
          setNextPath('Next path: work is already in flight. Watch Run Ledger and Ingest Backlog before treating this as a taxonomy or Permission Intel problem.', 'info');
        } else {
          setNextPath('Next path: no immediate VT work is eligible. Check Run Ledger, intake backlog, and sample seeding before forcing downstream workflow actions.', 'info');
        }

        if (metaEl) {
          const refreshedAt = toRecord(res.meta).server_utc_now || new Date().toISOString().replace('T', ' ').replace('Z', ' UTC');
          metaEl.textContent = `Last refresh: ${String(refreshedAt)}`;
        }
      } catch (error) {
        App.renderPageError(errorEl, {
          title: 'VT & pipeline health load failed',
          summary: 'The page hit an unexpected client-side failure while processing the health response.',
          detail: error instanceof Error ? error.message : String(error),
          hint: 'Reload the page once. If the failure persists, verify the health endpoint and recent backend changes.',
          primaryActionHref: App.currentPageUrl(),
          primaryActionLabel: 'Reload health page',
          secondaryActionHref: App.pageUrl('landing'),
          secondaryActionLabel: 'Back to landing',
        });
        setNextPath('Next path: health data is unavailable. Confirm DB/API health first before interpreting VT or PI workflow pages.', 'error');
      }
    }

    void loadHealth();
    window.setInterval(() => {
      void loadHealth();
    }, refreshMs);
  }
}
