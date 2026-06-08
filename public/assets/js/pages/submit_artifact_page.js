(function() {
  "use strict";
  const root = document.getElementById("submit-artifact-page");
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
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
  }
})();
