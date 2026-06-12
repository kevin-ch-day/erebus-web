(function() {
  "use strict";
  const App = window.App;
  const SampleDetail = window.SampleDetail;
  if (App && SampleDetail) {
    const app = App;
    const esc = app.escapeHtml;
    const fmt = app.fmt;
    app.formatUtc;
    SampleDetail.createPermissionsController = (elements, endpoints) => {
      const {
        permSummaryList,
        permSummaryEmpty,
        permDetailBody,
        permErrorEl,
        permNonAndroid,
        permTilesEl,
        permFilterBucket,
        permFilterKnown,
        permUnknownList,
        permStatusNote,
        permPipeline,
        permExportBtn
      } = elements;
      const { permSummaryEndpoint, permDetailEndpoint } = endpoints;
      let permissionRows = [];
      let summaryRows = [];
      let exportSampleId = "";
      function bucketLabel(value) {
        const raw = fmt(value, "").trim();
        return raw === "" ? "Unclassified yet" : raw;
      }
      function ruleLabel(value) {
        const raw = fmt(value, "").trim();
        return raw === "" ? "n/a" : raw;
      }
      function bucketDisplay(row) {
        const bucket = bucketLabel(row.bucket);
        const classification = String(row.classification || "").toUpperCase();
        const isUnknownBucket = bucket.toUpperCase().includes("UNKNOWN");
        if (classification === "OEM" && isUnknownBucket) {
          return {
            label: "OEM (Unreviewed)",
            title: "OEM permissions are intentionally treated as UNKNOWN until reviewed."
          };
        }
        return { label: bucket, title: "" };
      }
      function isUnknown(row) {
        const bucketRaw = fmt(row.bucket, "").trim();
        if (bucketRaw === "") return true;
        return bucketRaw.toUpperCase() === "UNKNOWN";
      }
      function knownBadge(row) {
        if (isUnknown(row)) {
          const classification = String(row.classification || "").toUpperCase();
          if (classification === "OEM") {
            return '<span class="badge warn" title="OEM permissions remain unknown until reviewed.">OEM (Unreviewed)</span>';
          }
          return '<span class="badge warn">Unknown</span>';
        }
        return '<span class="badge ok">Known</span>';
      }
      function setPipelineStage(stage, state) {
        if (!permPipeline) return;
        const el = permPipeline.querySelector(`[data-stage="${stage}"]`);
        if (!(el instanceof HTMLElement)) return;
        el.classList.remove("stage-done", "stage-warn", "stage-pending");
        el.classList.add(state);
      }
      function updatePipelineStages() {
        const hasPerms = permissionRows.length > 0;
        const hasKnown = permissionRows.some((row) => !isUnknown(row));
        const hasSummary = summaryRows.length > 0;
        setPipelineStage("extract", hasPerms ? "stage-done" : "stage-pending");
        if (!hasPerms) {
          setPipelineStage("classify", "stage-pending");
        } else if (hasKnown) {
          setPipelineStage("classify", "stage-done");
        } else {
          setPipelineStage("classify", "stage-warn");
        }
        setPipelineStage("persist", hasPerms ? "stage-done" : "stage-pending");
        setPipelineStage("summarize", hasSummary ? "stage-done" : "stage-pending");
      }
      const renderers = SampleDetail.createPermissionsRenderers({
        permSummaryList,
        permSummaryEmpty,
        permDetailBody,
        permTilesEl,
        permFilterBucket,
        permUnknownList
      }, {
        bucketLabel,
        bucketDisplay,
        isUnknown,
        knownBadge,
        ruleLabel
      });
      function applyFilters(rows) {
        const bucket = permFilterBucket instanceof HTMLSelectElement ? permFilterBucket.value : "";
        const known = permFilterKnown instanceof HTMLSelectElement ? permFilterKnown.value : "";
        return rows.filter((row) => {
          const matchesBucket = bucket === "" || bucketLabel(row.bucket) === bucket;
          const rowIsKnown = !isUnknown(row);
          const matchesKnown = known === "" || known === "known" && rowIsKnown || known === "unknown" && !rowIsKnown;
          return matchesBucket && matchesKnown;
        });
      }
      function updatePermissionTable() {
        const rows = applyFilters(permissionRows);
        renderers.renderPermDetail(rows);
      }
      const csvTools = SampleDetail.createPermissionsCsv({
        bucketLabel,
        isUnknown,
        ruleLabel
      });
      function setStatus(message) {
        if (!permStatusNote) return;
        if (!message) {
          permStatusNote.style.display = "none";
          permStatusNote.textContent = "";
          return;
        }
        permStatusNote.textContent = message;
        permStatusNote.style.display = "block";
      }
      function resetEmpty() {
        permissionRows = [];
        summaryRows = [];
        if (permSummaryEmpty) permSummaryEmpty.style.display = "block";
        if (permDetailBody) {
          permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">No permissions available.</td></tr>';
        }
        renderers.renderPermTiles(permissionRows);
        renderers.populateBucketFilter(permissionRows);
        renderers.renderUnknownList(permissionRows);
        updatePipelineStages();
        if (permExportBtn instanceof HTMLButtonElement) permExportBtn.disabled = true;
      }
      function renderFailure(prefix, response) {
        if (!permErrorEl) return;
        permErrorEl.innerHTML = `<pre>${prefix}

HTTP ${response.status}
error: ${esc(response.error)}</pre>`;
      }
      function responseRows(response) {
        const body = response.body;
        return Array.isArray(body?.data) ? body.data : [];
      }
      async function loadPermissions(sampleIdValue) {
        if (!sampleIdValue || !permSummaryEndpoint || !permDetailEndpoint) {
          resetEmpty();
          return;
        }
        try {
          if (permErrorEl) permErrorEl.textContent = "";
          if (permSummaryEmpty) permSummaryEmpty.style.display = "none";
          if (permSummaryList) permSummaryList.innerHTML = "";
          if (permDetailBody) {
            permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">Loading permissions...</td></tr>';
          }
          if (permFilterBucket instanceof HTMLSelectElement) permFilterBucket.value = "";
          if (permFilterKnown instanceof HTMLSelectElement) permFilterKnown.value = "";
          const encodedSampleId = encodeURIComponent(String(sampleIdValue));
          const summaryUrl = `${permSummaryEndpoint}?sample_id=${encodedSampleId}`;
          const detailUrl = `${permDetailEndpoint}?sample_id=${encodedSampleId}`;
          const [summaryRes, detailRes] = await Promise.all([
            app.fetchJson(summaryUrl),
            app.fetchJson(detailUrl)
          ]);
          if (!summaryRes.ok) {
            summaryRows = [];
            if (permSummaryEmpty) permSummaryEmpty.style.display = "block";
            renderFailure("Permissions summary error.", summaryRes);
          } else {
            summaryRows = responseRows(summaryRes);
            renderers.renderPermSummary(summaryRows);
          }
          if (!detailRes.ok) {
            permissionRows = [];
            renderFailure("Permissions detail error.", detailRes);
            renderers.renderPermTiles(permissionRows);
            renderers.populateBucketFilter(permissionRows);
            renderers.renderUnknownList(permissionRows);
            renderers.renderPermDetail(permissionRows);
          } else {
            permissionRows = responseRows(detailRes);
            renderers.renderPermTiles(permissionRows);
            renderers.populateBucketFilter(permissionRows);
            renderers.renderUnknownList(permissionRows);
            updatePermissionTable();
          }
          updatePipelineStages();
          if (permExportBtn instanceof HTMLButtonElement) {
            permExportBtn.disabled = permissionRows.length === 0;
          }
        } catch (error) {
          if (permErrorEl) {
            permErrorEl.innerHTML = `<pre>Permissions error:
${esc(error instanceof Error ? error.message : String(error))}</pre>`;
          }
          permissionRows = [];
          summaryRows = [];
          renderers.renderPermTiles(permissionRows);
          renderers.populateBucketFilter(permissionRows);
          renderers.renderUnknownList(permissionRows);
          renderers.renderPermDetail(permissionRows);
          updatePipelineStages();
          if (permExportBtn instanceof HTMLButtonElement) permExportBtn.disabled = true;
        }
      }
      function showNonAndroid() {
        setStatus("");
        if (permNonAndroid) permNonAndroid.style.display = "block";
        if (permSummaryEmpty) permSummaryEmpty.style.display = "none";
        if (permDetailBody) {
          permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">Not an Android sample.</td></tr>';
        }
        permissionRows = [];
        summaryRows = [];
        renderers.renderPermTiles(permissionRows);
        renderers.populateBucketFilter(permissionRows);
        renderers.renderUnknownList(permissionRows);
        updatePipelineStages();
        if (permExportBtn instanceof HTMLButtonElement) permExportBtn.disabled = true;
      }
      if (permFilterBucket instanceof HTMLSelectElement) {
        permFilterBucket.addEventListener("change", updatePermissionTable);
      }
      if (permFilterKnown instanceof HTMLSelectElement) {
        permFilterKnown.addEventListener("change", updatePermissionTable);
      }
      if (permTilesEl) {
        permTilesEl.addEventListener("click", (event) => {
          const target = event.target;
          const tile = target instanceof Element ? target.closest(".perm-tile") : null;
          if (!(tile instanceof HTMLElement) || !(permFilterBucket instanceof HTMLSelectElement)) return;
          const bucket = tile.getAttribute("data-bucket") || "";
          permFilterBucket.value = bucket;
          updatePermissionTable();
        });
      }
      if (permExportBtn instanceof HTMLButtonElement) {
        permExportBtn.addEventListener("click", () => {
          if (!permissionRows.length) return;
          const rows = applyFilters(permissionRows);
          const filename = `permissions_sample_${exportSampleId || "unknown"}.csv`;
          csvTools.downloadCsv(filename, csvTools.toCsv(rows));
        });
      }
      return {
        loadPermissions,
        setStatus,
        showNonAndroid,
        resetEmpty,
        setExportSampleId: (value) => {
          exportSampleId = String(value || "");
        }
      };
    };
  }
})();
