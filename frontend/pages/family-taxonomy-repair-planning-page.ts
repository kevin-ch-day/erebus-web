import type { AppSurface, JsonRecord } from '../types/app-globals';
import {
  asRows,
  mapEntries,
  toRecord,
  type ApplyPlanPayload,
  type ApplyPlanRow,
  type DecisionInventory,
  type FixActionInventory,
  type PageParams,
  type QueueData,
  type QueueMeta,
  type QueuePreset,
  type QueueRow,
  type RepairOpportunity,
  type SummaryRow,
} from './family-taxonomy-queue-types';

const root = document.getElementById('family-taxonomy-repair-planning-page') as HTMLElement | null;

if (root && window.App) {
  const pageRoot = root;
  const App = window.App as AppSurface;
  const endpoint = pageRoot.dataset.endpoint || '';

  if (endpoint) {
    const metaEl = document.getElementById('family-taxonomy-repair-planning-meta');
    const focusEl = document.getElementById('family-taxonomy-repair-planning-focus');
    const activeSliceEl = document.getElementById('family-taxonomy-repair-planning-active-slice');
    const summaryEl = document.getElementById('family-taxonomy-repair-planning-summary');
    const presetsSectionEl = document.getElementById('family-taxonomy-repair-planning-presets-section');
    const actionsSectionEl = document.getElementById('family-taxonomy-repair-planning-actions-section');
    const opportunitiesSectionEl = document.getElementById('family-taxonomy-repair-planning-opportunities-section');
    const planSectionEl = document.getElementById('family-taxonomy-repair-planning-plan-section');
    const presetsEl = document.getElementById('family-taxonomy-repair-planning-presets');
    const actionsEl = document.getElementById('family-taxonomy-repair-planning-actions');
    const opportunitiesBodyEl = document.getElementById('family-taxonomy-repair-planning-opportunities-body');
    const applyPlanSummaryEl = document.getElementById('family-taxonomy-repair-planning-plan-summary');
    const applyPlanBodyEl = document.getElementById('family-taxonomy-repair-planning-plan-body');
    const errorEl = document.getElementById('family-taxonomy-repair-planning-error');

    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const formatUtc = App.formatUtc;

    function badgeClassForAlignment(value: unknown): string {
      const key = String(value || '').toLowerCase();
      if (key === 'aligned') return 'badge ok';
      if (key === 'mismatch') return 'badge err';
      if (key === 'signal_only' || key === 'catalog_only') return 'badge warn';
      if (key === 'semantic_conflict' || key === 'placeholder_catalog') return 'badge err';
      if (key === 'alias_resolved') return 'badge ok';
      if (key === 'alias_candidate' || key === 'generic_signal' || key === 'short_signal_token') return 'badge warn';
      return 'badge muted';
    }

    function badgeClassForConfidence(value: unknown): string {
      const key = String(value || '').toLowerCase();
      if (key === 'high') return 'badge ok';
      if (key === 'medium') return 'badge warn';
      if (key === 'low') return 'badge muted';
      return 'badge muted';
    }

    function compactCopy(value: unknown, fallback = '--', limit = 56): string {
      const text = String(value || '').trim();
      if (text === '') return fallback;
      const normalized = text.replace(/\s+/g, ' ');
      if (normalized.length <= limit) return normalized;
      return `${normalized.slice(0, Math.max(0, limit - 1)).trimEnd()}…`;
    }

    function pageLink(page: string, params: Record<string, unknown> = {}): string {
      if (!App.pageUrl) return '#';
      return App.pageUrl(page, params);
    }

    function currentFilterParams(): PageParams {
      const currentUrl = new URL(window.location.href);
      const params: PageParams = {
        limit: pageRoot.dataset.limit || '100',
      };
      const alignment = pageRoot.dataset.alignment || '';
      const platform = pageRoot.dataset.platform || '';
      const pattern = pageRoot.dataset.pattern || '';
      const query = pageRoot.dataset.query || '';
      const pairCatalog = currentUrl.searchParams.get('pair_catalog') || pageRoot.dataset.pairCatalog || '';
      const pairSignal = currentUrl.searchParams.get('pair_signal') || pageRoot.dataset.pairSignal || '';
      const fixAction = currentUrl.searchParams.get('fix_action') || pageRoot.dataset.fixAction || '';
      const targetFamily = currentUrl.searchParams.get('target_family') || pageRoot.dataset.targetFamily || '';
      const decisionMode = currentUrl.searchParams.get('decision_mode') || pageRoot.dataset.decisionMode || '';

      if (alignment) params.alignment = alignment;
      if (platform) params.platform = platform;
      if (pattern) params.pattern = pattern;
      if (query) params.q = query;
      if (pairCatalog) params.pair_catalog = pairCatalog;
      if (pairSignal) params.pair_signal = pairSignal;
      if (fixAction) params.fix_action = fixAction;
      if (targetFamily) params.target_family = targetFamily;
      if (decisionMode) params.decision_mode = decisionMode;
      return params;
    }

    function setSectionHidden(sectionEl: HTMLElement | null, hidden: boolean): void {
      if (!sectionEl) return;
      sectionEl.hidden = hidden;
    }

    function renderPresets(presets: QueuePreset[]): void {
      if (!presetsEl) return;
      if (!presets.length) {
        presetsEl.innerHTML = '<div class="detail-card"><div class="muted">No queue presets available.</div></div>';
        return;
      }
      const currentLimit = pageRoot.dataset.limit || '100';
      presetsEl.innerHTML = presets.map((preset) => {
        const href = pageLink('taxonomy_repairs', {
          limit: currentLimit,
          alignment: preset.alignment || undefined,
          pattern: preset.pattern || undefined,
          decision_mode: preset.decision_mode || undefined,
        });
        const buttonClass = preset.button_tone === 'primary' ? 'btn btn-primary' : 'btn';
        return `
          <div class="detail-card">
            <div class="detail-card-title">${esc(preset.title || '--')}</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(preset.count || 0))}</div></div>
            <p class="muted">${esc(preset.description || '--')}</p>
            <div style="margin-top: 10px;">
              <a class="${buttonClass}" href="${esc(href)}">${esc(preset.button_label || 'Open queue')}</a>
            </div>
          </div>
        `;
      }).join('');
    }

    function renderFixActionInventory(inventory: FixActionInventory): void {
      if (!actionsEl) return;
      const actionItems = mapEntries(inventory.action_counts).slice(0, 8);
      const targetItems = mapEntries(inventory.top_target_families).slice(0, 5);
      if (!actionItems.length) {
        actionsEl.innerHTML = '<div class="detail-card"><div class="muted">No repair-action inventory available for this slice.</div></div>';
        return;
      }
      const list = (items: Array<[string, unknown]>, type: 'action' | 'target') => items.length
        ? `<ul class="maintenance-list">${items.map(([label, count]) => {
          const params = currentFilterParams();
          if (type === 'action') params.fix_action = label;
          if (type === 'target') params.target_family = label;
          const href = pageLink('taxonomy_repairs', params);
          return `<li><a class="table-link" href="${esc(href)}">${esc(label)}</a> <span class="muted">(${esc(fmt(count))})</span></li>`;
        }).join('')}</ul>`
        : '<div class="muted">--</div>';
      actionsEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Suggested fix actions</div>
          <div class="detail-row"><div class="detail-label">Rows in slice</div><div class="detail-value">${esc(fmt(inventory.total_rows || 0))}</div></div>
          ${list(actionItems, 'action')}
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Top target families</div>
          ${list(targetItems, 'target')}
        </div>
      `;
    }

    function renderRepairOpportunities(opportunities: RepairOpportunity[]): void {
      if (!opportunitiesBodyEl) return;
      if (!opportunities.length) {
        opportunitiesBodyEl.innerHTML = '<tr><td colspan="7" class="muted">No batch repair opportunities found for this slice.</td></tr>';
        return;
      }
      opportunitiesBodyEl.innerHTML = opportunities.map((item) => {
        const params = currentFilterParams();
        if (item.suggested_fix_action) params.fix_action = String(item.suggested_fix_action);
        if (item.suggested_target_family) params.target_family = String(item.suggested_target_family);
        if (item.decision_mode) params.decision_mode = String(item.decision_mode);
        const href = pageLink('taxonomy_repairs', params);
        const catalogExamples = asRows<unknown>(item.catalog_label_examples).length
          ? asRows<unknown>(item.catalog_label_examples).map(String).join(', ')
          : '--';
        const signalExamples = asRows<unknown>(item.signal_label_examples).length
          ? asRows<unknown>(item.signal_label_examples).map(String).join(', ')
          : '--';
        const samplePreview = asRows<unknown>(item.sample_id_preview).length
          ? asRows<unknown>(item.sample_id_preview).map(String).join(', ')
          : '--';
        return `
          <tr>
            <td>
              <span class="${badgeClassForConfidence(item.suggested_fix_action)}">${esc(item.suggested_fix_action || '--')}</span><br>
              <span class="muted">${esc(item.suggested_fix_reason || '--')}</span>
            </td>
            <td>${esc(item.suggested_target_family || '--')}</td>
            <td>${esc(fmt(item.row_count || 0))}</td>
            <td>${esc(fmt(item.high_confidence_rows || 0))}</td>
            <td>
              <span class="${badgeClassForAlignment(item.dominant_issue_kind)}">${esc(item.dominant_issue_kind || '--')}</span><br>
              <span class="${badgeClassForConfidence(item.decision_priority)}">${esc(item.decision_mode || '--')}</span>
            </td>
            <td>
              <span class="muted">Catalog:</span> ${esc(catalogExamples)}<br>
              <span class="muted">Signal:</span> ${esc(signalExamples)}<br>
              <span class="muted">Samples:</span> ${esc(samplePreview)}
            </td>
            <td><a class="table-link" href="${esc(href)}">Open rows</a></td>
          </tr>
        `;
      }).join('');
    }

    function renderApplyPlan(plan: ApplyPlanPayload): void {
      const summary = toRecord(plan.summary);
      const rows = asRows<ApplyPlanRow>(plan.plan_rows);

      if (applyPlanSummaryEl) {
        const supportedActions = asRows<unknown>(plan.supported_actions).map(String);
        applyPlanSummaryEl.innerHTML = `
          <div class="detail-card">
            <div class="detail-card-title">Plan candidate rows</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(summary.candidate_rows || 0))}</div></div>
            <div class="detail-row"><div class="detail-label">Plan groups</div><div class="detail-value">${esc(fmt(summary.plan_group_count || 0))}</div></div>
          </div>
          <div class="detail-card">
            <div class="detail-card-title">Excluded rows</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(summary.excluded_rows || 0))}</div></div>
            <div class="detail-row"><div class="detail-label">Why</div><div class="detail-value">${esc(Object.entries(toRecord(summary.excluded_reasons)).map(([label, count]) => `${label}=${fmt(count)}`).join(', ') || '--')}</div></div>
          </div>
          <div class="detail-card">
            <div class="detail-card-title">Supported actions</div>
            <div class="muted">${esc(supportedActions.join(', ') || '--')}</div>
          </div>
        `;
      }

      if (!applyPlanBodyEl) return;
      if (!rows.length) {
        applyPlanBodyEl.innerHTML = '<tr><td colspan="8" class="muted">No dry-run repair plan rows found for this slice.</td></tr>';
        return;
      }

      applyPlanBodyEl.innerHTML = rows.map((row) => {
        const params = currentFilterParams();
        if (row.plan_action) params.fix_action = String(row.plan_action);
        if (row.target_family) params.target_family = String(row.target_family);
        const href = pageLink('taxonomy_repairs', params);
        const decisions = asRows<unknown>(row.decision_modes).map(String).join(', ') || '--';
        const confidence = asRows<unknown>(row.confidence_buckets).map(String).join(', ') || '--';
        const sqlPreview = String(row.sql_preview || '--');

        return `
          <tr>
            <td><span class="${badgeClassForConfidence(row.plan_action)}">${esc(row.plan_action || '--')}</span></td>
            <td>${esc(row.target_family || '--')}</td>
            <td>${esc(fmt(row.row_count || 0))}</td>
            <td>${esc(fmt(row.sample_id_count || 0))}</td>
            <td>${esc(decisions)}</td>
            <td>${esc(confidence)}</td>
            <td><details><summary>Preview SQL</summary><pre style="white-space: pre-wrap; margin-top: 8px;">${esc(sqlPreview)}</pre></details></td>
            <td><a class="table-link" href="${esc(href)}">Open rows</a></td>
          </tr>
        `;
      }).join('');
    }

    function renderSummary(summaryRows: SummaryRow[], meta: QueueMeta): void {
      if (!summaryEl) return;
      if (!summaryRows.length) {
        summaryEl.innerHTML = '<div class="detail-card"><div class="muted">No planning summary rows found.</div></div>';
        return;
      }
      const pairFocus = meta && (meta.pair_catalog || meta.pair_signal)
        ? `${meta.pair_catalog || '(empty)'} vs ${meta.pair_signal || '(empty)'}`
        : 'None';
      const clearUrl = pageLink('taxonomy_repairs', { limit: pageRoot.dataset.limit || '100' });
      summaryEl.innerHTML = summaryRows.map((row) => `
        <div class="detail-card">
          <div class="detail-card-title"><span class="${badgeClassForAlignment(row.alignment_status)}">${esc(row.alignment_status || '--')}</span></div>
          <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(row.row_count))}</div></div>
          <div class="detail-row"><div class="detail-label">Generic labels</div><div class="detail-value">${esc(fmt(row.generic_label_count))}</div></div>
          <div class="detail-row"><div class="detail-label">Pair focus</div><div class="detail-value">${esc(pairFocus)}</div></div>
          <div class="detail-row"><div class="detail-label">Clear focus</div><div class="detail-value"><a class="table-link" href="${esc(clearUrl)}">Reset pair filter</a></div></div>
        </div>
      `).join('');
    }

    function renderActiveSlice(meta: QueueMeta): void {
      if (!activeSliceEl) return;
      const chip = (label: string, value: string, params: PageParams): string => {
        const href = pageLink('taxonomy_repairs', params);
        return `
          <div class="filters-chip">
            <span class="filters-chip-label">${esc(label)}</span>
            <span>${esc(value)}</span>
            <a class="filters-chip-clear" href="${esc(href)}">Clear</a>
          </div>
        `;
      };

      const baseParams = currentFilterParams();
      const chips: string[] = [];
      const pairCatalog = String(meta.pair_catalog || '').trim();
      const pairSignal = String(meta.pair_signal || '').trim();
      const alignment = String(meta.alignment || '').trim();
      const platform = String(meta.platform || '').trim();
      const pattern = String(meta.pattern || '').trim();
      const decisionMode = String(meta.decision_mode || '').trim();
      const fixAction = String(meta.fix_action || '').trim();
      const targetFamily = String(meta.target_family || '').trim();
      const query = String(meta.query || meta.q || '').trim();

      if (pairCatalog !== '' || pairSignal !== '') {
        const params = { ...baseParams };
        delete params.pair_catalog;
        delete params.pair_signal;
        chips.push(chip('Pair focus', `${pairCatalog || '(empty)'} vs ${pairSignal || '(empty)'}`, params));
      }
      if (decisionMode !== '') {
        const params = { ...baseParams };
        delete params.decision_mode;
        chips.push(chip('Decision lane', decisionMode, params));
      }
      if (fixAction !== '') {
        const params = { ...baseParams };
        delete params.fix_action;
        chips.push(chip('Action', fixAction, params));
      }
      if (targetFamily !== '') {
        const params = { ...baseParams };
        delete params.target_family;
        chips.push(chip('Target', targetFamily, params));
      }
      if (alignment !== '') {
        const params = { ...baseParams };
        delete params.alignment;
        chips.push(chip('Alignment', alignment, params));
      }
      if (platform !== '') {
        const params = { ...baseParams };
        delete params.platform;
        chips.push(chip('Platform', platform, params));
      }
      if (pattern !== '') {
        const params = { ...baseParams };
        delete params.pattern;
        chips.push(chip('Pattern', pattern, params));
      }
      if (query !== '') {
        const params = { ...baseParams };
        delete params.q;
        chips.push(chip('Search', query, params));
      }

      const currentLimit = String(meta.limit || pageRoot.dataset.limit || '100');
      const resetHref = pageLink('taxonomy_repairs', { limit: currentLimit });

      activeSliceEl.innerHTML = chips.length
        ? `
          <div class="filters-active-slice">
            <div class="detail-card-title">Active slice</div>
            <div class="filters-active-slice-copy">These filters are shaping the current repair planning view.</div>
            <div class="filters-chip-row">${chips.join('')}</div>
            <div><a class="table-link" href="${esc(resetHref)}">Reset to broad queue</a></div>
          </div>
        `
        : `
          <div class="filters-active-slice">
            <div class="detail-card-title">Active slice</div>
            <div class="filters-active-slice-copy">Broad planning view. No narrow filters are active yet.</div>
          </div>
        `;
    }

    function renderFocusState(meta: QueueMeta, decisionInventory: DecisionInventory, rows: QueueRow[]): void {
      if (!focusEl) return;
      const rowCount = rows.length;
      const pairCatalog = String(meta.pair_catalog || '').trim();
      const pairSignal = String(meta.pair_signal || '').trim();
      const decisionMode = String(meta.decision_mode || '').trim();
      const fixAction = String(meta.fix_action || '').trim();
      const targetFamily = String(meta.target_family || '').trim();
      const hasPairFocus = pairCatalog !== '' || pairSignal !== '';
      const dominantIssue = String(rows[0]?.issue_kind || '--');
      const dominantAction = String(rows[0]?.suggested_fix_action || '--');
      const highPriorityCount = Number(toRecord(decisionInventory.decision_priority_counts).high || 0);
      const queueHref = pageLink('taxonomy_repairs', currentFilterParams());
      const resetHref = pageLink('taxonomy_repair_planning', { limit: String(meta.limit || pageRoot.dataset.limit || '100') });
      const scopeLabel = hasPairFocus
        ? 'Pair-focused slice'
        : (decisionMode !== '' || fixAction !== '' || targetFamily !== '' ? 'Filtered planning slice' : 'Broad planning slice');
      const focusLabel = hasPairFocus
        ? `${pairCatalog || '(empty)'} vs ${pairSignal || '(empty)'}`
        : (decisionMode || fixAction || targetFamily || '--');
      const nextMove = dominantAction !== '--'
        ? compactCopy(`Use grouped ${dominantAction} planning first.`, '--', 64)
        : 'Review grouped opportunities first.';

      focusEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Slice summary</div>
          <div class="detail-row"><div class="detail-label">Mode</div><div class="detail-value">${esc(scopeLabel)}</div></div>
          <div class="detail-row"><div class="detail-label">Rows in scope</div><div class="detail-value">${esc(fmt(rowCount))}</div></div>
          <div class="detail-row"><div class="detail-label">Main issue</div><div class="detail-value"><span class="${badgeClassForAlignment(dominantIssue)}">${esc(dominantIssue)}</span></div></div>
          <div class="detail-row"><div class="detail-label">Focus</div><div class="detail-value">${esc(focusLabel)}</div></div>
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Start path</div>
          <p class="muted">${esc(nextMove)}</p>
          <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="btn btn-primary" href="#family-taxonomy-repair-planning-opportunities-section">Open grouped opportunities</a>
            <a class="btn" href="#family-taxonomy-repair-planning-plan-section">Open dry-run plan</a>
            <a class="btn" href="${esc(queueHref)}">Back to repair queue</a>
          </div>
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Pressure</div>
          <div class="detail-row"><div class="detail-label">High-priority rows</div><div class="detail-value">${esc(fmt(highPriorityCount))}</div></div>
          <div class="detail-row"><div class="detail-label">Suggested action</div><div class="detail-value">${esc(dominantAction)}</div></div>
          <div class="detail-row"><div class="detail-label">Reset slice</div><div class="detail-value"><a class="table-link" href="${esc(resetHref)}">Broad planning view</a></div></div>
        </div>
      `;
    }

    async function load(): Promise<void> {
      const currentUrl = new URL(window.location.href);
      const url = new URL(endpoint, window.location.origin);
      const limit = pageRoot.dataset.limit || '100';
      url.searchParams.set('limit', limit);
      [
        ['alignment', pageRoot.dataset.alignment || ''],
        ['platform', pageRoot.dataset.platform || ''],
        ['pattern', pageRoot.dataset.pattern || ''],
        ['q', pageRoot.dataset.query || ''],
        ['pair_catalog', currentUrl.searchParams.get('pair_catalog') || pageRoot.dataset.pairCatalog || ''],
        ['pair_signal', currentUrl.searchParams.get('pair_signal') || pageRoot.dataset.pairSignal || ''],
        ['fix_action', currentUrl.searchParams.get('fix_action') || pageRoot.dataset.fixAction || ''],
        ['target_family', currentUrl.searchParams.get('target_family') || pageRoot.dataset.targetFamily || ''],
        ['decision_mode', currentUrl.searchParams.get('decision_mode') || pageRoot.dataset.decisionMode || ''],
      ].forEach(([key, value]) => {
        if (value) url.searchParams.set(key, value);
      });

      App.clearPageError(errorEl);
      try {
        const res = await App.fetchJson(url.toString());
        if (!res.ok) {
          App.renderPageError(errorEl, {
            title: 'Repair planning unavailable',
            summary: 'The repair planning API did not return usable data.',
            detail: res.error,
            status: res.status,
            raw: res.raw,
            primaryActionHref: App.currentPageUrl(),
            primaryActionLabel: 'Retry this page',
            secondaryActionHref: App.pageUrl('taxonomy_repairs'),
            secondaryActionLabel: 'Back to repair queue',
          });
          return;
        }

        const body = toRecord(res.body);
        const data = toRecord(body.data) as QueueData;
        const meta = toRecord(body.meta) as QueueMeta;
        const applyPlan = toRecord(data.apply_plan) as ApplyPlanPayload;
        const repairOpportunities = asRows<RepairOpportunity>(data.repair_opportunities);
        const queueRows = asRows<QueueRow>(data.rows);

        if (meta.schema_available === false) {
          if (metaEl) metaEl.textContent = `Primary: ${meta.primary_database || '--'} | schema unavailable`;
          if (summaryEl) summaryEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
          if (presetsEl) presetsEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
          if (actionsEl) actionsEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
          if (applyPlanSummaryEl) applyPlanSummaryEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
          if (applyPlanBodyEl) applyPlanBodyEl.innerHTML = '<tr><td colspan="8" class="muted">No dry-run plan available.</td></tr>';
          if (opportunitiesBodyEl) opportunitiesBodyEl.innerHTML = '<tr><td colspan="7" class="muted">No repair opportunities available.</td></tr>';
          return;
        }

        if (metaEl) {
          const generated = meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : '--';
          const platformFocus = meta.platform ? ` | Platform: ${meta.platform}` : '';
          const pairFocus = meta.pair_catalog || meta.pair_signal ? ` | Pair focus: ${(meta.pair_catalog || '(empty)')} vs ${(meta.pair_signal || '(empty)')}` : '';
          const actionFocus = meta.fix_action ? ` | Action: ${meta.fix_action}` : '';
          const targetFocus = meta.target_family ? ` | Target: ${meta.target_family}` : '';
          const decisionFocus = meta.decision_mode ? ` | Decision: ${meta.decision_mode}` : '';
          metaEl.textContent = `Primary: ${meta.primary_database || '--'} | Updated: ${generated}${platformFocus}${pairFocus}${actionFocus}${targetFocus}${decisionFocus}`;
        }

        const isFocusedSlice = Boolean(meta.pair_catalog || meta.pair_signal || meta.fix_action || meta.target_family || meta.decision_mode);
        setSectionHidden(opportunitiesSectionEl, !repairOpportunities.length && isFocusedSlice);
        setSectionHidden(planSectionEl, asRows<ApplyPlanRow>(applyPlan.plan_rows).length === 0 && isFocusedSlice);
        setSectionHidden(presetsSectionEl, Boolean(meta.pair_catalog || meta.pair_signal));

        renderActiveSlice(meta);
        renderSummary(asRows<SummaryRow>(data.summary), meta);
        renderFocusState(meta, toRecord(data.decision_inventory) as DecisionInventory, queueRows);
        renderPresets(asRows<QueuePreset>(data.queue_presets));
        renderFixActionInventory(toRecord(data.fix_action_inventory) as FixActionInventory);
        renderRepairOpportunities(repairOpportunities);
        renderApplyPlan(applyPlan);
      } catch (err) {
        App.renderPageError(errorEl, {
          title: 'Repair planning load failed',
          summary: 'The browser hit an unexpected failure while rendering repair planning.',
          detail: err instanceof Error ? err.message : String(err),
          primaryActionHref: App.currentPageUrl(),
          primaryActionLabel: 'Reload this page',
          secondaryActionHref: App.pageUrl('taxonomy_repairs'),
          secondaryActionLabel: 'Back to repair queue',
        });
      }
    }

    void load();
  }
}
