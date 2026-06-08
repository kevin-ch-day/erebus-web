(() => {
  const root = document.getElementById('vt-snapshot-inventory-root');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  if (!endpoint) return;

  const maxRowsEl = document.getElementById('vt-inv-max-rows');
  const recentHoursEl = document.getElementById('vt-inv-recent-hours');
  const refreshBtn = document.getElementById('vt-inv-refresh');

  const summaryEl = document.getElementById('vt-inv-summary');
  const statusMixEl = document.getElementById('vt-inv-status-mix');
  const sourceMixEl = document.getElementById('vt-inv-source-mix');
  const attrsBody = document.getElementById('vt-inv-attrs-body');
  const recentCaptionEl = document.getElementById('vt-inv-recent-caption');
  const recentListEl = document.getElementById('vt-inv-recent-new');
  const errorEl = document.getElementById('vt-inv-error');

  const esc = App.escapeHtml;

  function listRows(node, rows) {
    node.innerHTML = '';
    if (!rows || rows.length === 0) {
      node.innerHTML = '<li class="muted">--</li>';
      return;
    }
    rows.forEach(([k, v]) => {
      const li = document.createElement('li');
      li.innerHTML = `${esc(String(k))}: <strong>${esc(String(v))}</strong>`;
      node.appendChild(li);
    });
  }

  function renderAttrs(rows) {
    attrsBody.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      attrsBody.innerHTML = '<tr><td colspan="2" class="muted">No attributes found.</td></tr>';
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(row.attribute)}</td><td>${esc(String(row.count))}</td>`;
      attrsBody.appendChild(tr);
    });
  }

  function renderRecent(hours, attrs) {
    recentCaptionEl.textContent = `Window: last ${hours}h`;
    recentListEl.innerHTML = '';
    if (!Array.isArray(attrs) || attrs.length === 0) {
      recentListEl.innerHTML = '<li class="muted">(none)</li>';
      return;
    }
    attrs.slice(0, 60).forEach((name) => {
      const li = document.createElement('li');
      li.textContent = name;
      recentListEl.appendChild(li);
    });
    if (attrs.length > 60) {
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = `... +${attrs.length - 60} more`;
      recentListEl.appendChild(li);
    }
  }

  async function load() {
    const maxRows = Number(maxRowsEl.value || 5000);
    const recentHours = Number(recentHoursEl.value || 24);
    const params = new URLSearchParams({
      max_rows: String(Math.min(Math.max(maxRows, 1), 20000)),
      recent_hours: String(Math.min(Math.max(recentHours, 1), 720)),
    });
    const url = `${endpoint}?${params.toString()}`;

    errorEl.textContent = '';
    summaryEl.textContent = 'Loading...';
    attrsBody.innerHTML = '<tr><td colspan="2" class="muted">Loading...</td></tr>';

    try {
      const res = await App.fetchJson(url);
      if (!res.ok) {
        errorEl.textContent = `Inventory API error: ${res.error || 'request failed'}`;
        summaryEl.textContent = 'Unavailable';
        return;
      }
      const payload = res.body || {};
      const data = payload.data || payload || {};
      summaryEl.textContent =
        `snapshots=${data.snapshot_total ?? 0} | with_attributes=${data.with_attributes ?? 0} | parse_errors=${data.parse_errors ?? 0} | generated_at_utc=${data.generated_at_utc ?? '--'}`;

      const statusMix = Object.entries(data.status_mix || {});
      const sourceMix = Object.entries(data.source_mix || {});
      listRows(statusMixEl, statusMix);
      listRows(sourceMixEl, sourceMix);
      renderAttrs(data.top_attributes || []);
      renderRecent(data.recent_hours ?? recentHours, data.recent_new_attributes || []);
    } catch (err) {
      errorEl.textContent = `Inventory API error: ${err && err.message ? err.message : String(err)}`;
      summaryEl.textContent = 'Unavailable';
    }
  }

  refreshBtn?.addEventListener('click', load);
  maxRowsEl?.addEventListener('change', load);
  recentHoursEl?.addEventListener('change', load);
  load();
})();
