(function() {
  "use strict";
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  const root = document.getElementById("perm-overview-page");
  if (root && window.App && window.PermissionIntel) {
    const App = window.App;
    const PI = window.PermissionIntel;
    const endpoint = root.dataset.intelEndpoint || "";
    const classificationGapsEndpoint = root.dataset.classificationGapsEndpoint || "";
    const limit = root.dataset.limit || "100";
    const phase2bEnabled = root.dataset.flagPhase2b === "1";
    const refreshSeconds = Number(root.dataset.refreshSeconds || "60") || 60;
    const refreshMs = Math.max(15, refreshSeconds) * 1e3;
    if (endpoint) {
      let setPageLoading = function(message) {
        if (loadingTextEl) loadingTextEl.textContent = message;
        if (loadingCardEl) loadingCardEl.style.display = "";
        if (shellContentEl) shellContentEl.classList.add("pi-shell-content-hidden");
      }, setPageReady = function() {
        if (loadingCardEl) loadingCardEl.style.display = "none";
        if (shellContentEl) shellContentEl.classList.remove("pi-shell-content-hidden");
      }, setPageError = function(message) {
        if (loadingTextEl) loadingTextEl.textContent = message;
        if (loadingCardEl) loadingCardEl.style.display = "";
        if (shellContentEl) shellContentEl.classList.add("pi-shell-content-hidden");
      }, updateNextAction = function(unknownPct, operatorSummary, session) {
        if (!nextActionEl) return;
        const pct = Number(unknownPct ?? 0);
        const summary = operatorSummary || {};
        const sessionData = session || {};
        const currentEvidenceBacklog = Number(
          summary.current_evidence_review_backlog || sessionData.current_evidence_review_backlog || 0
        );
        const governedBacklog = Number(
          summary.governed_current_unknown_backlog || sessionData.governed_current_unknown_backlog || 0
        );
        const ledgerDiagnosticBacklog = Number(
          summary.ledger_diagnostic_backlog || sessionData.ledger_diagnostic_backlog || 0
        );
        const queuedDecisions = Number(summary.queued_dict_decisions || 0);
        if (currentEvidenceBacklog > 0) {
          nextActionEl.textContent = "Next action: Open Triage and review current evidence-backed UNKNOWNs first.";
          return;
        }
        if (governedBacklog > 0) {
          nextActionEl.textContent = "Next action: Inspect governed current residue in Triage.";
          return;
        }
        if (ledgerDiagnosticBacklog > 0) {
          nextActionEl.textContent = "Next action: Inspect ledger diagnostics before treating high-seen rows as active work.";
          return;
        }
        if (queuedDecisions > 0) {
          nextActionEl.textContent = phase2bEnabled ? "Next action: Inspect Queue diagnostics only if these rows overlap current evidence; apply still belongs to the CLI/operator workflow." : "Next action: Inspect queued dictionary diagnostics through the active operator workflow.";
          return;
        }
        if (pct > 8) {
          nextActionEl.textContent = "Next action: Investigate Drift and recent unknown-rate growth.";
          return;
        }
        if (pct >= 3) {
          nextActionEl.textContent = "Next action: Investigate Drift or open Triage for unknowns.";
          return;
        }
        nextActionEl.textContent = "Next action: Monitor only. No immediate PI action required.";
      }, renderHealth = function(health, meta) {
        const knownPct = Number(health.known_pct ?? 0);
        const unknownPct = Number(health.unknown_pct ?? 0);
        const total = Number(health.total_count ?? 0);
        const knownCount = Number(health.known_count ?? 0);
        const unknownCount = Number(health.unknown_count ?? 0);
        if (total === 0) {
          if (healthStatusEl) {
            healthStatusEl.innerHTML = 'Pipeline status: <span class="badge muted">No data</span>';
          }
          if (knownPctEl) knownPctEl.textContent = "--";
          if (unknownPctEl) unknownPctEl.textContent = "--";
          if (healthCountsEl) healthCountsEl.textContent = "No permission observations yet.";
          if (knownBarEl) knownBarEl.style.width = "0%";
          if (unknownBarEl) {
            unknownBarEl.style.width = "0%";
            unknownBarEl.classList.remove("warn", "err", "ok");
          }
          if (healthUpdatedEl) {
            const updated = meta && meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : "--";
            healthUpdatedEl.textContent = `Updated: ${updated}`;
          }
          updateNextAction(null, null, null);
          return;
        }
        const status = statusFromUnknownPct(unknownPct);
        if (healthStatusEl) {
          healthStatusEl.innerHTML = `Pipeline status: <span class="${status.className}">${esc(status.label)}</span>`;
        }
        if (knownPctEl) knownPctEl.textContent = formatPct(knownPct);
        if (unknownPctEl) unknownPctEl.textContent = formatPct(unknownPct);
        if (healthCountsEl) {
          healthCountsEl.textContent = `Known ${formatCount(knownCount)} | Unknown ${formatCount(unknownCount)} | Total ${formatCount(total)}`;
        }
        const knownWidth = Math.max(0, Math.min(100, knownPct));
        const unknownWidth = Math.max(0, Math.min(100 - knownWidth, unknownPct));
        if (knownBarEl) knownBarEl.style.width = `${knownWidth}%`;
        if (unknownBarEl) {
          unknownBarEl.style.width = `${unknownWidth}%`;
          unknownBarEl.classList.remove("warn", "err", "ok");
          unknownBarEl.classList.add(unknownPct > 8 ? "err" : unknownPct >= 3 ? "warn" : "ok");
        }
        if (healthUpdatedEl) {
          const updated = meta && meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : "--";
          healthUpdatedEl.textContent = `Updated: ${updated}`;
        }
      }, renderSignals = function(health, taxonomy) {
        if (taxonomyVersionEl) {
          taxonomyVersionEl.textContent = taxonomy && taxonomy.version ? String(taxonomy.version) : "--";
        }
        if (lastTaxonomyRefreshEl) {
          const value = health.last_taxonomy_refresh_at_utc ? formatUtc(health.last_taxonomy_refresh_at_utc) : "--";
          lastTaxonomyRefreshEl.textContent = value;
        }
        if (unknownTrendEl) {
          const delta = health.unknown_pct_delta;
          if (delta === null || delta === void 0) {
            unknownTrendEl.textContent = "n/a";
          } else {
            const sign = Number(delta) > 0 ? "+" : "";
            unknownTrendEl.textContent = `${formatPct(health.unknown_pct_7d)} (${sign}${Number(delta).toFixed(1)}%)`;
          }
        }
      }, renderStatusModel = function(statusModel) {
        if (!statusModelEl || !statusModelSummaryEl || !statusModelListEl) return;
        statusModelListEl.innerHTML = "";
        const unexpectedStatuses = Array.isArray(statusModel && statusModel.unexpected_live_triage_statuses) ? statusModel.unexpected_live_triage_statuses : [];
        const legacyQueueActions = Array.isArray(statusModel && statusModel.legacy_queue_actions_active) ? statusModel.legacy_queue_actions_active : [];
        const legacyQueueActionsTotal = Array.isArray(statusModel && statusModel.legacy_queue_actions_total) ? statusModel.legacy_queue_actions_total : [];
        const historicalLegacyQueueActions = legacyQueueActionsTotal.filter((item) => {
          const status = String(item && item.status ? item.status : "").toLowerCase();
          return status && !["queued", "claimed", "pending"].includes(status);
        });
        const hasLiveDrift = unexpectedStatuses.length > 0 || legacyQueueActions.length > 0;
        const hasCompatNote = historicalLegacyQueueActions.length > 0;
        if (!hasLiveDrift && !hasCompatNote) {
          statusModelEl.style.display = "none";
          return;
        }
        statusModelEl.style.display = "block";
        statusModelEl.className = hasLiveDrift ? "notice warn" : "notice info";
        if (statusModelTitleEl) {
          statusModelTitleEl.textContent = hasLiveDrift ? "Status model drift detected." : "Status model compatibility note.";
        }
        const parts = [];
        if (unexpectedStatuses.length) {
          parts.push(`${formatCount(unexpectedStatuses.length)} live triage statuses are not modeled in the configured web contract.`);
        }
        if (legacyQueueActions.length) {
          parts.push(`${formatCount(legacyQueueActions.length)} legacy queue action aliases are still active in stored rows.`);
        }
        if (historicalLegacyQueueActions.length) {
          const historicalCount = historicalLegacyQueueActions.reduce(
            (sum, item) => sum + Number(item && item.count ? item.count : 0),
            0
          );
          parts.push(`${formatCount(historicalCount)} historical legacy queue-alias rows remain in closed queue history but are not active workflow debt.`);
        }
        statusModelSummaryEl.textContent = parts.join(" ");
        unexpectedStatuses.forEach((statusKey) => {
          const li = document.createElement("li");
          li.textContent = `Unexpected live triage status: ${String(statusKey)}`;
          statusModelListEl.appendChild(li);
        });
        legacyQueueActions.forEach((item) => {
          const li = document.createElement("li");
          li.textContent = `Legacy queue action still active: ${item.raw} -> ${item.normalized} (${formatCount(item.count)}, status ${item.status || "queued"})`;
          statusModelListEl.appendChild(li);
        });
        historicalLegacyQueueActions.forEach((item) => {
          const li = document.createElement("li");
          li.textContent = `Historical legacy queue alias row: ${item.raw} -> ${item.normalized} (${formatCount(item.count)}, status ${item.status || "--"})`;
          statusModelListEl.appendChild(li);
        });
      }, renderMaintenance = function(health, maintenance, statusModel) {
        if (!maintenanceStatusEl || !maintenanceListEl) return;
        const newUnknowns = Number(maintenance.new_unknowns_24h ?? 0);
        const newNamespaces = Number(maintenance.new_namespaces_7d ?? 0);
        const securityUnknowns = Number(maintenance.security_sensitive_unknowns ?? 0);
        const unknownDelta = health.unknown_pct_delta;
        const unknownPct = Number(health.unknown_pct ?? 0);
        const triggers = [];
        if (unknownPct > 8) {
          triggers.push(`Unknown rate is ${unknownPct.toFixed(1)}%, above the 8% maintenance threshold.`);
        }
        if (newUnknowns > 0) {
          triggers.push(`${formatCount(newUnknowns)} new current-unknown permissions detected in the last 24h.`);
        }
        if (securityUnknowns > 0) {
          triggers.push(`${formatCount(securityUnknowns)} security-sensitive current UNKNOWN permissions detected.`);
        }
        if (newNamespaces > 0) {
          triggers.push(`${formatCount(newNamespaces)} new current-unknown namespaces detected in the last 7 days.`);
        }
        if (unknownDelta !== null && unknownDelta !== void 0 && Number(unknownDelta) > 0) {
          triggers.push(`Unknown rate increased by ${Number(unknownDelta).toFixed(1)}% over 7 days.`);
        }
        const unexpectedStatuses = Array.isArray(statusModel && statusModel.unexpected_live_triage_statuses) ? statusModel.unexpected_live_triage_statuses : [];
        const legacyQueueActions = Array.isArray(statusModel && statusModel.legacy_queue_actions_active) ? statusModel.legacy_queue_actions_active : [];
        if (unexpectedStatuses.length > 0) {
          triggers.push(`${formatCount(unexpectedStatuses.length)} live triage statuses are not modeled in the configured web contract.`);
        }
        if (legacyQueueActions.length > 0) {
          triggers.push(`${formatCount(legacyQueueActions.length)} legacy queue action aliases are still active in stored queue rows.`);
        }
        const critical = unknownPct > 8 || securityUnknowns > 0;
        const warning = triggers.length > 0;
        if (critical) {
          maintenanceStatusEl.className = "notice error";
          maintenanceStatusEl.textContent = "Maintenance required";
        } else if (warning) {
          maintenanceStatusEl.className = "notice warn";
          maintenanceStatusEl.textContent = "Maintenance recommended";
        } else {
          maintenanceStatusEl.className = "notice success";
          maintenanceStatusEl.textContent = "No maintenance signals detected";
        }
        maintenanceListEl.innerHTML = "";
        if (triggers.length === 0) {
          maintenanceListEl.innerHTML = '<li class="muted">No maintenance signals detected.</li>';
          return;
        }
        triggers.forEach((item) => {
          const li = document.createElement("li");
          li.textContent = item;
          maintenanceListEl.appendChild(li);
        });
      }, renderTopUnknowns = function(rows) {
        if (!topUnknownBodyEl) return;
        topUnknownBodyEl.innerHTML = "";
        const list = Array.isArray(rows) ? rows.slice(0, 8) : [];
        if (!list.length) {
          topUnknownBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No current evidence-backed review rows in the current snapshot.</td></tr>';
          return;
        }
        list.forEach((row) => {
          const permission = fmt(row.permission_string, "");
          const lastSeen = row.last_observed_at_utc ? formatUtc(row.last_observed_at_utc) : "--";
          const risk = riskHint(row.permission_string, row.namespace);
          const returnUrl = pageUrl("permissions_overview");
          const evidenceUrl = pageUrl("permissions_evidence", { permission, return: returnUrl });
          const reviewUrl = pageUrl("permissions_review", { permission, return: returnUrl });
          const tr = document.createElement("tr");
          tr.innerHTML = `
        <td class="mono cell-wrap">${esc(permission)}</td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(formatCount(row.current_unknown_obs_rows))}</td>
        <td><span class="${risk.className}">${esc(risk.label)}</span><br><span class="muted">${esc(riskReasonLabel(row.risk_reason))}</span></td>
        <td>${esc(lastSeen)}</td>
        <td>
          <a class="btn btn-small btn-primary" href="${esc(reviewUrl)}">Review</a>
          <a class="btn btn-small btn-muted" href="${esc(evidenceUrl)}">Evidence</a>
        </td>
      `;
          topUnknownBodyEl.appendChild(tr);
        });
      }, renderGovernedUnknowns = function(rows) {
        if (!governedUnknownBodyEl) return;
        governedUnknownBodyEl.innerHTML = "";
        const list = Array.isArray(rows) ? rows.slice(0, 8) : [];
        if (!list.length) {
          governedUnknownBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No governed current residue rows in the current snapshot.</td></tr>';
          return;
        }
        list.forEach((row) => {
          const permission = fmt(row.permission_string, "");
          const lastSeen = row.last_observed_at_utc ? formatUtc(row.last_observed_at_utc) : "--";
          const lane = REVIEW_LANE_LABELS[String(row.review_lane_label || "").toLowerCase()] || fmt(row.review_lane_label, "--");
          const returnUrl = pageUrl("permissions_overview");
          const evidenceUrl = pageUrl("permissions_evidence", { permission, return: returnUrl });
          const reviewUrl = pageUrl("permissions_review", { permission, return: returnUrl });
          const tr = document.createElement("tr");
          tr.innerHTML = `
        <td class="mono cell-wrap">${esc(permission)}</td>
        <td>${esc(lane)}</td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(lastSeen)}</td>
        <td>
          <a class="btn btn-small" href="${esc(reviewUrl)}">Inspect</a>
          <a class="btn btn-small btn-muted" href="${esc(evidenceUrl)}">View Evidence</a>
        </td>
      `;
          governedUnknownBodyEl.appendChild(tr);
        });
      }, renderLedgerDiagnostics = function(rows) {
        if (!ledgerDiagnosticsBodyEl) return;
        ledgerDiagnosticsBodyEl.innerHTML = "";
        const list = Array.isArray(rows) ? rows.slice(0, 8) : [];
        if (!list.length) {
          ledgerDiagnosticsBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No ledger diagnostic rows in the current snapshot.</td></tr>';
          return;
        }
        list.forEach((row) => {
          const permission = fmt(row.permission_string, "");
          const label = DIAGNOSTIC_LABELS[String(row.diagnostic_label || "").toLowerCase()] || fmt(row.diagnostic_label, "--");
          const lastSeen = row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : "--";
          const returnUrl = pageUrl("permissions_overview");
          const reviewUrl = pageUrl("permissions_review", { permission, return: returnUrl });
          const tr = document.createElement("tr");
          tr.innerHTML = `
        <td class="mono cell-wrap">${esc(permission)}</td>
        <td>${esc(label)}</td>
        <td>${esc(formatCount(row.historical_ledger_seen_count))}</td>
        <td>${esc(lastSeen)}</td>
        <td>
          <a class="btn btn-small" href="${esc(reviewUrl)}">Inspect</a>
        </td>
      `;
          ledgerDiagnosticsBodyEl.appendChild(tr);
        });
      }, renderRollupGuard = function(guard) {
        if (!rollupGuardEl || !rollupGuardSummaryEl || !rollupGuardListEl) return;
        rollupGuardListEl.innerHTML = "";
        if (rollupGuardDetailsEl) rollupGuardDetailsEl.style.display = "none";
        if (!guard) {
          rollupGuardEl.style.display = "none";
          return;
        }
        const stale = Number(guard.stale_permissions_count || 0);
        const mismatches = Number(guard.stale_count_mismatch_count || 0);
        const driftCount = Math.max(stale, mismatches);
        if (driftCount === 0) {
          rollupGuardEl.style.display = "none";
          return;
        }
        rollupGuardEl.style.display = "block";
        rollupGuardEl.className = driftCount <= 10 ? "notice warn" : "notice error";
        const maxLagDays = guard.max_lag_days;
        const maxLagLabel = maxLagDays === null || maxLagDays === void 0 ? "n/a" : `${Number(maxLagDays).toFixed(2)} days`;
        rollupGuardSummaryEl.textContent = `Stale rows: ${stale} | Count mismatches: ${mismatches} | Max lag: ${maxLagLabel}`;
        const samples = Array.isArray(guard.sample) ? guard.sample : [];
        if (samples.length > 0 && rollupGuardDetailsEl) {
          rollupGuardDetailsEl.style.display = "block";
          samples.slice(0, 10).forEach((row) => {
            const perm = row.permission_string || "--";
            const lag = row.lag_seconds ? `${Math.round(Number(row.lag_seconds) / 86400)}d` : "0d";
            const delta = row.count_delta !== void 0 && row.count_delta !== null ? String(row.count_delta) : "0";
            const li = document.createElement("li");
            li.textContent = `${perm} (lag ${lag}, delta ${delta})`;
            rollupGuardListEl.appendChild(li);
          });
        }
      }, renderBacklog = function(statusCounts, queue, health, metrics, operatorSummary, maintenance) {
        if (!backlogNewEl || !backlogAospEl || !backlogOemEl || !backlogQueuedEl) return;
        const metricData = metrics || {};
        const summary = operatorSummary || {};
        const counts = toRecord(metricData.triage_status_counts || statusCounts || {});
        const healthData = health || {};
        const maintenanceData = maintenance || {};
        backlogNewEl.textContent = formatCount(maintenanceData.new_unknowns_24h || 0);
        if (backlogEffectiveUnknownEl) {
          backlogEffectiveUnknownEl.textContent = formatCount(
            summary.current_evidence_review_backlog || 0
          );
        }
        if (backlogGovernedEl) {
          backlogGovernedEl.textContent = formatCount(
            summary.governed_current_unknown_backlog || 0
          );
        }
        if (backlogLedgerDiagnosticsEl) {
          backlogLedgerDiagnosticsEl.textContent = formatCount(
            summary.ledger_diagnostic_backlog || 0
          );
        }
        backlogAospEl.textContent = formatCount(counts.aosp_missing || 0);
        backlogOemEl.textContent = formatCount(counts.oem_candidate || 0);
        if (backlogResolvedOemEl) {
          backlogResolvedOemEl.textContent = formatCount(
            counts.resolved_oem || metricData.resolved_oem_count || healthData.resolved_oem_count || 0
          );
        }
        const queueCounts = toRecord(metricData.queue_counts);
        backlogQueuedEl.textContent = formatCount(
          queueCounts.queued_current_unknown ?? queueCounts.queued ?? queue.queued_current_unknown_count ?? queue.queued_count ?? 0
        );
      }, gapReasonLabel = function(value) {
        return GAP_REASON_LABELS[String(value || "")] || String(value || "Review context");
      }, workflowLabel = function(row) {
        return String(row && row.workflow_label || gapReasonLabel(row && row.classification_gap_reason) || "Review context");
      }, filteredClassificationGapRows = function() {
        if (!Array.isArray(classificationGapRows) || classificationGapFilter === "all") {
          return Array.isArray(classificationGapRows) ? classificationGapRows : [];
        }
        return classificationGapRows.filter(
          (row) => String(row.workflow_state || "").toLowerCase() === classificationGapFilter
        );
      }, aggregateClassificationGapSamples = function(rows) {
        const sampleMap = /* @__PURE__ */ new Map();
        (Array.isArray(rows) ? rows : []).forEach((row) => {
          const sampleKey = row.sha256 ? `sha:${String(row.sha256)}` : `id:${String(row.sample_id || "")}`;
          if (!sampleKey || sampleKey === "id:") return;
          if (!sampleMap.has(sampleKey)) {
            sampleMap.set(sampleKey, {
              sample_id: row.sample_id || "",
              sha256: row.sha256 || "",
              package_name: row.package_name || "",
              confidence_bucket: row.confidence_bucket || "",
              recommended_action: row.recommended_action || "",
              vt_malicious_count: row.vt_malicious_count,
              vt_harmless_count: row.vt_harmless_count,
              review_priority: row.review_priority || "low",
              workflow_state: row.workflow_state || "",
              workflow_label: row.workflow_label || "",
              workflow_reason_label: row.workflow_reason_label || "",
              sample_strong_attack_surface_rows: Number(row.sample_strong_attack_surface_rows || 0),
              attack_rows: [],
              permission_set: /* @__PURE__ */ new Set()
            });
          }
          const entry = sampleMap.get(sampleKey);
          entry.sample_strong_attack_surface_rows = Math.max(
            Number(entry.sample_strong_attack_surface_rows || 0),
            Number(row.sample_strong_attack_surface_rows || 0)
          );
          entry.attack_rows.push({
            attack_technique_id: row.attack_technique_id || "",
            attack_name: row.attack_name || "",
            permissions: row.permissions || "",
            max_mapping_strength_rank: Number(row.max_mapping_strength_rank || 0)
          });
          String(row.permissions || "").split(",").map((value) => value.trim()).filter(Boolean).forEach((permission) => entry.permission_set.add(permission));
        });
        return Array.from(sampleMap.values()).map((entry) => {
          entry.attack_rows.sort((a, b) => {
            const aRank = Number(a.max_mapping_strength_rank || 0);
            const bRank = Number(b.max_mapping_strength_rank || 0);
            if (bRank !== aRank) {
              return bRank - aRank;
            }
            return String(a.attack_technique_id).localeCompare(String(b.attack_technique_id));
          });
          entry.permission_list = Array.from(entry.permission_set || []);
          delete entry.permission_set;
          return entry;
        }).sort((a, b) => {
          const aPriority = String(a.review_priority || "").toLowerCase();
          const bPriority = String(b.review_priority || "").toLowerCase();
          const priorityRank = { high: 0, medium: 1, low: 2 };
          const aRank = Object.prototype.hasOwnProperty.call(priorityRank, aPriority) ? priorityRank[aPriority] : 3;
          const bRank = Object.prototype.hasOwnProperty.call(priorityRank, bPriority) ? priorityRank[bPriority] : 3;
          if (aRank !== bRank) return aRank - bRank;
          if (Number(b.sample_strong_attack_surface_rows || 0) !== Number(a.sample_strong_attack_surface_rows || 0)) {
            return Number(b.sample_strong_attack_surface_rows || 0) - Number(a.sample_strong_attack_surface_rows || 0);
          }
          return Number(a.sample_id || 0) - Number(b.sample_id || 0);
        });
      }, renderClassificationGapFilterState = function(summary) {
        if (!classificationGapFiltersEl) return;
        classificationGapFiltersEl.querySelectorAll("button[data-gap-filter]").forEach((button) => {
          const isActive = String(button.getAttribute("data-gap-filter") || "") === classificationGapFilter;
          button.classList.toggle("btn-primary", isActive);
          button.classList.toggle("btn-small", true);
          if (!isActive) {
            button.classList.remove("btn-muted");
          }
        });
        if (classificationGapsSummaryEl && Array.isArray(summary) && summary.length) {
          const activeSamples = aggregateClassificationGapSamples(filteredClassificationGapRows());
          classificationGapsSummaryEl.textContent += ` | Showing ${formatCount(activeSamples.length)} sample${activeSamples.length === 1 ? "" : "s"}`;
        }
      }, priorityBadge = function(priority) {
        const key = String(priority || "").toLowerCase();
        if (key === "high") return '<span class="badge err">High</span>';
        if (key === "medium") return '<span class="badge warn">Medium</span>';
        return '<span class="badge muted">' + esc(String(priority || "Low")) + "</span>";
      }, renderClassificationGapsPayload = function(body) {
        if (!classificationGapsBodyEl) return;
        const data = toRecord(body && body.data ? body.data : {});
        const meta = toRecord(body && body.meta ? body.meta : {});
        classificationGapRows = Array.isArray(data.gaps) ? data.gaps : [];
        classificationGapSummaryRows = Array.isArray(data.summary) ? data.summary : [];
        const summary = classificationGapSummaryRows;
        const gaps = aggregateClassificationGapSamples(filteredClassificationGapRows());
        if (classificationGapsMetaEl) {
          const primary = meta.primary_database || "--";
          const pi = meta.permission_intel_database || "--";
          classificationGapsMetaEl.textContent = `Primary: ${primary} | PI: ${pi}`;
        }
        if (meta.schema_available === false) {
          const missing = Array.isArray(data.schema_missing) ? data.schema_missing.length : 0;
          if (classificationGapsSummaryEl) {
            classificationGapsSummaryEl.textContent = `Classification gap surfaces are not available yet (${missing} missing schema items).`;
          }
          classificationGapsBodyEl.innerHTML = '<tr><td colspan="5" class="muted">Apply the VT confidence and Permission ATT&amp;CK database surfaces before using this panel.</td></tr>';
          return;
        }
        if (classificationGapsSummaryEl) {
          if (summary.length) {
            classificationGapsSummaryEl.textContent = summary.map((row) => {
              const sampleCount = Number(row.sample_count || 0);
              const attackRowCount = Number(row.attack_row_count || 0);
              const density = attackRowCount > sampleCount ? ` (${formatCount(attackRowCount)} behavior rows)` : "";
              return `${workflowLabel(row)}: ${formatCount(sampleCount)} sample${sampleCount === 1 ? "" : "s"}${density}`;
            }).join(" | ");
          } else {
            classificationGapsSummaryEl.textContent = "No high-priority cross-signal classification gaps found.";
          }
        }
        renderClassificationGapFilterState(summary);
        if (!gaps.length) {
          classificationGapsBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No classification gaps found.</td></tr>';
          return;
        }
        classificationGapsBodyEl.innerHTML = gaps.map((row) => {
          const sample = row.sha256 ? `<code>${esc(String(row.sha256).slice(0, 16))}</code>` : `sample ${esc(String(row.sample_id || "--"))}`;
          const topAttackRows = Array.isArray(row.attack_rows) ? row.attack_rows.slice(0, 3) : [];
          const attack = [
            `<strong>${esc(formatCount(row.sample_strong_attack_surface_rows || topAttackRows.length || 0))} strong ATT&CK behavior row${Number(row.sample_strong_attack_surface_rows || 0) === 1 ? "" : "s"}</strong>`,
            ...topAttackRows.map((attackRow) => `${esc(attackRow.attack_technique_id || "--")} ${esc(attackRow.attack_name || "--")}`),
            row.permission_list && row.permission_list.length ? `<span class="muted">${esc(row.permission_list.slice(0, 6).join(", "))}${row.permission_list.length > 6 ? " ..." : ""}</span>` : '<span class="muted">--</span>'
          ].join("<br>");
          const vt = [
            `bucket=${esc(row.confidence_bucket || "missing")}`,
            `action=${esc(row.recommended_action || "missing")}`,
            `mal=${esc(row.vt_malicious_count ?? "--")} harmless=${esc(row.vt_harmless_count ?? "--")}`
          ].join("<br>");
          const workflowDetail = row.workflow_reason_label && row.workflow_reason_label !== workflowLabel(row) ? `<br><span class="muted">${esc(row.workflow_reason_label)}</span>` : "";
          const detailUrl = row.sha256 ? pageUrl("sample_detail", { sha256: row.sha256 }) : pageUrl("sample_detail", { sample_id: row.sample_id || "" });
          const sampleActions = `<br><a class="btn btn-small btn-muted" href="${esc(detailUrl)}">Open sample</a>`;
          return `
        <tr>
          <td>${sample}<br><span class="muted">${esc(row.package_name || "--")}</span>${sampleActions}</td>
          <td>${attack}</td>
          <td>${vt}</td>
          <td>${esc(workflowLabel(row))}${workflowDetail}</td>
          <td>${priorityBadge(row.review_priority)}</td>
        </tr>
      `;
        }).join("");
      };
      const healthUpdatedEl = document.getElementById("perm-health-updated");
      const healthStatusEl = document.getElementById("perm-health-status");
      const knownPctEl = document.getElementById("perm-known-pct");
      const unknownPctEl = document.getElementById("perm-unknown-pct");
      const healthCountsEl = document.getElementById("perm-health-counts");
      const knownBarEl = document.getElementById("perm-health-known");
      const unknownBarEl = document.getElementById("perm-health-unknown");
      const taxonomyVersionEl = document.getElementById("perm-taxonomy-version");
      const lastTaxonomyRefreshEl = document.getElementById("perm-last-taxonomy-refresh");
      const unknownTrendEl = document.getElementById("perm-unknown-trend");
      const maintenanceStatusEl = document.getElementById("perm-maintenance-status");
      const maintenanceListEl = document.getElementById("perm-maintenance-list");
      const statusModelEl = document.getElementById("perm-status-model");
      const statusModelTitleEl = document.getElementById("perm-status-model-title");
      const statusModelSummaryEl = document.getElementById("perm-status-model-summary");
      const statusModelListEl = document.getElementById("perm-status-model-list");
      const rollupGuardEl = document.getElementById("perm-rollup-guard");
      const rollupGuardSummaryEl = document.getElementById("perm-rollup-guard-summary");
      const rollupGuardDetailsEl = document.getElementById("perm-rollup-guard-details");
      const rollupGuardListEl = document.getElementById("perm-rollup-guard-list");
      const backlogNewEl = document.getElementById("perm-backlog-new");
      const backlogEffectiveUnknownEl = document.getElementById("perm-backlog-effective-unknown");
      const backlogGovernedEl = document.getElementById("perm-backlog-governed");
      const backlogLedgerDiagnosticsEl = document.getElementById("perm-backlog-ledger-diagnostics");
      const backlogAospEl = document.getElementById("perm-backlog-aosp");
      const backlogOemEl = document.getElementById("perm-backlog-oem");
      const backlogResolvedOemEl = document.getElementById("perm-backlog-resolved-oem");
      const backlogQueuedEl = document.getElementById("perm-backlog-queued");
      const topUnknownBodyEl = document.getElementById("perm-top-unknown-body");
      const governedUnknownBodyEl = document.getElementById("perm-governed-unknown-body");
      const ledgerDiagnosticsBodyEl = document.getElementById("perm-ledger-diagnostics-body");
      const classificationGapsMetaEl = document.getElementById("perm-classification-gaps-meta");
      const classificationGapsSummaryEl = document.getElementById("perm-classification-gaps-summary");
      const classificationGapsBodyEl = document.getElementById("perm-classification-gaps-body");
      const classificationGapFiltersEl = document.getElementById("perm-classification-gaps-filters");
      const nextActionEl = document.getElementById("perm-next-action");
      const errorEl = document.getElementById("perm-overview-error");
      const shellContentEl = document.getElementById("perm-overview-shell-content");
      const loadingCardEl = document.getElementById("perm-overview-loading-card");
      const loadingTextEl = document.getElementById("perm-overview-loading-text");
      const liveMetaEl = document.getElementById("perm-overview-live-meta");
      const esc = App.escapeHtml;
      const fmt = App.fmt;
      const formatUtc = App.formatUtc;
      const {
        formatCount,
        formatPct,
        statusFromUnknownPct,
        riskHint,
        riskReasonLabel
      } = PI;
      const pageUrl = App.pageUrl;
      let classificationGapRows = [];
      let classificationGapSummaryRows = [];
      let classificationGapFilter = "all";
      const REVIEW_LANE_LABELS = {
        active_review_candidate: "Active review",
        governed_launcher_ecosystem: "Governed launcher ecosystem",
        governed_known_google: "Governed known Google",
        malformed_or_conflict: "Malformed or conflict",
        resolved_or_dictionary_known: "Resolved or dictionary-known",
        missing_ledger_context: "Missing ledger context"
      };
      const DIAGNOSTIC_LABELS = {
        ledger_only_no_evidence: "Ledger only / no current UNKNOWNs",
        resolved_high_seen_historical: "Resolved high-seen historical",
        recent_ledger_without_evidence: "Recent ledger without evidence",
        governed_historical_residue: "Governed historical residue",
        orphan_ledger_row: "Orphan ledger row"
      };
      const GAP_REASON_LABELS = {
        missing_vt_confidence_for_strong_attack_surface: "Missing VT confidence for strong behavior",
        missing_vt_confidence_for_attack_surface: "Missing VT confidence",
        strong_attack_surface_low_vt_action: "Strong behavior but low VT action",
        strong_attack_surface_weak_vt_confidence: "Strong behavior but weak VT confidence",
        attack_surface_supports_vt_review: "Behavior supports VT review"
      };
      async function loadClassificationGaps() {
        if (!classificationGapsEndpoint || !classificationGapsBodyEl) return;
        try {
          const url = new URL(classificationGapsEndpoint, window.location.origin);
          url.searchParams.set("limit", "10");
          const res = await App.fetchJson(url.toString());
          if (!res.ok) {
            if (classificationGapsSummaryEl) {
              classificationGapsSummaryEl.textContent = "Failed to load classification gaps.";
            }
            classificationGapsBodyEl.innerHTML = `<tr><td colspan="5" class="err">HTTP ${esc(res.status)} loading classification gaps.</td></tr>`;
            return;
          }
          renderClassificationGapsPayload(toRecord(res.body || {}));
        } catch (error) {
          if (classificationGapsSummaryEl) {
            classificationGapsSummaryEl.textContent = "Failed to load classification gaps.";
          }
          classificationGapsBodyEl.innerHTML = '<tr><td colspan="5" class="err">' + esc(error instanceof Error ? error.message : String(error)) + "</td></tr>";
        }
      }
      if (classificationGapFiltersEl) {
        classificationGapFiltersEl.querySelectorAll("button[data-gap-filter]").forEach((button) => {
          button.addEventListener("click", () => {
            classificationGapFilter = String(button.getAttribute("data-gap-filter") || "all");
            renderClassificationGapsPayload({
              data: { gaps: classificationGapRows, summary: classificationGapSummaryRows },
              meta: {}
            });
          });
        });
      }
      async function loadOverview() {
        setPageLoading("Loading permission overview...");
        if (errorEl) errorEl.textContent = "";
        try {
          const url = new URL(endpoint, window.location.origin);
          url.searchParams.set("mode", "overview");
          url.searchParams.set("limit", String(limit));
          const res = await App.fetchJson(url.toString());
          if (!res.ok) {
            setPageError("Failed to load permission overview.");
            if (errorEl) {
              errorEl.innerHTML = "<pre>Permission overview error.\n\nHTTP " + res.status + "\nerror: " + esc(res.error) + "</pre>";
            }
            if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
            return;
          }
          const body = toRecord(res.body);
          const data = toRecord(body.data);
          const meta = toRecord(body.meta);
          renderHealth(data.health ? toRecord(data.health) : {}, meta);
          renderSignals(data.health ? toRecord(data.health) : {}, data.taxonomy ? toRecord(data.taxonomy) : {});
          renderStatusModel(data.status_model ? toRecord(data.status_model) : {});
          renderMaintenance(
            data.health ? toRecord(data.health) : {},
            data.maintenance ? toRecord(data.maintenance) : {},
            data.status_model ? toRecord(data.status_model) : {}
          );
          renderTopUnknowns(Array.isArray(data.current_evidence_review_rows) ? data.current_evidence_review_rows : []);
          renderGovernedUnknowns(Array.isArray(data.governed_current_unknown_rows) ? data.governed_current_unknown_rows : []);
          renderLedgerDiagnostics(Array.isArray(data.ledger_diagnostic_rows) ? data.ledger_diagnostic_rows : []);
          renderBacklog(
            data.triage_status_counts ? toRecord(data.triage_status_counts) : {},
            data.queue ? toRecord(data.queue) : {},
            data.health ? toRecord(data.health) : {},
            data.metrics ? toRecord(data.metrics) : {},
            data.operator_summary ? toRecord(data.operator_summary) : {},
            data.maintenance ? toRecord(data.maintenance) : {}
          );
          updateNextAction(
            data.health && toRecord(data.health).unknown_pct,
            data.operator_summary ? toRecord(data.operator_summary) : {},
            data.session ? toRecord(data.session) : {}
          );
          renderRollupGuard(data.rollup_guard ? toRecord(data.rollup_guard) : null);
          void loadClassificationGaps();
          setPageReady();
          if (liveMetaEl) {
            liveMetaEl.textContent = `Live refresh: ${String(meta.generated_at_utc || "ok")}`;
          }
        } catch (error) {
          setPageError("Failed to load permission overview.");
          if (errorEl) {
            errorEl.innerHTML = "<pre>Permission overview error:\n" + esc(error instanceof Error ? error.message : String(error)) + "</pre>";
          }
          if (liveMetaEl) liveMetaEl.textContent = "Live refresh unavailable";
        }
      }
      void loadOverview();
      window.setInterval(() => {
        void loadOverview();
      }, refreshMs);
    }
  }
})();
