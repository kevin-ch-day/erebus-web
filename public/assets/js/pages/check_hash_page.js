(function() {
  "use strict";
  const root = document.getElementById("check-hash-page");
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
  if (root && window.App) {
    let normalizeHash = function(rawValue) {
      return String(rawValue || "").replace(/\s+/g, "").trim().toLowerCase();
    }, hasValue = function(value) {
      if (value === null || value === void 0) return false;
      return !(typeof value === "string" && value.trim() === "");
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
    }, bindHint = function(inputId, hintId) {
      const input = document.getElementById(inputId);
      const hint = document.getElementById(hintId);
      if (!input || !hint) return;
      const update = () => {
        hint.textContent = detectHashType(input.value);
      };
      input.addEventListener("input", update);
      update();
    }, showBox = function(el, message) {
      if (!el) return;
      el.textContent = message;
      el.style.display = "block";
    }, clearBox = function(el) {
      if (!el) return;
      el.textContent = "";
      el.style.display = "none";
    }, clearDetails = function() {
      if (!detailGrid) return;
      detailGrid.innerHTML = "";
      detailGrid.style.display = "none";
    }, clearActionLinks = function() {
      if (!actionBox) return;
      actionBox.innerHTML = "";
      actionBox.style.display = "none";
    }, setPreviewVisible = function(visible) {
      if (!previewRow) return;
      previewRow.style.display = visible ? "block" : "none";
    }, setIntakeVisible = function(visible) {
      if (!intakeSection) return;
      intakeSection.style.display = visible ? "block" : "none";
    }, formatMaybeDate = function(value) {
      if (!hasValue(value)) return "--";
      const raw = String(value);
      const normalized = raw.includes("T") ? raw : `${raw.replace(" ", "T")}Z`;
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) return raw;
      return localDateFormatter.format(date);
    }, actionButton = function(link) {
      const a = document.createElement("a");
      a.className = link.primary ? "btn btn-primary check-hash-action-btn" : "btn check-hash-action-btn";
      a.href = link.href;
      if (link.newTab) {
        a.target = "_blank";
        a.rel = "noopener noreferrer";
      }
      a.textContent = link.label;
      return a;
    }, setActionLinks = function(links) {
      if (!actionBox || links.length === 0) {
        clearActionLinks();
        return;
      }
      actionBox.innerHTML = "";
      links.forEach((link) => actionBox.appendChild(actionButton(link)));
      actionBox.style.display = "flex";
    }, sampleLink = function(sampleId, sampleLabel) {
      if (!sampleBase || !hasValue(sampleId)) return null;
      const id = String(sampleId);
      return {
        href: App.appendQueryParam(sampleBase, "sample_id", id),
        label: hasValue(sampleLabel) ? `Open sample ${id} (${String(sampleLabel)})` : `Open sample ${id}`,
        primary: true,
        newTab: true
      };
    }, addDetailCard = function(title, rows) {
      if (!detailGrid || rows.length === 0) return;
      const card = document.createElement("div");
      card.className = "detail-card";
      const body = rows.map(([label, value]) => `<div class="detail-row"><div class="detail-label">${esc(label)}</div><div class="detail-value">${esc(String(value ?? "--"))}</div></div>`).join("");
      card.innerHTML = `<div class="detail-card-title">${esc(title)}</div>${body}`;
      detailGrid.appendChild(card);
      detailGrid.style.display = "grid";
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
      if (duplicatesKnown > 0) errorParts.push(`${duplicatesKnown} already known in registry.`);
      if (duplicatesQueued > 0) errorParts.push(`${duplicatesQueued} already queued.`);
      if (failed > 0) errorParts.push(`${failed} row(s) were not queued.`);
      if (warnings.length > 0) statusParts.push(warnings.join(" "));
      const detailMessages = rowResults.filter((row) => hasValue(row.status) && row.status !== "accepted").map((row) => String(row.message || "").trim()).filter(Boolean);
      if (detailMessages.length > 0) {
        errorParts.push(detailMessages.join(" "));
      } else if (asRows(payload.errors).length > 0) {
        errorParts.push(asRows(payload.errors).join(" "));
      }
      return {
        statusText: statusParts.join(" ").trim(),
        errorText: errorParts.join(" ").trim(),
        accepted
      };
    }, updateLookupButton = function() {
      if (!checkBtn || !hashInput) return;
      checkBtn.disabled = !normalizeHashType(detectHashType(hashInput.value));
    }, validateQueueInput = function() {
      const hashEl = document.getElementById("artifact-hash");
      const sourceEl = document.getElementById("artifact-source");
      const hashValue = hashEl ? normalizeHash(hashEl.value) : "";
      if (hashEl && hashEl.value !== hashValue) hashEl.value = hashValue;
      const hashType = normalizeHashType(detectHashType(hashValue));
      if (!hashType) return { ok: false, message: "Provide a valid hash." };
      if (!sourceEl || !sourceEl.value) return { ok: false, message: "Select an artifact source." };
      return { ok: true };
    };
    const App = window.App;
    const lookupEndpoint = root.dataset.lookupEndpoint || "";
    const ingestEndpoint = root.dataset.ingestEndpoint || "";
    const sampleBase = root.dataset.sampleBase || "";
    const backlogBase = root.dataset.backlogBase || "";
    const hashInput = document.getElementById("hash-input");
    const checkBtn = document.getElementById("hash-check-btn");
    const resultBox = document.getElementById("hash-result");
    const errorBox = document.getElementById("hash-error");
    const actionBox = document.getElementById("hash-actions");
    const previewRow = document.getElementById("hash-preview-row");
    const intakeSection = document.getElementById("artifact-intake");
    const detailGrid = document.getElementById("hash-detail-grid");
    const queueBtn = document.getElementById("artifact-queue-btn");
    const queueStatus = document.getElementById("artifact-queue-status");
    const queueError = document.getElementById("artifact-queue-error");
    const sourceSelect = document.getElementById("artifact-source");
    const sourceOther = document.getElementById("artifact-source-other");
    const esc = App.escapeHtml;
    const localDateFormatter = new Intl.DateTimeFormat(void 0, {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
      month: "numeric",
      day: "numeric",
      year: "numeric"
    });
    async function lookupHash() {
      clearBox(resultBox);
      clearBox(errorBox);
      clearActionLinks();
      clearDetails();
      const hashValue = hashInput ? normalizeHash(hashInput.value) : "";
      if (hashInput && hashInput.value !== hashValue) hashInput.value = hashValue;
      const hashType = normalizeHashType(detectHashType(hashValue));
      if (!hashType || !lookupEndpoint) {
        showBox(errorBox, "Provide a valid MD5, SHA-1, or SHA-256 hash.");
        return;
      }
      try {
        const res = await App.fetchJson(`${lookupEndpoint}?hash=${encodeURIComponent(hashValue)}`);
        if (!res.ok) {
          const err = res;
          showBox(errorBox, err.error || `Lookup failed (HTTP ${err.status}).`);
          return;
        }
        const body = res.body;
        const data = toRecord(body.data ?? body);
        const record = toRecord(data.record);
        if (Boolean(data.found)) {
          const statusText = hasValue(record.vt_status_code) ? ` Current VT state: ${String(record.vt_status_code)}.` : "";
          showBox(resultBox, `Known artifact found in the catalog.${statusText}`);
          const links = [];
          const sampleAction = sampleLink(record.sample_id, record.sample_label);
          if (sampleAction) links.push(sampleAction);
          if (backlogBase) links.push({ href: backlogBase, label: "Open ingest backlog" });
          setActionLinks(links);
          setPreviewVisible(false);
          const catalogRows = [
            ["Matched by", data.match_column || "hash"],
            ["Sample ID", record.sample_id],
            ["Sample label", record.sample_label],
            ["Family label", record.family_label],
            ["Catalog created", formatMaybeDate(record.record_created_at_utc)],
            ["SHA-256", record.sha256],
            ["MD5", record.md5],
            ["SHA-1", record.sha1]
          ];
          addDetailCard("Catalog record", catalogRows.filter((entry) => hasValue(entry[1])));
          const stateRows = [
            ["Status", record.vt_status_code],
            ["Attempt count", record.attempt_count],
            ["Next eligible", formatMaybeDate(record.next_eligible_at_utc)],
            ["Last attempt", formatMaybeDate(record.last_attempt_at_utc)],
            ["Last HTTP", record.last_http_status],
            ["Last error category", record.last_error_category],
            ["Last error message", record.last_error_message],
            ["Last run id", record.last_run_id],
            ["Source URL", record.source_url]
          ];
          addDetailCard("VirusTotal state", stateRows.filter((entry) => hasValue(entry[1]) && entry[1] !== "--"));
          setIntakeVisible(false);
          return;
        }
        if (hasValue(data.queue_status)) {
          showBox(resultBox, "This artifact is already in intake and does not need to be queued again.");
          setPreviewVisible(false);
          const links = [];
          if (backlogBase) links.push({ href: backlogBase, label: "Open ingest backlog", primary: true });
          links.push({ href: "#hash-lookup", label: "Check another hash" });
          setActionLinks(links);
          addDetailCard("Queue", [
            ["Queue status", data.queue_status],
            ["Queued at", formatMaybeDate(data.queued_at_utc)]
          ]);
          setIntakeVisible(false);
          return;
        }
        showBox(resultBox, "No catalog match was found. If this artifact should enter the system, queue it below.");
        setPreviewVisible(true);
        setActionLinks([
          { href: "#artifact-intake", label: "Queue this artifact", primary: true },
          ...backlogBase ? [{ href: backlogBase, label: "Review intake backlog first" }] : []
        ]);
        setIntakeVisible(true);
        const artifactHash = document.getElementById("artifact-hash");
        if (artifactHash) {
          artifactHash.value = hashValue;
          artifactHash.dispatchEvent(new Event("input"));
        }
      } catch (error) {
        setPreviewVisible(true);
        showBox(errorBox, error instanceof Error ? error.message : "Lookup failed.");
      }
    }
    async function queueArtifact() {
      clearBox(queueStatus);
      clearBox(queueError);
      const validation = validateQueueInput();
      if (!validation.ok) {
        showBox(queueError, validation.message);
        return;
      }
      if (!ingestEndpoint) {
        showBox(queueError, "Ingest endpoint not configured.");
        return;
      }
      const getValue = (id) => {
        const el = document.getElementById(id);
        return el ? el.value.trim() : "";
      };
      const payload = {
        items: [{
          artifact_hash: normalizeHash(getValue("artifact-hash")),
          artifact_name: getValue("artifact-name"),
          artifact_family: getValue("artifact-family"),
          artifact_category: getValue("artifact-category"),
          artifact_subtype: getValue("artifact-subtype"),
          artifact_source: getValue("artifact-source"),
          artifact_source_other: getValue("artifact-source-other")
        }]
      };
      try {
        const res = await App.postJson(ingestEndpoint, payload);
        if (!res.ok) {
          const err = res;
          showBox(queueError, err.error || `Queue submission failed (HTTP ${err.status}).`);
          return;
        }
        const body = res.body;
        const data = toRecord(body.data ?? body);
        const summary = summarizeQueueResponse(data);
        if (summary.errorText) showBox(queueError, summary.errorText);
        if (summary.statusText) showBox(queueStatus, summary.statusText);
        if (summary.accepted > 0) {
          setPreviewVisible(false);
          setIntakeVisible(false);
          setActionLinks([
            ...backlogBase ? [{ href: backlogBase, label: "Open ingest backlog", primary: true }] : [],
            { href: "#hash-lookup", label: "Check another hash" }
          ]);
          showBox(resultBox, "Artifact queued successfully. Review intake backlog instead of re-submitting the same hash.");
        }
      } catch (error) {
        showBox(queueError, error instanceof Error ? error.message : "Queue submission failed.");
      }
    }
    bindHint("hash-input", "hash-type-hint");
    bindHint("artifact-hash", "artifact-hash-hint");
    if (hashInput && checkBtn) {
      hashInput.addEventListener("input", updateLookupButton);
      updateLookupButton();
      checkBtn.addEventListener("click", () => {
        void lookupHash();
      });
      hashInput.addEventListener("keydown", (evt) => {
        if (evt.key === "Enter") {
          evt.preventDefault();
          if (!checkBtn.disabled) {
            void lookupHash();
          }
        }
      });
    }
    if (sourceSelect && sourceOther) {
      const field = sourceOther.closest(".filter-field");
      const updateSource = () => {
        const show = sourceSelect.value === "other";
        sourceOther.disabled = !show;
        if (field) field.style.display = show ? "flex" : "none";
        if (!field) sourceOther.style.display = show ? "block" : "none";
        if (!show) sourceOther.value = "";
      };
      sourceSelect.addEventListener("change", updateSource);
      updateSource();
    }
    if (queueBtn) {
      const updateQueueBtn = () => {
        queueBtn.disabled = !validateQueueInput().ok;
      };
      ["artifact-hash", "artifact-name", "artifact-family", "artifact-category", "artifact-subtype", "artifact-source", "artifact-source-other"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener("input", updateQueueBtn);
          el.addEventListener("change", updateQueueBtn);
        }
      });
      updateQueueBtn();
      queueBtn.addEventListener("click", () => {
        void queueArtifact();
      });
    }
  }
})();
