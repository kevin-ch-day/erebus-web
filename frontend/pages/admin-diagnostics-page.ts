import type { AppPayloadFailure, AppPayloadSuccess, AppSurface, JsonRecord } from '../types/app-globals';
import { createDiagnosticsPanel } from '../shared/diagnostics-panel';

type CheckStatus = 'PASS' | 'WARN' | 'FAIL';

type ResultRow = {
  label: string;
  status: CheckStatus;
  detail: string;
  latencyMs?: number | null;
  rawDetails?: string | null;
};

type EndpointResult = AppPayloadSuccess | AppPayloadFailure;

const root = document.getElementById('admin-smoke-page') as HTMLElement | null;

function toRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}

if (root && window.App) {
  const App = window.App as AppSurface;

  const vtHealthUrl = root.dataset.vtHealth || '';
  const vtStatusUrl = root.dataset.vtStatus || '';
  const vtOpsSummaryUrl = root.dataset.vtOpsSummary || '';
  const queueUrl = root.dataset.permissionQueue || '';
  const pipelineHealthUrl = root.dataset.pipelineHealth || '';
  const pipelineStatusUrl = root.dataset.pipelineStatus || '';
  const pipelineActivityUrl = root.dataset.pipelineActivity || '';
  const apiBase = root.dataset.apiBase || '';
  const appVersion = root.dataset.appVersion || '';
  const appSha = root.dataset.appSha || '';
  const flagPhase2b = root.dataset.flagPhase2b === '1';
  const flagPhase3 = root.dataset.flagPhase3 === '1';

  const runBtn = document.getElementById('admin-smoke-run');
  const copyBtn = document.getElementById('admin-smoke-copy');
  const copyGoodBtn = document.getElementById('admin-smoke-copy-good');
  const saveBtn = document.getElementById('admin-smoke-save');
  const lastRunEl = document.getElementById('admin-smoke-last-run');
  const lastGoodEl = document.getElementById('admin-smoke-last-good');
  const totalsEl = document.getElementById('admin-smoke-totals');
  const bodyEl = document.getElementById('admin-smoke-body');
  const errorEl = document.getElementById('admin-smoke-error');

  const apiBaseEl = document.getElementById('admin-smoke-api-base');
  const apiLastFetchEl = document.getElementById('admin-smoke-api-last-fetch');
  const apiLastStatusEl = document.getElementById('admin-smoke-api-last-status');
  const apiLatencyEl = document.getElementById('admin-smoke-api-latency');
  const apiCapabilitiesEl = document.getElementById('admin-smoke-api-capabilities');

  const esc = App.escapeHtml;
  const formatUtcFixed = (value: unknown, includeSeconds = false): string =>
    App.formatUtc(value, { timeZone: 'UTC', includeSeconds });

  const diagnostics = createDiagnosticsPanel({
    baseUrlEl: apiBaseEl,
    lastFetchEl: apiLastFetchEl,
    statusEl: apiLastStatusEl,
    latencyEl: apiLatencyEl,
    capabilitiesEl: apiCapabilitiesEl,
    apiBase,
    formatUtc: formatUtcFixed,
  });

  const results: ResultRow[] = [];
  let lastPayloads: JsonRecord = {};
  const lastGoodKey = 'admin_diagnostics_last_good';
  let lastHttpStatus: number | null = null;

  function addResult(
    label: string,
    status: CheckStatus,
    detail: string,
    latencyMs: number | null = null,
    rawDetails: string | null = null,
  ): void {
    results.push({ label, status, detail, latencyMs, rawDetails });
  }

  function renderResults(): void {
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    if (!results.length) {
      bodyEl.innerHTML = '<tr><td colspan="4" class="muted">No results yet.</td></tr>';
      return;
    }

    results.forEach((row) => {
      const badgeClass = row.status === 'PASS'
        ? 'badge ok'
        : row.status === 'WARN'
          ? 'badge warn'
          : 'badge err';
      const tr = document.createElement('tr');
      const latency = row.latencyMs !== undefined && row.latencyMs !== null
        ? `${row.latencyMs} ms`
        : '--';
      const details = row.rawDetails
        ? `<details><summary>View JSON</summary><pre>${esc(row.rawDetails)}</pre></details>`
        : '--';
      tr.innerHTML = `
        <td>${esc(row.label)}</td>
        <td><span class="${badgeClass}">${esc(row.status)}</span></td>
        <td class="cell-wrap">${esc(row.detail || '--')}<div style="margin-top:6px;">${details}</div></td>
        <td class="mono">${esc(latency)}</td>
      `;
      bodyEl.appendChild(tr);
    });
    setTotals();
  }

  function setLastRun(): void {
    if (lastRunEl) lastRunEl.textContent = formatUtcFixed(new Date().toISOString(), true);
  }

  function setTotals(): void {
    if (!totalsEl) return;
    if (!results.length) {
      totalsEl.textContent = '--';
      return;
    }
    const counts = results.reduce<Record<string, number>>((acc, row) => {
      acc[row.status] = (acc[row.status] || 0) + 1;
      return acc;
    }, {});
    totalsEl.textContent = `PASS ${counts.PASS || 0} | WARN ${counts.WARN || 0} | FAIL ${counts.FAIL || 0}`;
  }

  function setLastGood(value: string): void {
    if (lastGoodEl) lastGoodEl.textContent = value || '--';
  }

  function loadLastGood(): void {
    if (!lastGoodEl) return;
    const raw = localStorage.getItem(lastGoodKey);
    if (!raw) {
      setLastGood('--');
      return;
    }
    try {
      const data = JSON.parse(raw) as JsonRecord;
      setLastGood(String(data.timestamp || '--'));
    } catch {
      setLastGood('--');
    }
  }

  async function checkEndpoint(name: string, url: string): Promise<EndpointResult> {
    if (!url) {
      addResult(name, 'FAIL', 'Missing endpoint URL');
      return { ok: false, error: 'Missing endpoint URL', status: 0, raw: '', elapsedMs: 0 };
    }
    const res = await App.fetchPayload(url);
    const data = res.ok ? toRecord(res.data) : {};
    const meta = res.ok ? toRecord(res.meta) : {};
    const caps = data.capabilities ?? meta.capabilities ?? null;
    const supportsLeases = data.supports_leases;
    const capPayload = supportsLeases !== undefined && supportsLeases !== null && (!caps || typeof caps === 'object')
      ? { ...(toRecord(caps)), supports_leases: supportsLeases }
      : caps;
    const diagPayload: { status?: number; elapsedMs?: number; capabilities?: unknown } = {
      status: res.status,
      elapsedMs: res.elapsedMs,
    };
    if (capPayload !== null && capPayload !== undefined) {
      diagPayload.capabilities = capPayload;
    }
    diagnostics.update(diagPayload);
    if (res.status !== undefined) {
      lastHttpStatus = res.status;
    }
    return res;
  }

  function summarizeQueue(payload: unknown): string {
    const body = toRecord(payload);
    const rows = body.rows ?? body.items;
    const hasMore = body.has_more === true || body.has_more === 1;
    const nextCursor = body.next_cursor ?? body.nextCursor;
    const counts = body.counts_by_status ?? body.countsByStatus;
    return `rows=${Array.isArray(rows) ? rows.length : 0}, has_more=${hasMore}, next_cursor=${nextCursor ? 'yes' : 'no'}, counts=${counts ? 'yes' : 'no'}`;
  }

  function summarizeHealth(payload: unknown): string {
    const body = toRecord(payload);
    return `eligible=${body.eligible_key_count ?? '--'}, cooling=${body.cooling_key_count ?? '--'}, supports_leases=${body.supports_leases ?? '--'}`;
  }

  function summarizeStatus(payload: unknown): string {
    const body = toRecord(payload);
    const keyCount = Array.isArray(body.keys) ? body.keys.length : 0;
    return `keys=${keyCount}, supports_leases=${body.supports_leases ?? '--'}`;
  }

  function summarizeVtOps(payload: unknown): string {
    const body = toRecord(payload);
    const surfaces = toRecord(body.vt_surface_summary);
    const posture = toRecord(body.key_posture);
    const confidence = toRecord(body.confidence_schema);
    return `vt_surfaces=${surfaces.available_count ?? '--'}/${surfaces.known_count ?? '--'}, eligible_keys=${posture.eligible_keys ?? '--'}, confidence_schema=${confidence.available ? 'yes' : 'no'}`;
  }

  function buildDetails(res: EndpointResult | null): string | null {
    if (!res) return null;
    const detailPayload = {
      ok: res.ok === true,
      status: res.status,
      data: res.ok ? res.data : res.data ?? null,
      meta: res.ok ? res.meta : undefined,
    };
    try {
      return JSON.stringify(detailPayload, null, 2);
    } catch {
      return res.raw || null;
    }
  }

  function summarizePipelineActivity(payload: unknown): string {
    const body = toRecord(payload);
    const summary = toRecord(body.run_summary);
    const runs = Array.isArray(body.recent_runs) ? body.recent_runs.length : 0;
    const latest = summary.latest_run_id ?? '--';
    const runs24h = summary.runs_24h ?? '--';
    return `latest run #${latest} · ${runs24h} runs/24h · ${runs} recent row(s)`;
  }

  function summarizePipelineStatus(payload: unknown): string {
    const body = toRecord(payload);
    const lanes = toRecord(body.queue_lanes);
    const rec = toRecord(body.recommendation);
    const pipe = toRecord(body.pipeline);
    const queuePending = pipe.queue_pending ?? '--';
    const source = body.source ?? 'unknown';
    const laneBits: string[] = [];
    if (Number(lanes.lamda_pending ?? 0) > 0) laneBits.push(`lamda=${lanes.lamda_pending}`);
    if (Number(lanes.reservoir_pending ?? 0) > 0) laneBits.push(`reservoir=${lanes.reservoir_pending}`);
    if (Number(lanes.lamda_vt_ready ?? 0) > 0) laneBits.push(`vt_ready=${lanes.lamda_vt_ready}`);
    const cmd = rec.command ? `, cmd=${rec.command}` : '';
    return `source=${source}, queue=${queuePending}, lanes=[${laneBits.join(', ') || 'none'}]${cmd}`;
  }

  function summarizePipeline(payload: unknown): string {
    const body = toRecord(payload);
    const pipe = toRecord(body.pipeline);
    const lanes = toRecord(pipe.queue_lanes);
    const rec = toRecord(pipe.recommendation);
    const metrics = toRecord(body.metrics);
    const queuePending = toRecord(pipe.pipeline).queue_pending ?? '--';
    const eligible = metrics.eligible_now ?? '--';
    const laneBits: string[] = [];
    if (Number(lanes.lamda_pending ?? 0) > 0) laneBits.push(`lamda=${lanes.lamda_pending}`);
    if (Number(lanes.reservoir_pending ?? 0) > 0) laneBits.push(`reservoir=${lanes.reservoir_pending}`);
    if (Number(lanes.lamda_vt_ready ?? 0) > 0) laneBits.push(`vt_ready=${lanes.lamda_vt_ready}`);
    const cmd = rec.command ? `, cmd=${rec.command}` : '';
    const action = rec.action ? `, action=${rec.action}` : '';
    return `queue=${queuePending}, eligible=${eligible}, lanes=[${laneBits.join(', ') || 'none'}]${action}${cmd}`;
  }

  function summarizeVtSurfaceDrift(payload: unknown): { status: CheckStatus; detail: string } {
    const body = toRecord(payload);
    const summary = toRecord(body.vt_surface_summary);
    if (!Object.keys(summary).length) return { status: 'FAIL', detail: 'Missing VT surface summary' };
    const missing = Number(summary.missing_count || 0);
    if (missing > 0) {
      const names = Array.isArray(summary.missing_names) ? summary.missing_names.map(String) : [];
      return {
        status: 'WARN',
        detail: `Missing ${missing} VT surfaces: ${names.slice(0, 4).join(', ')}${names.length > 4 ? ' ...' : ''}`,
      };
    }
    return {
      status: 'PASS',
      detail: `All ${summary.available_count ?? '--'} VT evidence surfaces are available`,
    };
  }

  function summarizeConfidenceSchema(payload: unknown): { status: CheckStatus; detail: string } {
    const body = toRecord(payload);
    const schema = toRecord(body.confidence_schema);
    if (!Object.keys(schema).length) return { status: 'FAIL', detail: 'Missing confidence schema summary' };
    if (schema.available) {
      return { status: 'PASS', detail: 'VT confidence schema is available' };
    }
    const missing = Array.isArray(schema.missing) ? schema.missing : [];
    return {
      status: 'WARN',
      detail: `VT confidence schema incomplete (${schema.missing_count ?? missing.length} missing column checks)`,
    };
  }

  function formatFlags(): string {
    return `FEATURE_PHASE2B_READONLY=${flagPhase2b ? '1' : '0'}, FEATURE_PHASE3_OPS=${flagPhase3 ? '1' : '0'}`;
  }

  async function runChecks(): Promise<void> {
    results.length = 0;
    lastPayloads = {};
    lastHttpStatus = null;
    if (errorEl) errorEl.textContent = '';
    setLastRun();

    try {
      const vtOps = await checkEndpoint('VT ops summary reachable', vtOpsSummaryUrl);
      lastPayloads.vtOps = vtOps.ok ? vtOps.data : null;
      lastPayloads.vtOpsMeta = vtOps.ok ? vtOps.meta : null;
      addResult(
        'VT ops summary reachable',
        vtOps.ok ? 'PASS' : 'FAIL',
        vtOps.ok ? summarizeVtOps(vtOps.data) : (vtOps.error || 'Request failed'),
        vtOps.elapsedMs,
        buildDetails(vtOps),
      );
      if (vtOps.ok) {
        const surfaceCheck = summarizeVtSurfaceDrift(vtOps.data);
        addResult('VT evidence surfaces aligned', surfaceCheck.status, surfaceCheck.detail);
        const confidenceCheck = summarizeConfidenceSchema(vtOps.data);
        addResult('VT confidence schema live', confidenceCheck.status, confidenceCheck.detail);
      }

      const vtHealth = await checkEndpoint('VT health reachable', vtHealthUrl);
      lastPayloads.vtHealth = vtHealth.ok ? vtHealth.data : null;
      lastPayloads.vtHealthMeta = vtHealth.ok ? vtHealth.meta : null;
      addResult(
        'VT health reachable',
        vtHealth.ok ? 'PASS' : 'FAIL',
        vtHealth.ok ? summarizeHealth(vtHealth.data) : (vtHealth.error || 'Request failed'),
        vtHealth.elapsedMs,
        buildDetails(vtHealth),
      );

      const vtStatus = await checkEndpoint('VT status reachable', vtStatusUrl);
      lastPayloads.vtStatus = vtStatus.ok ? vtStatus.data : null;
      lastPayloads.vtStatusMeta = vtStatus.ok ? vtStatus.meta : null;
      addResult(
        'VT status reachable',
        vtStatus.ok ? 'PASS' : 'FAIL',
        vtStatus.ok ? summarizeStatus(vtStatus.data) : (vtStatus.error || 'Request failed'),
        vtStatus.elapsedMs,
        buildDetails(vtStatus),
      );

      const queue = await checkEndpoint('Permission queue reachable', `${queueUrl}?limit=5`);
      lastPayloads.queue = queue.ok ? queue.data : null;
      const queueData = queue.ok ? toRecord(queue.data) : {};
      const queuePass = queue.ok && Array.isArray(queueData.rows ?? queueData.items);
      addResult(
        'Permission queue reachable',
        queuePass ? 'PASS' : 'FAIL',
        queue.ok ? summarizeQueue(queue.data) : (queue.error || 'Request failed'),
        queue.elapsedMs,
        buildDetails(queue),
      );

      const pipeline = await checkEndpoint('Pipeline health snapshot', pipelineHealthUrl);
      lastPayloads.pipeline = pipeline.ok ? pipeline.data : null;
      lastPayloads.pipelineMeta = pipeline.ok ? pipeline.meta : null;
      addResult(
        'Pipeline health snapshot',
        pipeline.ok ? 'PASS' : 'FAIL',
        pipeline.ok ? summarizePipeline(pipeline.data) : (pipeline.error || 'Request failed'),
        pipeline.elapsedMs,
        buildDetails(pipeline),
      );

      if (pipelineStatusUrl) {
        const pipelineStatus = await checkEndpoint('Pipeline status API', pipelineStatusUrl);
        lastPayloads.pipelineStatus = pipelineStatus.ok ? pipelineStatus.data : null;
        lastPayloads.pipelineStatusMeta = pipelineStatus.ok ? pipelineStatus.meta : null;
        const statusData = pipelineStatus.ok ? toRecord(pipelineStatus.data) : {};
        const statusPass = pipelineStatus.ok
          && Boolean(statusData.queue_lanes)
          && Boolean(statusData.recommendation);
        addResult(
          'Pipeline status API',
          statusPass ? 'PASS' : 'FAIL',
          pipelineStatus.ok ? summarizePipelineStatus(pipelineStatus.data) : (pipelineStatus.error || 'Request failed'),
          pipelineStatus.elapsedMs,
          buildDetails(pipelineStatus),
        );
      }

      if (pipelineActivityUrl) {
        const pipelineActivity = await checkEndpoint('Pipeline activity API', pipelineActivityUrl);
        lastPayloads.pipelineActivity = pipelineActivity.ok ? pipelineActivity.data : null;
        lastPayloads.pipelineActivityMeta = pipelineActivity.ok ? pipelineActivity.meta : null;
        const activityData = pipelineActivity.ok ? toRecord(pipelineActivity.data) : {};
        const activityPass = pipelineActivity.ok
          && Boolean(activityData.pipeline)
          && Boolean(activityData.run_summary)
          && Array.isArray(activityData.recent_runs);
        addResult(
          'Pipeline activity API',
          activityPass ? 'PASS' : 'FAIL',
          pipelineActivity.ok ? summarizePipelineActivity(pipelineActivity.data) : (pipelineActivity.error || 'Request failed'),
          pipelineActivity.elapsedMs,
          buildDetails(pipelineActivity),
        );
      }

      addResult('Feature flag state', 'PASS', formatFlags());

      const contract =
        toRecord(lastPayloads.vtOpsMeta).contract_version
        || toRecord(lastPayloads.vtStatusMeta).contract_version
        || toRecord(lastPayloads.vtHealthMeta).contract_version
        || toRecord(lastPayloads.pipelineMeta).contract_version
        || null;
      if (contract) {
        addResult('Contract version', 'PASS', String(contract));
      }
    } catch (error) {
      if (errorEl) {
        errorEl.innerHTML = `<pre>Diagnostics check error:\n${esc(error instanceof Error ? error.message : String(error))}</pre>`;
      }
    }

    renderResults();
    setTotals();
    storeLastGoodIfPassed();
  }

  function buildReport(): string {
    const now = formatUtcFixed(new Date().toISOString(), true);
    const ua = navigator.userAgent || 'unknown';
    const platform = navigator.platform || 'unknown';
    const baseUrl = apiBase && apiBase.trim() !== '' ? apiBase : 'same-origin';
    const apiStatus = lastHttpStatus ? `HTTP ${lastHttpStatus}` : '--';

    const lines = [
      '# Admin Diagnostics Report',
      '',
      `- Timestamp (UTC): ${now}`,
      `- Page URL: ${window.location.href}`,
      `- App version: ${appVersion || 'unknown'}`,
      `- App commit: ${appSha || 'unknown'}`,
      `- API base URL: ${baseUrl}`,
      `- API status: ${apiStatus}`,
      `- Backend used: API`,
      `- Browser/OS: ${ua} (${platform})`,
      `- Feature flags: ${formatFlags()}`,
      '',
      '## Checks',
    ];

    results.forEach((row) => {
      const latency = row.latencyMs !== undefined && row.latencyMs !== null ? `, latency=${row.latencyMs}ms` : '';
      lines.push(`- ${row.label}: ${row.status} (${row.detail || '--'}${latency})`);
    });

    return lines.join('\n');
  }

  function copyReport(): void {
    App.copyText(buildReport());
  }

  function storeLastGoodIfPassed(): void {
    const hasFail = results.some((row) => row.status === 'FAIL');
    if (hasFail) return;
    const payload = {
      timestamp: formatUtcFixed(new Date().toISOString(), true),
      report: buildReport(),
      results,
    };
    try {
      localStorage.setItem(lastGoodKey, JSON.stringify(payload));
      setLastGood(payload.timestamp);
    } catch {
      // ignore storage failures
    }
  }

  function copyLastGood(): void {
    const raw = localStorage.getItem(lastGoodKey);
    if (!raw) return;
    try {
      const data = JSON.parse(raw) as JsonRecord;
      if (data.report) App.copyText(String(data.report));
    } catch {
      // no-op
    }
  }

  function saveReport(): void {
    const report = buildReport();
    const ts = new Date().toISOString().replace(/[:]/g, '').replace(/\..+$/, 'Z');
    const filename = `admin_diagnostics_${ts}.md`;
    const blob = new Blob([report], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  runBtn?.addEventListener('click', () => { void runChecks(); });
  copyBtn?.addEventListener('click', copyReport);
  copyGoodBtn?.addEventListener('click', copyLastGood);
  saveBtn?.addEventListener('click', saveReport);

  loadLastGood();
  void runChecks();
}
