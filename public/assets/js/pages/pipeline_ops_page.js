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
  function formatQueueLaneSummary(lanes) {
    if (!lanes) return "";
    const parts = [];
    const lamdaPending = Number(lanes.lamda_pending ?? 0);
    const reservoirPending = Number(lanes.reservoir_pending ?? 0);
    const lamdaVtReady = lanes.lamda_vt_ready;
    if (lamdaPending > 0) {
      parts.push(`LAMDA ${lamdaPending.toLocaleString()} pending`);
    }
    if (reservoirPending > 0) {
      parts.push(`reservoir ${reservoirPending.toLocaleString()} pending`);
    }
    if (lamdaVtReady !== null && lamdaVtReady !== void 0 && lamdaVtReady !== "") {
      const ready = Number(lamdaVtReady);
      if (Number.isFinite(ready) && ready > 0) {
        parts.push(`${ready.toLocaleString()} LAMDA VT-ready`);
      }
    }
    const topLane = String(lanes.top_workload_lane || "").trim();
    if (topLane !== "" && parts.length === 0) {
      parts.push(`top lane ${topLane}`);
    }
    return parts.join(" · ");
  }
  function pipelineLaneChips(snapshot) {
    const lanes = snapshot?.queue_lanes;
    const summary = formatQueueLaneSummary(lanes);
    if (!summary) return [];
    return summary.split(" · ").filter(Boolean);
  }
  function asPipelineSnapshot(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("pipeline-ops-page");
  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }
  function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }
  if (root && window.App) {
    let renderLaneChips = function(snapshot) {
      const chips = pipelineLaneChips(snapshot);
      if (!chips.length) {
        setHtml("pipeline-ops-lane-chips", '<span class="landing-chip landing-empty">No lane data</span>');
        return;
      }
      setHtml(
        "pipeline-ops-lane-chips",
        chips.map((chip) => `<span class="landing-chip">${esc(chip)}</span>`).join("")
      );
    }, renderPipeline = function(snapshot, sourceLabel, refreshedAt) {
      const core = snapshot.pipeline || {};
      const vt = snapshot.vt || {};
      const rec = snapshot.recommendation || {};
      const runPlan = snapshot.run_plan || {};
      const queuePending = Number(core.queue_pending ?? 0);
      const queueProcessing = Number(core.queue_processing ?? 0);
      const stateEligible = Number(core.state_eligible_now ?? 0);
      const action = String(rec.action || "--");
      const summary = String(rec.summary || "No recommendation available.");
      const command = pipelinePrimaryCommand(snapshot);
      const tone = pipelineActionTone(rec.action);
      lastCommand = command;
      setText("pipeline-ops-queue-pending", queuePending.toLocaleString());
      setText("pipeline-ops-queue-processing", queueProcessing.toLocaleString());
      setText("pipeline-ops-state-eligible", stateEligible.toLocaleString());
      setText("pipeline-ops-vt-keys", Number(vt.keys_ready ?? 0).toLocaleString());
      setText("pipeline-ops-vt-quota", Number(vt.quota_remaining ?? 0).toLocaleString());
      setText("pipeline-ops-action", action);
      setText("pipeline-ops-summary", summary);
      setText("pipeline-ops-command", command);
      setText("pipeline-ops-run-mode", String(runPlan.mode || "--"));
      setText("pipeline-ops-run-reason", String(runPlan.reason || ""));
      setText("pipeline-ops-source", `source: ${sourceLabel}`);
      setText("pipeline-ops-refreshed", `Last refresh: ${refreshedAt}`);
      const holdActive = Boolean(vt.hold_active);
      setText("pipeline-ops-hold", holdActive ? "Active" : "Clear");
      setText(
        "pipeline-ops-hold-detail",
        holdActive ? `Until ${String(vt.hold_until_utc || "")} · ${String(vt.hold_reason_code || "")}` : "No global hold blocking VT."
      );
      renderLaneChips(snapshot);
      const notice = document.getElementById("pipeline-ops-notice-ssr");
      if (notice) {
        notice.className = `notice ${tone === "warn" ? "warn" : "info"} pipeline-ops-notice`;
        notice.textContent = command !== "" ? `${summary} · CLI: ${command}` : summary;
      }
      const lane = String(runPlan.lane || snapshot.queue_lanes?.top_workload_lane || "").trim();
      if (laneLink && ingestBase) {
        laneLink.href = App.pageUrl("ingest_backlog", { lane });
        laneLink.textContent = lane !== "" ? `Focus lane ${lane}` : "Open Ingest Backlog";
      }
      const lanesSsr = document.getElementById("pipeline-ops-lanes-ssr");
      if (lanesSsr) {
        lanesSsr.textContent = formatQueueLaneSummary(snapshot.queue_lanes);
      }
      if (errorEl) errorEl.style.display = "none";
    };
    const App = window.App;
    const pipelineEndpoint = root.dataset.pipelineEndpoint || "";
    const activityEndpoint = root.dataset.activityEndpoint || "";
    const ingestBase = root.dataset.ingestUrl || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "15") || 15;
    const refreshMs = Math.max(5, refreshSeconds) * 1e3;
    const errorEl = document.getElementById("pipeline-ops-error");
    const copyBtn = document.getElementById("pipeline-ops-copy-cmd");
    const refreshBtn = document.getElementById("pipeline-ops-refresh");
    const laneLink = document.getElementById("pipeline-ops-lane-link");
    let lastCommand = "";
    const esc = App.escapeHtml;
    async function loadActivity() {
      if (!activityEndpoint) return;
      try {
        const res = await App.fetchPayload(activityEndpoint);
        if (!res.ok || !res.data) return;
        const data = res.data;
        const runSummary = data.run_summary && typeof data.run_summary === "object" ? data.run_summary : {};
        const recentRuns = Array.isArray(data.recent_runs) ? data.recent_runs : [];
        setText(
          "pipeline-ops-run-meta",
          `24h: ${Number(runSummary.runs_24h ?? 0).toLocaleString()} runs · ${Number(runSummary.processed_24h ?? 0).toLocaleString()} processed · last stopped: ${String(runSummary.latest_stopped_reason || "--")}`
        );
        const tbody = document.getElementById("pipeline-ops-recent-runs");
        if (!tbody) return;
        if (!recentRuns.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="muted">No recent runs in ledger.</td></tr>';
          return;
        }
        tbody.innerHTML = recentRuns.map((row) => `
        <tr>
          <td>${esc(String(row.run_id ?? ""))}</td>
          <td>${esc(row.finished_at_utc ? App.formatUtc(String(row.finished_at_utc)) : "--")}</td>
          <td>${esc(Number(row.processed_count ?? 0).toLocaleString())}</td>
          <td>${esc(Number(row.ok_count ?? 0).toLocaleString())}</td>
          <td>${esc(Number(row.error_count ?? 0).toLocaleString())}</td>
          <td>${esc(row.stopped_reason ?? "")}</td>
        </tr>
      `).join("");
      } catch {
      }
    }
    async function load() {
      if (!pipelineEndpoint) return;
      try {
        const res = await App.fetchPayload(pipelineEndpoint);
        if (!res.ok) {
          throw new Error(res.error || "Pipeline status request failed");
        }
        const snapshot = asPipelineSnapshot(res.data);
        const meta = res.meta && typeof res.meta === "object" ? res.meta : {};
        const refreshedAt = String(meta.server_utc_now || (/* @__PURE__ */ new Date()).toISOString().replace("T", " ").replace("Z", " UTC"));
        const sourceLabel = String(snapshot.source || "db");
        renderPipeline(snapshot, sourceLabel, refreshedAt);
      } catch (error) {
        if (errorEl) {
          errorEl.style.display = "";
          errorEl.textContent = error instanceof Error ? error.message : String(error);
        }
      }
    }
    if (copyBtn) {
      copyBtn.addEventListener("click", async () => {
        if (!lastCommand) return;
        try {
          await navigator.clipboard.writeText(lastCommand);
          copyBtn.textContent = "Copied";
          window.setTimeout(() => {
            copyBtn.textContent = "Copy CLI command";
          }, 1500);
        } catch {
          copyBtn.textContent = "Copy failed";
        }
      });
    }
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        void load();
      });
    }
    void load();
    void loadActivity();
    window.setInterval(() => {
      void load();
      void loadActivity();
    }, refreshMs);
  }
})();
