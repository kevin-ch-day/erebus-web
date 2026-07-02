(function() {
  "use strict";
  const root = document.getElementById("perm-aosp-page");
  if (root && window.App && window.PermissionIntel) {
    const App = window.App;
    const PI = window.PermissionIntel;
    const endpoint = root.dataset.catalogEndpoint || "";
    const lovEndpoint = root.dataset.lovEndpoint || "";
    const pageSize = Number(root.dataset.pageSize || 200);
    const searchEl = document.getElementById("perm-dict-search");
    const bucketEl = document.getElementById("perm-dict-bucket");
    const searchBtn = document.getElementById("perm-aosp-search");
    const bodyEl = document.getElementById("perm-aosp-body");
    const metaEl = document.getElementById("perm-aosp-meta");
    const errorEl = document.getElementById("perm-aosp-error");
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const fmtCount = PI.formatCount;
    const fmtUtc = App.formatUtc;
    let bucketLabelMap = /* @__PURE__ */ new Map();
    if (!endpoint || !bodyEl || !metaEl || !errorEl) ;
    else {
      const page = PI.createReadonlyCatalogPage({
        endpoint,
        lovEndpoint,
        pageSize,
        bodyEl,
        metaEl,
        errorEl,
        colSpan: 5,
        loadMessage: "Loading AOSP permissions...",
        emptyMessage: "No observed AOSP permissions found.",
        emptyMeta: "Check android_permission_obs_sample for data.",
        renderMetaText: (meta) => `Showing ${fmtCount(meta.total_count)} permissions (historical samples).`,
        buildParams: () => ({
          q: searchEl?.value.trim() || "",
          bucket: bucketEl?.value || ""
        }),
        renderRow: (row) => {
          const bucketKey = PI.normalizeKey(row.bucket);
          const bucketLabel = bucketLabelMap.get(bucketKey) || fmt(row.bucket);
          return `
          <td class="mono">${esc(fmt(row.permission_string))}</td>
          <td>${esc(bucketLabel)}</td>
          <td>${esc(fmtCount(row.seen_count))}</td>
          <td>${esc(row.first_seen_at_utc ? fmtUtc(row.first_seen_at_utc) : "--")}</td>
          <td>${esc(row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : "--")}</td>
        `;
        },
        loadLov: (lov) => {
          if (!bucketEl) return;
          const body = lov && typeof lov === "object" ? lov : {};
          const buckets = Array.isArray(body.buckets) ? body.buckets : [];
          bucketLabelMap = /* @__PURE__ */ new Map();
          const options = ['<option value="">All</option>'];
          buckets.forEach((bucket) => {
            const key = PI.normalizeKey(bucket.key);
            const label = String(bucket.label || "");
            if (!key || !label || !key.startsWith("AOSP")) return;
            bucketLabelMap.set(key, label);
            options.push(`<option value="${esc(key)}">${esc(label)}</option>`);
          });
          bucketEl.innerHTML = options.join("");
        }
      });
      searchBtn?.addEventListener("click", () => {
        void page.resetAndLoad();
      });
      PI.bindEnterReload([searchEl], () => {
        void page.resetAndLoad();
      });
      bucketEl?.addEventListener("change", () => {
        void page.resetAndLoad();
      });
      void page.primeLov().finally(() => {
        void page.loadPage();
      });
    }
  }
})();
