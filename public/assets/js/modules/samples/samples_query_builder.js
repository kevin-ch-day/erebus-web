(() => {
  if (!window.App) return;
  const SamplesPage = window.SamplesPage || (window.SamplesPage = {});

  SamplesPage.buildQuery = (els, pageOverride = null) => {
    const params = new URLSearchParams();
    const q = els.searchEl ? els.searchEl.value.trim() : '';
    const family = els.familyEl ? els.familyEl.value.trim() : '';
    const familyAlignment = els.familyAlignmentEl ? els.familyAlignmentEl.value : '';
    const status = els.statusEl ? els.statusEl.value : '';
    const pageSize = els.pageSizeEl ? els.pageSizeEl.value : '';
    const page = pageOverride ?? (els.pageEl ? els.pageEl.value : '1');
    const columns = els.columnsEl ? els.columnsEl.value : '';
    const sortBy = els.sortByEl ? els.sortByEl.value : '';
    const sortDir = els.sortDirEl ? els.sortDirEl.value : '';

    if (q) params.set('q', q);
    if (family) params.set('family', family);
    if (familyAlignment) params.set('family_alignment', familyAlignment);
    if (status) params.set('status', status);
    if (pageSize) params.set('page_size', pageSize);
    if (columns) params.set('columns', columns);
    if (sortBy) params.set('sort_by', sortBy);
    if (sortDir) params.set('sort_dir', sortDir);
    params.set('page', page || '1');

    return params;
  };

  SamplesPage.updateUrl = (params) => {
    const url = new URL(window.location.href);
    url.search = params.toString();
    window.history.replaceState({}, '', url.toString());
  };
})();
