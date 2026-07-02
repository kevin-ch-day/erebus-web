(function() {
  "use strict";
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("schema-inventory-page");
  if (root && window.App) {
    let badgeForSurface = function(surface) {
      if (surface.available) return '<span class="badge ok">Available</span>';
      if (surface.present) return '<span class="badge warn">Missing columns</span>';
      return '<span class="badge err">Missing</span>';
    }, surfaceMatches = function(surface, search, availability, role) {
      if (availability === "available" && !surface.available) return false;
      if (availability === "missing" && surface.available) return false;
      if (role !== "all" && String(surface.catalog_role || "") !== role) return false;
      if (!search) return true;
      const haystack = [
        surface.name,
        surface.catalog,
        surface.catalog_role,
        surface.analysis_role,
        ...Array.isArray(surface.consumer_pages) ? surface.consumer_pages : [],
        ...Array.isArray(surface.missing_columns) ? surface.missing_columns : []
      ].join(" ").toLowerCase();
      return haystack.includes(search);
    }, renderRows = function() {
      if (!bodyEl) return;
      const search = (searchEl?.value || "").trim().toLowerCase();
      const availability = availabilityEl?.value || "all";
      const role = roleEl?.value || "all";
      const filtered = surfaces.filter((surface) => surfaceMatches(surface, search, availability, role));
      const sorted = [...filtered].sort((a, b) => {
        if (Boolean(a.available) !== Boolean(b.available)) return a.available ? 1 : -1;
        return String(a.name || "").localeCompare(String(b.name || ""));
      });
      if (!sorted.length) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No surfaces match the current filters.</td></tr>';
        return;
      }
      bodyEl.innerHTML = sorted.map((surface) => {
        const consumers = Array.isArray(surface.consumer_pages) && surface.consumer_pages.length ? surface.consumer_pages.join(", ") : "--";
        const missingColumns = Array.isArray(surface.missing_columns) && surface.missing_columns.length ? surface.missing_columns.join(", ") : "--";
        return `
        <tr>
          <td class="mono">${esc(String(surface.name || "--"))}</td>
          <td>${esc(String(surface.catalog || "--"))}<br><span class="muted">${esc(String(surface.catalog_role || "--"))}</span></td>
          <td>${esc(String(surface.expected_object_kind || "--"))}<br><span class="muted">${esc(String(surface.actual_table_type || "--"))}</span></td>
          <td>${esc(String(surface.analysis_role || "--"))}</td>
          <td>${badgeForSurface(surface)}</td>
          <td class="cell-wrap">${esc(consumers)}</td>
          <td class="cell-wrap">${esc(missingColumns)}</td>
        </tr>
      `;
      }).join("");
    }, renderSummary = function(payload, meta) {
      const summary = toRecord(payload.summary);
      if (primaryDbEl) primaryDbEl.textContent = String(meta.primary_database || "--");
      if (piDbEl) piDbEl.textContent = String(meta.permission_intel_database || "--");
      if (splitEl) splitEl.textContent = meta.permission_intel_split ? "yes" : "no";
      if (totalEl) totalEl.textContent = String(summary.surface_count || 0);
      if (availableEl) availableEl.textContent = String(summary.available_count || 0);
      if (missingSurfacesEl) missingSurfacesEl.textContent = String(summary.missing_surface_count || 0);
      if (missingColumnsEl) missingColumnsEl.textContent = String(summary.missing_column_count || 0);
      if (metaEl) metaEl.textContent = `Loaded: ${formatUtc((/* @__PURE__ */ new Date()).toISOString())}`;
    };
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "60") || 60;
    const refreshMs = Math.max(15, refreshSeconds) * 1e3;
    const metaEl = document.getElementById("schema-inventory-meta");
    const primaryDbEl = document.getElementById("schema-inventory-primary-db");
    const piDbEl = document.getElementById("schema-inventory-pi-db");
    const splitEl = document.getElementById("schema-inventory-split");
    const totalEl = document.getElementById("schema-inventory-total");
    const availableEl = document.getElementById("schema-inventory-available");
    const missingSurfacesEl = document.getElementById("schema-inventory-missing-surfaces");
    const missingColumnsEl = document.getElementById("schema-inventory-missing-columns");
    const searchEl = document.getElementById("schema-inventory-search");
    const availabilityEl = document.getElementById("schema-inventory-filter-availability");
    const roleEl = document.getElementById("schema-inventory-filter-role");
    const bodyEl = document.getElementById("schema-inventory-body");
    const errorEl = document.getElementById("schema-inventory-error");
    const esc = App.escapeHtml;
    const formatUtc = App.formatUtc;
    let surfaces = [];
    async function load() {
      if (!endpoint) return;
      if (errorEl) errorEl.textContent = "";
      try {
        const res = await App.fetchJson(endpoint);
        if (!res.ok) {
          if (errorEl) errorEl.textContent = `Schema inventory API error: HTTP ${res.status} ${res.error || ""}`;
          return;
        }
        const body = toRecord(res.body);
        const payload = toRecord(body.data);
        const meta = toRecord(body.meta);
        surfaces = Array.isArray(payload.surfaces) ? payload.surfaces : [];
        renderSummary(payload, meta);
        renderRows();
      } catch (error) {
        if (errorEl) {
          errorEl.textContent = `Schema inventory load failed: ${error instanceof Error ? error.message : String(error)}`;
        }
      }
    }
    searchEl?.addEventListener("input", renderRows);
    availabilityEl?.addEventListener("change", renderRows);
    roleEl?.addEventListener("change", renderRows);
    void load();
    window.setInterval(() => {
      void load();
    }, refreshMs);
  }
})();
