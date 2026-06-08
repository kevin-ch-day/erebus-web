(() => {
  const root = document.getElementById('perm-drift-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.intelEndpoint || '';
  const defaultLimit = root.dataset.namespaceLimit || '100';
  if (!endpoint) return;

  const limitEl = document.getElementById('perm-namespace-limit');
  const searchEl = document.getElementById('perm-namespace-search');
  const namespaceBody = document.getElementById('perm-namespace-body');
  const noteEl = document.getElementById('perm-drift-note');
  const errorEl = document.getElementById('perm-drift-error');
  const quickFilterRoot = document.getElementById('perm-drift-quick-filters');
  const quickFilterBtns = Array.from(document.querySelectorAll('.drift-quick-btn'));

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;
  const { formatCount, classifyNamespace } = PermissionIntel;
  const pageUrl = App.pageUrl;

  let namespaceRows = [];
  let driftMeta = {};
  let quickMode = 'review';

  function classificationHelp(label) {
    switch (label.toLowerCase()) {
      case 'core':
        return 'Core Android namespaces (android.*).';
      case 'expected':
        return 'Google/GMS namespaces (com.google.*).';
      case 'known ecosystem':
        return 'Known third-party or platform-adjacent ecosystem namespace.';
      case 'oem':
        return 'Known vendor namespaces (Samsung/Huawei/etc.).';
      default:
        return 'Everything else; review for new vendors or app-defined permissions.';
    }
  }

  function isNewNamespace(row, cutoffMs) {
    const ms = App.parseUtcToMs(row.first_seen_at_utc);
    return !!ms && ms >= cutoffMs;
  }

  function triageLink(namespaceValue) {
    return pageUrl('permissions_triage', { namespace: namespaceValue });
  }

  function updateUrl(limit) {
    const url = new URL(window.location.href);
    url.searchParams.set('namespace_limit', limit);
    window.history.replaceState({}, '', url.toString());
  }

  function updateQuickButtons() {
    quickFilterBtns.forEach((btn) => {
      const mode = btn.dataset.mode || 'all';
      if (mode === quickMode) {
        btn.classList.add('is-active');
      } else {
        btn.classList.remove('is-active');
      }
    });
  }

  function applyFilters(rows) {
    const term = searchEl ? searchEl.value.trim().toLowerCase() : '';
    return rows.filter((row) => {
      const ns = String(row.namespace ?? '').toLowerCase();
      const cls = String(row.namespace_class_label || classifyNamespace(row.namespace).label || '').toLowerCase();
      const matchesTerm = term ? ns.includes(term) : true;
      const matchesQuick = (
        quickMode === 'all'
          ? true
          : quickMode === 'review'
            ? (cls === 'oem' || cls === 'anomalous')
            : cls === quickMode
      );
      return matchesTerm && matchesQuick;
    });
  }

  function renderNamespaceTable() {
    const rows = applyFilters(namespaceRows);
    namespaceBody.innerHTML = '';

    if (!rows.length) {
      let emptyNote = 'No namespaces match current filters.';
      if (!namespaceRows.length) {
        const source = driftMeta.namespace_drift_source || '';
        const reason = driftMeta.namespace_drift_reason || '';
        if (source === 'obs_sample' && reason === 'vt_event_empty') {
          emptyNote = 'No VT enrichment events yet; fallback source is also empty.';
        } else if (source === 'unavailable') {
          emptyNote = 'Namespace drift unavailable; check schema guard from Health.';
        } else {
          emptyNote = 'No namespace drift data available yet.';
        }
      }
      namespaceBody.innerHTML = `<tr><td colspan="6" class="muted">${esc(emptyNote)}</td></tr>`;
      if (noteEl) noteEl.textContent = emptyNote;
      return;
    }

    if (noteEl) {
      const quickLabel = quickMode === 'review' ? 'review first' : quickMode;
      const sourceNote = driftMeta.namespace_drift_source === 'obs_sample' ? ' Source: obs_sample fallback.' : '';
      noteEl.textContent = `Showing ${formatCount(rows.length)} of ${formatCount(namespaceRows.length)} namespaces (${quickLabel}).${sourceNote}`;
    }

    const cutoffMs = Date.now() - (7 * 24 * 60 * 60 * 1000);
    rows.forEach((row) => {
      const classification = {
        label: String(row.namespace_class_label || classifyNamespace(row.namespace).label || '--'),
        className: String(row.namespace_class_name || classifyNamespace(row.namespace).className || 'err'),
      };
      const namespaceValue = fmt(row.namespace);
      const triageUrl = triageLink(namespaceValue);
      const newBadge = isNewNamespace(row, cutoffMs) ? ' <span class="badge warn">New</span>' : '';
      const classHelp = String(row.review_hint || classificationHelp(classification.label));
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="mono">${esc(namespaceValue)}${newBadge}</td>
        <td title="Historical seen count">${esc(formatCount(row.seen_count))}</td>
        <td>${esc(formatCount(row.permission_count))}</td>
        <td title="${esc(classHelp)}"><span class="status-dot ${esc(classification.className)}"></span>${esc(classification.label)}</td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : '--')}</td>
        <td><a class="table-link" href="${esc(triageUrl)}">Open Triage</a></td>
      `;
      namespaceBody.appendChild(tr);
    });
  }

  async function loadDrift() {
    const limit = limitEl ? (limitEl.value || defaultLimit) : defaultLimit;
    updateUrl(limit);
    errorEl.textContent = '';
    namespaceBody.innerHTML = '<tr><td colspan="6" class="muted">Loading namespace drift...</td></tr>';

    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('mode', 'drift');
      url.searchParams.set('namespace_limit', String(limit));
      const res = await App.fetchJson(url.toString());
      if (!res.ok) {
        errorEl.innerHTML = `<pre>Permission drift error.\n\nHTTP ${res.status}\nerror: ${esc(res.error)}</pre>`;
        return;
      }
      const data = res.body.data || {};
      driftMeta = res.body.meta || {};
      namespaceRows = Array.isArray(data.namespace_drift) ? data.namespace_drift : [];
      renderNamespaceTable();
    } catch (e) {
      errorEl.innerHTML = `<pre>Permission drift error:\n${esc(e && e.message ? e.message : String(e))}</pre>`;
    }
  }

  if (limitEl) limitEl.addEventListener('change', loadDrift);
  if (searchEl) searchEl.addEventListener('input', renderNamespaceTable);

  if (quickFilterRoot) {
    quickFilterRoot.addEventListener('click', (event) => {
      const btn = event.target.closest('.drift-quick-btn');
      if (!btn) return;
      quickMode = btn.dataset.mode || 'all';
      updateQuickButtons();
      renderNamespaceTable();
    });
  }

  updateQuickButtons();
  loadDrift();
})();
