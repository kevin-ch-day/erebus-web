(() => {
  const root = document.getElementById('perm-aosp-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.catalogEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const pageSize = Number(root.dataset.pageSize || 200);
  if (!endpoint) return;

  const searchEl = document.getElementById('perm-dict-search');
  const bucketEl = document.getElementById('perm-dict-bucket');
  const searchBtn = document.getElementById('perm-aosp-search');
  const bodyEl = document.getElementById('perm-aosp-body');
  const metaEl = document.getElementById('perm-aosp-meta');
  const errorEl = document.getElementById('perm-aosp-error');

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
    loadMessage: 'Loading AOSP permissions...',
    emptyMessage: 'No observed AOSP permissions found.',
    emptyMeta: 'Check android_permission_obs_sample for data.',
    renderMetaText: (meta) => `Showing ${fmtCount(meta.total_count)} permissions (historical samples).`,
    buildParams: () => ({
      q: searchEl && searchEl.value.trim() ? searchEl.value.trim() : '',
      bucket: bucketEl && bucketEl.value ? bucketEl.value : '',
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
      if (!bucketEl) return;
      const buckets = Array.isArray(lov.buckets) ? lov.buckets : [];
      bucketLabelMap = new Map();
      const options = ['<option value="">All</option>'];
      buckets.forEach((bucket) => {
        const key = PermissionIntel.normalizeKey(bucket.key);
        const label = String(bucket.label || '');
        if (!key || !label || !key.startsWith('AOSP')) return;
        bucketLabelMap.set(key, label);
        options.push(`<option value="${esc(key)}">${esc(label)}</option>`);
      });
      bucketEl.innerHTML = options.join('');
    },
  });

  if (searchBtn) {
    searchBtn.addEventListener('click', page.resetAndLoad);
  }
  PermissionIntel.bindEnterReload([searchEl], page.resetAndLoad);
  if (bucketEl) {
    bucketEl.addEventListener('change', page.resetAndLoad);
  }

  page.primeLov().finally(page.loadPage);
})();
