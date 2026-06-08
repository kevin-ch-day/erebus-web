import type { AppSurface } from '../types/app-globals';
import {
  asRows,
  toRecord,
  type PageParams,
  type QueueData,
  type QueueMeta,
  type QueueRow,
  type SummaryRow,
} from './family-taxonomy-queue-types';

const root = document.getElementById('family-taxonomy-queue-page') as HTMLElement | null;

if (root && window.App) {
  const pageRoot = root;
  const App = window.App as AppSurface;
  const endpoint = pageRoot.dataset.endpoint || '';
  const exportEndpoint = pageRoot.dataset.exportEndpoint || '';

  if (endpoint) {
    const searchInput = document.getElementById('family-taxonomy-queue-search') as HTMLInputElement | null;
    const alignmentSelect = document.getElementById('family-taxonomy-queue-alignment') as HTMLSelectElement | null;
    const platformSelect = document.getElementById('family-taxonomy-queue-platform') as HTMLSelectElement | null;
    const patternSelect = document.getElementById('family-taxonomy-queue-pattern') as HTMLSelectElement | null;
    const limitSelect = document.getElementById('family-taxonomy-queue-limit') as HTMLSelectElement | null;
    const rowFilterInput = document.getElementById('family-taxonomy-queue-row-filter') as HTMLInputElement | null;
    const refreshBtn = document.getElementById('family-taxonomy-queue-refresh') as HTMLButtonElement | null;
    const exportBtn = document.getElementById('family-taxonomy-queue-export') as HTMLAnchorElement | null;
    const metaEl = document.getElementById('family-taxonomy-queue-meta');
    const activeSliceEl = document.getElementById('family-taxonomy-queue-active-slice');
    const summaryEl = document.getElementById('family-taxonomy-queue-summary');
    const rowsCopyEl = document.getElementById('family-taxonomy-queue-rows-copy');
    const rowsBodyEl = document.getElementById('family-taxonomy-queue-rows-body');
    const errorEl = document.getElementById('family-taxonomy-queue-error');

    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const formatUtc = App.formatUtc;
    let latestQueueRows: QueueRow[] = [];

    function badgeClassForAlignment(value: unknown): string {
      const key = String(value || '').toLowerCase();
      if (key === 'aligned') return 'badge ok';
      if (key === 'mismatch') return 'badge err';
      if (key === 'signal_only' || key === 'catalog_only') return 'badge warn';
      if (key === 'semantic_conflict' || key === 'placeholder_catalog') return 'badge err';
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
        limit: limitSelect ? limitSelect.value : (pageRoot.dataset.limit || '100'),
      };
      const alignment = alignmentSelect ? alignmentSelect.value : (pageRoot.dataset.alignment || '');
      const platform = platformSelect ? platformSelect.value : (pageRoot.dataset.platform || '');
      const pattern = patternSelect ? patternSelect.value : (pageRoot.dataset.pattern || '');
      const query = searchInput ? searchInput.value.trim() : (pageRoot.dataset.query || '');
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

    function updateExportLink(): void {
      if (!exportBtn || !exportEndpoint) return;
      const url = new URL(exportEndpoint, window.location.origin);
      const params = currentFilterParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== '') {
          url.searchParams.set(key, value);
        }
      });
      exportBtn.href = url.toString();
    }

    function renderSummary(summaryRows: SummaryRow[], meta: QueueMeta): void {
      if (!summaryEl) return;
      if (!summaryRows.length) {
        summaryEl.innerHTML = '<div class="detail-card"><div class="muted">No queue summary rows found.</div></div>';
        return;
      }
      const pairFocus = meta && (meta.pair_catalog || meta.pair_signal)
        ? `${meta.pair_catalog || '(empty)'} vs ${meta.pair_signal || '(empty)'}`
        : 'None';
      const clearUrl = pageLink('taxonomy_repairs', { limit: limitSelect ? limitSelect.value : '100' });
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
      if (pattern !== '') {
        const params = { ...baseParams };
        delete params.pattern;
        chips.push(chip('Pattern', pattern, params));
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
      if (query !== '') {
        const params = { ...baseParams };
        delete params.q;
        chips.push(chip('Search', query, params));
      }

      const currentLimit = String(meta.limit || limitSelect?.value || pageRoot.dataset.limit || '100');
      const resetHref = pageLink('taxonomy_repairs', { limit: currentLimit });

      activeSliceEl.innerHTML = chips.length
        ? `
          <div class="filters-active-slice">
            <div class="detail-card-title">Active slice</div>
            <div class="filters-active-slice-copy">These filters are shaping the current repair queue.</div>
            <div class="filters-chip-row">${chips.join('')}</div>
            <div><a class="table-link" href="${esc(resetHref)}">Reset to broad queue</a></div>
          </div>
        `
        : `
          <div class="filters-active-slice">
            <div class="detail-card-title">Active slice</div>
            <div class="filters-active-slice-copy">Broad queue view. No narrow pair or decision filters are active yet.</div>
          </div>
        `;
    }

    function rowMatchesFilter(row: QueueRow, query: string): boolean {
      const haystack = [
        row.sample_id,
        row.family_label,
        row.popular_threat_name,
        row.popular_threat_label,
        row.issue_kind,
        row.suggested_fix_action,
        row.decision_mode,
        row.suggested_target_family,
      ].map((value) => String(value || '').toLowerCase()).join(' ');
      return haystack.includes(query);
    }

    function visibleQueueRows(): QueueRow[] {
      const query = String(rowFilterInput?.value || '').trim().toLowerCase();
      if (query === '') return latestQueueRows;
      return latestQueueRows.filter((row) => rowMatchesFilter(row, query));
    }

    function renderRows(): void {
      if (!rowsBodyEl) return;
      const rows = visibleQueueRows();
      if (!rows.length) {
        rowsBodyEl.innerHTML = '<tr><td colspan="6" class="muted">No repair rows match this filter.</td></tr>';
        return;
      }

      rowsBodyEl.innerHTML = rows.map((row) => {
        const sampleUrl = App.pageUrl('sample', { sample_id: row.sample_id || '' });
        const signalName = row.popular_threat_name || '--';
        const signalLabel = row.popular_threat_label || '--';
        const confBucket = row.confidence_bucket || '--';
        const rowStateClass = String(row.decision_priority || '').toLowerCase() === 'high'
          ? 'queue-row-priority-high'
          : String(row.decision_priority || '').toLowerCase() === 'medium'
            ? 'queue-row-priority-medium'
            : 'queue-row-priority-low';
        const targetFamily = String(row.suggested_target_family || '').trim();
        const targetLine = targetFamily !== ''
          ? `<div class="queue-row-meta"><span class="queue-row-meta-label">Target</span> ${esc(targetFamily)}</div>`
          : '';
        const reason = compactCopy(row.issue_reason, '--', 52);
        const nextStep = compactCopy(row.decision_why || row.suggested_fix_reason, '--', 52);
        const genericState = row.generic_label_flag ? '<div class="queue-row-meta">generic</div>' : '';
        const signalHint = signalLabel && signalLabel !== signalName
          ? `<div class="queue-row-meta">${esc(compactCopy(signalLabel, '--', 32))}</div>`
          : '';

        return `
          <tr class="${rowStateClass}">
            <td>
              <a class="table-link queue-row-sample-id" href="${esc(sampleUrl)}">${esc(fmt(row.sample_id))}</a>
            </td>
            <td>
              <div class="queue-row-party">
                <div class="queue-row-party-value">${esc(row.family_label || '--')}</div>
                ${genericState}
              </div>
            </td>
            <td>
              <div class="queue-row-party">
                <div class="queue-row-party-value">${esc(signalName)}</div>
                ${signalHint}
                <div class="queue-row-meta"><span class="${badgeClassForAlignment(row.alignment_status)}">${esc(row.alignment_status || '--')}</span></div>
              </div>
            </td>
            <td>
              <div class="queue-row-verdict">
                <div class="queue-row-chip-row">
                  <span class="${badgeClassForAlignment(row.issue_kind)}">${esc(row.issue_kind || '--')}</span>
                  <span class="${badgeClassForConfidence(row.decision_priority)}">${esc(row.decision_priority || '--')}</span>
                </div>
              </div>
            </td>
            <td>
              <div class="queue-row-verdict">
                <div class="queue-row-chip-row">
                  <span class="${badgeClassForConfidence(row.suggested_fix_confidence)}">${esc(row.suggested_fix_action || '--')}</span>
                  <span class="${badgeClassForConfidence(row.decision_priority)}">${esc(row.decision_mode || '--')}</span>
                  ${confBucket !== '--' ? `<span class="${badgeClassForAlignment(confBucket)}">${esc(confBucket)}</span>` : ''}
                </div>
                ${targetLine}
              </div>
            </td>
            <td>
              <div class="queue-row-notes queue-row-notes-compact">
                <div class="queue-row-note-block">
                  <div class="queue-row-note-title">Why</div>
                  <div class="queue-row-note-copy" title="${esc(String(row.issue_reason || '--'))}">${esc(reason)}</div>
                </div>
                <div class="queue-row-note-block">
                  <div class="queue-row-note-title">Next</div>
                  <div class="queue-row-note-copy" title="${esc(String(row.decision_why || row.suggested_fix_reason || '--'))}">${esc(nextStep)}</div>
                </div>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function applyUrl(limit: string, alignment: string, platform: string, pattern: string, query: string, pairCatalog: string, pairSignal: string, fixAction: string, targetFamily: string, decisionMode: string): void {
      const url = new URL(window.location.href);
      url.searchParams.set('p', 'family_taxonomy_queue');
      url.searchParams.set('limit', String(limit));
      if (alignment) url.searchParams.set('alignment', alignment);
      else url.searchParams.delete('alignment');
      if (platform) url.searchParams.set('platform', platform);
      else url.searchParams.delete('platform');
      if (pattern) url.searchParams.set('pattern', pattern);
      else url.searchParams.delete('pattern');
      if (query) url.searchParams.set('q', query);
      else url.searchParams.delete('q');
      if (pairCatalog) url.searchParams.set('pair_catalog', pairCatalog);
      else url.searchParams.delete('pair_catalog');
      if (pairSignal) url.searchParams.set('pair_signal', pairSignal);
      else url.searchParams.delete('pair_signal');
      if (fixAction) url.searchParams.set('fix_action', fixAction);
      else url.searchParams.delete('fix_action');
      if (targetFamily) url.searchParams.set('target_family', targetFamily);
      else url.searchParams.delete('target_family');
      if (decisionMode) url.searchParams.set('decision_mode', decisionMode);
      else url.searchParams.delete('decision_mode');
      window.history.replaceState({}, '', url.toString());
    }

    async function load(): Promise<void> {
      const limit = limitSelect ? limitSelect.value : (pageRoot.dataset.limit || '100');
      const alignment = alignmentSelect ? alignmentSelect.value : (pageRoot.dataset.alignment || '');
      const platform = platformSelect ? platformSelect.value : (pageRoot.dataset.platform || '');
      const pattern = patternSelect ? patternSelect.value : (pageRoot.dataset.pattern || '');
      const query = searchInput ? searchInput.value.trim() : (pageRoot.dataset.query || '');
      const currentUrl = new URL(window.location.href);
      const pairCatalog = currentUrl.searchParams.get('pair_catalog') || pageRoot.dataset.pairCatalog || '';
      const pairSignal = currentUrl.searchParams.get('pair_signal') || pageRoot.dataset.pairSignal || '';
      const fixAction = currentUrl.searchParams.get('fix_action') || pageRoot.dataset.fixAction || '';
      const targetFamily = currentUrl.searchParams.get('target_family') || pageRoot.dataset.targetFamily || '';
      const decisionMode = currentUrl.searchParams.get('decision_mode') || pageRoot.dataset.decisionMode || '';

      applyUrl(limit, alignment, platform, pattern, query, pairCatalog, pairSignal, fixAction, targetFamily, decisionMode);
      updateExportLink();
      App.clearPageError(errorEl);

      try {
        const buildQueueUrl = (decisionModeOverride: string): string => {
          const url = new URL(endpoint, window.location.origin);
          url.searchParams.set('limit', String(limit));
          if (alignment) url.searchParams.set('alignment', alignment);
          if (platform) url.searchParams.set('platform', platform);
          if (pattern) url.searchParams.set('pattern', pattern);
          if (query) url.searchParams.set('q', query);
          if (pairCatalog) url.searchParams.set('pair_catalog', pairCatalog);
          if (pairSignal) url.searchParams.set('pair_signal', pairSignal);
          if (fixAction) url.searchParams.set('fix_action', fixAction);
          if (targetFamily) url.searchParams.set('target_family', targetFamily);
          if (decisionModeOverride) url.searchParams.set('decision_mode', decisionModeOverride);
          return url.toString();
        };

        let effectiveDecisionMode = decisionMode;
        let res = await App.fetchJson(buildQueueUrl(effectiveDecisionMode));
        if (!res.ok) {
          App.renderPageError(errorEl, {
            title: 'Family repair queue unavailable',
            summary: 'The repair queue API did not return usable data, so the row-level adjudication view cannot be trusted yet.',
            detail: res.error,
            status: res.status,
            raw: res.raw,
            hint: 'Confirm the family taxonomy API and queue schema are healthy, then retry this exact slice.',
            primaryActionHref: App.currentPageUrl(),
            primaryActionLabel: 'Retry this queue',
            secondaryActionHref: App.pageUrl('taxonomy_home'),
            secondaryActionLabel: 'Open taxonomy home',
          });
          return;
        }

        let body = toRecord(res.body);
        let data = toRecord(body.data) as QueueData;
        let meta = toRecord(body.meta) as QueueMeta;
        let queueRows = asRows<QueueRow>(data.rows);

        const hasExactPairFocus = pairCatalog !== '' || pairSignal !== '';
        if (hasExactPairFocus && decisionMode !== '' && queueRows.length === 0) {
          const fallbackRes = await App.fetchJson(buildQueueUrl(''));
          if (fallbackRes.ok) {
            const fallbackBody = toRecord(fallbackRes.body);
            const fallbackData = toRecord(fallbackBody.data) as QueueData;
            const fallbackRows = asRows<QueueRow>(fallbackData.rows);
            if (fallbackRows.length > 0) {
              const fallbackMeta = toRecord(fallbackBody.meta) as QueueMeta;
              effectiveDecisionMode = String(fallbackMeta.decision_mode || '').trim();
              fallbackMeta.requested_decision_mode = decisionMode;
              fallbackMeta.recovered_decision_mode = effectiveDecisionMode;
              body = fallbackBody;
              data = fallbackData;
              meta = fallbackMeta;
              queueRows = fallbackRows;
              applyUrl(limit, alignment, platform, pattern, query, pairCatalog, pairSignal, fixAction, targetFamily, effectiveDecisionMode);
              updateExportLink();
            }
          }
        }

        if (meta.schema_available === false) {
          if (metaEl) metaEl.textContent = `Primary: ${meta.primary_database || '--'} | schema unavailable`;
          if (summaryEl) summaryEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
          if (rowsBodyEl) rowsBodyEl.innerHTML = '<tr><td colspan="6" class="muted">No rows available.</td></tr>';
          return;
        }

        if (metaEl) {
          const generated = meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : '--';
          const platformFocus = meta.platform ? ` | Platform: ${meta.platform}` : '';
          const pairFocus = meta.pair_catalog || meta.pair_signal
            ? ` | Pair focus: ${(meta.pair_catalog || '(empty)')} vs ${(meta.pair_signal || '(empty)')}`
            : '';
          const actionFocus = meta.fix_action ? ` | Action: ${meta.fix_action}` : '';
          const targetFocus = meta.target_family ? ` | Target: ${meta.target_family}` : '';
          const decisionFocus = meta.decision_mode ? ` | Decision: ${meta.decision_mode}` : '';
          const recoveryFocus = meta.requested_decision_mode && meta.recovered_decision_mode && meta.requested_decision_mode !== meta.recovered_decision_mode
            ? ` | Recovered: ${meta.requested_decision_mode} -> ${meta.recovered_decision_mode}`
            : '';
          metaEl.textContent = `Primary: ${meta.primary_database || '--'} | Updated: ${generated}${platformFocus}${pairFocus}${actionFocus}${targetFocus}${decisionFocus}${recoveryFocus}`;
        }

        if (rowsCopyEl) {
          rowsCopyEl.textContent = (meta.pair_catalog || meta.pair_signal)
            ? 'Rows ordered for this focused conflict. Use the quick filter here before opening grouped planning work.'
            : 'Rows ordered by repair priority. Use the quick filter here before opening grouped planning work.';
        }

        renderActiveSlice(meta);
        renderSummary(asRows<SummaryRow>(data.summary), meta);
        latestQueueRows = queueRows;
        renderRows();
      } catch (err) {
        App.renderPageError(errorEl, {
          title: 'Family repair queue load failed',
          summary: 'The browser hit an unexpected failure while rendering the repair queue.',
          detail: err instanceof Error ? err.message : String(err),
          hint: 'Reload the queue once. If the error persists, inspect the queue API and recent frontend changes.',
          primaryActionHref: App.currentPageUrl(),
          primaryActionLabel: 'Reload this queue',
          secondaryActionHref: App.pageUrl('landing'),
          secondaryActionLabel: 'Back to landing',
        });
      }
    }

    if (refreshBtn) refreshBtn.addEventListener('click', () => {
      void load();
    });

    if (searchInput) {
      searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          void load();
        }
      });
    }

    if (rowFilterInput) {
      rowFilterInput.addEventListener('input', () => {
        renderRows();
      });
    }

    [searchInput, alignmentSelect, platformSelect, patternSelect, limitSelect]
      .filter((el): el is HTMLInputElement | HTMLSelectElement => el !== null)
      .forEach((el) => {
        const eventName = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(eventName, updateExportLink);
      });

    updateExportLink();
    void load();
  }
}
