(function() {
  "use strict";
  const SamplesPage = window.SamplesPage || (window.SamplesPage = {});
  if (window.App) {
    SamplesPage.buildQuery = (elements, pageOverride = null) => {
      const params = new URLSearchParams();
      const q = elements.searchEl ? elements.searchEl.value.trim() : "";
      const family = elements.familyEl ? elements.familyEl.value.trim() : "";
      const familyAlignment = elements.familyAlignmentEl ? elements.familyAlignmentEl.value : "";
      const status = elements.statusEl ? elements.statusEl.value : "";
      const pageSize = elements.pageSizeEl ? elements.pageSizeEl.value : "";
      const page = pageOverride ?? (elements.pageEl ? elements.pageEl.value : "1");
      const columns = elements.columnsEl ? elements.columnsEl.value : "";
      const sortBy = elements.sortByEl ? elements.sortByEl.value : "";
      const sortDir = elements.sortDirEl ? elements.sortDirEl.value : "";
      if (q) params.set("q", q);
      if (family) params.set("family", family);
      if (familyAlignment) params.set("family_alignment", familyAlignment);
      if (status) params.set("status", status);
      if (pageSize) params.set("page_size", pageSize);
      if (columns) params.set("columns", columns);
      if (sortBy) params.set("sort_by", sortBy);
      if (sortDir) params.set("sort_dir", sortDir);
      params.set("page", String(page || "1"));
      return params;
    };
    SamplesPage.updateUrl = (params) => {
      const url = new URL(window.location.href);
      url.search = params.toString();
      window.history.replaceState({}, "", url.toString());
    };
  }
})();
