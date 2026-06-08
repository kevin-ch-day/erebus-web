(() => {
  const root = document.getElementById('perm-oem-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.permissionsEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const pageSize = Number(root.dataset.pageSize || 200);
  if (!endpoint) return;

  const searchEl = document.getElementById('perm-oem-search');
  const statusEl = document.getElementById('perm-oem-status');
  const searchBtn = document.getElementById('perm-oem-search-btn');
  const bodyEl = document.getElementById('perm-oem-body');
  const metaEl = document.getElementById('perm-oem-meta');
  const errorEl = document.getElementById('perm-oem-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const fmtCount = PermissionIntel.formatCount;
  const fmtUtc = App.formatUtc;
  const pageUrl = App.pageUrl;

  let statusLabelMap = new Map();

  const triageLink = (namespaceValue) => pageUrl('permissions_triage', { namespace: namespaceValue });
  const evidenceLink = (permissionValue) => pageUrl('permissions_evidence', { permission: permissionValue });

  const page = PermissionIntel.createReadonlyCatalogPage({
    endpoint,
    lovEndpoint,
    pageSize,
    bodyEl,
    metaEl,
    errorEl,
    colSpan: 6,
    loadMessage: 'Loading OEM permissions...',
    emptyMessage: 'No OEM permissions found.',
    emptyMeta: 'Check OEM prefixes in config or android_permission_dict_unknown for data.',
    renderMetaText: (meta) => `Showing ${fmtCount(meta.total_count)} permissions.`,
    buildParams: () => ({
      q: searchEl && searchEl.value.trim() ? searchEl.value.trim() : '',
      status: statusEl && statusEl.value ? statusEl.value : '',
    }),
    renderRow: (row) => {
      const permissionValue = fmt(row.permission_string);
      const namespaceValue = fmt(row.namespace);
      const statusKey = String(row.triage_status || '').toLowerCase();
      const statusLabel = statusLabelMap.get(statusKey) || fmt(row.triage_status);
      return `
        <td class="mono">${esc(permissionValue)}</td>
        <td class="mono">${esc(namespaceValue)}</td>
        <td>${esc(statusLabel)}</td>
        <td title="Historical seen count">${esc(fmtCount(row.seen_count))}</td>
        <td>${esc(row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--')}</td>
        <td>
          <a class="table-link" href="${esc(triageLink(namespaceValue))}">Triage</a>
          <span class="muted"> | </span>
          <a class="table-link" href="${esc(evidenceLink(permissionValue))}">Evidence</a>
        </td>
      `;
    },
    loadLov: (lov) => {
      if (!statusEl) return;
      const statuses = Array.isArray(lov.triage_statuses) ? lov.triage_statuses : [];
      if (!statuses.length) return;
      const options = ['<option value="">All</option>'];
      statusLabelMap = new Map();
      statuses.forEach((item) => {
        const key = String(item.key || '').toLowerCase();
        const label = String(item.label || '');
        if (!key || !label) return;
        statusLabelMap.set(key, label);
        options.push(`<option value="${esc(key)}">${esc(label)}</option>`);
      });
      statusEl.innerHTML = options.join('');
    },
  });

  if (searchBtn) {
    searchBtn.addEventListener('click', page.resetAndLoad);
  }
  PermissionIntel.bindEnterReload([searchEl], page.resetAndLoad);
  if (statusEl) {
    statusEl.addEventListener('change', page.resetAndLoad);
  }

  page.primeLov().finally(page.loadPage);
})();
