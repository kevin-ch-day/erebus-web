(() => {
  const root = document.getElementById('admin-smoke-page');
  if (!root || !window.App) return;

  const vtHealthUrl = root.dataset.vtHealth || '';
  const vtStatusUrl = root.dataset.vtStatus || '';
  const vtOpsSummaryUrl = root.dataset.vtOpsSummary || '';
  const queueUrl = root.dataset.permissionQueue || '';
  const pipelineHealthUrl = root.dataset.pipelineHealth || '';
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
  const readonly = App.readonly || {};
  const formatUtcFixed = readonly.formatUtcFixed || ((value, includeSeconds = false) =>
    App.formatUtc(value, { timeZone: 'UTC', includeSeconds }));
  const fetchPayload = readonly.fetchPayload || App.fetchPayload;

  const diagnostics = App.diagnostics
    ? App.diagnostics.create({
        baseUrlEl: apiBaseEl,
        lastFetchEl: apiLastFetchEl,
        statusEl: apiLastStatusEl,
        latencyEl: apiLatencyEl,
        capabilitiesEl: apiCapabilitiesEl,
        apiBase,
        formatUtc: formatUtcFixed,
      })
    : null;

  const results = [];
  let lastPayloads = {};
  const lastGoodKey = 'admin_diagnostics_last_good';
  let lastHttpStatus = null;

  function addResult(label, status, detail, latencyMs, rawDetails) {
    results.push({ label, status, detail, latencyMs, rawDetails });
  }

  function renderResults() {
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

  function setLastRun() {
    if (lastRunEl) lastRunEl.textContent = formatUtcFixed(new Date().toISOString(), true);
  }

  function setTotals() {
    if (!totalsEl) return;
    if (!results.length) {
      totalsEl.textContent = '--';
      return;
    }
    const counts = results.reduce((acc, row) => {
      acc[row.status] = (acc[row.status] || 0) + 1;
      return acc;
    }, {});
    const pass = counts.PASS || 0;
    const warn = counts.WARN || 0;
    const fail = counts.FAIL || 0;
    totalsEl.textContent = `PASS ${pass} | WARN ${warn} | FAIL ${fail}`;
  }

  function setLastGood(value) {
    if (!lastGoodEl) return;
    lastGoodEl.textContent = value || '--';
  }

  function loadLastGood() {
    if (!lastGoodEl) return;
    const raw = localStorage.getItem(lastGoodKey);
    if (!raw) {
      setLastGood('--');
      return;
    }
    try {
      const data = JSON.parse(raw);
      setLastGood(data.timestamp || '--');
    } catch (_) {
      setLastGood('--');
    }
  }

  async function checkEndpoint(name, url) {
    if (!url) {
      addResult(name, 'FAIL', 'Missing endpoint URL');
      return { ok: false };
    }
    const res = await fetchPayload(url);
    const caps = (res.data && res.data.capabilities) || (res.meta && res.meta.capabilities) || null;
    const supportsLeases = res.data && res.data.supports_leases !== undefined
      ? res.data.supports_leases
      : null;
    const capPayload = supportsLeases !== null && (!caps || typeof caps === 'object')
      ? { ...(caps || {}), supports_leases: supportsLeases }
      : caps;
    const diagPayload = {
      status: res.status,
      elapsedMs: res.elapsedMs,
    };
    if (capPayload !== null && capPayload !== undefined) {
      diagPayload.capabilities = capPayload;
    }
    diagnostics && diagnostics.update(diagPayload);
    if (res.status !== undefined) {
      lastHttpStatus = res.status;
    }
    return res;
  }

  function summarizeQueue(payload) {
    if (!payload) return 'Missing payload';
    const rows = payload.rows || payload.items;
    const hasMore = payload.has_more === true || payload.has_more === 1;
    const nextCursor = payload.next_cursor || payload.nextCursor;
    const counts = payload.counts_by_status || payload.countsByStatus;
    return `rows=${Array.isArray(rows) ? rows.length : 0}, has_more=${hasMore}, next_cursor=${nextCursor ? 'yes' : 'no'}, counts=${counts ? 'yes' : 'no'}`;
  }

  function summarizeHealth(payload) {
    if (!payload) return 'Missing payload';
    return `eligible=${payload.eligible_key_count ?? '--'}, cooling=${payload.cooling_key_count ?? '--'}, supports_leases=${payload.supports_leases ?? '--'}`;
  }

  function summarizeStatus(payload) {
    if (!payload) return 'Missing payload';
    const keyCount = Array.isArray(payload.keys) ? payload.keys.length : 0;
    const supports = payload.supports_leases ?? '--';
    return `keys=${keyCount}, supports_leases=${supports}`;
  }

  function summarizeVtOps(payload) {
    if (!payload) return 'Missing payload';
    const surfaces = payload.vt_surface_summary || {};
    const posture = payload.key_posture || {};
    return `vt_surfaces=${surfaces.available_count ?? '--'}/${surfaces.known_count ?? '--'}, eligible_keys=${posture.eligible_keys ?? '--'}, confidence_schema=${payload.confidence_schema && payload.confidence_schema.available ? 'yes' : 'no'}`;
  }

  function buildDetails(res) {
    if (!res) return null;
    const detailPayload = {
      ok: res.ok === true,
      status: res.status,
      data: res.data || null,
      meta: res.meta || null,
    };
    try {
      return JSON.stringify(detailPayload, null, 2);
    } catch (_) {
      return res.raw || null;
    }
  }

  function summarizePipeline(payload) {
    if (!payload) return 'Missing payload';
    const eligible = payload.metrics && payload.metrics.eligible_now !== undefined ? payload.metrics.eligible_now : '--';
    const errors = payload.metrics && payload.metrics.error_count !== undefined ? payload.metrics.error_count : '--';
    const primary = payload.catalogs && payload.catalogs.primary ? payload.catalogs.primary : '--';
    return `primary=${primary}, eligible_now=${eligible}, errors=${errors}`;
  }

  function summarizeVtSurfaceDrift(payload) {
    if (!payload || !payload.vt_surface_summary) return { status: 'FAIL', detail: 'Missing VT surface summary' };
    const summary = payload.vt_surface_summary;
    const missing = Number(summary.missing_count || 0);
    if (missing > 0) {
      const names = Array.isArray(summary.missing_names) ? summary.missing_names : [];
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

  function summarizeConfidenceSchema(payload) {
    if (!payload || !payload.confidence_schema) return { status: 'FAIL', detail: 'Missing confidence schema summary' };
    const schema = payload.confidence_schema;
    if (schema.available) {
      return { status: 'PASS', detail: 'VT confidence schema is available' };
    }
    const missing = Array.isArray(schema.missing) ? schema.missing : [];
    return {
      status: 'WARN',
      detail: `VT confidence schema incomplete (${schema.missing_count ?? missing.length} missing column checks)`,
    };
  }

  function formatFlags() {
    return `FEATURE_PHASE2B_READONLY=${flagPhase2b ? '1' : '0'}, FEATURE_PHASE3_OPS=${flagPhase3 ? '1' : '0'}`;
  }

  async function runChecks() {
    results.length = 0;
    lastPayloads = {};
    lastHttpStatus = null;
    if (errorEl) errorEl.textContent = '';
    setLastRun();

    try {
      const vtOps = await checkEndpoint('VT ops summary reachable', vtOpsSummaryUrl);
      lastPayloads.vtOps = vtOps.data || null;
      lastPayloads.vtOpsMeta = vtOps.meta || null;
      addResult(
        'VT ops summary reachable',
        vtOps.ok ? 'PASS' : 'FAIL',
        vtOps.ok ? summarizeVtOps(vtOps.data) : (vtOps.error || 'Request failed'),
        vtOps.elapsedMs,
        buildDetails(vtOps)
      );
      if (vtOps.ok) {
        const surfaceCheck = summarizeVtSurfaceDrift(vtOps.data);
        addResult('VT evidence surfaces aligned', surfaceCheck.status, surfaceCheck.detail, null, null);
        const confidenceCheck = summarizeConfidenceSchema(vtOps.data);
        addResult('VT confidence schema live', confidenceCheck.status, confidenceCheck.detail, null, null);
      }

      const vtHealth = await checkEndpoint('VT health reachable', vtHealthUrl);
      lastPayloads.vtHealth = vtHealth.data || null;
      lastPayloads.vtHealthMeta = vtHealth.meta || null;
      addResult(
        'VT health reachable',
        vtHealth.ok ? 'PASS' : 'FAIL',
        vtHealth.ok ? summarizeHealth(vtHealth.data) : (vtHealth.error || 'Request failed'),
        vtHealth.elapsedMs,
        buildDetails(vtHealth)
      );

      const vtStatus = await checkEndpoint('VT status reachable', vtStatusUrl);
      lastPayloads.vtStatus = vtStatus.data || null;
      lastPayloads.vtStatusMeta = vtStatus.meta || null;
      addResult(
        'VT status reachable',
        vtStatus.ok ? 'PASS' : 'FAIL',
        vtStatus.ok ? summarizeStatus(vtStatus.data) : (vtStatus.error || 'Request failed'),
        vtStatus.elapsedMs,
        buildDetails(vtStatus)
      );

      const queue = await checkEndpoint('Permission queue reachable', queueUrl + '?limit=5');
      lastPayloads.queue = queue.data || null;
      const queuePass = queue.ok && Array.isArray(queue.data && (queue.data.rows || queue.data.items));
      addResult(
        'Permission queue reachable',
        queuePass ? 'PASS' : 'FAIL',
        queue.ok ? summarizeQueue(queue.data) : (queue.error || 'Request failed'),
        queue.elapsedMs,
        buildDetails(queue)
      );

      const pipeline = await checkEndpoint('Pipeline health snapshot', pipelineHealthUrl);
      lastPayloads.pipeline = pipeline.data || null;
      lastPayloads.pipelineMeta = pipeline.meta || null;
      addResult(
        'Pipeline health snapshot',
        pipeline.ok ? 'PASS' : 'FAIL',
        pipeline.ok ? summarizePipeline(pipeline.data) : (pipeline.error || 'Request failed'),
        pipeline.elapsedMs,
        buildDetails(pipeline)
      );

      addResult('Feature flag state', 'PASS', formatFlags(), null, null);

      const contract =
        (lastPayloads.vtOpsMeta && lastPayloads.vtOpsMeta.contract_version) ||
        (lastPayloads.vtStatusMeta && lastPayloads.vtStatusMeta.contract_version) ||
        (lastPayloads.vtHealthMeta && lastPayloads.vtHealthMeta.contract_version) ||
        (lastPayloads.pipelineMeta && lastPayloads.pipelineMeta.contract_version) ||
        null;
      if (contract) {
        addResult('Contract version', 'PASS', String(contract), null, null);
      }
    } catch (e) {
      if (errorEl) {
        errorEl.innerHTML = '<pre>Diagnostics check error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
      }
    }

    renderResults();
    setTotals();
    storeLastGoodIfPassed();
  }

  function buildReport() {
    const now = formatUtcFixed(new Date().toISOString(), true);
    const ua = navigator.userAgent || 'unknown';
    const platform = navigator.platform || 'unknown';
    const baseUrl = apiBase && apiBase.trim() !== '' ? apiBase : 'same-origin';
    const apiStatus = lastHttpStatus ? `HTTP ${lastHttpStatus}` : '--';
    const backendUsed = 'API';
    const pageUrl = window.location.href;

    const lines = [
      '# Admin Diagnostics Report',
      '',
      `- Timestamp (UTC): ${now}`,
      `- Page URL: ${pageUrl}`,
      `- App version: ${appVersion || 'unknown'}`,
      `- App commit: ${appSha || 'unknown'}`,
      `- API base URL: ${baseUrl}`,
      `- API status: ${apiStatus}`,
      `- Backend used: ${backendUsed}`,
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

  function copyReport() {
    const report = buildReport();
    if (App.copyText) {
      App.copyText(report);
    }
  }

  function storeLastGoodIfPassed() {
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
    } catch (_) {
      // ignore storage failures
    }
  }

  function copyLastGood() {
    const raw = localStorage.getItem(lastGoodKey);
    if (!raw || !App.copyText) return;
    try {
      const data = JSON.parse(raw);
      if (data && data.report) {
        App.copyText(data.report);
      }
    } catch (_) {
      // no-op
    }
  }

  function saveReport() {
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

  if (runBtn) runBtn.addEventListener('click', runChecks);
  if (copyBtn) copyBtn.addEventListener('click', copyReport);
  if (copyGoodBtn) copyGoodBtn.addEventListener('click', copyLastGood);
  if (saveBtn) saveBtn.addEventListener('click', saveReport);

  loadLastGood();
  runChecks();
})();
