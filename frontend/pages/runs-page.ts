import type { AppSurface, JsonRecord } from '../types/app-globals';
import {
  asPipelineSnapshot,
  pipelinePrimaryCommand,
  pipelineActionTone,
} from '../shared/pipeline-posture';

type RunRow = JsonRecord & {
  run_id?: unknown;
  started_at_utc?: unknown;
  finished_at_utc?: unknown;
  db_name?: unknown;
  key_id?: unknown;
  processed_count?: unknown;
  ok_count?: unknown;
  no_data_count?: unknown;
  retry_wait_count?: unknown;
  error_count?: unknown;
  perm_taxonomy_version?: unknown;
  stopped_reason?: unknown;
};

type PlatformContext = JsonRecord & {
  primary_catalog?: unknown;
  permission_intel_catalog?: unknown;
  primary_schema_head?: unknown;
  permission_intel_schema_head?: unknown;
  latest_perm_taxonomy_version?: unknown;
  latest_perm_taxonomy_finished_at_utc?: unknown;
  mixed_visible_run_db_names?: unknown;
  mixed_visible_run_schema_versions?: unknown;
  mixed_visible_run_perm_taxonomy_versions?: unknown;
  visible_run_db_names?: unknown;
  visible_run_schema_versions?: unknown;
  visible_run_perm_taxonomy_versions?: unknown;
  split_enabled?: unknown;
  schema_heads_match?: unknown;
};

type RunsPayload = JsonRecord & {
  rows?: unknown;
  total_count?: unknown;
  page?: unknown;
  page_size?: unknown;
  total_pages?: unknown;
  platform_context?: unknown;
};

const root = document.getElementById('runs-page-root') as HTMLElement | null;

