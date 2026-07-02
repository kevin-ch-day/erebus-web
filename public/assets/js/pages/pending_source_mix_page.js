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
  const root = document.getElementById("pending-source-mix-page");
  function fmtInt(value) {
    const num = Number(value ?? 0);
    return Number.isFinite(num) ? num.toLocaleString() : "--";
  }
  if (root && window.App) {
    let classifySources = function(sources) {
      const android = [];
      const generic = [];
      const other = [];
      sources.forEach((row) => {
        const source = String(row.artifact_source || "").toLowerCase();
        if (source.includes("android") || source.includes("zimperium") || source.includes("lamda") || source.includes("beacon")) {
          android.push(row);
          return;
        }
        if (source.startsWith("raw_hash_reservoir")) {
          generic.push(row);
          return;
        }
        other.push(row);
      });
      return { android, generic, other };
    }, setText = function(id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    const App = window.App;
    const endpoint = root.dataset.endpoint || "";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "30") || 30;
    const metaEl = document.getElementById("source-mix-live-meta");
    const noticeEl = document.getElementById("source-mix-engine-notice");
    const pendingTotalEl = document.getElementById("source-mix-pending-total");
    const sourceCountEl = document.getElementById("source-mix-source-count");
    async function load() {
      if (!endpoint) return;
      const res = await App.fetchPayload(endpoint);
      if (!res.ok) {
        if (metaEl) metaEl.textContent = "Live refresh unavailable";
        return;
      }
      const data = res.data && typeof res.data === "object" ? res.data : {};
      const sources = Array.isArray(data.sources) ? data.sources : [];
      const totals = data.totals && typeof data.totals === "object" ? data.totals : {};
      const pipeline = asPipelineSnapshot(data.pipeline);
      const rec = pipeline.recommendation || {};
      const command = pipelinePrimaryCommand(pipeline);
      const tone = pipelineActionTone(rec.action);
      const summary = String(rec.summary || "").trim();
      const pendingFromSources = sources.reduce((sum, row) => sum + Number(row.pending_rows || 0), 0);
      if (pendingTotalEl) pendingTotalEl.textContent = fmtInt(totals.pending_rows ?? pendingFromSources);
      if (sourceCountEl) sourceCountEl.textContent = fmtInt(sources.length);
      if (noticeEl) {
        noticeEl.className = `notice ${tone === "warn" ? "warn" : "info"}`;
        noticeEl.textContent = summary !== "" ? command !== "" ? `${summary} · CLI: ${command}` : summary : "Engine recommendation unavailable.";
      }
      const { android, generic, other } = classifySources(sources);
      setText("source-mix-android-count", String(android.length));
      setText("source-mix-generic-count", String(generic.length));
      setText("source-mix-other-count", String(other.length));
      setText("source-mix-android-pending", fmtInt(android.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));
      setText("source-mix-generic-pending", fmtInt(generic.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));
      setText("source-mix-other-pending", fmtInt(other.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));
      if (metaEl) {
        const meta = res.meta && typeof res.meta === "object" ? res.meta : {};
        metaEl.textContent = `Live refresh: ${String(meta.server_utc_now || "ok")}`;
      }
    }
    void load();
    window.setInterval(() => {
      void load();
    }, Math.max(10, refreshSeconds) * 1e3);
  }
})();
