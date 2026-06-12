(function() {
  "use strict";
  const SampleDetail = window.SampleDetail;
  if (window.App && SampleDetail) {
    const esc = window.App.escapeHtml;
    const fmt = window.App.fmt;
    const titleCase = SampleDetail.titleCase;
    const formatBytes = SampleDetail.formatBytes;
    SampleDetail.createSurface = (elements) => {
      const {
        headerTitleEl,
        headerSubtitleEl,
        headerPlatformEl,
        headerVtStatusEl,
        headerAlignmentEl,
        headerLastAnalysisEl,
        headerShaEl,
        coreGridEl,
        interpretationSectionEl,
        opsGridEl,
        opsSectionEl,
        platformGridEl,
        platformNoteEl,
        platformSectionEl,
        platformEvidenceGridEl,
        advancedGridEl,
        advancedSectionEl,
        errorSectionEl,
        errorBodyEl,
        androidSectionEl
      } = elements;
      const fmtUtc = SampleDetail.fmtUtc;
      const hasDisplayValue = SampleDetail.hasDisplayValue || ((value) => {
        return value !== null && value !== void 0 && String(value).trim() !== "" && String(value).trim() !== "--";
      });
      function rowLabel(row) {
        return Array.isArray(row) ? String(row[0]) : String(row.label);
      }
      function rowValue(row) {
        return Array.isArray(row) ? row[1] : row.value;
      }
      function addCard(target, title, rows, options = {}) {
        if (!target) return;
        const filteredRows = rows.filter((row) => {
          const label = rowLabel(row);
          const value = rowValue(row);
          if (options.keepEmpty) return true;
          if (Array.isArray(options.keepLabels) && options.keepLabels.includes(label)) return true;
          return hasDisplayValue(value);
        });
        if (filteredRows.length === 0 && !options.renderWhenEmpty) {
          return;
        }
        const card = document.createElement("div");
        card.className = "detail-card";
        if (typeof options.className === "string" && options.className) {
          String(options.className).split(/\s+/).filter(Boolean).forEach((name) => card.classList.add(name));
        }
        const rowClass = options.rowClass ? " detail-row-vertical" : "";
        const list = filteredRows.map((row) => {
          const label = rowLabel(row);
          const value = rowValue(row);
          const objectRow = !Array.isArray(row) ? row : null;
          const rendered = objectRow && objectRow.rendered !== void 0 ? objectRow.rendered : esc(fmt(value));
          const valueClass = objectRow && objectRow.valueClass ? ` ${objectRow.valueClass}` : "";
          return `<div class="detail-row${rowClass}"><div class="detail-label">${esc(label)}</div><div class="detail-value${valueClass}">${rendered}</div></div>`;
        }).join("");
        card.innerHTML = `<div class="detail-card-title">${esc(title)}</div>${list}`;
        target.appendChild(card);
      }
      function backoffNote(statusRaw, nextEligibleRaw) {
        const status = String(statusRaw || "").toUpperCase();
        const nextMs = window.App.parseUtcToMs ? window.App.parseUtcToMs(nextEligibleRaw) : null;
        if (nextMs && nextMs > Date.now()) {
          return `Blocked until ${fmtUtc(nextEligibleRaw)}`;
        }
        if (status === "RETRY_WAIT") {
          return "Retry wait (timestamp missing).";
        }
        return "--";
      }
      function boolLabel(value, yes = "Yes", no = "No") {
        return value ? yes : no;
      }
      function normalizeFamilyValue(value) {
        return String(value || "").trim().toLowerCase();
      }
      function familySignalStatus(sample) {
        const family = normalizeFamilyValue(sample.family_label);
        const signalName = normalizeFamilyValue(sample.popular_threat_name);
        if (!family && !signalName) return "unlabeled";
        if (family && !signalName) return "catalog_only";
        if (!family && signalName) return "signal_only";
        if (family === signalName) return "aligned";
        return "mismatch";
      }
      function pillToneForAlignment(value) {
        const raw = String(value || "").toLowerCase();
        if (raw === "aligned") return "ok";
        if (raw === "mismatch") return "err";
        if (raw === "signal_only" || raw === "catalog_only") return "warn";
        return "info";
      }
      function pillToneForVtStatus(value) {
        const raw = String(value || "").toUpperCase();
        if (raw === "LOOKED_UP") return "ok";
        if (raw === "RETRY_WAIT" || raw === "PROCESSING") return "warn";
        if (raw.includes("ERROR") || raw.includes("FAILED")) return "err";
        return "info";
      }
      function badgeMarkup(value, tone) {
        return `<span class="sample-status-pill ${esc(tone)}">${esc(fmt(value))}</span>`;
      }
      function boolBadge(value, options = {}) {
        const yesLabel = typeof options.yesLabel === "string" ? options.yesLabel : "Yes";
        const noLabel = typeof options.noLabel === "string" ? options.noLabel : "No";
        const invert = options.invert === true;
        const tone = value ? invert ? "warn" : "ok" : invert ? "ok" : "err";
        return badgeMarkup(value ? yesLabel : noLabel, tone);
      }
      function syncSectionVisibility(sectionEl, contentEl, extraVisible = false) {
        if (!sectionEl) return;
        const hasContent = !!(contentEl && contentEl.children && contentEl.children.length > 0);
        sectionEl.style.display = hasContent || extraVisible ? "" : "none";
      }
      function updateHeader(sample) {
        const platformLabel = titleCase(sample.platform || sample.artifact_type);
        const familyStatus = familySignalStatus(sample);
        const titleParts = [
          fmt(sample.sample_label, "").trim(),
          fmt(sample.classification_primary, "").trim()
        ].filter(Boolean);
        if (headerTitleEl) {
          headerTitleEl.textContent = titleParts.length ? titleParts.join(" · ") : "Sample Detail";
        }
        if (headerSubtitleEl) {
          const bits = [
            hasDisplayValue(sample.sample_id) ? `Sample ID ${fmt(sample.sample_id)}` : "",
            hasDisplayValue(sample.family_label) ? `family ${fmt(sample.family_label)}` : "",
            hasDisplayValue(sample.vt_suggested_label) ? `VT label ${fmt(sample.vt_suggested_label)}` : ""
          ].filter(Boolean);
          headerSubtitleEl.textContent = bits.length ? bits.join(" · ") : "Sample identity loaded.";
        }
        if (headerPlatformEl) headerPlatformEl.textContent = fmt(platformLabel);
        if (headerVtStatusEl) headerVtStatusEl.innerHTML = badgeMarkup(sample.vt_status_code, pillToneForVtStatus(sample.vt_status_code));
        if (headerAlignmentEl) headerAlignmentEl.innerHTML = badgeMarkup(familyStatus, pillToneForAlignment(familyStatus));
        if (headerLastAnalysisEl) headerLastAnalysisEl.textContent = fmtUtc(sample.vt_last_analysis_at_utc);
        if (headerShaEl) headerShaEl.textContent = fmt(sample.sha256);
      }
      function renderPlatformContext(platformContext) {
        if (!platformGridEl) return;
        platformGridEl.innerHTML = "";
        if (platformNoteEl) {
          platformNoteEl.style.display = "none";
          platformNoteEl.textContent = "";
        }
        if (!platformContext || typeof platformContext !== "object") {
          return;
        }
        addCard(platformGridEl, "Catalog Alignment", [
          ["Primary catalog", platformContext.primary_catalog],
          ["Permission Intel catalog", platformContext.permission_intel_catalog],
          {
            label: "Split enabled",
            value: boolLabel(platformContext.split_enabled),
            rendered: boolBadge(platformContext.split_enabled, { yesLabel: "Split", noLabel: "Unified" })
          },
          ["Primary schema head", platformContext.primary_schema_head],
          ["Permission Intel schema head", platformContext.permission_intel_schema_head],
          {
            label: "Schema heads match",
            value: boolLabel(platformContext.schema_heads_match),
            rendered: boolBadge(platformContext.schema_heads_match, { yesLabel: "Match", noLabel: "Drift" })
          }
        ], { className: "sample-detail-card sample-detail-card-compact", keepLabels: ["Split enabled", "Schema heads match"] });
        addCard(platformGridEl, "Sample Run Alignment", [
          ["Sample last-run catalog", platformContext.sample_last_run_db_name],
          ["Sample last-run schema version", platformContext.sample_last_run_schema_version],
          ["Sample last-run taxonomy version", platformContext.sample_last_run_perm_taxonomy_version],
          {
            label: "Against current primary",
            value: boolLabel(platformContext.sample_last_run_against_current_primary),
            rendered: boolBadge(platformContext.sample_last_run_against_current_primary, { yesLabel: "Current", noLabel: "Older / other" })
          },
          {
            label: "Against known catalog",
            value: boolLabel(platformContext.sample_last_run_against_known_catalog),
            rendered: boolBadge(platformContext.sample_last_run_against_known_catalog, { yesLabel: "Known", noLabel: "Unknown" })
          },
          {
            label: "Schema matches current primary head",
            value: boolLabel(platformContext.sample_last_run_schema_matches_primary_head),
            rendered: boolBadge(platformContext.sample_last_run_schema_matches_primary_head, { yesLabel: "Match", noLabel: "Drift" })
          },
          {
            label: "Taxonomy matches latest",
            value: boolLabel(platformContext.sample_last_run_perm_taxonomy_matches_latest),
            rendered: boolBadge(platformContext.sample_last_run_perm_taxonomy_matches_latest, { yesLabel: "Current", noLabel: "Older" })
          }
        ], {
          className: "sample-detail-card sample-detail-card-compact",
          keepLabels: [
            "Against current primary",
            "Against known catalog",
            "Schema matches current primary head",
            "Taxonomy matches latest"
          ]
        });
        addCard(platformGridEl, "Latest Platform Taxonomy", [
          ["Latest taxonomy version", platformContext.latest_perm_taxonomy_version],
          ["Latest taxonomy finished", fmtUtc(platformContext.latest_perm_taxonomy_finished_at_utc)],
          {
            label: "Sample has last run",
            value: boolLabel(platformContext.sample_has_last_run),
            rendered: boolBadge(platformContext.sample_has_last_run, { yesLabel: "Present", noLabel: "No run yet" })
          },
          {
            label: "Platform mismatch detected",
            value: boolLabel(platformContext.sample_platform_state_mismatch),
            rendered: boolBadge(platformContext.sample_platform_state_mismatch, { yesLabel: "Mismatch", noLabel: "Clear", invert: true })
          }
        ], { className: "sample-detail-card sample-detail-card-compact", keepLabels: ["Sample has last run", "Platform mismatch detected"] });
        if (!platformNoteEl) return;
        if (!platformContext.sample_has_last_run) {
          platformNoteEl.textContent = "This sample has no recorded run yet, so platform drift cannot be compared.";
          platformNoteEl.style.display = "block";
          return;
        }
        if (platformContext.sample_platform_state_mismatch) {
          platformNoteEl.textContent = "This sample was last processed under a platform state that does not fully match the current primary catalog, schema head, or permission taxonomy.";
          platformNoteEl.style.display = "block";
          return;
        }
        if (!platformContext.schema_heads_match) {
          platformNoteEl.textContent = "Primary and Permission Intel schema heads differ. That can be valid, but operators should compare sample results against the correct catalog role.";
          platformNoteEl.style.display = "block";
        }
      }
      function renderLastError(row) {
        if (!errorSectionEl || !errorBodyEl) return;
        const hasError = row.last_http_status || row.last_error_category || row.last_error_message;
        if (!hasError) {
          errorSectionEl.style.display = "none";
          return;
        }
        errorSectionEl.style.display = "block";
        errorBodyEl.innerHTML = "";
        const tr = document.createElement("tr");
        tr.innerHTML = `
        <td>${esc(fmt(row.last_http_status))}</td>
        <td>${esc(fmt(row.last_error_category))}</td>
        <td>${esc(fmt(row.last_error_message))}</td>
        <td>${esc(fmtUtc(row.last_attempt_at_utc))}</td>
      `;
        errorBodyEl.appendChild(tr);
      }
      function renderSample(data) {
        const sample = data.sample;
        const lastRun = data.last_run || null;
        if (coreGridEl) coreGridEl.innerHTML = "";
        if (opsGridEl) opsGridEl.innerHTML = "";
        if (platformGridEl) platformGridEl.innerHTML = "";
        if (platformEvidenceGridEl) platformEvidenceGridEl.innerHTML = "";
        if (advancedGridEl) advancedGridEl.innerHTML = "";
        updateHeader(sample);
        const platform = String(sample.platform || "").toLowerCase();
        const fileExt = String(sample.file_extension || "").toLowerCase();
        const mimeType = String(sample.mime_type || "").toLowerCase();
        const hasPackageName = String(sample.android_package_name || "").trim() !== "";
        const isAndroid = platform.includes("android") || fileExt === "apk" || mimeType.includes("android.package-archive") || hasPackageName;
        addCard(platformEvidenceGridEl, "Observed Platform Traits", [
          ["Platform", titleCase(sample.platform || sample.artifact_type)],
          ["Artifact type", sample.artifact_type],
          ["File extension", sample.file_extension],
          ["MIME type", sample.mime_type],
          ["File size", formatBytes(sample.file_size_bytes)]
        ], {
          className: "sample-detail-card sample-detail-card-compact",
          keepLabels: ["Platform"]
        });
        if (isAndroid) {
          addCard(platformEvidenceGridEl, "Android Metadata", [
            ["Package name", sample.android_package_name],
            ["Launcher activity", sample.android_launcher_activity],
            ["Min SDK", sample.android_min_sdk],
            ["Target SDK", sample.android_target_sdk],
            ["Receiver", sample.android_receiver_count],
            ["Activity", sample.android_activity_count],
            ["Service", sample.android_service_count],
            ["Provider", sample.android_provider_count],
            ["Library", sample.android_library_count],
            ["Permission", sample.android_permission_count]
          ], { className: "sample-detail-card sample-detail-card-tall" });
        }
        addCard(coreGridEl, "Erebus Interpretation", [
          ["Sample label", sample.sample_label],
          ["Primary", sample.classification_primary],
          ["Subtype", sample.classification_subtype],
          ["Catalog family", sample.family_label],
          ["VT suggested label", sample.vt_suggested_label],
          ["VT popular threat label", sample.popular_threat_label],
          ["VT popular threat category", sample.popular_threat_category],
          ["VT popular threat name", sample.popular_threat_name],
          ["Signal parse version", sample.vt_signal_parse_version],
          {
            label: "Alignment status",
            value: familySignalStatus(sample),
            rendered: badgeMarkup(familySignalStatus(sample), pillToneForAlignment(familySignalStatus(sample)))
          }
        ], { className: "sample-detail-card sample-detail-card-tall", keepLabels: ["Alignment status"] });
        addCard(opsGridEl, "VT State", [
          {
            label: "Status",
            value: sample.vt_status_code,
            rendered: badgeMarkup(sample.vt_status_code, pillToneForVtStatus(sample.vt_status_code))
          },
          ["Reason", sample.reason_code],
          ["Backoff", backoffNote(sample.vt_status_code, sample.next_eligible_at_utc)],
          ["Next eligible", fmtUtc(sample.next_eligible_at_utc)],
          ["Attempt count", sample.attempt_count],
          ["Last attempt", fmtUtc(sample.last_attempt_at_utc)],
          ["Last HTTP status", sample.last_http_status],
          ["Last error category", sample.last_error_category],
          ["Last error message", sample.last_error_message]
        ], {
          className: "sample-detail-card sample-detail-card-compact",
          keepLabels: ["Status", "Attempt count"]
        });
        addCard(opsGridEl, "VT Timeline", [
          ["First submitted to VirusTotal", fmtUtc(sample.vt_first_submission_at_utc)],
          ["Last analysis", fmtUtc(sample.vt_last_analysis_at_utc)],
          ["Summary updated", fmtUtc(sample.vt_summary_updated_at_utc)],
          ["First seen in the wild", sample.vt_first_seen_itw_date],
          ["Times submitted", sample.vt_times_submitted],
          ["Unique sources", sample.vt_unique_sources]
        ], {
          className: "sample-detail-card sample-detail-card-compact",
          keepLabels: ["Times submitted", "Unique sources"]
        });
        if (lastRun) {
          addCard(opsGridEl, "Last Run", [
            ["Run ID", lastRun.run_id],
            ["Started", fmtUtc(lastRun.started_at_utc)],
            ["Finished", fmtUtc(lastRun.finished_at_utc)],
            ["Processed", lastRun.processed_count],
            ["OK", lastRun.ok_count],
            ["No data", lastRun.no_data_count],
            ["Retry wait", lastRun.retry_wait_count],
            ["Error", lastRun.error_count],
            ["Stopped reason", lastRun.stopped_reason],
            ["Perm taxonomy version", lastRun.perm_taxonomy_version]
          ], {
            className: "sample-detail-card sample-detail-card-compact",
            keepLabels: ["Processed", "OK", "No data", "Retry wait", "Error"]
          });
        }
        renderPlatformContext(data.platform_context || null);
        if (advancedGridEl) {
          addCard(advancedGridEl, "State Diagnostics", [
            ["Last run id", sample.last_run_id],
            ["Last key id", sample.last_key_id],
            ["Claim token", sample.claim_token],
            ["Claimed at", fmtUtc(sample.claimed_at_utc)],
            ["Claimed by host", sample.claimed_by_host],
            ["Claimed by user", sample.claimed_by_user],
            ["Claimed by ip", sample.claimed_by_ip],
            ["Claimed by pid", sample.claimed_by_pid],
            ["State created", fmtUtc(sample.state_created_at_utc)],
            ["State updated", fmtUtc(sample.state_updated_at_utc)]
          ], { className: "sample-detail-card sample-detail-card-tall" });
          if (lastRun) {
            addCard(advancedGridEl, "Run Diagnostics", [
              ["DB name", lastRun.db_name],
              ["Key ID", lastRun.key_id],
              ["Tool version", lastRun.tool_version],
              ["Schema version", lastRun.schema_version]
            ], { className: "sample-detail-card sample-detail-card-compact" });
          }
        }
        syncSectionVisibility(opsSectionEl, opsGridEl);
        syncSectionVisibility(interpretationSectionEl, coreGridEl);
        syncSectionVisibility(platformSectionEl, platformGridEl, !!(platformNoteEl && platformNoteEl.style.display !== "none"));
        syncSectionVisibility(advancedSectionEl, advancedGridEl);
        renderLastError(sample);
        if (androidSectionEl) {
          androidSectionEl.style.display = platformEvidenceGridEl && platformEvidenceGridEl.children.length > 0 ? "" : "none";
        }
        return {
          isAndroid,
          lastRun
        };
      }
      return {
        renderSample,
        updateHeader
      };
    };
  }
})();
