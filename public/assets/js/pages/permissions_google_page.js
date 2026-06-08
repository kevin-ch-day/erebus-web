(() => {
  const root = document.getElementById('perm-google-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.catalogEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const pageSize = Number(root.dataset.pageSize || 200);
  if (!endpoint) return;

  const searchEl = document.getElementById('perm-google-search');
  const namespaceEl = document.getElementById('perm-google-namespace');
  const searchBtn = document.getElementById('perm-google-search-btn');
  const bodyEl = document.getElementById('perm-google-body');
  const metaEl = document.getElementById('perm-google-meta');
  const errorEl = document.getElementById('perm-google-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const fmtCount = PermissionIntel.formatCount;
  const fmtUtc = App.formatUtc;

  let bucketLabelMap = new Map();

  const page = PermissionIntel.createReadonlyCatalogPage({
    endpoint,
    lovEndpoint,
    pageSize,
    bodyEl,
    metaEl,
    errorEl,
    colSpan: 5,
    loadMessage: 'Loading Google permissions...',
    emptyMessage: 'No observed Google permissions found.',
    emptyMeta: 'Check android_permission_obs_sample for data.',
    renderMetaText: (meta) => `Showing ${fmtCount(meta.total_count)} permissions (historical samples).`,
    buildParams: () => ({
      q: searchEl && searchEl.value.trim() ? searchEl.value.trim() : '',
      namespace: namespaceEl && namespaceEl.value.trim() ? namespaceEl.value.trim() : '',
    }),
    renderRow: (row) => {
      const bucketKey = PermissionIntel.normalizeKey(row.bucket);
      const bucketLabel = bucketLabelMap.get(bucketKey) || fmt(row.bucket);
      return `
        <td class="mono">${esc(fmt(row.permission_string))}</td>
        <td>${esc(bucketLabel)}</td>
        <td>${esc(fmtCount(row.seen_count))}</td>
        <td>${esc(row.first_seen_at_utc ? fmtUtc(row.first_seen_at_utc) : '--')}</td>
        <td>${esc(row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--')}</td>
      `;
    },
    loadLov: (lov) => {
      const buckets = Array.isArray(lov.buckets) ? lov.buckets : [];
      bucketLabelMap = new Map();
      buckets.forEach((bucket) => {
        const key = PermissionIntel.normalizeKey(bucket.key);
        const label = String(bucket.label || '');
        if (!key || !label) return;
        bucketLabelMap.set(key, label);
      });
    },
  });

  if (searchBtn) {
    searchBtn.addEventListener('click', page.resetAndLoad);
  }
  PermissionIntel.bindEnterReload([searchEl, namespaceEl], page.resetAndLoad);

  page.primeLov().finally(page.loadPage);
})();