function asRows<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';
  const activityEndpoint = root.dataset.activityEndpoint || '';
  const pipelineOpsUrl = root.dataset.pipelineOpsUrl || '';
  const refreshSeconds = Number(root.dataset.refreshSeconds || '30') || 30;

  const searchEl = document.getElementById('runs-search') as HTMLInputElement | null;
  const pageSizeEl = document.getElementById('runs-page-size') as HTMLSelectElement | null;
  const stoppedReasonEl = document.getElementById('runs-stopped-reason') as HTMLSelectElement | null;
  const pageEl = document.getElementById('runs-page') as HTMLInputElement | null;
  const bodyEl = document.getElementById('runs-body') as HTMLElement | null;
  const metaEl = document.getElementById('runs-meta') as HTMLElement | null;
  const pagesEl = document.getElementById('runs-pages') as HTMLElement | null;
  const prevBtn = document.getElementById('runs-prev') as HTMLButtonElement | null;
  const nextBtn = document.getElementById('runs-next') as HTMLButtonElement | null;
  const errorEl = document.getElementById('runs-error') as HTMLElement | null;
  const platformPrimaryEl = document.getElementById('runs-platform-primary');
  const platformPiEl = document.getElementById('runs-platform-pi');
  const platformHeadsEl = document.getElementById('runs-platform-heads');
  const platformTaxonomyEl = document.getElementById('runs-platform-taxonomy');
  const platformSummaryEl = document.getElementById('runs-platform-summary');
  const platformListEl = document.getElementById('runs-platform-list');
  const activitySummaryEl = document.getElementById('runs-activity-summary');
  const activityRunsEl = document.getElementById('runs-activity-recent');
  const activityMetaEl = document.getElementById('runs-activity-meta');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;

  let pendingTimer: ReturnType<typeof setTimeout> | null = null;
  let lastPayload: RunsPayload | null = null;

  function buildQuery(pageOverride: number | null = null): URLSearchParams {
    const params = new URLSearchParams();
    const q = searchEl?.value.trim() || '';
    const pageSize = pageSizeEl?.value || '';
    const stoppedReason = stoppedReasonEl?.value.trim() || '';
    const page = pageOverride ?? pageEl?.value ?? '1';
    if (q) params.set('q', q);
    if (stoppedReason) params.set('stopped_reason', stoppedReason);
    if (pageSize) params.set('page_size', pageSize);
    params.set('page', String(page || '1'));
    return params;
  }

  function updateUrl(params: URLSearchParams): void {
    const url = new URL(window.location.href);
    url.search = params.toString();
    window.history.replaceState({}, '', url.toString());
  }

  function renderRows(rows: RunRow[]): void {
    if (!bodyEl) return;
    if (!rows.length) {
      bodyEl.innerHTML = '<tr><td colspan="11" class="muted">No runs found.</td></tr>';
      return;
    }
    bodyEl.innerHTML = rows.map((row) => {
      const keyLabel = row.key_id ? String(row.key_id) : '--';
      return `
        <tr>
          <td>${esc(row.run_id)}</td>
          <td>${esc(row.started_at_utc ? formatUtc(row.started_at_utc) : '--')}</td>
          <td>${esc(row.finished_at_utc ? formatUtc(row.finished_at_utc) : '--')}</td>
          <td>${esc(fmt(row.db_name))}<div class="muted">Key: ${esc(keyLabel)}</div></td>
          <td>${esc(fmt(row.processed_count))}</td>
          <td>${esc(fmt(row.ok_count))}</td>
          <td>${esc(fmt(row.no_data_count))}</td>
          <td>${esc(fmt(row.retry_wait_count))}</td>
          <td>${esc(fmt(row.error_count))}</td>
          <td class="mono">${esc(fmt(row.perm_taxonomy_version))}</td>
          <td>${esc(fmt(row.stopped_reason))}</td>
        </tr>
      `;
    }).join('');
  }

  function renderMeta(payload: RunsPayload): void {
    const total = Number(payload.total_count ?? 0);
    const page = Number(payload.page ?? 1);
    const pageSize = Number(payload.page_size ?? 0);
    const pages = Number(payload.total_pages ?? 1);
    if (metaEl) metaEl.textContent = `Total: ${total} | Page size: ${pageSize}`;
    if (pagesEl) pagesEl.textContent = String(pages);
    if (pageEl) pageEl.value = String(page);
    if (prevBtn) prevBtn.disabled = page <= 1;
    if (nextBtn) nextBtn.disabled = page >= pages;
  }

  function renderPlatformContext(context: PlatformContext | null): void {
    if (!platformPrimaryEl || !platformPiEl || !platformHeadsEl || !platformTaxonomyEl || !platformSummaryEl || !platformListEl) {
      return;
    }
    platformPrimaryEl.textContent = fmt(context?.primary_catalog);
    platformPiEl.textContent = fmt(context?.permission_intel_catalog);
    const primaryHead = context?.primary_schema_head ? String(context.primary_schema_head) : '--';
    const piHead = context?.permission_intel_schema_head ? String(context.permission_intel_schema_head) : '--';
    platformHeadsEl.textContent = `${primaryHead} / ${piHead}`;
    const taxVersion = context?.latest_perm_taxonomy_version ? String(context.latest_perm_taxonomy_version) : '--';
    const taxTime = context?.latest_perm_taxonomy_finished_at_utc
      ? formatUtc(context.latest_perm_taxonomy_finished_at_utc)
      : '--';
    platformTaxonomyEl.textContent = `${taxVersion} @ ${taxTime}`;

    const notices: string[] = [];
    if (context?.mixed_visible_run_db_names) {
      notices.push(`Visible rows span multiple db_name values: ${asRows<string>(context.visible_run_db_names).join(', ')}`);
    }
    if (context?.mixed_visible_run_schema_versions) {
      notices.push(`Visible rows span multiple schema_version values: ${asRows<string>(context.visible_run_schema_versions).join(', ')}`);
    }
    if (context?.mixed_visible_run_perm_taxonomy_versions) {
      notices.push(`Visible rows span multiple perm_taxonomy_version values: ${asRows<string>(context.visible_run_perm_taxonomy_versions).join(', ')}`);
    }
    if (context?.split_enabled && context.schema_heads_match === false) {
      notices.push(`Primary and PI schema heads differ: ${primaryHead} vs ${piHead}`);
    }

    if (!notices.length) {
      platformSummaryEl.textContent = 'Visible run rows align with one platform context.';
      platformListEl.innerHTML = '';
      return;
    }

    platformSummaryEl.textContent = 'Visible run rows span more than one platform state. Compare schema and taxonomy before drawing conclusions.';
    platformListEl.innerHTML = notices.map((notice) => `<li>${esc(notice)}</li>`).join('');
  }

  function renderActivity(data: JsonRecord): void {
    const pipeline = asPipelineSnapshot(data.pipeline);
    const rec = pipeline.recommendation || {};
    const command = pipelinePrimaryCommand(pipeline);
    const tone = pipelineActionTone(rec.action);
    const summary = String(rec.summary || '').trim();
    const runSummary = (data.run_summary && typeof data.run_summary === 'object') ? data.run_summary as JsonRecord : {};
    const recentRuns = asRows<RunRow>(data.recent_runs);

    if (activitySummaryEl) {
      const hint = summary !== '' ? (command !== '' ? `${summary} · CLI: ${command}` : summary) : 'No engine recommendation.';
      activitySummaryEl.className = `notice ${tone === 'warn' ? 'warn' : 'info'}`;
      activitySummaryEl.textContent = hint;
    }

    if (activityMetaEl) {
      activityMetaEl.textContent = [
        `Last run #${fmt(runSummary.latest_run_id)}`,
        `${fmt(runSummary.latest_processed_count)} processed`,
        `${fmt(runSummary.latest_stopped_reason)}`,
        `24h: ${fmt(runSummary.runs_24h)} runs · ${fmt(runSummary.processed_24h)} processed`,
      ].join(' · ');
    }

    if (!activityRunsEl) return;
    if (!recentRuns.length) {
      activityRunsEl.innerHTML = '<tr><td colspan="5" class="muted">No recent runs in ledger.</td></tr>';
      return;
    }

    activityRunsEl.innerHTML = recentRuns.map((row) => `
      <tr>
        <td>${esc(row.run_id)}</td>
        <td>${esc(row.finished_at_utc ? formatUtc(row.finished_at_utc) : '--')}</td>
        <td>${esc(fmt(row.processed_count))}</td>
        <td>${esc(fmt(row.ok_count))}</td>
        <td>${esc(fmt(row.stopped_reason))}</td>
      </tr>
    `).join('');
  }

  async function loadActivity(): Promise<void> {
    if (!activityEndpoint) return;
    const res = await App.fetchPayload(activityEndpoint);
    if (res.ok && res.data) {
      renderActivity(res.data as JsonRecord);
    }
  }

  async function loadRuns(pageOverride: number | null = null): Promise<void> {
    if (!endpoint || !bodyEl) return;
    const params = buildQuery(pageOverride);
    updateUrl(params);
    const url = `${endpoint}?${params.toString()}`;

    if (pendingTimer) {
      clearTimeout(pendingTimer);
      pendingTimer = null;
    }

    if (errorEl) errorEl.textContent = '';
    bodyEl.innerHTML = '<tr><td colspan="11" class="muted">Loading runs...</td></tr>';

    try {
      const res = await App.fetchJson(url);
      if (!res.ok) {
        if (errorEl) {
          const raw = res.raw ? String(res.raw).slice(0, 2000) : '';
          errorEl.innerHTML = `<pre>Run ledger API error.\n\nHTTP ${res.status}\nerror: ${esc(res.error)}${raw ? `\n\n${esc(raw)}` : ''}</pre>`;
        }
        return;
      }

      lastPayload = (res.body && typeof res.body === 'object') ? res.body as RunsPayload : {};
      renderRows(asRows<RunRow>(lastPayload.rows));
      renderMeta(lastPayload);
      renderPlatformContext((lastPayload.platform_context as PlatformContext) || null);
    } catch (error) {
      if (errorEl) {
        errorEl.innerHTML = `<pre>Run ledger API error:\n${esc(error instanceof Error ? error.message : String(error))}</pre>`;
      }
    }
  }

  function scheduleReload(): void {
    if (pendingTimer) clearTimeout(pendingTimer);
    pendingTimer = setTimeout(() => {
      void loadRuns(1);
    }, 200);
  }

  [searchEl, pageSizeEl, stoppedReasonEl].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', scheduleReload);
    el.addEventListener('change', scheduleReload);
  });

  pageEl?.addEventListener('change', () => {
    void loadRuns(Number(pageEl.value || 1));
  });
  prevBtn?.addEventListener('click', () => {
    void loadRuns(Math.max(1, Number(pageEl?.value || 1) - 1));
  });
  nextBtn?.addEventListener('click', () => {
    const pages = lastPayload ? Number(lastPayload.total_pages || 1) : 1;
    void loadRuns(Math.min(pages, Number(pageEl?.value || 1) + 1));
  });

  void loadRuns();
  void loadActivity();
  window.setInterval(() => {
    void loadActivity();
  }, Math.max(10, refreshSeconds) * 1000);

  void pipelineOpsUrl;
}
