(function() {
  "use strict";
  function fmtInt(value) {
    const num = Number(value ?? 0);
    return Number.isFinite(num) ? num.toLocaleString() : "--";
  }
  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }
  async function copyTextWithFeedback(button, text) {
    const command = text.trim();
    if (!command) return;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(command);
      } else {
        throw new Error("clipboard unavailable");
      }
      const original = button.textContent;
      button.textContent = "Copied";
      window.setTimeout(() => {
        button.textContent = original;
      }, 1500);
    } catch {
      window.prompt("Copy CLI command:", command);
    }
  }
  function bindPipelineEngineCopyButtons(root2 = document) {
    root2.querySelectorAll(".pipeline-engine-copy[data-copy-command]").forEach((button) => {
      if (button.dataset.copyBound === "1") return;
      button.dataset.copyBound = "1";
      button.addEventListener("click", () => {
        void copyTextWithFeedback(button, button.getAttribute("data-copy-command") || "");
      });
    });
  }
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
  function asPipelineSnapshot(value) {
    return value && typeof value === "object" ? value : {};
  }
  function refreshPipelineEnginePanel(_app, pipelinePayload, options, meta) {
    const { idPrefix, recommendedLaneKey = "recommended_lane" } = options;
    const pipeline = asPipelineSnapshot(pipelinePayload);
    const rec = pipeline.recommendation || {};
    const command = pipelinePrimaryCommand(pipeline);
    const tone = pipelineActionTone(rec.action);
    const summary = String(rec.summary || "").trim();
    setText(`${idPrefix}-queue-pending`, fmtInt(pipeline.pipeline?.queue_pending));
    setText(`${idPrefix}-state-eligible`, fmtInt(pipeline.pipeline?.state_eligible_now));
    setText(`${idPrefix}-lane-summary`, formatQueueLaneSummary(pipeline.queue_lanes) || "No lane breakdown");
    setText(`${idPrefix}-keys-ready`, fmtInt(pipeline.vt?.keys_ready));
    setText(`${idPrefix}-run-command`, command || "--");
    setText(`${idPrefix}-source`, `source: ${String(pipeline.source || "db")}`);
    const recommendedLane = String(
      (pipelinePayload && typeof pipelinePayload === "object" ? pipelinePayload[recommendedLaneKey] : null) || pipeline.run_plan?.lane || ""
    ).trim();
    setText(`${idPrefix}-recommended-lane`, recommendedLane || "--");
    const notice = document.getElementById(`${idPrefix}-notice`);
    if (notice) {
      notice.className = `notice ${tone === "warn" ? "warn" : "info"}`;
      notice.textContent = summary !== "" ? command !== "" ? `${summary} · CLI: ${command}` : summary : "Engine recommendation unavailable.";
    }
    document.querySelectorAll(`.pipeline-engine-copy[data-panel-prefix="${idPrefix}"]`).forEach((button) => {
      if (command) button.setAttribute("data-copy-command", command);
    });
    bindPipelineEngineCopyButtons(document.getElementById(`${idPrefix}-panel`) || document);
  }
  const root = document.getElementById("ingest-backlog-page");
  if (root && window.App) {
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "20") || 20;
    const refreshMs = Math.max(10, refreshSeconds) * 1e3;
    const metaEl = document.getElementById("ingest-backlog-live-meta");
    async function load() {
      if (!endpoint) return;
      const res = await App.fetchPayload(endpoint);
      if (!res.ok) {
        if (metaEl) metaEl.textContent = "Live refresh unavailable";
        return;
      }
      const data = res.data && typeof res.data === "object" ? res.data : {};
      const totals = data.totals && typeof data.totals === "object" ? data.totals : {};
      setText("ingest-tile-pending", fmtInt(totals.pending_rows));
      setText("ingest-tile-processing", fmtInt(totals.processing_rows));
      setText("ingest-tile-failed", fmtInt(totals.failed_rows));
      setText("ingest-tile-queue-rows", fmtInt(totals.queue_rows));
      setText("ingest-tile-lanes", fmtInt(totals.lane_count));
      refreshPipelineEnginePanel(App, data.pipeline, {
        idPrefix: "ingest-engine",
        recommendedLaneKey: "recommended_lane"
      }, res.meta);
      const recommendedLane = String(data.recommended_lane || "").trim();
      setText("ingest-engine-recommended-lane", recommendedLane || "--");
      if (metaEl) {
        metaEl.textContent = `Live refresh: ${String(res.meta?.server_utc_now || "ok")}`;
      }
    }
    void load();
    window.setInterval(() => {
      void load();
    }, refreshMs);
  }
})();
