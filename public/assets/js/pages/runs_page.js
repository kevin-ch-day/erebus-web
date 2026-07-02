(function() {
  "use strict";
  function pipelinePrimaryCommand(snapshot) {
    if (!snapshot) return "";
    const runPlan = snapshot.run_plan;
    const runCommand = String(runPlan?.command || "").trim();
    if (runCommand !== "") return runCommand;
    return String(snapshot.recommendation?.command || "").trim();
  }
  function pipelineActionTone(action) {
    const normalized = String(action || "").trim().toLowerCase();
    if (normalized === "wait_vt_blocked") return "warn";
    if (normalized === "idle") return "ok";
    if (normalized === "run_state") return "info";
    if (normalized === "run_queue") return "info";
    return "info";
  }
  function asPipelineSnapshot(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("runs-page-root");
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
  if (root && window.App) {
    let buildQuery = function(pageOverride = null) {
      const params = new URLSearchParams();
      const q = searchEl?.value.trim() || "";
      const pageSize = pageSizeEl?.value || "";
      const stoppedReason = stoppedReasonEl?.value.trim() || "";
      const page = pageOverride ?? pageEl?.value ?? "1";
      if (q) params.set("q", q);
      if (stoppedReason) params.set("stopped_reason", stoppedReason);
      if (pageSize) params.set("page_size", pageSize);
      params.set("page", String(page || "1"));
      return params;
    }, updateUrl = function(params) {
      const url = new URL(window.location.href);
      url.search = params.toString();
      window.history.replaceState({}, "", url.toString());
    }, renderRows = function(rows) {
      if (!bodyEl) return;
      if (!rows.length) {
        bodyEl.innerHTML = '<tr><td colspan="11" class="muted">No runs found.</td></tr>';
        return;
      }
      bodyEl.innerHTML = rows.map((row) => {
        const keyLabel = row.key_id ? String(row.key_id) : "--";
        return `
        <tr>
          <td>${esc(row.run_id)}</td>
          <td>${esc(row.started_at_utc ? formatUtc(row.started_at_utc) : "--")}</td>
          <td>${esc(row.finished_at_utc ? formatUtc(row.finished_at_utc) : "--")}</td>
          <td>${esc(fmt(row.db_name))}<div class="muted">Key: ${esc(keyLabel)}</div></td>
          <td>${esc(fmt(row.processed_count))}</td>
          <td>${esc(fmt(row.ok_count))}</td>
          <td>${esc(fmt(row.no_data_count))}</td>
          <td>${esc(fmt(row.retry_wait_count))}</td>
          <td>${esc(fmt(row.error_count))}</td>
          <td class="mono">${esc(fmt(row.perm_taxonomy_version))}</td>
          <td>${esc(fmt(row.stopped_reason))}</td>
        </tr>
      `;
      }).join("");
    }, renderMeta = function(payload) {
      const total = Number(payload.total_count ?? 0);
      const page = Number(payload.page ?? 1);
      const pageSize = Number(payload.page_size ?? 0);
      const pages = Number(payload.total_pages ?? 1);
      if (metaEl) metaEl.textContent = `Total: ${total} | Page size: ${pageSize}`;
      if (pagesEl) pagesEl.textContent = String(pages);
      if (pageEl) pageEl.value = String(page);
      if (prevBtn) prevBtn.disabled = page <= 1;
      if (nextBtn) nextBtn.disabled = page >= pages;
    }, renderPlatformContext = function(context) {
      if (!platformPrimaryEl || !platformPiEl || !platformHeadsEl || !platformTaxonomyEl || !platformSummaryEl || !platformListEl) {
        return;
      }
      platformPrimaryEl.textContent = fmt(context?.primary_catalog);
      platformPiEl.textContent = fmt(context?.permission_intel_catalog);
      const primaryHead = context?.primary_schema_head ? String(context.primary_schema_head) : "--";
      const piHead = context?.permission_intel_schema_head ? String(context.permission_intel_schema_head) : "--";
      platformHeadsEl.textContent = `${primaryHead} / ${piHead}`;
      const taxVersion = context?.latest_perm_taxonomy_version ? String(context.latest_perm_taxonomy_version) : "--";
      const taxTime = context?.latest_perm_taxonomy_finished_at_utc ? formatUtc(context.latest_perm_taxonomy_finished_at_utc) : "--";
      platformTaxonomyEl.textContent = `${taxVersion} @ ${taxTime}`;
      const notices = [];
      if (context?.mixed_visible_run_db_names) {
        notices.push(`Visible rows span multiple db_name values: ${asRows(context.visible_run_db_names).join(", ")}`);
      }
      if (context?.mixed_visible_run_schema_versions) {
        notices.push(`Visible rows span multiple schema_version values: ${asRows(context.visible_run_schema_versions).join(", ")}`);
      }
      if (context?.mixed_visible_run_perm_taxonomy_versions) {
        notices.push(`Visible rows span multiple perm_taxonomy_version values: ${asRows(context.visible_run_perm_taxonomy_versions).join(", ")}`);
      }
      if (context?.split_enabled && context.schema_heads_match === false) {
        notices.push(`Primary and PI schema heads differ: ${primaryHead} vs ${piHead}`);
      }
      if (!notices.length) {
        platformSummaryEl.textContent = "Visible run rows align with one platform context.";
        platformListEl.innerHTML = "";
        return;
      }
      platformSummaryEl.textContent = "Visible run rows span more than one platform state. Compare schema and taxonomy before drawing conclusions.";
      platformListEl.innerHTML = notices.map((notice) => `<li>${esc(notice)}</li>`).join("");
    }, renderActivity = function(data) {
      const pipeline = asPipelineSnapshot(data.pipeline);
      const rec = pipeline.recommendation || {};
      const command = pipelinePrimaryCommand(pipeline);
      const tone = pipelineActionTone(rec.action);
      const summary = String(rec.summary || "").trim();
      const runSummary = data.run_summary && typeof data.run_summary === "object" ? data.run_summary : {};
      const recentRuns = asRows(data.recent_runs);
      if (activitySummaryEl) {
        const hint = summary !== "" ? command !== "" ? `${summary} · CLI: ${command}` : summary : "No engine recommendation.";
        activitySummaryEl.className = `notice ${tone === "warn" ? "warn" : "info"}`;
        activitySummaryEl.textContent = hint;
      }
      if (activityMetaEl) {
        activityMetaEl.textContent = [
          `Last run #${fmt(runSummary.latest_run_id)}`,
          `${fmt(runSummary.latest_processed_count)} processed`,
          `${fmt(runSummary.latest_stopped_reason)}`,
          `24h: ${fmt(runSummary.runs_24h)} runs · ${fmt(runSummary.processed_24h)} processed`
        ].join(" · ");
      }
      if (!activityRunsEl) return;
      if (!recentRuns.length) {
        activityRunsEl.innerHTML = '<tr><td colspan="5" class="muted">No recent runs in ledger.</td></tr>';
        return;
      }
      activityRunsEl.innerHTML = recentRuns.map((row) => `
      <tr>
        <td>${esc(row.run_id)}</td>
        <td>${esc(row.finished_at_utc ? formatUtc(row.finished_at_utc) : "--")}</td>
        <td>${esc(fmt(row.processed_count))}</td>
        <td>${esc(fmt(row.ok_count))}</td>
        <td>${esc(fmt(row.stopped_reason))}</td>
      </tr>
    `).join("");
    }, scheduleReload = function() {
      if (pendingTimer) clearTimeout(pendingTimer);
      pendingTimer = setTimeout(() => {
        void loadRuns(1);
      }, 200);
    };
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const activityEndpoint = root.dataset.activityEndpoint || "";
    root.dataset.pipelineOpsUrl || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "30") || 30;
    const searchEl = document.getElementById("runs-search");
    const pageSizeEl = document.getElementById("runs-page-size");
    const stoppedReasonEl = document.getElementById("runs-stopped-reason");
    const pageEl = document.getElementById("runs-page");
    const bodyEl = document.getElementById("runs-body");
    const metaEl = document.getElementById("runs-meta");
    const pagesEl = document.getElementById("runs-pages");
    const prevBtn = document.getElementById("runs-prev");
    const nextBtn = document.getElementById("runs-next");
    const errorEl = document.getElementById("runs-error");
    const platformPrimaryEl = document.getElementById("runs-platform-primary");
    const platformPiEl = document.getElementById("runs-platform-pi");
    const platformHeadsEl = document.getElementById("runs-platform-heads");
    const platformTaxonomyEl = document.getElementById("runs-platform-taxonomy");
    const platformSummaryEl = document.getElementById("runs-platform-summary");
    const platformListEl = document.getElementById("runs-platform-list");
    const activitySummaryEl = document.getElementById("runs-activity-summary");
    const activityRunsEl = document.getElementById("runs-activity-recent");
    const activityMetaEl = document.getElementById("runs-activity-meta");
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const formatUtc = App.formatUtc;
    let pendingTimer = null;
    let lastPayload = null;
    async function loadActivity() {
      if (!activityEndpoint) return;
      const res = await App.fetchPayload(activityEndpoint);
      if (res.ok && res.data) {
        renderActivity(res.data);
      }
    }
    async function loadRuns(pageOverride = null) {
      if (!endpoint || !bodyEl) return;
      const params = buildQuery(pageOverride);
      updateUrl(params);
      const url = `${endpoint}?${params.toString()}`;
      if (pendingTimer) {
        clearTimeout(pendingTimer);
        pendingTimer = null;
      }
      if (errorEl) errorEl.textContent = "";
      bodyEl.innerHTML = '<tr><td colspan="11" class="muted">Loading runs...</td></tr>';
      try {
        const res = await App.fetchJson(url);
        if (!res.ok) {
          if (errorEl) {
            const raw = res.raw ? String(res.raw).slice(0, 2e3) : "";
            errorEl.innerHTML = `<pre>Run ledger API error.

HTTP ${res.status}
error: ${esc(res.error)}${raw ? `

${esc(raw)}` : ""}</pre>`;
          }
          return;
        }
        lastPayload = res.body && typeof res.body === "object" ? res.body : {};
        renderRows(asRows(lastPayload.rows));
        renderMeta(lastPayload);
        renderPlatformContext(lastPayload.platform_context || null);
      } catch (error) {
        if (errorEl) {
          errorEl.innerHTML = `<pre>Run ledger API error:
${esc(error instanceof Error ? error.message : String(error))}</pre>`;
        }
      }
    }
    [searchEl, pageSizeEl, stoppedReasonEl].forEach((el) => {
      if (!el) return;
      el.addEventListener("input", scheduleReload);
      el.addEventListener("change", scheduleReload);
    });
    pageEl?.addEventListener("change", () => {
      void loadRuns(Number(pageEl.value || 1));
    });
    prevBtn?.addEventListener("click", () => {
      void loadRuns(Math.max(1, Number(pageEl?.value || 1) - 1));
    });
    nextBtn?.addEventListener("click", () => {
      const pages = lastPayload ? Number(lastPayload.total_pages || 1) : 1;
      void loadRuns(Math.min(pages, Number(pageEl?.value || 1) + 1));
    });
    void loadRuns();
    void loadActivity();
    window.setInterval(() => {
      void loadActivity();
    }, Math.max(10, refreshSeconds) * 1e3);
  }
})();
