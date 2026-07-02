import type { AppSurface, JsonRecord } from '../types/app-globals';
import { asRows, debounce, toRecord } from '../shared/dom';
import {
  bindCopyTargets,
  createCursorPager,
  formatUtcFixed,
  renderUnknown,
  setTableMessage,
  shouldQueueApiFallback,
} from '../shared/readonly-table';

type QueueRow = JsonRecord & {
  permission_string?: unknown;
  normalized_action?: unknown;
  queue_action?: unknown;
  queue_action_normalized?: unknown;
  queue_action_raw?: unknown;
  status?: unknown;
  queue_status?: unknown;
  status_normalized?: unknown;
  status_raw?: unknown;
  queue_population_label?: unknown;
  source_system?: unknown;
  has_obs_sample?: unknown;
  has_vt_event?: unknown;
  has_ledger_anchor?: unknown;
  already_in_aosp?: unknown;
  conflict_label?: unknown;
  queue_triage_status_display?: unknown;
  triage_status_display?: unknown;
  queue_triage_status?: unknown;
  triage_status?: unknown;
  dict_unknown_triage_status_display?: unknown;
  dict_unknown_triage_status?: unknown;
  proposed_classification?: unknown;
  proposed_bucket?: unknown;
  queued_at_utc?: unknown;
  processed_at_utc?: unknown;
  updated_at_utc?: unknown;
  error_message?: unknown;
};

const root = document.getElementById('permission-queue-page') as HTMLElement | null;

const STATUS_LABELS: Record<string, { label: string; className: string }> = {
  queued: { label: 'Queued', className: 'badge warn' },
  claimed: { label: 'Claimed', className: 'badge warn' },
  applied: { label: 'Applied', className: 'badge ok' },
  error: { label: 'Error', className: 'badge err' },
  rejected: { label: 'Rejected', className: 'badge muted' },
  skipped: { label: 'Skipped', className: 'badge muted' },
};

const ACTION_LABELS: Record<string, string> = {
  aosp: 'AOSP',
  oem: 'OEM',
  google: 'Google',
  reject: 'Reject / no action',
  defer: 'Defer',
  skip: 'Skip',
  app_defined: 'App Defined',
  apply: 'Apply',
};

const POPULATION_LABELS: Record<string, { label: string; className: string }> = {
  imported_static_candidate_no_anchor: { label: 'Imported static candidate', className: 'badge warn' },
  already_resolved_aosp_duplicate: { label: 'Superseded duplicate', className: 'badge muted' },
  malformed_ledger_conflict: { label: 'Malformed conflict', className: 'badge err' },
  evidence_backed_queue_work: { label: 'Evidence-backed queue work', className: 'badge ok' },
  web_triage_queue: { label: 'Web triage queue', className: 'badge ok' },
  other_queue_state: { label: 'Other queue state', className: 'badge muted' },
};

