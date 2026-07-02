(function() {
  "use strict";
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("perm-drift-page");
  if (root && window.App && window.PermissionIntel) {
    let classificationHelp = function(label) {
      switch (label.toLowerCase()) {
        case "core":
          return "Core Android namespaces (android.*).";
        case "expected":
          return "Google/GMS namespaces (com.google.*).";
        case "known ecosystem":
          return "Known third-party or platform-adjacent ecosystem namespace.";
        case "oem":
          return "Known vendor namespaces (Samsung/Huawei/etc.).";
        default:
          return "Everything else; review for new vendors or app-defined permissions.";
      }
    }, isNewNamespace = function(row, cutoffMs) {
      const ms = App.parseUtcToMs(row.last_seen_at_utc);
      return !!ms && ms >= cutoffMs;
    }, triageLink = function(namespaceValue) {
      return pageUrl("permissions_triage", { namespace: namespaceValue });
    }, updateUrl = function(limit) {
      const url = new URL(window.location.href);
      url.searchParams.set("namespace_limit", limit);
      window.history.replaceState({}, "", url.toString());
    }, updateQuickButtons = function() {
      quickFilterBtns.forEach((btn) => {
        const mode = btn.dataset.mode || "all";
        btn.classList.toggle("is-active", mode === quickMode);
      });
    }, applyFilters = function(rows) {
      const term = searchEl?.value.trim().toLowerCase() || "";
      return rows.filter((row) => {
        const ns = String(row.namespace ?? "").toLowerCase();
        const cls = String(row.namespace_class_label || classifyNamespace(row.namespace).label || "").toLowerCase();
        const matchesTerm = term ? ns.includes(term) : true;
        const matchesQuick = quickMode === "all" ? true : quickMode === "review" ? cls === "oem" || cls === "anomalous" : cls === quickMode;
        return matchesTerm && matchesQuick;
      });
    }, renderNamespaceTable = function() {
      if (!namespaceBody) return;
      const rows = applyFilters(namespaceRows);
      namespaceBody.innerHTML = "";
      if (!rows.length) {
        let emptyNote = "No namespaces match current filters.";
        if (!namespaceRows.length) {
          const source = String(driftMeta.namespace_drift_source || "");
          const reason = String(driftMeta.namespace_drift_reason || "");
          if (source === "obs_sample" && reason === "vt_event_empty") {
            emptyNote = "No VT enrichment events yet; fallback source is also empty.";
          } else if (source === "unavailable") {
            emptyNote = "Namespace drift unavailable; check schema guard from Health.";
          } else {
            emptyNote = "No namespace drift data available yet.";
          }
        }
        namespaceBody.innerHTML = `<tr><td colspan="6" class="muted">${esc(emptyNote)}</td></tr>`;
        if (noteEl) noteEl.textContent = emptyNote;
        return;
      }
      if (noteEl) {
        const quickLabel = quickMode === "review" ? "review first" : quickMode;
        const sourceNote = driftMeta.namespace_drift_source === "obs_sample" ? " Source: obs_sample fallback." : "";
        noteEl.textContent = `Showing ${formatCount(rows.length)} of ${formatCount(namespaceRows.length)} namespaces (${quickLabel}).${sourceNote}`;
      }
      const cutoffMs = Date.now() - 7 * 24 * 60 * 60 * 1e3;
      rows.forEach((row) => {
        const classified = classifyNamespace(row.namespace);
        const classification = {
          label: String(row.namespace_class_label || classified.label || "--"),
          className: String(row.namespace_class_name || classified.className || "err")
        };
        const namespaceValue = fmt(row.namespace);
        const triageUrl = triageLink(namespaceValue);
        const newBadge = isNewNamespace(row, cutoffMs) ? ' <span class="badge warn">New</span>' : "";
        const classHelp = String(row.review_hint || classificationHelp(classification.label));
        const tr = document.createElement("tr");
        tr.innerHTML = `
        <td class="mono">${esc(namespaceValue)}${newBadge}</td>
        <td title="Historical seen count">${esc(formatCount(row.seen_count))}</td>
        <td>${esc(formatCount(row.permission_count))}</td>
        <td title="${esc(classHelp)}"><span class="status-dot ${esc(classification.className)}"></span>${esc(classification.label)}</td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : "--")}</td>
        <td><a class="table-link" href="${esc(triageUrl)}">Open Triage</a></td>
      `;
        namespaceBody.appendChild(tr);
      });
    };
    const App = window.App;
    const PI = window.PermissionIntel;
    const endpoint = root.dataset.intelEndpoint || "";
    const defaultLimit = root.dataset.namespaceLimit || "100";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "45") || 45;
    const refreshMs = Math.max(15, refreshSeconds) * 1e3;
    const limitEl = document.getElementById("perm-namespace-limit");
    const searchEl = document.getElementById("perm-namespace-search");
    const namespaceBody = document.getElementById("perm-namespace-body");
    const noteEl = document.getElementById("perm-drift-note");
    const errorEl = document.getElementById("perm-drift-error");
    const liveMetaEl = document.getElementById("perm-drift-live-meta");
    const quickFilterRoot = document.getElementById("perm-drift-quick-filters");
    const quickFilterBtns = Array.from(document.querySelectorAll(".drift-quick-btn"));
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const formatUtc = App.formatUtc;
    const { formatCount, classifyNamespace } = PI;
    const pageUrl = App.pageUrl;
    let namespaceRows = [];
    let driftMeta = {};
    let quickMode = "review";
    async function loadDrift() {
      if (!endpoint || !namespaceBody) return;
      const limit = limitEl?.value || defaultLimit;
      updateUrl(limit);
      if (errorEl) errorEl.textContent = "";
      namespaceBody.innerHTML = '<tr><td colspan="6" class="muted">Loading namespace drift...</td></tr>';
      try {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("mode", "drift");
        url.searchParams.set("namespace_limit", String(limit));
        const res = await App.fetchJson(url.toString());
        if (!res.ok) {
          if (errorEl) {
            errorEl.innerHTML = `<pre>Permission drift error.

HTTP ${res.status}
error: ${esc(res.error)}</pre>`;
          }
          if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
          return;
        }
        const body = toRecord(res.body);
        const data = toRecord(body.data);
        driftMeta = toRecord(body.meta);
        namespaceRows = Array.isArray(data.namespace_drift) ? data.namespace_drift : [];
        renderNamespaceTable();
        if (liveMetaEl) {
          liveMetaEl.textContent = `Live refresh: ${String(driftMeta.generated_at_utc || "ok")}`;
        }
      } catch (error) {
        if (errorEl) {
          errorEl.innerHTML = `<pre>Permission drift error:
${esc(error instanceof Error ? error.message : String(error))}</pre>`;
        }
        if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
      }
    }
    limitEl?.addEventListener("change", () => {
      void loadDrift();
    });
    searchEl?.addEventListener("input", renderNamespaceTable);
    quickFilterRoot?.addEventListener("click", (event) => {
      const btn = event.target?.closest(".drift-quick-btn");
      if (!btn) return;
      quickMode = btn.dataset.mode || "all";
      updateQuickButtons();
      renderNamespaceTable();
    });
    updateQuickButtons();
    void loadDrift();
    window.setInterval(() => {
      void loadDrift();
    }, refreshMs);
  }
})();
