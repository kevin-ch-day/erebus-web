(function() {
  "use strict";
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
  function debounce(fn, waitMs) {
    let timer = null;
    return (...args) => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => fn(...args), waitMs);
    };
  }
  function formatUtcFixed(app, value, includeSeconds = false) {
    return app.formatUtc(value, { timeZone: "UTC", includeSeconds });
  }
  function renderUnknown(value, labelMap) {
    if (value === null || value === void 0 || value === "") return "--";
    const key = String(value).toLowerCase();
    if (key.startsWith("unknown:")) return String(value);
    return `unknown:${value}`;
  }
  function setTableMessage(app, bodyEl, colSpan, message, className = "muted") {
    if (!bodyEl) return;
    bodyEl.innerHTML = `<tr><td colspan="${colSpan}" class="${className}">${app.escapeHtml(message)}</td></tr>`;
  }
  function createCursorPager() {
    let cursorStack = [];
    let currentCursor = "";
    let hasMore = false;
    let nextCursor = "";
    return {
      reset() {
        cursorStack = [];
        currentCursor = "";
        hasMore = false;
        nextCursor = "";
      },
      push() {
        cursorStack.push(currentCursor);
      },
      pop() {
        currentCursor = cursorStack.pop() || "";
        return currentCursor;
      },
      setCurrent(value) {
        currentCursor = value || "";
      },
      setNext(value) {
        nextCursor = value || "";
      },
      setHasMore(value) {
        hasMore = value === true || value === 1;
      },
      updateFromPayload(payload) {
        hasMore = payload.has_more === true || payload.has_more === 1;
        nextCursor = String(payload.next_cursor ?? payload.nextCursor ?? "");
      },
      getCurrent() {
        return currentCursor;
      },
      getNext() {
        return nextCursor;
      },
      getHasMore() {
        return hasMore;
      },
      getPageIndex() {
        return cursorStack.length + 1;
      },
      getStackSize() {
        return cursorStack.length;
      }
    };
  }
  function bindCopyTargets(app, root2) {
    if (!root2 || !app.copyText) return;
    const host = root2;
    if (host.dataset.copyBound === "1") return;
    host.dataset.copyBound = "1";
    host.addEventListener("click", (event) => {
      const target = event.target?.closest("[data-copy]");
      if (!target) return;
      const value = target.getAttribute("data-copy");
      if (!value) return;
      app.copyText(value);
      target.classList.add("copyable-active");
      window.setTimeout(() => target.classList.remove("copyable-active"), 700);
    });
  }
  function shouldQueueApiFallback(res) {
    if (res.ok) return false;
    if (res.code === "ERR_SCHEMA_MISSING") return false;
    if (res.status === 0 || res.status === 404 || res.status >= 500) return true;
    if (res.error === "Non-JSON response") return true;
    return false;
  }
  const root = document.getElementById("permission-queue-page");
  const STATUS_LABELS = {
    queued: { label: "Queued", className: "badge warn" },
    claimed: { label: "Claimed", className: "badge warn" },
    applied: { label: "Applied", className: "badge ok" },
    error: { label: "Error", className: "badge err" },
    rejected: { label: "Rejected", className: "badge muted" },
    skipped: { label: "Skipped", className: "badge muted" }
  };
  const ACTION_LABELS = {
    aosp: "AOSP",
    oem: "OEM",
    google: "Google",
    reject: "Reject / no action",
    defer: "Defer",
    skip: "Skip",
    app_defined: "App Defined",
    apply: "Apply"
  };
  const POPULATION_LABELS = {
    imported_static_candidate_no_anchor: { label: "Imported static candidate", className: "badge warn" },
    already_resolved_aosp_duplicate: { label: "Superseded duplicate", className: "badge muted" },
    malformed_ledger_conflict: { label: "Malformed conflict", className: "badge err" },
    evidence_backed_queue_work: { label: "Evidence-backed queue work", className: "badge ok" },
    web_triage_queue: { label: "Web triage queue", className: "badge ok" },
    other_queue_state: { label: "Other queue state", className: "badge muted" }
  };
  const CONFLICT_LABELS = {
    missing_ledger_anchor: { label: "No ledger anchor", className: "badge warn" },
    already_resolved_duplicate: { label: "Already resolved", className: "badge muted" },
    malformed_ledger: { label: "Malformed ledger", className: "badge err" },
    none: null
  };
  if (root && window.App) {
    let setUnavailable = function(message) {
      if (!unavailableEl) return;
      unavailableEl.textContent = message;
      unavailableEl.style.display = "block";
    }, clearUnavailable = function() {
      if (!unavailableEl) return;
      unavailableEl.textContent = "";
      unavailableEl.style.display = "none";
    }, setCompact = function(enabled) {
      pageRoot?.classList.toggle("compact", enabled);
      if (compactBtn) compactBtn.textContent = enabled ? "Expanded view" : "Compact view";
      try {
        localStorage.setItem(compactKey, enabled ? "1" : "0");
      } catch {
      }
    }, loadCompact = function() {
      let enabled = false;
      try {
        enabled = localStorage.getItem(compactKey) === "1";
      } catch {
        enabled = false;
      }
      setCompact(enabled);
    }, setBannerApiRunning = function() {
      if (!bannerEl) return;
      bannerEl.className = "notice info";
      bannerEl.textContent = "API server: RUNNING";
    }, setBannerFallback = function() {
      if (!bannerEl) return;
      bannerEl.className = "notice warn";
      bannerEl.textContent = offlineBanner;
    }, formatActionCell = function(row) {
      const normalized = String(row.normalized_action ?? row.queue_action ?? row.queue_action_normalized ?? row.queue_action_raw ?? "");
      if (!normalized) return "--";
      const key = normalized.toLowerCase();
      const label = ACTION_LABELS[key] ?? renderUnknown(normalized);
      let extra = "";
      if (row.queue_action_raw && row.queue_action && String(row.queue_action_raw).toLowerCase() !== String(row.queue_action).toLowerCase()) {
        extra = `<div class="muted">raw: ${esc(row.queue_action_raw)}</div>`;
      }
      return `<div>${esc(label)}</div>${extra}`;
    }, formatStatusCell = function(row) {
      const normalized = String(row.status ?? row.queue_status ?? row.status_normalized ?? row.status_raw ?? "");
      if (!normalized) return "--";
      const key = normalized.toLowerCase();
      const known = STATUS_LABELS[key];
      let detail = "";
      if (row.status_raw && row.status && String(row.status_raw).toLowerCase() !== String(row.status).toLowerCase()) {
        detail = `<div class="muted">raw: ${esc(row.status_raw)}</div>`;
      }
      if (known) return `<span class="${known.className}">${esc(known.label)}</span>${detail}`;
      return `<span class="badge muted">${esc(renderUnknown(normalized))}</span>${detail}`;
    }, renderSummary = function(counts) {
      if (!summaryEl || !summaryNoteEl) return;
      summaryEl.innerHTML = "";
      if (!counts || !Object.keys(counts).length) {
        summaryNoteEl.style.display = "block";
        return;
      }
      summaryNoteEl.style.display = "none";
      Object.entries(counts).forEach(([key, value]) => {
        const label = STATUS_LABELS[key]?.label ?? renderUnknown(key);
        const row = document.createElement("div");
        row.className = "detail-row";
        row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, "0"))}</div>
      `;
        summaryEl.appendChild(row);
      });
    }, renderPopulationSummary = function(counts) {
      if (!populationSummaryEl || !counts) return;
      populationSummaryEl.innerHTML = "";
      const entries = Object.entries(counts).filter(([, value]) => Number(value || 0) > 0).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0));
      entries.forEach(([key, value]) => {
        const known = POPULATION_LABELS[key];
        const label = known?.label ?? renderUnknown(key);
        const row = document.createElement("div");
        row.className = "detail-row";
        row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, "0"))}</div>
      `;
        populationSummaryEl.appendChild(row);
      });
    }, renderBadge = function(label, className = "badge muted") {
      return `<span class="${className}">${esc(label)}</span>`;
    }, renderPopulationCell = function(row) {
      const key = String(row.queue_population_label || "other_queue_state");
      const known = POPULATION_LABELS[key];
      const label = known?.label ?? renderUnknown(key);
      const badge = renderBadge(label, known?.className ?? "badge muted");
      const source = row.source_system ? `<div class="muted">source: ${esc(row.source_system)}</div>` : "";
      return `${badge}${source}`;
    }, renderSignalsCell = function(row) {
      const badges = [];
      const source = row.source_system ? String(row.source_system) : "unknown";
      badges.push(renderBadge(source, source === "web" ? "badge ok" : source === "static-analysis" ? "badge warn" : "badge muted"));
      if (row.has_obs_sample && row.has_vt_event) badges.push(renderBadge("obs + vt", "badge ok"));
      else if (row.has_obs_sample) badges.push(renderBadge("obs", "badge ok"));
      else if (row.has_vt_event) badges.push(renderBadge("vt", "badge ok"));
      else badges.push(renderBadge("no evidence", "badge muted"));
      badges.push(renderBadge(row.has_ledger_anchor ? "anchored" : "no ledger", row.has_ledger_anchor ? "badge ok" : "badge warn"));
      badges.push(renderBadge(row.already_in_aosp ? "in AOSP" : "not in AOSP", row.already_in_aosp ? "badge muted" : "badge warn"));
      const conflict = CONFLICT_LABELS[String(row.conflict_label || "none")];
      if (conflict) badges.push(renderBadge(conflict.label, conflict.className));
      return badges.join(" ");
    }, renderTriageCell = function(row) {
      const queueTriage = String(row.queue_triage_status_display ?? row.triage_status_display ?? row.queue_triage_status ?? row.triage_status ?? "--");
      const ledgerTriage = String(row.dict_unknown_triage_status_display ?? row.dict_unknown_triage_status ?? "");
      if (!ledgerTriage || ledgerTriage === "-" || ledgerTriage === queueTriage) return esc(queueTriage);
      return `
      <div>${esc(queueTriage)}</div>
      <div class="muted">ledger: ${esc(ledgerTriage)}</div>
    `;
    }, renderTimelineCell = function(row) {
      const queuedAt = row.queued_at_utc ? formatUtcFixed(App, row.queued_at_utc) : "--";
      const processedAt = row.processed_at_utc ? formatUtcFixed(App, row.processed_at_utc) : "--";
      const updatedAt = row.updated_at_utc ? formatUtcFixed(App, row.updated_at_utc) : "--";
      return `
      <div>Queued: ${esc(queuedAt)}</div>
      <div class="muted">Processed: ${esc(processedAt)}</div>
      <div class="muted">Updated: ${esc(updatedAt)}</div>
    `;
    }, renderSummaryNote = function(payload) {
      if (!summaryNoteEl) return;
      const countsByAction = toRecord(payload.counts_by_action_active ?? payload.counts_by_action);
      const countsByStatusActive = toRecord(payload.counts_by_status_active);
      const legacyQueueActions = asRows(payload.legacy_queue_actions_active);
      const parts = [];
      if (Object.keys(countsByAction).length) {
        const actionSummary = Object.entries(countsByAction).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).map(([key, value]) => `${ACTION_LABELS[String(key).toLowerCase()] ?? renderUnknown(key)} ${fmt(value, "0")}`);
        if (actionSummary.length) parts.push(`Active action mix: ${actionSummary.join(" | ")}`);
      }
      if (Object.keys(countsByStatusActive).length) {
        const activeStatusSummary = Object.entries(countsByStatusActive).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).map(([key, value]) => `${STATUS_LABELS[String(key).toLowerCase()]?.label ?? renderUnknown(key)} ${fmt(value, "0")}`);
        if (activeStatusSummary.length) parts.push(`Active queue state: ${activeStatusSummary.join(" | ")}`);
      }
      const countsByPopulation = toRecord(payload.counts_by_population);
      if (Object.keys(countsByPopulation).length) {
        const populationSummary = Object.entries(countsByPopulation).filter(([, value]) => Number(value || 0) > 0).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).map(([key, value]) => `${POPULATION_LABELS[key]?.label ?? renderUnknown(key)} ${fmt(value, "0")}`);
        if (populationSummary.length) parts.push(`Populations: ${populationSummary.join(" | ")}`);
      }
      if (legacyQueueActions.length) {
        const legacySummary = legacyQueueActions.map((item) => `${item.raw} -> ${item.normalized} (${fmt(item.count, "0")})`).join(" | ");
        parts.push(`Legacy raw actions still active: ${legacySummary}`);
      }
      summaryNoteEl.style.display = "block";
      summaryNoteEl.textContent = parts.length ? parts.join(" ") : "Counts unavailable (backend not providing summary).";
    }, renderRows = function(rows) {
      if (!bodyEl) return;
      bodyEl.innerHTML = "";
      if (!rows.length) {
        setTableMessage(App, bodyEl, 10, "No queue entries found.");
        return;
      }
      rows.forEach((row) => {
        const permission = fmt(row.permission_string);
        const tr = document.createElement("tr");
        tr.innerHTML = `
        <td class="cell-wrap"><span class="copyable mono" data-copy="${esc(permission)}">${esc(permission)}</span></td>
        <td class="cell-wrap">${renderPopulationCell(row)}</td>
        <td class="cell-wrap">${renderSignalsCell(row)}</td>
        <td>${formatActionCell(row)}</td>
        <td>${formatStatusCell(row)}</td>
        <td class="cell-wrap">${renderTriageCell(row)}</td>
        <td>${esc(fmt(row.proposed_classification))}</td>
        <td>${esc(fmt(row.proposed_bucket))}</td>
        <td class="cell-wrap">${renderTimelineCell(row)}</td>
        <td class="cell-wrap">${esc(fmt(row.error_message))}</td>
      `;
        bodyEl.appendChild(tr);
      });
      bindCopyTargets(App, bodyEl);
    }, renderMeta = function(payload, meta) {
      if (!metaEl) return;
      const rows = asRows(payload.items ?? payload.rows);
      const generated = payload.generated_at_utc ? formatUtcFixed(App, payload.generated_at_utc) : meta.generated_at_utc ? formatUtcFixed(App, meta.generated_at_utc) : "--";
      const total = payload.total_count !== void 0 ? `Total: ${payload.total_count} | ` : "";
      metaEl.textContent = `${total}Showing: ${rows.length} | Last refresh: ${generated}`;
    }, renderPaging = function() {
      if (pageIndexEl) pageIndexEl.textContent = String(pager.getPageIndex());
      if (prevBtn) prevBtn.disabled = pager.getStackSize() === 0;
      if (nextBtn) nextBtn.disabled = !pager.getHasMore() || !pager.getNext();
    }, buildQuery = function() {
      const params = new URLSearchParams();
      params.set("include_population_counts", "0");
      if (searchEl?.value.trim()) params.set("search", searchEl.value.trim());
      if (statusEl?.value) params.set("status", statusEl.value);
      if (actionEl?.value) params.set("queue_action", actionEl.value);
      params.set("limit", limitEl?.value || String(defaultLimit));
      if (pager.getCurrent()) params.set("cursor", pager.getCurrent());
      return params;
    }, updateUrl = function(params) {
      const url = new URL(window.location.href);
      const keep = new URLSearchParams();
      ["search", "status", "queue_action", "limit"].forEach((key) => {
        if (params.has(key)) keep.set(key, params.get(key) || "");
      });
      url.search = keep.toString();
      window.history.replaceState({}, "", url.toString());
    };
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const defaultLimit = Number(root.dataset.defaultLimit || 50);
    const searchEl = document.getElementById("permission-queue-search");
    const statusEl = document.getElementById("permission-queue-status");
    const actionEl = document.getElementById("permission-queue-action");
    const limitEl = document.getElementById("permission-queue-limit");
    const refreshBtn = document.getElementById("permission-queue-refresh");
    const compactBtn = document.getElementById("permission-queue-compact-toggle");
    const pageIndexEl = document.getElementById("permission-queue-page-index");
    const summaryEl = document.getElementById("permission-queue-summary");
    const populationSummaryEl = document.getElementById("permission-queue-population-summary");
    const summaryNoteEl = document.getElementById("permission-queue-summary-note");
    const unavailableEl = document.getElementById("permission-queue-unavailable");
    const bodyEl = document.getElementById("permission-queue-body");
    const metaEl = document.getElementById("permission-queue-meta");
    const errorEl = document.getElementById("permission-queue-error");
    const bannerEl = document.getElementById("permission-queue-api-banner");
    const pageRoot = document.getElementById("permission-queue-root");
    const prevBtn = document.getElementById("permission-queue-prev");
    const nextBtn = document.getElementById("permission-queue-next");
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const pager = createCursorPager();
    const offlineBanner = "Enhanced API unavailable - showing DB-backed read-only state.";
    const compactKey = "perm_queue_compact";
    const scheduleReload = debounce(() => {
      pager.reset();
      renderPaging();
      void loadQueue();
    }, 200);
    async function loadQueue() {
      if (!endpoint) return;
      if (errorEl) errorEl.textContent = "";
      setTableMessage(App, bodyEl, 10, "Loading queue...");
      clearUnavailable();
      const params = buildQuery();
      updateUrl(params);
      const url = `${endpoint}?${params.toString()}`;
      try {
        const res = await App.fetchPayload(url);
        if (!res.ok) {
          if (res.code === "ERR_SCHEMA_MISSING" || res.status === 404) {
            setUnavailable("Backend capability not enabled for the permission queue yet.");
            setBannerApiRunning();
            setTableMessage(App, bodyEl, 10, "Not available.");
            renderSummary(null);
            renderPopulationSummary(null);
            return;
          }
          if (shouldQueueApiFallback(res)) setBannerFallback();
          else setBannerApiRunning();
          const detail = res.raw ? `

${String(res.raw).slice(0, 2e3)}` : "";
          if (errorEl) {
            errorEl.innerHTML = `<pre>Permission queue API error.

HTTP ${res.status}
error: ${esc(res.error || "Request failed")}${detail}</pre>`;
          }
          return;
        }
        setBannerApiRunning();
        const payload = toRecord(res.data);
        pager.updateFromPayload(payload);
        renderRows(asRows(payload.items ?? payload.rows));
        renderSummary(toRecord(payload.counts_by_status ?? payload.countsByStatus));
        renderPopulationSummary(toRecord(payload.counts_by_population));
        renderSummaryNote(payload);
        renderMeta(payload, toRecord(res.meta));
        renderPaging();
      } catch (error) {
        if (errorEl) {
          errorEl.innerHTML = `<pre>Permission queue API error:
${esc(error instanceof Error ? error.message : String(error))}</pre>`;
        }
      }
    }
    if (limitEl && !limitEl.value) limitEl.value = String(defaultLimit);
    [searchEl, statusEl, actionEl, limitEl].forEach((el) => {
      if (!el) return;
      el.addEventListener("input", scheduleReload);
      el.addEventListener("change", scheduleReload);
    });
    refreshBtn?.addEventListener("click", () => {
      void loadQueue();
    });
    nextBtn?.addEventListener("click", () => {
      if (!pager.getHasMore() || !pager.getNext()) return;
      pager.push();
      pager.setCurrent(pager.getNext());
      void loadQueue();
    });
    prevBtn?.addEventListener("click", () => {
      if (pager.getStackSize() === 0) return;
      pager.pop();
      void loadQueue();
    });
    compactBtn?.addEventListener("click", () => {
      setCompact(!(pageRoot?.classList.contains("compact") ?? false));
    });
    pager.reset();
    renderPaging();
    loadCompact();
    void loadQueue();
  }
})();
