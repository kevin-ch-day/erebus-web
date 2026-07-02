(function() {
  "use strict";
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
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
  function initPipelineEnginePanelLive(app, options) {
    const refreshSeconds = Math.max(10, options.refreshSeconds ?? 30);
    const liveMetaId = options.liveMetaId;
    const metaEl = liveMetaId ? document.getElementById(liveMetaId) : null;
    bindPipelineEngineCopyButtons(document.getElementById(`${options.idPrefix}-panel`) || document);
    async function load() {
      if (!options.endpoint) return;
      const res = await app.fetchPayload(options.endpoint);
      if (!res.ok) {
        if (metaEl) metaEl.textContent = "Live refresh unavailable";
        return;
      }
      refreshPipelineEnginePanel(app, res.data, options, res.meta);
      if (metaEl) {
        metaEl.textContent = `Live refresh: ${String(res.meta?.server_utc_now || "ok")}`;
      }
    }
    void load();
    const timer = window.setInterval(() => {
      void load();
    }, refreshSeconds * 1e3);
    return () => window.clearInterval(timer);
  }
  const root = document.getElementById("submit-artifact-page");
  if (root && window.App) {
    let normalizeHash = function(rawValue) {
      return String(rawValue || "").replace(/\s+/g, "").trim().toLowerCase();
    }, detectHashType = function(rawValue) {
      const value = normalizeHash(rawValue);
      if (!value) return "--";
      if (!/^[a-fA-F0-9]+$/.test(value)) return "Invalid";
      if (value.length === 32) return "MD5";
      if (value.length === 40) return "SHA-1";
      if (value.length === 64) return "SHA-256";
      return "Invalid";
    }, normalizeHashType = function(type) {
      return ["MD5", "SHA-1", "SHA-256"].includes(type) ? type : null;
    }, showBox = function(el, message) {
      if (!el) return;
      el.textContent = message;
      el.style.display = "block";
    }, clearBox = function(el) {
      if (!el) return;
      el.textContent = "";
      el.style.display = "none";
    }, summarizeQueueResponse = function(payload) {
      const accepted = Number(payload.accepted || 0);
      const failed = Number(payload.failed || 0);
      const duplicatesKnown = asRows(payload.duplicates_known).length;
      const duplicatesQueued = asRows(payload.duplicates_queued).length;
      const warnings = asRows(payload.warnings);
      const rowResults = asRows(payload.row_results);
      const statusParts = [];
      const errorParts = [];
      if (accepted > 0) statusParts.push(`Queued ${accepted} artifact(s).`);
      if (failed > 0) errorParts.push(`${failed} row(s) were not queued.`);
      if (duplicatesKnown > 0) errorParts.push(`${duplicatesKnown} already known in registry.`);
      if (duplicatesQueued > 0) errorParts.push(`${duplicatesQueued} already queued.`);
      if (warnings.length > 0) statusParts.push(warnings.join(" "));
      const detailMessages = rowResults.filter((row) => row.status && row.status !== "accepted").map((row) => String(row.message || "").trim()).filter(Boolean);
      if (detailMessages.length > 0) {
        errorParts.push(detailMessages.join(" "));
      } else if (asRows(payload.errors).length > 0) {
        errorParts.push(asRows(payload.errors).join(" "));
      }
      return {
        statusText: statusParts.join(" ").trim(),
        errorText: errorParts.join(" ").trim(),
        accepted,
        failed
      };
    }, bindRow = function(row) {
      const input = row.querySelector(".artifact-hash-input");
      const hint = row.querySelector(".artifact-hash-hint");
      if (input && hint) {
        const update = () => {
          hint.textContent = detectHashType(input.value);
        };
        input.addEventListener("input", update);
        update();
      }
      const select = row.querySelector(".artifact-source-select");
      const other = row.querySelector(".artifact-source-other");
      if (select && other) {
        const update = () => {
          const show = select.value === "other";
          other.disabled = !show;
          other.style.display = show ? "block" : "none";
          if (!show) other.value = "";
        };
        select.addEventListener("change", update);
        update();
      }
    }, parseCsv = function(text) {
      const rows = [];
      let current = "";
      let row = [];
      let inQuotes = false;
      for (let i = 0; i < text.length; i += 1) {
        const ch = text[i];
        const next = text[i + 1];
        if (ch === '"') {
          if (inQuotes && next === '"') {
            current += '"';
            i += 1;
          } else {
            inQuotes = !inQuotes;
          }
        } else if (ch === "," && !inQuotes) {
          row.push(current);
          current = "";
        } else if ((ch === "\n" || ch === "\r") && !inQuotes) {
          if (ch === "\r" && next === "\n") i += 1;
          row.push(current);
          current = "";
          if (row.some((value) => String(value || "").trim() !== "")) rows.push(row);
          row = [];
        } else {
          current += ch;
        }
      }
      row.push(current);
      if (row.some((value) => String(value || "").trim() !== "")) rows.push(row);
      return rows;
    }, toHeaderMap = function(headers) {
      const map = {};
      headers.forEach((header, idx) => {
        map[String(header || "").trim().toLowerCase()] = idx;
      });
      return map;
    }, getCell = function(values, headerMap, key) {
      const idx = headerMap[key];
      return typeof idx === "number" ? String(values[idx] || "").trim() : "";
    }, isBeaconLamdaShape = function(headerMap) {
      return ["sha256", "family_raw", "review_reason"].some((key) => Object.prototype.hasOwnProperty.call(headerMap, key));
    }, mapImportedRow = function(values, headerMap) {
      if (isBeaconLamdaShape(headerMap)) {
        const familyCandidate = getCell(values, headerMap, "family_label_candidate") || getCell(values, headerMap, "sample_label_candidate") || getCell(values, headerMap, "family_raw");
        const sourceOtherParts = [
          getCell(values, headerMap, "source_batch_label_candidate"),
          getCell(values, headerMap, "year_month") || getCell(values, headerMap, "year")
        ].filter(Boolean);
        return {
          artifact_hash: getCell(values, headerMap, "sha256"),
          artifact_name: getCell(values, headerMap, "sample_label_candidate") || familyCandidate,
          artifact_family: familyCandidate,
          artifact_category: getCell(values, headerMap, "platform_candidate") || "android",
          artifact_subtype: getCell(values, headerMap, "payload_target_platform_candidate") || "apk",
          artifact_source: "csv",
          artifact_source_other: sourceOtherParts.join(" | ")
        };
      }
      return {
        artifact_hash: getCell(values, headerMap, "artifact_hash") || getCell(values, headerMap, "sha256"),
        artifact_name: getCell(values, headerMap, "artifact_name"),
        artifact_family: getCell(values, headerMap, "artifact_family") || getCell(values, headerMap, "family_candidate"),
        artifact_category: getCell(values, headerMap, "artifact_category"),
        artifact_subtype: getCell(values, headerMap, "artifact_subtype") || getCell(values, headerMap, "type_candidate"),
        artifact_source: getCell(values, headerMap, "artifact_source") || getCell(values, headerMap, "source_kind"),
        artifact_source_other: getCell(values, headerMap, "artifact_source_other") || getCell(values, headerMap, "source_title")
      };
    }, ensureRowCount = function(count) {
      if (!tableBody) return;
      const template = tableBody.querySelector("tr");
      if (!template) return;
      while (tableBody.querySelectorAll("tr").length < count) {
        const clone = template.cloneNode(true);
        clone.querySelectorAll("input").forEach((input) => {
          input.value = "";
        });
        clone.querySelectorAll("select").forEach((select) => {
          select.value = "";
        });
        tableBody.appendChild(clone);
        bindRow(clone);
      }
    }, populateRowsFromCsv = function() {
      clearBox(statusBox);
      clearBox(errorBox);
      if (!csvInput || !tableBody) return;
      const text = csvInput.value.trim();
      if (!text) {
        showBox(errorBox, "Paste CSV content first.");
        return;
      }
      const parsed = parseCsv(text);
      if (parsed.length < 2) {
        showBox(errorBox, "CSV needs a header row and at least one data row.");
        return;
      }
      const [headers, ...dataRows] = parsed;
      const headerMap = toHeaderMap(headers);
      const imported = dataRows.map((values) => mapImportedRow(values, headerMap)).filter((item) => item.artifact_hash);
      if (imported.length === 0) {
        showBox(errorBox, "No importable rows found in CSV.");
        return;
      }
      ensureRowCount(imported.length);
      const rows = Array.from(tableBody.querySelectorAll("tr"));
      imported.forEach((item, idx) => {
        const row = rows[idx];
        if (!row) return;
        const cols = row.querySelectorAll("td");
        const hashInput = row.querySelector(".artifact-hash-input");
        const sourceSelect = row.querySelector(".artifact-source-select");
        const sourceOther = row.querySelector(".artifact-source-other");
        if (hashInput) hashInput.value = normalizeHash(item.artifact_hash);
        (cols[1]?.querySelector("input")).value = item.artifact_name || "";
        (cols[2]?.querySelector("input")).value = item.artifact_family || "";
        (cols[3]?.querySelector("input")).value = item.artifact_category || "";
        (cols[4]?.querySelector("input")).value = item.artifact_subtype || "";
        if (sourceSelect) sourceSelect.value = item.artifact_source || "";
        if (sourceOther) sourceOther.value = item.artifact_source_other || "";
        bindRow(row);
      });
      showBox(statusBox, `Imported ${imported.length} CSV row(s) into the intake table.`);
    }, collectRows = function() {
      const rows = Array.from(document.querySelectorAll("#artifact-bulk-table tbody tr"));
      const items = [];
      const errors = [];
      rows.forEach((row, idx) => {
        const hashInput = row.querySelector(".artifact-hash-input");
        const hashValue = hashInput ? normalizeHash(hashInput.value) : "";
        if (hashInput && hashInput.value !== hashValue) hashInput.value = hashValue;
        if (!hashValue) return;
        if (!normalizeHashType(detectHashType(hashValue))) {
          errors.push(`Row ${idx + 1}: invalid hash.`);
          return;
        }
        const sourceSelect = row.querySelector(".artifact-source-select");
        const sourceOther = row.querySelector(".artifact-source-other");
        const sourceValue = sourceSelect ? sourceSelect.value : "";
        if (!sourceValue) {
          errors.push(`Row ${idx + 1}: select a source.`);
          return;
        }
        if (sourceValue === "other" && sourceOther && sourceOther.value.trim().length > 120) {
          errors.push(`Row ${idx + 1}: source detail too long.`);
          return;
        }
        const cols = row.querySelectorAll("td");
        items.push({
          artifact_hash: hashValue,
          artifact_name: (cols[1]?.querySelector("input")?.value || "").trim(),
          artifact_family: (cols[2]?.querySelector("input")?.value || "").trim(),
          artifact_category: (cols[3]?.querySelector("input")?.value || "").trim(),
          artifact_subtype: (cols[4]?.querySelector("input")?.value || "").trim(),
          artifact_source: sourceValue,
          artifact_source_other: sourceOther ? sourceOther.value.trim() : ""
        });
      });
      return { items, errors };
    };
    const App = window.App;
    const ingestEndpoint = root.dataset.ingestEndpoint || "";
    const tableBody = document.querySelector("#artifact-bulk-table tbody");
    const addRowBtn = document.getElementById("artifact-add-row");
    const queueBtn = document.getElementById("artifact-queue-bulk");
    const importCsvBtn = document.getElementById("artifact-import-csv");
    const clearCsvBtn = document.getElementById("artifact-clear-csv");
    const csvInput = document.getElementById("artifact-csv-input");
    const statusBox = document.getElementById("artifact-bulk-status");
    const errorBox = document.getElementById("artifact-bulk-error");
    async function queueArtifacts() {
      clearBox(statusBox);
      clearBox(errorBox);
      if (!ingestEndpoint) {
        showBox(errorBox, "Ingest endpoint not configured.");
        return;
      }
      const { items, errors } = collectRows();
      if (errors.length > 0) {
        showBox(errorBox, errors.join(" "));
        return;
      }
      if (items.length === 0) {
        showBox(errorBox, "Provide at least one valid row with a hash and source.");
        return;
      }
      try {
        const res = await App.postJson(ingestEndpoint, { items });
        if (!res.ok) {
          const err = res;
          showBox(errorBox, err.error || "Queue submission failed.");
          return;
        }
        const body = res.body;
        const data = body.data ?? body;
        const summary = summarizeQueueResponse(data);
        if (summary.errorText) showBox(errorBox, summary.errorText);
        if (summary.statusText) showBox(statusBox, summary.statusText);
        if (summary.accepted > 0 && summary.failed === 0 && tableBody) {
          tableBody.querySelectorAll("tr").forEach((row) => {
            row.querySelectorAll("input").forEach((input) => {
              input.value = "";
            });
            row.querySelectorAll("select").forEach((select) => {
              select.value = "";
            });
            bindRow(row);
          });
        }
      } catch (error) {
        showBox(errorBox, error instanceof Error ? error.message : "Queue submission failed.");
      }
    }
    if (tableBody) {
      Array.from(tableBody.querySelectorAll("tr")).forEach((row) => bindRow(row));
    }
    if (addRowBtn && tableBody) {
      addRowBtn.addEventListener("click", () => {
        const template = tableBody.querySelector("tr");
        if (!template) return;
        const clone = template.cloneNode(true);
        clone.querySelectorAll("input").forEach((input) => {
          input.value = "";
        });
        clone.querySelectorAll("select").forEach((select) => {
          select.value = "";
        });
        tableBody.appendChild(clone);
        bindRow(clone);
      });
    }
    if (queueBtn) {
      queueBtn.addEventListener("click", () => {
        void queueArtifacts();
      });
    }
    if (importCsvBtn) {
      importCsvBtn.addEventListener("click", populateRowsFromCsv);
    }
    if (clearCsvBtn && csvInput) {
      clearCsvBtn.addEventListener("click", () => {
        csvInput.value = "";
        clearBox(statusBox);
        clearBox(errorBox);
      });
    }
    const pipelineEndpoint = root.dataset.pipelineEndpoint || "";
    if (pipelineEndpoint) {
      initPipelineEnginePanelLive(App, {
        endpoint: pipelineEndpoint,
        idPrefix: root.dataset.pipelinePrefix || "submit-artifact-engine",
        liveMetaId: root.dataset.pipelineLiveMeta || void 0,
        refreshSeconds: Number(root.dataset.pipelineRefreshSeconds || "30") || 30
      });
    }
  }
})();
