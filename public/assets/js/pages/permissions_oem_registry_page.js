(() => {
  const root = document.getElementById('perm-oem-registry-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.registryEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const pageSize = Number(root.dataset.pageSize || 200);
  if (!endpoint) return;

  const searchEl = document.getElementById('oem-search');
  const classEl = document.getElementById('oem-class');
  const searchBtn = document.getElementById('oem-registry-search');
  const bodyEl = document.getElementById('oem-registry-body');
  const metaEl = document.getElementById('oem-registry-meta');
  const errorEl = document.getElementById('oem-registry-error');

  const esc = App.escapeHtml;
  const fmtCount = PermissionIntel.formatCount;
  const fmtUtc = App.formatUtc;
  let classPrimed = false;

  const page = PermissionIntel.createReadonlyCatalogPage({
    endpoint,
    lovEndpoint,
    pageSize,
    bodyEl,
    metaEl,
    errorEl,
    colSpan: 7,
    loadMessage: 'Loading OEM registry...',
    emptyMessage: 'No OEM namespaces found.',
    emptyMeta: 'Check android_permission_enrich_vt_event for data.',
    renderMetaText: (meta) => `Showing ${fmtCount(meta.total_count)} namespaces. Default review scope: ${String(meta.default_class_recommended || 'oem').toUpperCase()}.`,
    buildParams: () => ({
      q: searchEl && searchEl.value.trim() ? searchEl.value.trim() : '',
      class: classEl && classEl.value ? classEl.value : '',
    }),
    renderRow: (row) => {
      const ns = String(row.namespace || '--');
      const classification = {
        label: String(row.namespace_class_label || PermissionIntel.classifyNamespace(ns).label || '--'),
        className: String(row.namespace_class_name || PermissionIntel.classifyNamespace(ns).className || 'err'),
      };
      return `
        <td class="mono">${esc(ns)}</td>
        <td title="Historical seen count">${esc(fmtCount(row.seen_count))}</td>
        <td>${esc(fmtCount(row.permission_count))}</td>
        <td><span class="status-dot ${esc(classification.className)}"></span>${esc(classification.label)}</td>
        <td>
          <div><span class="badge ${esc(row.namespace_class_name || classification.className || 'muted')}">${esc(String(row.validation_label || 'Needs review'))}</span></div>
          <div class="muted" style="margin-top:4px;">${esc(String(row.review_hint || '--'))}</div>
        </td>
        <td>${esc(row.first_seen_at_utc ? fmtUtc(row.first_seen_at_utc) : '--')}</td>
        <td>${esc(row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--')}</td>
      `;
    },
    loadLov: (lov) => {
      if (!classEl) return;
      const classes = Array.isArray(lov.namespace_classes) ? lov.namespace_classes : [];
      if (!classes.length) return;
      const options = ['<option value="">All</option>'];
      classes.forEach((item) => {
        const key = String(item.key || '').toLowerCase();
        const label = String(item.label || '');
        if (!key || !label) return;
        options.push(`<option value="${esc(key)}">${esc(label)}</option>`);
      });
      classEl.innerHTML = options.join('');
      if (!classPrimed && !classEl.value) {
        classEl.value = 'oem';
        classPrimed = true;
      }
    },
  });

  if (searchBtn) {
    searchBtn.addEventListener('click', page.resetAndLoad);
  }
  PermissionIntel.bindEnterReload([searchEl], page.resetAndLoad);
  if (classEl) {
    classEl.addEventListener('change', page.resetAndLoad);
  }

  page.primeLov().finally(page.loadPage);
})();