const CONFLICT_LABELS: Record<string, { label: string; className: string } | null> = {
  missing_ledger_anchor: { label: 'No ledger anchor', className: 'badge warn' },
  already_resolved_duplicate: { label: 'Already resolved', className: 'badge muted' },
  malformed_ledger: { label: 'Malformed ledger', className: 'badge err' },
  none: null,
};

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';
  const defaultLimit = Number(root.dataset.defaultLimit || 50);

  const searchEl = document.getElementById('permission-queue-search') as HTMLInputElement | null;
  const statusEl = document.getElementById('permission-queue-status') as HTMLSelectElement | null;
  const actionEl = document.getElementById('permission-queue-action') as HTMLSelectElement | null;
  const limitEl = document.getElementById('permission-queue-limit') as HTMLSelectElement | null;
  const refreshBtn = document.getElementById('permission-queue-refresh');
  const compactBtn = document.getElementById('permission-queue-compact-toggle');
  const pageIndexEl = document.getElementById('permission-queue-page-index');

  const summaryEl = document.getElementById('permission-queue-summary');
  const populationSummaryEl = document.getElementById('permission-queue-population-summary');
  const summaryNoteEl = document.getElementById('permission-queue-summary-note');
  const unavailableEl = document.getElementById('permission-queue-unavailable');
  const bodyEl = document.getElementById('permission-queue-body');
  const metaEl = document.getElementById('permission-queue-meta');
  const errorEl = document.getElementById('permission-queue-error');
  const bannerEl = document.getElementById('permission-queue-api-banner');
  const pageRoot = document.getElementById('permission-queue-root');
  const prevBtn = document.getElementById('permission-queue-prev') as HTMLButtonElement | null;
  const nextBtn = document.getElementById('permission-queue-next') as HTMLButtonElement | null;

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const pager = createCursorPager();
  const offlineBanner = 'Enhanced API unavailable - showing DB-backed read-only state.';
  const compactKey = 'perm_queue_compact';

  const scheduleReload = debounce(() => {
    pager.reset();
    renderPaging();
    void loadQueue();
  }, 200);

  function setUnavailable(message: string): void {
    if (!unavailableEl) return;
    unavailableEl.textContent = message;
    unavailableEl.style.display = 'block';
  }

  function clearUnavailable(): void {
    if (!unavailableEl) return;
    unavailableEl.textContent = '';
    unavailableEl.style.display = 'none';
  }

  function setCompact(enabled: boolean): void {
    pageRoot?.classList.toggle('compact', enabled);
    if (compactBtn) compactBtn.textContent = enabled ? 'Expanded view' : 'Compact view';
    try {
      localStorage.setItem(compactKey, enabled ? '1' : '0');
    } catch {
      // ignore storage failures
    }
  }

  function loadCompact(): void {
    let enabled = false;
    try {
      enabled = localStorage.getItem(compactKey) === '1';
    } catch {
      enabled = false;
    }
    setCompact(enabled);
  }

  function setBannerApiRunning(): void {
    if (!bannerEl) return;
    bannerEl.className = 'notice info';
    bannerEl.textContent = 'API server: RUNNING';
  }

  function setBannerFallback(): void {
    if (!bannerEl) return;
    bannerEl.className = 'notice warn';
    bannerEl.textContent = offlineBanner;
  }

  function formatActionCell(row: QueueRow): string {
    const normalized = String(row.normalized_action ?? row.queue_action ?? row.queue_action_normalized ?? row.queue_action_raw ?? '');
    if (!normalized) return '--';
    const key = normalized.toLowerCase();
    const label = ACTION_LABELS[key] ?? renderUnknown(normalized);
    let extra = '';
    if (row.queue_action_raw && row.queue_action
      && String(row.queue_action_raw).toLowerCase() !== String(row.queue_action).toLowerCase()) {
      extra = `<div class="muted">raw: ${esc(row.queue_action_raw)}</div>`;
    }
    return `<div>${esc(label)}</div>${extra}`;
  }

  function formatStatusCell(row: QueueRow): string {
    const normalized = String(row.status ?? row.queue_status ?? row.status_normalized ?? row.status_raw ?? '');
    if (!normalized) return '--';
    const key = normalized.toLowerCase();
    const known = STATUS_LABELS[key];
    let detail = '';
    if (row.status_raw && row.status && String(row.status_raw).toLowerCase() !== String(row.status).toLowerCase()) {
      detail = `<div class="muted">raw: ${esc(row.status_raw)}</div>`;
    }
    if (known) return `<span class="${known.className}">${esc(known.label)}</span>${detail}`;
    return `<span class="badge muted">${esc(renderUnknown(normalized))}</span>${detail}`;
  }

  function renderSummary(counts: JsonRecord | null): void {
    if (!summaryEl || !summaryNoteEl) return;
    summaryEl.innerHTML = '';
    if (!counts || !Object.keys(counts).length) {
      summaryNoteEl.style.display = 'block';
      return;
    }
    summaryNoteEl.style.display = 'none';
    Object.entries(counts).forEach(([key, value]) => {
      const label = STATUS_LABELS[key]?.label ?? renderUnknown(key);
      const row = document.createElement('div');
      row.className = 'detail-row';
      row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, '0'))}</div>
      `;
      summaryEl.appendChild(row);
    });
  }

  function renderPopulationSummary(counts: JsonRecord | null): void {
    if (!populationSummaryEl || !counts) return;
    populationSummaryEl.innerHTML = '';
    const entries = Object.entries(counts)
      .filter(([, value]) => Number(value || 0) > 0)
      .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0));
    entries.forEach(([key, value]) => {
      const known = POPULATION_LABELS[key];
      const label = known?.label ?? renderUnknown(key);
      const row = document.createElement('div');
      row.className = 'detail-row';
      row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, '0'))}</div>
      `;
      populationSummaryEl.appendChild(row);
    });
  }

  function renderBadge(label: string, className = 'badge muted'): string {
    return `<span class="${className}">${esc(label)}</span>`;
  }

  function renderPopulationCell(row: QueueRow): string {
    const key = String(row.queue_population_label || 'other_queue_state');
    const known = POPULATION_LABELS[key];
    const label = known?.label ?? renderUnknown(key);
    const badge = renderBadge(label, known?.className ?? 'badge muted');
    const source = row.source_system ? `<div class="muted">source: ${esc(row.source_system)}</div>` : '';
    return `${badge}${source}`;
  }

  function renderSignalsCell(row: QueueRow): string {
    const badges: string[] = [];
    const source = row.source_system ? String(row.source_system) : 'unknown';
    badges.push(renderBadge(source, source === 'web' ? 'badge ok' : (source === 'static-analysis' ? 'badge warn' : 'badge muted')));

    if (row.has_obs_sample && row.has_vt_event) badges.push(renderBadge('obs + vt', 'badge ok'));
    else if (row.has_obs_sample) badges.push(renderBadge('obs', 'badge ok'));
    else if (row.has_vt_event) badges.push(renderBadge('vt', 'badge ok'));
    else badges.push(renderBadge('no evidence', 'badge muted'));

    badges.push(renderBadge(row.has_ledger_anchor ? 'anchored' : 'no ledger', row.has_ledger_anchor ? 'badge ok' : 'badge warn'));
    badges.push(renderBadge(row.already_in_aosp ? 'in AOSP' : 'not in AOSP', row.already_in_aosp ? 'badge muted' : 'badge warn'));

    const conflict = CONFLICT_LABELS[String(row.conflict_label || 'none')];
    if (conflict) badges.push(renderBadge(conflict.label, conflict.className));

    return badges.join(' ');
  }

  function renderTriageCell(row: QueueRow): string {
    const queueTriage = String(row.queue_triage_status_display ?? row.triage_status_display ?? row.queue_triage_status ?? row.triage_status ?? '--');
    const ledgerTriage = String(row.dict_unknown_triage_status_display ?? row.dict_unknown_triage_status ?? '');
    if (!ledgerTriage || ledgerTriage === '-' || ledgerTriage === queueTriage) return esc(queueTriage);
    return `
      <div>${esc(queueTriage)}</div>
      <div class="muted">ledger: ${esc(ledgerTriage)}</div>
    `;
  }

  function renderTimelineCell(row: QueueRow): string {
    const queuedAt = row.queued_at_utc ? formatUtcFixed(App, row.queued_at_utc) : '--';
    const processedAt = row.processed_at_utc ? formatUtcFixed(App, row.processed_at_utc) : '--';
    const updatedAt = row.updated_at_utc ? formatUtcFixed(App, row.updated_at_utc) : '--';
    return `
      <div>Queued: ${esc(queuedAt)}</div>
      <div class="muted">Processed: ${esc(processedAt)}</div>
      <div class="muted">Updated: ${esc(updatedAt)}</div>
    `;
  }

  function renderSummaryNote(payload: JsonRecord): void {
    if (!summaryNoteEl) return;
    const countsByAction = toRecord(payload.counts_by_action_active ?? payload.counts_by_action);
    const countsByStatusActive = toRecord(payload.counts_by_status_active);
    const legacyQueueActions = asRows<JsonRecord>(payload.legacy_queue_actions_active);
    const parts: string[] = [];

    if (Object.keys(countsByAction).length) {
      const actionSummary = Object.entries(countsByAction)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${ACTION_LABELS[String(key).toLowerCase()] ?? renderUnknown(key)} ${fmt(value, '0')}`);
      if (actionSummary.length) parts.push(`Active action mix: ${actionSummary.join(' | ')}`);
    }

    if (Object.keys(countsByStatusActive).length) {
      const activeStatusSummary = Object.entries(countsByStatusActive)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${STATUS_LABELS[String(key).toLowerCase()]?.label ?? renderUnknown(key)} ${fmt(value, '0')}`);
      if (activeStatusSummary.length) parts.push(`Active queue state: ${activeStatusSummary.join(' | ')}`);
    }

    const countsByPopulation = toRecord(payload.counts_by_population);
    if (Object.keys(countsByPopulation).length) {
      const populationSummary = Object.entries(countsByPopulation)
        .filter(([, value]) => Number(value || 0) > 0)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${POPULATION_LABELS[key]?.label ?? renderUnknown(key)} ${fmt(value, '0')}`);
      if (populationSummary.length) parts.push(`Populations: ${populationSummary.join(' | ')}`);
    }

    if (legacyQueueActions.length) {
      const legacySummary = legacyQueueActions
        .map((item) => `${item.raw} -> ${item.normalized} (${fmt(item.count, '0')})`)
        .join(' | ');
      parts.push(`Legacy raw actions still active: ${legacySummary}`);
    }

    summaryNoteEl.style.display = 'block';
    summaryNoteEl.textContent = parts.length ? parts.join(' ') : 'Counts unavailable (backend not providing summary).';
  }

  function renderRows(rows: QueueRow[]): void {
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    if (!rows.length) {
      setTableMessage(App, bodyEl, 10, 'No queue entries found.');
      return;
    }

    rows.forEach((row) => {
      const permission = fmt(row.permission_string);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="cell-wrap"><span class="copyable mono" data-copy="${esc(permission)}">${esc(permission)}</span></td>
        <td class="cell-wrap">${renderPopulationCell(row)}</td>
        <td class="cell-wrap">${renderSignalsCell(row)}</td>
        <td>${formatActionCell(row)}</td>
        <td>${formatStatusCell(row)}</td>
        <td class="cell-wrap">${renderTriageCell(row)}</td>
        <td>${esc(fmt(row.proposed_classification))}</td>
        <td>${esc(fmt(row.proposed_bucket))}</td>
        <td class="cell-wrap">${renderTimelineCell(row)}</td>
        <td class="cell-wrap">${esc(fmt(row.error_message))}</td>
      `;
      bodyEl.appendChild(tr);
    });

    bindCopyTargets(App, bodyEl);
  }

  function renderMeta(payload: JsonRecord, meta: JsonRecord): void {
    if (!metaEl) return;
    const rows = asRows<unknown>(payload.items ?? payload.rows);
    const generated = payload.generated_at_utc
      ? formatUtcFixed(App, payload.generated_at_utc)
      : (meta.generated_at_utc ? formatUtcFixed(App, meta.generated_at_utc) : '--');
    const total = payload.total_count !== undefined ? `Total: ${payload.total_count} | ` : '';
    metaEl.textContent = `${total}Showing: ${rows.length} | Last refresh: ${generated}`;
  }

  function renderPaging(): void {
    if (pageIndexEl) pageIndexEl.textContent = String(pager.getPageIndex());
    if (prevBtn) prevBtn.disabled = pager.getStackSize() === 0;
    if (nextBtn) nextBtn.disabled = !pager.getHasMore() || !pager.getNext();
  }

  function buildQuery(): URLSearchParams {
    const params = new URLSearchParams();
    params.set('include_population_counts', '0');
    if (searchEl?.value.trim()) params.set('search', searchEl.value.trim());
    if (statusEl?.value) params.set('status', statusEl.value);
    if (actionEl?.value) params.set('queue_action', actionEl.value);
    params.set('limit', limitEl?.value || String(defaultLimit));
    if (pager.getCurrent()) params.set('cursor', pager.getCurrent());
    return params;
  }

  function updateUrl(params: URLSearchParams): void {
    const url = new URL(window.location.href);
    const keep = new URLSearchParams();
    ['search', 'status', 'queue_action', 'limit'].forEach((key) => {
      if (params.has(key)) keep.set(key, params.get(key) || '');
    });
    url.search = keep.toString();
    window.history.replaceState({}, '', url.toString());
  }

  async function loadQueue(): Promise<void> {
    if (!endpoint) return;
    if (errorEl) errorEl.textContent = '';
    setTableMessage(App, bodyEl, 10, 'Loading queue...');
    clearUnavailable();

    const params = buildQuery();
    updateUrl(params);
    const url = `${endpoint}?${params.toString()}`;

    try {
      const res = await App.fetchPayload(url);
      if (!res.ok) {
        if (res.code === 'ERR_SCHEMA_MISSING' || res.status === 404) {
          setUnavailable('Backend capability not enabled for the permission queue yet.');
          setBannerApiRunning();
          setTableMessage(App, bodyEl, 10, 'Not available.');
          renderSummary(null);
          renderPopulationSummary(null);
          return;
        }
        if (shouldQueueApiFallback(res)) setBannerFallback();
        else setBannerApiRunning();
        const detail = res.raw ? `\n\n${String(res.raw).slice(0, 2000)}` : '';
        if (errorEl) {
          errorEl.innerHTML = `<pre>Permission queue API error.\n\nHTTP ${res.status}\nerror: ${esc(res.error || 'Request failed')}${detail}</pre>`;
        }
        return;
      }

      setBannerApiRunning();
      const payload = toRecord(res.data);
      pager.updateFromPayload(payload);
      renderRows(asRows<QueueRow>(payload.items ?? payload.rows));
      renderSummary(toRecord(payload.counts_by_status ?? payload.countsByStatus));
      renderPopulationSummary(toRecord(payload.counts_by_population));
      renderSummaryNote(payload);
      renderMeta(payload, toRecord(res.meta));
      renderPaging();
    } catch (error) {
      if (errorEl) {
        errorEl.innerHTML = `<pre>Permission queue API error:\n${esc(error instanceof Error ? error.message : String(error))}</pre>`;
      }
    }
  }

  if (limitEl && !limitEl.value) limitEl.value = String(defaultLimit);

  [searchEl, statusEl, actionEl, limitEl].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', scheduleReload);
    el.addEventListener('change', scheduleReload);
  });

  refreshBtn?.addEventListener('click', () => { void loadQueue(); });
  nextBtn?.addEventListener('click', () => {
    if (!pager.getHasMore() || !pager.getNext()) return;
    pager.push();
    pager.setCurrent(pager.getNext());
    void loadQueue();
  });
  prevBtn?.addEventListener('click', () => {
    if (pager.getStackSize() === 0) return;
    pager.pop();
    void loadQueue();
  });
  compactBtn?.addEventListener('click', () => {
    setCompact(!(pageRoot?.classList.contains('compact') ?? false));
  });

  pager.reset();
  renderPaging();
  loadCompact();
  void loadQueue();
}
