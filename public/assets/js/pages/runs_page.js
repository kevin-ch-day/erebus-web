(() => {
  const root = document.getElementById('runs-page-root');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  if (!endpoint) return;

  const searchEl = document.getElementById('runs-search');
  const pageSizeEl = document.getElementById('runs-page-size');
  const pageEl = document.getElementById('runs-page');

  const bodyEl = document.getElementById('runs-body');
  const metaEl = document.getElementById('runs-meta');
  const pagesEl = document.getElementById('runs-pages');
  const prevBtn = document.getElementById('runs-prev');
  const nextBtn = document.getElementById('runs-next');
  const errorEl = document.getElementById('runs-error');
  const platformPrimaryEl = document.getElementById('runs-platform-primary');
  const platformPiEl = document.getElementById('runs-platform-pi');
  const platformHeadsEl = document.getElementById('runs-platform-heads');
  const platformTaxonomyEl = document.getElementById('runs-platform-taxonomy');
  const platformSummaryEl = document.getElementById('runs-platform-summary');
  const platformListEl = document.getElementById('runs-platform-list');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;

  let pendingTimer = null;
  let lastPayload = null;

  function buildQuery(pageOverride = null) {
    const params = new URLSearchParams();
    const q = searchEl.value.trim();
    const pageSize = pageSizeEl.value;
    const page = pageOverride ?? pageEl.value;

    if (q) params.set('q', q);
    if (pageSize) params.set('page_size', pageSize);
    params.set('page', page || '1');

    return params;
  }

  function updateUrl(params) {
    const url = new URL(window.location.href);
    url.search = params.toString();
    window.history.replaceState({}, '', url.toString());
  }

  function renderRows(rows) {
    bodyEl.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      bodyEl.innerHTML = '<tr><td colspan="11" class="muted">No runs found.</td></tr>';
      return;
    }

    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const keyLabel = row.key_id ? row.key_id : '--';
      tr.innerHTML = `
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
      `;
      bodyEl.appendChild(tr);
    });
  }

  function renderMeta(payload) {
    const total = payload.total_count ?? 0;
    const page = payload.page ?? 1;
    const pageSize = payload.page_size ?? 0;
    const pages = payload.total_pages ?? 1;
    metaEl.textContent = `Total: ${total} | Page size: ${pageSize}`;
    pagesEl.textContent = String(pages);
    pageEl.value = page;
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= pages;
  }

  function renderPlatformContext(context) {
    if (!platformPrimaryEl || !platformPiEl || !platformHeadsEl || !platformTaxonomyEl || !platformSummaryEl || !platformListEl) {
      return;
    }

    platformPrimaryEl.textContent = fmt(context && context.primary_catalog);
    platformPiEl.textContent = fmt(context && context.permission_intel_catalog);
    const primaryHead = context && context.primary_schema_head ? String(context.primary_schema_head) : '--';
    const piHead = context && context.permission_intel_schema_head ? String(context.permission_intel_schema_head) : '--';
    platformHeadsEl.textContent = `${primaryHead} / ${piHead}`;
    const taxVersion = context && context.latest_perm_taxonomy_version ? String(context.latest_perm_taxonomy_version) : '--';
    const taxTime = context && context.latest_perm_taxonomy_finished_at_utc
      ? formatUtc(context.latest_perm_taxonomy_finished_at_utc)
      : '--';
    platformTaxonomyEl.textContent = `${taxVersion} @ ${taxTime}`;

    platformListEl.innerHTML = '';
    const notices = [];
    if (context && context.mixed_visible_run_db_names) {
      notices.push(`Visible rows span multiple db_name values: ${(context.visible_run_db_names || []).join(', ')}`);
    }
    if (context && context.mixed_visible_run_schema_versions) {
      notices.push(`Visible rows span multiple schema_version values: ${(context.visible_run_schema_versions || []).join(', ')}`);
    }
    if (context && context.mixed_visible_run_perm_taxonomy_versions) {
      notices.push(`Visible rows span multiple perm_taxonomy_version values: ${(context.visible_run_perm_taxonomy_versions || []).join(', ')}`);
    }
    if (context && context.split_enabled && context.schema_heads_match === false) {
      notices.push(`Primary and PI schema heads differ: ${primaryHead} vs ${piHead}`);
    }

    if (notices.length === 0) {
      platformSummaryEl.textContent = 'Visible run rows align with one platform context.';
      return;
    }

    platformSummaryEl.textContent = 'Visible run rows span more than one platform state. Compare schema and taxonomy before drawing conclusions.';
    notices.forEach((notice) => {
      const li = document.createElement('li');
      li.textContent = notice;
      platformListEl.appendChild(li);
    });
  }

  async function loadRuns(pageOverride = null) {
    const params = buildQuery(pageOverride);
    updateUrl(params);
    const url = endpoint + '?' + params.toString();

    if (pendingTimer) {
      clearTimeout(pendingTimer);
      pendingTimer = null;
    }

    errorEl.textContent = '';
    bodyEl.innerHTML = '<tr><td colspan="11" class="muted">Loading runs...</td></tr>';

    try {
      const res = await App.fetchJson(url);
      if (!res.ok) {
        const raw = res.raw ? String(res.raw).slice(0, 2000) : '';
        const detail = raw ? `\n\n${esc(raw)}` : '';
        errorEl.innerHTML = '<pre>Run ledger API error.\n\nHTTP ' + res.status + '\nerror: ' +
          esc(res.error) + detail + '</pre>';
        return;
      }

      lastPayload = res.body;
      renderRows(res.body.rows || []);
      renderMeta(res.body);
      renderPlatformContext(res.body.platform_context || null);
    } catch (e) {
      errorEl.innerHTML = '<pre>Run ledger API error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
    }
  }

  function scheduleReload() {
    if (pendingTimer) clearTimeout(pendingTimer);
    pendingTimer = setTimeout(() => loadRuns(1), 200);
  }

  [searchEl, pageSizeEl].forEach((el) => {
    el.addEventListener('input', scheduleReload);
    el.addEventListener('change', scheduleReload);
  });

  pageEl.addEventListener('change', () => loadRuns(pageEl.value || 1));
  prevBtn.addEventListener('click', () => loadRuns(Math.max(1, Number(pageEl.value) - 1)));
  nextBtn.addEventListener('click', () => {
    const pages = lastPayload ? Number(lastPayload.total_pages || 1) : 1;
    const next = Math.min(pages, Number(pageEl.value) + 1);
    loadRuns(next);
  });

  loadRuns();
})();
