(() => {
  const root = document.getElementById('schema-inventory-page');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  if (!endpoint) return;

  const metaEl = document.getElementById('schema-inventory-meta');
  const primaryDbEl = document.getElementById('schema-inventory-primary-db');
  const piDbEl = document.getElementById('schema-inventory-pi-db');
  const splitEl = document.getElementById('schema-inventory-split');
  const totalEl = document.getElementById('schema-inventory-total');
  const availableEl = document.getElementById('schema-inventory-available');
  const missingSurfacesEl = document.getElementById('schema-inventory-missing-surfaces');
  const missingColumnsEl = document.getElementById('schema-inventory-missing-columns');
  const searchEl = document.getElementById('schema-inventory-search');
  const availabilityEl = document.getElementById('schema-inventory-filter-availability');
  const roleEl = document.getElementById('schema-inventory-filter-role');
  const bodyEl = document.getElementById('schema-inventory-body');
  const errorEl = document.getElementById('schema-inventory-error');

  const esc = App.escapeHtml;
  const formatUtc = App.formatUtc;

  let surfaces = [];

  function badgeForSurface(surface) {
    if (surface.available) return '<span class="badge ok">Available</span>';
    if (surface.present) return '<span class="badge warn">Missing columns</span>';
    return '<span class="badge err">Missing</span>';
  }

  function surfaceMatches(surface, search, availability, role) {
    if (availability === 'available' && !surface.available) return false;
    if (availability === 'missing' && surface.available) return false;
    if (role !== 'all' && surface.catalog_role !== role) return false;
    if (!search) return true;

    const haystack = [
      surface.name,
      surface.catalog,
      surface.catalog_role,
      surface.analysis_role,
      ...(Array.isArray(surface.consumer_pages) ? surface.consumer_pages : []),
      ...(Array.isArray(surface.missing_columns) ? surface.missing_columns : []),
    ].join(' ').toLowerCase();
    return haystack.includes(search);
  }

  function renderRows() {
    if (!bodyEl) return;
    const search = (searchEl && searchEl.value ? String(searchEl.value) : '').trim().toLowerCase();
    const availability = availabilityEl ? String(availabilityEl.value || 'all') : 'all';
    const role = roleEl ? String(roleEl.value || 'all') : 'all';

    const filtered = surfaces.filter((surface) => surfaceMatches(surface, search, availability, role));
    const sorted = [...filtered].sort((a, b) => {
      if (a.available !== b.available) return a.available ? 1 : -1;
      return String(a.name || '').localeCompare(String(b.name || ''));
    });

    if (!sorted.length) {
      bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No surfaces match the current filters.</td></tr>';
      return;
    }

    bodyEl.innerHTML = sorted.map((surface) => {
      const consumers = Array.isArray(surface.consumer_pages) && surface.consumer_pages.length
        ? surface.consumer_pages.join(', ')
        : '--';
      const missingColumns = Array.isArray(surface.missing_columns) && surface.missing_columns.length
        ? surface.missing_columns.join(', ')
        : '--';
      const catalog = surface.catalog || '--';
      const roleLabel = surface.catalog_role || '--';
      return `
        <tr>
          <td class="mono">${esc(surface.name || '--')}</td>
          <td>${esc(catalog)}<br><span class="muted">${esc(roleLabel)}</span></td>
          <td>${esc(surface.expected_object_kind || '--')}<br><span class="muted">${esc(surface.actual_table_type || '--')}</span></td>
          <td>${esc(surface.analysis_role || '--')}</td>
          <td>${badgeForSurface(surface)}</td>
          <td class="cell-wrap">${esc(consumers)}</td>
          <td class="cell-wrap">${esc(missingColumns)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderSummary(payload, meta) {
    const summary = payload && payload.summary ? payload.summary : {};
    if (primaryDbEl) primaryDbEl.textContent = meta.primary_database || '--';
    if (piDbEl) piDbEl.textContent = meta.permission_intel_database || '--';
    if (splitEl) splitEl.textContent = meta.permission_intel_split ? 'yes' : 'no';
    if (totalEl) totalEl.textContent = String(summary.surface_count || 0);
    if (availableEl) availableEl.textContent = String(summary.available_count || 0);
    if (missingSurfacesEl) missingSurfacesEl.textContent = String(summary.missing_surface_count || 0);
    if (missingColumnsEl) missingColumnsEl.textContent = String(summary.missing_column_count || 0);
    if (metaEl) {
      const timestamp = formatUtc(new Date().toISOString());
      metaEl.textContent = `Loaded: ${timestamp}`;
    }
  }

  async function load() {
    if (errorEl) errorEl.textContent = '';
    try {
      const res = await App.fetchJson(endpoint);
      if (!res.ok) {
        if (errorEl) errorEl.textContent = `Schema inventory API error: HTTP ${res.status} ${res.error || ''}`;
        return;
      }
      const body = res.body || {};
      const payload = body.data || {};
      const meta = body.meta || {};
      surfaces = Array.isArray(payload.surfaces) ? payload.surfaces : [];
      renderSummary(payload, meta);
      renderRows();
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = `Schema inventory load failed: ${err && err.message ? err.message : String(err)}`;
      }
    }
  }

  if (searchEl) searchEl.addEventListener('input', renderRows);
  if (availabilityEl) availabilityEl.addEventListener('change', renderRows);
  if (roleEl) roleEl.addEventListener('change', renderRows);

  load();
})();
