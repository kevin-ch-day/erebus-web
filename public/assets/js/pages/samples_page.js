(() => {
  const root = document.getElementById('samples-page-root');
  if (!root || !window.App || !window.SamplesPage) return;

  const endpoint = root.dataset.endpoint || '';
  const detailBase = root.dataset.detailBase || '';
  const searchEl = document.getElementById('samples-search');
  const familyEl = document.getElementById('samples-family');
  const familyAlignmentEl = document.getElementById('samples-family-alignment');
  const statusEl = document.getElementById('samples-status');
  const pageSizeEl = document.getElementById('samples-page-size');
  const pageEl = document.getElementById('samples-page');
  const columnsEl = document.getElementById('samples-columns');
  const sortByEl = document.getElementById('samples-sort-by');
  const sortDirEl = document.getElementById('samples-sort-dir');
  const sortButtons = Array.from(document.querySelectorAll('.samples-sort-btn'));
  const refreshBtn = document.getElementById('samples-refresh');
  const resetBtn = document.getElementById('samples-reset');

  const tableEl = document.querySelector('.samples-table');
  const bodyEl = document.getElementById('samples-body');
  const metaEl = document.getElementById('samples-meta');
  const pagesEl = document.getElementById('samples-pages');
  const prevBtn = document.getElementById('samples-prev');
  const nextBtn = document.getElementById('samples-next');
  const errorEl = document.getElementById('samples-error');

  if (!endpoint || !detailBase || !bodyEl) return;

  const esc = App.escapeHtml;
  const copyText = App.copyText;
  const SamplesPage = window.SamplesPage;

  let pendingTimer = null;
  let lastPayload = null;

  function updateSortIndicators() {
    if (!sortButtons.length || !sortByEl || !sortDirEl) return;
    const activeBy = String(sortByEl.value || '').toLowerCase();
    const activeDir = String(sortDirEl.value || '').toLowerCase();

    sortButtons.forEach((btn) => {
      const key = String(btn.dataset.sort || '').toLowerCase();
      const indicator = btn.querySelector('.samples-sort-indicator');
      const active = key === activeBy;

      btn.classList.toggle('active', active);
      if (indicator) {
        indicator.textContent = active ? (activeDir === 'asc' ? '^' : 'v') : '--';
      }
    });
  }

  function countActiveFilters() {
    let count = 0;
    if (searchEl && searchEl.value.trim() !== '') count += 1;
    if (familyEl && familyEl.value.trim() !== '') count += 1;
    if (familyAlignmentEl && familyAlignmentEl.value !== '') count += 1;
    if (statusEl && statusEl.value !== '') count += 1;
    if (columnsEl && columnsEl.value === 'detailed') count += 1;
    if (sortByEl && sortByEl.value !== (root.dataset.defaultSortBy || 'id')) count += 1;
    if (sortDirEl && sortDirEl.value !== (root.dataset.defaultSortDir || 'desc')) count += 1;
    return count;
  }

  function resetFiltersToDefaults() {
    const defaultPageSize = root.dataset.defaultPageSize || (pageSizeEl && pageSizeEl.options.length ? pageSizeEl.options[0].value : '100');
    const defaultColumns = root.dataset.defaultColumns || 'simple';
    const defaultSortBy = root.dataset.defaultSortBy || 'id';
    const defaultSortDir = root.dataset.defaultSortDir || 'desc';

    if (searchEl) searchEl.value = '';
    if (familyEl) familyEl.value = '';
    if (familyAlignmentEl) familyAlignmentEl.value = '';
    if (statusEl) statusEl.value = '';
    if (columnsEl) columnsEl.value = defaultColumns;
    if (sortByEl) sortByEl.value = defaultSortBy;
    if (sortDirEl) sortDirEl.value = defaultSortDir;
    if (pageSizeEl) pageSizeEl.value = defaultPageSize;
    if (pageEl) pageEl.value = '1';
    SamplesPage.applyColumnsView(tableEl, defaultColumns);
    updateSortIndicators();
  }

  async function loadSamples(pageOverride = null) {
    const params = SamplesPage.buildQuery({
      searchEl,
      familyEl,
      familyAlignmentEl,
      statusEl,
      pageSizeEl,
      pageEl,
      columnsEl,
      sortByEl,
      sortDirEl,
    }, pageOverride);
    SamplesPage.updateUrl(params);
    const url = endpoint + '?' + params.toString();

    if (pendingTimer) {
      clearTimeout(pendingTimer);
      pendingTimer = null;
    }

    errorEl.textContent = '';
    bodyEl.innerHTML = '<tr><td colspan="16" class="muted">Loading samples...</td></tr>';

    try {
      const res = await App.fetchPayload(url);
      if (!res.ok) {
        const raw = res.raw ? String(res.raw).slice(0, 2000) : '';
        const detail = raw ? `\n\n${esc(raw)}` : '';
        errorEl.innerHTML = '<pre>Samples API error.\n\nHTTP ' + res.status + '\nerror: ' +
          esc(res.error) + detail + '</pre>';
        return;
      }

      lastPayload = res.data || {};
      SamplesPage.renderRows(lastPayload.rows || [], { bodyEl, detailBase });
      SamplesPage.renderMeta(lastPayload, {
        metaEl,
        pagesEl,
        pageEl,
        prevBtn,
        nextBtn,
        activeFilters: countActiveFilters(),
      });
    } catch (e) {
      errorEl.innerHTML = '<pre>Samples API error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
    }
  }

  function scheduleReload() {
    if (pendingTimer) clearTimeout(pendingTimer);
    pendingTimer = setTimeout(() => loadSamples(1), 200);
  }

  bodyEl.addEventListener('click', (event) => {
    const target = event.target;
    const button = target && target.closest ? target.closest('.copy-btn') : null;
    if (!button) return;
    const value = button.getAttribute('data-copy') || '';
    if (!value) return;
    copyText(value);
  });

  [searchEl, familyEl, familyAlignmentEl, statusEl, pageSizeEl, columnsEl, sortByEl, sortDirEl].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', scheduleReload);
    el.addEventListener('change', scheduleReload);
  });
  [searchEl, familyEl].forEach((el) => {
    if (!el) return;
    el.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      loadSamples(1);
    });
  });

  columnsEl.addEventListener('change', () => SamplesPage.applyColumnsView(tableEl, columnsEl.value));
  if (sortByEl) sortByEl.addEventListener('change', updateSortIndicators);
  if (sortDirEl) sortDirEl.addEventListener('change', updateSortIndicators);
  pageEl.addEventListener('change', () => loadSamples(pageEl.value || 1));
  prevBtn.addEventListener('click', () => loadSamples(Math.max(1, Number(pageEl.value) - 1)));
  nextBtn.addEventListener('click', () => {
    const pages = lastPayload ? Number(lastPayload.total_pages || 1) : 1;
    const next = Math.min(pages, Number(pageEl.value) + 1);
    loadSamples(next);
  });
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => loadSamples(pageEl.value || 1));
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      resetFiltersToDefaults();
      loadSamples(1);
    });
  }
  sortButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!sortByEl || !sortDirEl) return;
      const nextBy = String(btn.dataset.sort || '').toLowerCase();
      if (!nextBy) return;

      const currentBy = String(sortByEl.value || '').toLowerCase();
      const currentDir = String(sortDirEl.value || '').toLowerCase();
      const nextDir = currentBy === nextBy ? (currentDir === 'asc' ? 'desc' : 'asc') : 'desc';

      sortByEl.value = nextBy;
      sortDirEl.value = nextDir;
      updateSortIndicators();
      loadSamples(1);
    });
  });

  SamplesPage.applyColumnsView(tableEl, columnsEl.value);
  updateSortIndicators();
  loadSamples();
})();
