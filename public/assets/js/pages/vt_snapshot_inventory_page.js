(function() {
  "use strict";
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("vt-snapshot-inventory-root");
  if (root && window.App) {
    let listRows = function(node, rows) {
      if (!node) return;
      node.innerHTML = "";
      if (!rows.length) {
        node.innerHTML = '<li class="muted">--</li>';
        return;
      }
      rows.forEach(([key, value]) => {
        const li = document.createElement("li");
        li.innerHTML = `${esc(String(key))}: <strong>${esc(String(value))}</strong>`;
        node.appendChild(li);
      });
    }, renderAttrs = function(rows) {
      if (!attrsBody) return;
      attrsBody.innerHTML = "";
      if (!rows.length) {
        attrsBody.innerHTML = '<tr><td colspan="2" class="muted">No attributes found.</td></tr>';
        return;
      }
      rows.forEach((row) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${esc(String(row.attribute ?? ""))}</td><td>${esc(String(row.count ?? ""))}</td>`;
        attrsBody.appendChild(tr);
      });
    }, renderRecent = function(hours, attrs) {
      if (recentCaptionEl) recentCaptionEl.textContent = `Window: last ${hours}h`;
      if (!recentListEl) return;
      recentListEl.innerHTML = "";
      if (!attrs.length) {
        recentListEl.innerHTML = '<li class="muted">(none)</li>';
        return;
      }
      attrs.slice(0, 60).forEach((name) => {
        const li = document.createElement("li");
        li.textContent = name;
        recentListEl.appendChild(li);
      });
      if (attrs.length > 60) {
        const li = document.createElement("li");
        li.className = "muted";
        li.textContent = `... +${attrs.length - 60} more`;
        recentListEl.appendChild(li);
      }
    };
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "30") || 30;
    const refreshMs = Math.max(10, refreshSeconds) * 1e3;
    const maxRowsEl = document.getElementById("vt-inv-max-rows");
    const recentHoursEl = document.getElementById("vt-inv-recent-hours");
    const refreshBtn = document.getElementById("vt-inv-refresh");
    const summaryEl = document.getElementById("vt-inv-summary");
    const statusMixEl = document.getElementById("vt-inv-status-mix");
    const sourceMixEl = document.getElementById("vt-inv-source-mix");
    const attrsBody = document.getElementById("vt-inv-attrs-body");
    const recentCaptionEl = document.getElementById("vt-inv-recent-caption");
    const recentListEl = document.getElementById("vt-inv-recent-new");
    const errorEl = document.getElementById("vt-inv-error");
    const liveMetaEl = document.getElementById("vt-inv-live-meta");
    const esc = App.escapeHtml;
    async function load() {
      if (!endpoint || !summaryEl || !attrsBody) return;
      const maxRows = Number(maxRowsEl?.value || 5e3);
      const recentHours = Number(recentHoursEl?.value || 24);
      const params = new URLSearchParams({
        max_rows: String(Math.min(Math.max(maxRows, 1), 2e4)),
        recent_hours: String(Math.min(Math.max(recentHours, 1), 720))
      });
      const url = `${endpoint}?${params.toString()}`;
      if (errorEl) errorEl.textContent = "";
      summaryEl.textContent = "Loading...";
      attrsBody.innerHTML = '<tr><td colspan="2" class="muted">Loading...</td></tr>';
      try {
        const res = await App.fetchJson(url);
        if (!res.ok) {
          if (errorEl) errorEl.textContent = `Inventory API error: ${res.error || "request failed"}`;
          summaryEl.textContent = "Unavailable";
          if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
          return;
        }
        const payload = toRecord(res.body);
        const data = toRecord(payload.data ?? payload);
        summaryEl.textContent = `snapshots=${data.snapshot_total ?? 0} | with_attributes=${data.with_attributes ?? 0} | parse_errors=${data.parse_errors ?? 0} | generated_at_utc=${data.generated_at_utc ?? "--"}`;
        listRows(statusMixEl, Object.entries(toRecord(data.status_mix)));
        listRows(sourceMixEl, Object.entries(toRecord(data.source_mix)));
        renderAttrs(Array.isArray(data.top_attributes) ? data.top_attributes : []);
        renderRecent(
          Number(data.recent_hours ?? recentHours),
          Array.isArray(data.recent_new_attributes) ? data.recent_new_attributes.map(String) : []
        );
        if (liveMetaEl) liveMetaEl.textContent = `Live refresh: ${String(data.generated_at_utc || "ok")}`;
      } catch (error) {
        if (errorEl) errorEl.textContent = `Inventory API error: ${error instanceof Error ? error.message : String(error)}`;
        summaryEl.textContent = "Unavailable";
        if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
      }
    }
    refreshBtn?.addEventListener("click", () => {
      void load();
    });
    maxRowsEl?.addEventListener("change", () => {
      void load();
    });
    recentHoursEl?.addEventListener("change", () => {
      void load();
    });
    void load();
    window.setInterval(() => {
      void load();
    }, refreshMs);
  }
})();
