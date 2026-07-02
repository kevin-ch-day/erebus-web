(function() {
  "use strict";
  const PermTriage$1 = window.PermTriage || (window.PermTriage = {});
  if (window.App && window.PermissionIntel) {
    let statusLabel = function(triageStatusMap, statusKey) {
      const key = String(statusKey || "").toLowerCase();
      return triageStatusMap && triageStatusMap.get(key) ? triageStatusMap.get(key) : String(statusKey || "");
    }, queueBadgeHtml = function(queueStatus) {
      const meta = queueStatusBadge(queueStatus);
      return `<span class="${meta.className}">${esc(meta.label)}</span>`;
    }, laneBadgeHtml = function(label, fallback = "--") {
      const text = String(label || "").trim();
      return `<span class="badge muted">${esc(text || fallback)}</span>`;
    }, currentEvidenceActionMeta = function(laneKey) {
      const key = String(laneKey || "").toLowerCase();
      if (key === "active_review_candidate") {
        return {
          reviewLabel: "Review",
          reviewClass: "btn-primary",
          evidenceLabel: "Evidence"
        };
      }
      return {
        reviewLabel: "Inspect",
        reviewClass: "btn-muted",
        evidenceLabel: "View Evidence"
      };
    };
    const App = window.App;
    const PI = window.PermissionIntel;
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const formatUtc = App.formatUtc;
    const formatUtcFixed = (value) => App.formatUtc(value, { timeZone: "UTC" });
    const { formatCount, queueStatusBadge, queueStatusLabel, riskHint, riskReasonLabel } = PI;
    const queueStatuses = ["queued", "claimed", "applied", "error", "rejected", "skipped"];
    PermTriage$1.applyFilters = (rows, filters) => {
      const term = filters.term ? filters.term.toLowerCase() : "";
      const namespace = filters.namespace ? filters.namespace.toLowerCase() : "";
      const risk = filters.risk ? filters.risk.toLowerCase() : "";
      const status = filters.status ? filters.status.toLowerCase() : "";
      const allowedStatuses = Array.isArray(filters.allowedStatuses) ? filters.allowedStatuses.map((item) => String(item).toLowerCase()) : [];
      const queued = filters.queued ? filters.queued.toLowerCase() : "";
      return rows.filter((row) => {
        const perm = String(row.permission_string ?? "").toLowerCase();
        const ns = String(row.namespace ?? "").toLowerCase();
        const triageStatus = String(
          row.triage_status ?? row.dict_unknown_triage_status ?? row.review_lane_label ?? row.diagnostic_label ?? ""
        ).toLowerCase();
        const triageDisplay = String(
          row.triage_status_display ?? row.dict_unknown_triage_status_display ?? row.review_lane_label ?? row.diagnostic_label ?? ""
        ).toLowerCase();
        const queueStatus = String(row.queue_status ?? "").toLowerCase();
        const matchesTerm = term ? perm.includes(term) || ns.includes(term) || triageStatus.includes(term) || triageDisplay.includes(term) : true;
        const matchesNamespace = namespace ? ns === namespace : true;
        const hint = riskHint(row.permission_string, row.namespace);
        const matchesRisk = risk ? hint.label.toLowerCase() === risk : true;
        const matchesStatus = status ? triageStatus === status : allowedStatuses.length ? allowedStatuses.includes(triageStatus) : true;
        const matchesQueued = queued ? queueStatus === queued : true;
        return matchesTerm && matchesNamespace && matchesRisk && matchesStatus && matchesQueued;
      });
    };
    PermTriage$1.emptyMessage = (unknownRows, healthData) => {
      const total = Number(healthData.total_count ?? 0);
      const unknownCount = Number(healthData.unknown_count ?? 0);
      if (unknownCount > 0 && unknownRows.length === 0) {
        return "Current evidence exists, but no rows match the selected lane or filters.";
      }
      if (total === 0) {
        return "No permissions available yet. Last run may have processed no OK payloads.";
      }
      return "No unknown permissions detected - taxonomy fully covers current data.";
    };
    PermTriage$1.populateNamespaceFilter = (namespaceEl, rows, initialNamespace) => {
      if (!namespaceEl) return "";
      if (initialNamespace && !namespaceEl.value) {
        namespaceEl.value = initialNamespace;
      }
      const current = namespaceEl.value;
      const namespaces = Array.from(new Set(rows.map((row) => String(row.namespace || "").trim()))).filter((value) => value !== "").sort((a, b) => a.localeCompare(b));
      namespaceEl.innerHTML = '<option value="">All namespaces</option>';
      namespaces.forEach((ns) => {
        const opt = document.createElement("option");
        opt.value = ns;
        opt.textContent = ns;
        namespaceEl.appendChild(opt);
      });
      if (current && namespaces.includes(current)) {
        namespaceEl.value = current;
        return "";
      }
      return initialNamespace;
    };
    PermTriage$1.populateStatusFilter = (statusEl, triageStatuses) => {
      if (!statusEl) return;
      const current = statusEl.value;
      statusEl.innerHTML = '<option value="">All statuses</option>';
      triageStatuses.forEach((status) => {
        const opt = document.createElement("option");
        opt.value = status.key;
        opt.textContent = status.label;
        statusEl.appendChild(opt);
      });
      if (current) {
        statusEl.value = current;
      }
    };
    PermTriage$1.renderUnknownTable = (rows, options) => {
      const { bodyEl, triageStatusMap, onReview, onEvidence, onQueue, showQueue, emptyMessage } = options;
      if (!bodyEl) return;
      bodyEl.innerHTML = "";
      if (!Array.isArray(rows) || rows.length === 0) {
        bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage)}</td></tr>`;
        return;
      }
      rows.forEach((row) => {
        const risk = riskHint(row.permission_string, row.namespace);
        const triage = fmt(row.triage_status, "");
        const triageKey = triage.toLowerCase();
        const triageLabel = fmt(row.triage_status_display, "") || (triageStatusMap?.get(triageKey) ?? triage);
        const queueStatus = String(row.queue_status || "").toLowerCase();
        const badges = [];
        if (triage) {
          badges.push(`<span class="badge muted">${esc(triageLabel)}</span>`);
        }
        if (queueStatus) {
          const queueBadge = queueStatusBadge(queueStatus);
          badges.push(`<span class="${queueBadge.className}">${esc(queueBadge.label)}</span>`);
        } else {
          const queueBadge = queueStatusBadge("");
          badges.push(`<span class="${queueBadge.className}">${esc(queueBadge.label)}</span>`);
        }
        let appliedNote = "";
        if (queueStatus === "applied") {
          const appliedAt = row.queue_processed_at_utc || row.queue_updated_at_utc;
          if (appliedAt) {
            appliedNote = `<div class="muted" style="margin-top:4px;">Applied at ${esc(formatUtcFixed(appliedAt))} UTC</div>`;
          }
        }
        const triageHtml = badges.length ? badges.join(" ") + appliedNote : "--";
        const tr = document.createElement("tr");
        const permissionValue = fmt(row.permission_string, "");
        tr.dataset.permission = permissionValue;
        tr.classList.add("triage-row");
        if (triageKey === "new") {
          tr.classList.add("triage-row-new");
        }
        if (risk.label.toLowerCase() === "high") {
          tr.classList.add("triage-row-high-risk");
        }
        const queueButton = showQueue ? '<button class="btn btn-small btn-muted" type="button" data-action="queue">Queue</button>' : "";
        tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, "--"))}</div>
        </td>
        <td class="mono">${esc(fmt(row.namespace))}</td>
        <td class="triage-status-cell">${triageHtml}</td>
        <td>${esc(formatCount(row.seen_count))}</td>
        <td><span class="${risk.className}">${esc(risk.label)}</span><div class="muted">${esc(riskReasonLabel(row.risk_reason))}</div></td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : "--")}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small btn-primary" type="button" data-action="review">Review</button>
          <button class="btn btn-small btn-muted" type="button" data-action="evidence">Evidence</button>
          ${queueButton}
        </td>
      `;
        tr.querySelector('button[data-action="review"]')?.addEventListener("click", () => {
          if (typeof onReview === "function") onReview(row);
        });
        tr.querySelector('button[data-action="evidence"]')?.addEventListener("click", () => {
          if (typeof onEvidence === "function") onEvidence(row);
        });
        if (showQueue) {
          const queueBtn = tr.querySelector('button[data-action="queue"]');
          if (queueBtn) {
            queueBtn.addEventListener("click", () => {
              if (typeof onQueue === "function") onQueue(row);
            });
          }
        }
        bodyEl.appendChild(tr);
      });
    };
    PermTriage$1.renderCurrentEvidenceTable = (rows, options) => {
      const {
        bodyEl,
        onReview,
        onEvidence,
        onQueue,
        showQueue,
        emptyMessage,
        reviewLaneLabelMap
      } = options || {};
      if (!bodyEl) return;
      bodyEl.innerHTML = "";
      const list = Array.isArray(rows) ? rows : [];
      if (!list.length) {
        bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage || "No current evidence rows in this lane.")}</td></tr>`;
        return;
      }
      list.forEach((row) => {
        const risk = riskHint(row.permission_string, row.namespace);
        const laneKey = String(row.review_lane_label || row.dict_unknown_triage_status || "").toLowerCase();
        const laneLabel = reviewLaneLabelMap && reviewLaneLabelMap[laneKey] || row.review_lane_label || row.dict_unknown_triage_status || "--";
        const queueStatus = String(row.queue_status || "").toLowerCase();
        const actionMeta = currentEvidenceActionMeta(laneKey);
        const tr = document.createElement("tr");
        tr.dataset.permission = fmt(row.permission_string, "");
        tr.classList.add("triage-row");
        if (laneKey === "active_review_candidate") {
          tr.classList.add("triage-row-new");
        }
        if (risk.label.toLowerCase() === "high") {
          tr.classList.add("triage-row-high-risk");
        }
        const queueButton = showQueue ? '<button class="btn btn-small btn-muted" type="button" data-action="queue">Queue</button>' : "";
        let queueNote = "";
        if (queueStatus === "applied") {
          const appliedAt = row.queue_processed_at_utc || row.queue_updated_at_utc;
          if (appliedAt) {
            queueNote = `<div class="muted" style="margin-top:4px;">Applied at ${esc(formatUtcFixed(appliedAt))} UTC</div>`;
          }
        }
        tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, "--"))}</div>
        </td>
        <td class="triage-status-cell">${laneBadgeHtml(laneLabel)} ${queueBadgeHtml(queueStatus)}${queueNote}</td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(formatCount(row.current_unknown_obs_rows))}</td>
        <td><span class="${risk.className}">${esc(risk.label)}</span></td>
        <td>${esc(row.last_observed_at_utc ? formatUtc(row.last_observed_at_utc) : "--")}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small ${actionMeta.reviewClass}" type="button" data-action="review">${esc(actionMeta.reviewLabel)}</button>
          <button class="btn btn-small btn-muted" type="button" data-action="evidence">${esc(actionMeta.evidenceLabel)}</button>
          ${queueButton}
        </td>
      `;
        tr.querySelector('button[data-action="review"]')?.addEventListener("click", () => {
          if (typeof onReview === "function") onReview(row);
        });
        tr.querySelector('button[data-action="evidence"]')?.addEventListener("click", () => {
          if (typeof onEvidence === "function") onEvidence(row);
        });
        if (showQueue) {
          const queueBtn = tr.querySelector('button[data-action="queue"]');
          if (queueBtn) {
            queueBtn.addEventListener("click", () => {
              if (typeof onQueue === "function") onQueue(row);
            });
          }
        }
        bodyEl.appendChild(tr);
      });
    };
    PermTriage$1.renderLedgerDiagnosticsTable = (rows, options) => {
      const {
        bodyEl,
        onInspect,
        emptyMessage,
        diagnosticLabelMap
      } = options || {};
      if (!bodyEl) return;
      bodyEl.innerHTML = "";
      const list = Array.isArray(rows) ? rows : [];
      if (!list.length) {
        bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage || "No ledger diagnostic rows in the current lane.")}</td></tr>`;
        return;
      }
      list.forEach((row) => {
        const risk = riskHint(row.permission_string, row.namespace);
        const diagKey = String(row.diagnostic_label || "").toLowerCase();
        const diagLabel = diagnosticLabelMap && diagnosticLabelMap[diagKey] || row.diagnostic_label || "--";
        const queueStatus = String(row.queue_status || "").toLowerCase();
        const tr = document.createElement("tr");
        tr.dataset.permission = fmt(row.permission_string, "");
        tr.classList.add("triage-row");
        if (risk.label.toLowerCase() === "high") {
          tr.classList.add("triage-row-high-risk");
        }
        const evidenceParts = [];
        if (row.has_obs_sample) evidenceParts.push('<span class="badge ok">obs</span>');
        if (row.has_vt_event) evidenceParts.push('<span class="badge ok">vt</span>');
        if (!evidenceParts.length) evidenceParts.push('<span class="badge muted">no evidence</span>');
        tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, "--"))}</div>
        </td>
        <td class="triage-status-cell">${laneBadgeHtml(diagLabel)} ${queueBadgeHtml(queueStatus)}</td>
        <td>${esc(formatCount(row.historical_ledger_seen_count))}</td>
        <td>${evidenceParts.join(" ")}<div class="muted">${esc(riskReasonLabel(row.risk_reason))}</div></td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : "--")}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small btn-primary" type="button" data-action="inspect">Inspect</button>
        </td>
      `;
        tr.querySelector('button[data-action="inspect"]')?.addEventListener("click", () => {
          if (typeof onInspect === "function") onInspect(row);
        });
        bodyEl.appendChild(tr);
      });
    };
    PermTriage$1.queueStatuses = queueStatuses;
    PermTriage$1.statusLabel = statusLabel;
    PermTriage$1.queueStatusLabel = queueStatusLabel;
    PermTriage$1.renderFilterSummary = (summaryEl, filters, paging, triageStatusMap) => {
      if (!summaryEl) return;
      const chips = [];
      const viewMap = {
        active: "View: Active review",
        governed: "View: Governed current UNKNOWNs",
        ledger: "View: Ledger diagnostics"
      };
      if (filters.view) chips.push(viewMap[String(filters.view).toLowerCase()] || `View: ${filters.view}`);
      if (filters.term) chips.push(`Search: ${filters.term}`);
      if (filters.namespace) chips.push(`Namespace: ${filters.namespace}`);
      if (filters.risk) chips.push(`Risk: ${filters.risk}`);
      if (filters.status) chips.push(`Status: ${statusLabel(triageStatusMap, filters.status)}`);
      if (filters.queued) chips.push(`Queue: ${queueStatusLabel(filters.queued)}`);
      const viewModeMap = {
        active: "Mode: current evidence review",
        governed: "Mode: governed current UNKNOWNs",
        ledger: "Mode: ledger diagnostics"
      };
      if (filters.view && viewModeMap[String(filters.view).toLowerCase()]) {
        chips.push(viewModeMap[String(filters.view).toLowerCase()]);
      } else {
        chips.push(filters.includeResolved ? "Mode: expanded incl. resolved" : "Mode: current evidence review");
      }
      if (filters.sort && filters.sort !== "seen_desc") {
        const sortMap = {
          seen_asc: "Seen low -> high",
          last_seen_desc: "Last seen newest",
          last_seen_asc: "Last seen oldest",
          risk_desc: "Risk high -> low",
          risk_asc: "Risk low -> high",
          permission_asc: "Permission A -> Z",
          permission_desc: "Permission Z -> A",
          namespace_asc: "Namespace A -> Z",
          namespace_desc: "Namespace Z -> A"
        };
        chips.push(`Sort: ${sortMap[filters.sort] || filters.sort}`);
      }
      const total = Number(paging && paging.total_count || 0);
      const page = Number(paging && paging.page || 1);
      const totalPages = Math.max(1, Number(paging && paging.total_pages || 1));
      const head = `<span class="triage-filter-summary-label">Current view</span><span class="triage-filter-summary-meta muted">${total} rows across ${totalPages} page${totalPages === 1 ? "" : "s"}; page ${page}</span>`;
      if (!chips.length) {
        summaryEl.innerHTML = `${head}<div class="triage-chip-row"><span class="triage-chip triage-chip-default">Default actionable review queue</span></div>`;
        return;
      }
      summaryEl.innerHTML = `${head}<div class="triage-chip-row">${chips.map((chip) => `<span class="triage-chip">${esc(chip)}</span>`).join("")}</div>`;
    };
  }
  const PermTriage = window.PermTriage || (window.PermTriage = {});
  if (window.App && window.PermissionIntel) {
    const App = window.App;
    const PI = window.PermissionIntel;
    const formatUtc = App.formatUtc;
    const {
      formatCount,
      riskHint,
      resolveActionableReviewBacklog,
      resolveWorkflowUnknownBacklog,
      triagePriorityBucket
    } = PI;
    PermTriage.priorityScore = (row) => {
      const risk = riskHint(row.permission_string, row.namespace).label.toLowerCase();
      const status = String(row.triage_status ?? "").toLowerCase();
      const riskScore = risk === "high" ? 0 : risk === "medium" ? 1 : 2;
      const statusScore = typeof triagePriorityBucket === "function" ? triagePriorityBucket(status) : 3;
      return statusScore * 10 + riskScore;
    };
    PermTriage.findNextRow = (rows) => {
      if (!Array.isArray(rows) || rows.length === 0) return null;
      const currentEvidenceRows = rows.some(
        (row) => Object.prototype.hasOwnProperty.call(row || {}, "current_unknown_samples") || Object.prototype.hasOwnProperty.call(row || {}, "current_unknown_obs_rows")
      );
      if (currentEvidenceRows) {
        return rows[0] || null;
      }
      const candidates = rows.slice().sort((a, b) => PermTriage.priorityScore(a) - PermTriage.priorityScore(b));
      return candidates[0] || null;
    };
    PermTriage.renderSessionHeader = (triageStatusCounts, session, health, taxonomy, elements, metrics, operatorSummary, currentEvidenceRows) => {
      const hasOwn = (obj, key) => !!obj && Object.prototype.hasOwnProperty.call(obj, key);
      metrics && metrics.triage_status_counts || triageStatusCounts || {};
      const riskCounts = metrics && metrics.current_evidence_risk_counts || session && session.current_evidence_risk_counts || session && session.new_risk_counts || {};
      const summary = operatorSummary || {};
      const counts = {
        high: Number(riskCounts.high || 0),
        medium: Number(riskCounts.medium || 0),
        low: Number(riskCounts.low || 0),
        total: 0
      };
      const hasExplicitEvidenceCount = hasOwn(summary, "current_evidence_review_backlog") || hasOwn(session, "current_evidence_review_backlog") || hasOwn(metrics, "current_evidence_review_backlog");
      const evidenceCount = Number(
        hasOwn(summary, "current_evidence_review_backlog") ? summary.current_evidence_review_backlog : hasOwn(session, "current_evidence_review_backlog") ? session.current_evidence_review_backlog : hasOwn(metrics, "current_evidence_review_backlog") ? metrics.current_evidence_review_backlog : 0
      );
      if (hasExplicitEvidenceCount) {
        counts.total = evidenceCount;
      } else if (Array.isArray(currentEvidenceRows)) {
        counts.total = currentEvidenceRows.length;
      }
      if (!counts.total && !hasExplicitEvidenceCount) {
        counts.total = resolveActionableReviewBacklog(
          summary,
          session && {
            actionable_review_backlog: session.actionable_review_backlog,
            actionable_workflow_unknowns: session.actionable_workflow_unknowns
          },
          metrics,
          health
        );
      }
      if (!counts.total && !hasExplicitEvidenceCount) {
        counts.total = resolveWorkflowUnknownBacklog(
          summary,
          session && {
            workflow_unknown_backlog: session.workflow_unknown_backlog,
            effective_unknown_compat_legacy: session.unknown_total_effective
          },
          metrics,
          health
        );
      }
      if (elements.sessionHighEl) elements.sessionHighEl.textContent = formatCount(counts.high);
      if (elements.sessionMediumEl) elements.sessionMediumEl.textContent = formatCount(counts.medium);
      if (elements.sessionLowEl) elements.sessionLowEl.textContent = formatCount(counts.low);
      if (elements.sessionTotalEl) elements.sessionTotalEl.textContent = formatCount(counts.total);
      if (elements.sessionLastOkEl) {
        const lastObserved = health && health.last_observed_at_utc ? health.last_observed_at_utc : null;
        elements.sessionLastOkEl.textContent = lastObserved ? formatUtc(lastObserved) : "--";
      }
      if (elements.sessionTaxonomyEl) {
        elements.sessionTaxonomyEl.textContent = taxonomy.version ? String(taxonomy.version) : "--";
      }
      if (elements.sessionNoteEl) {
        const lastObserved = health && health.last_observed_at_utc ? health.last_observed_at_utc : null;
        const lastOkMs = App.parseUtcToMs(lastObserved);
        if (lastOkMs && Date.now() - lastOkMs > 24 * 60 * 60 * 1e3) {
          elements.sessionNoteEl.textContent = "No new permission observations in the last 24h. Pipeline may be paused.";
        } else {
          elements.sessionNoteEl.textContent = "Review current evidence-backed rows first, then governed current UNKNOWNs, then ledger diagnostics.";
        }
      }
    };
  }
  const root = document.getElementById("perm-triage-page");
  if (root && window.App && window.PermissionIntel && window.PermTriage) {
    const App = window.App;
    const PI = window.PermissionIntel;
    const PermTriage2 = window.PermTriage;
    const endpoint = root.dataset.intelEndpoint || "";
    const defaultLimit = root.dataset.limit || "100";
    const actionableStatusRaw = root.dataset.actionableStatuses || "[]";
    const resolvedStatusRaw = root.dataset.resolvedStatuses || "[]";
    const triageStatusRaw = root.dataset.triageStatuses || "[]";
    if (!endpoint) ;
    else {
      let setPageLoading = function(message) {
        if (loadingTextEl) loadingTextEl.textContent = message;
        if (loadingCardEl) loadingCardEl.style.display = "";
        if (shellContentEl) shellContentEl.classList.add("pi-shell-content-hidden");
      }, setPageReady = function() {
        if (loadingCardEl) loadingCardEl.style.display = "none";
        if (shellContentEl) shellContentEl.classList.remove("pi-shell-content-hidden");
        hasLoadedOnce = true;
      }, setPageError = function(message) {
        if (loadingTextEl) loadingTextEl.textContent = message;
        if (loadingCardEl) loadingCardEl.style.display = "";
        if (shellContentEl) shellContentEl.classList.add("pi-shell-content-hidden");
      }, updateUrl = function(limit, filters, paging) {
        const url = new URL(window.location.href);
        url.searchParams.set("limit", limit);
        url.searchParams.set("page_size", limit);
        url.searchParams.set("page", String(paging && paging.page || 1));
        url.searchParams.set("sort", String(paging && paging.sort || "seen_desc"));
        const view = filters && filters.view ? filters.view : "";
        const term = filters && filters.term ? filters.term : "";
        const namespace = filters && filters.namespace ? filters.namespace : "";
        const risk = filters && filters.risk ? filters.risk : "";
        const status = filters && filters.status ? filters.status : "";
        const queued = filters && filters.queued ? filters.queued : "";
        const showResolved = showResolvedEl && showResolvedEl.checked;
        if (term) {
          url.searchParams.set("q", term);
        } else {
          url.searchParams.delete("q");
        }
        if (view) {
          url.searchParams.set("view", view);
        } else {
          url.searchParams.delete("view");
        }
        if (namespace) {
          url.searchParams.set("namespace", namespace);
        } else {
          url.searchParams.delete("namespace");
        }
        if (risk) {
          url.searchParams.set("risk", risk);
        } else {
          url.searchParams.delete("risk");
        }
        if (status) {
          url.searchParams.set("status", status);
        } else {
          url.searchParams.delete("status");
        }
        if (queued) {
          url.searchParams.set("queued", queued);
        } else {
          url.searchParams.delete("queued");
        }
        if (showResolved) {
          url.searchParams.set("show_resolved", "1");
        } else {
          url.searchParams.delete("show_resolved");
        }
        window.history.replaceState({}, "", url.toString());
      }, parseTriageStatuses = function() {
        try {
          const parsed = JSON.parse(triageStatusRaw);
          triageStatuses.length = 0;
          if (Array.isArray(parsed)) {
            triageStatuses.push(...parsed);
          }
        } catch (_) {
          triageStatuses.length = 0;
        }
        triageStatusMap.clear();
        triageStatuses.forEach((status) => {
          const key = String(status.key || "").toLowerCase();
          if (key) {
            triageStatusMap.set(key, status.label || status.key);
          }
        });
      }, parseStatusList = function(raw, target) {
        target.length = 0;
        if (!raw) return;
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            parsed.forEach((item) => {
              const key = String(item || "").toLowerCase();
              if (key && !target.includes(key)) {
                target.push(key);
              }
            });
          }
        } catch (_) {
        }
      }, selectedReviewLane = function() {
        const value = reviewLaneEl ? String(reviewLaneEl.value || "").toLowerCase() : "";
        return value || "active";
      }, allowedStatuses = function() {
        const selected = statusEl ? statusEl.value : "";
        if (selected) return [];
        if (selectedReviewLane() !== "active") {
          return [];
        }
        const list = actionableStatuses.slice();
        if (showResolvedEl && showResolvedEl.checked) {
          resolvedStatuses.forEach((status) => {
            if (!list.includes(status)) list.push(status);
          });
        }
        return list;
      }, currentFilters = function() {
        return {
          term: searchEl ? searchEl.value.trim() : "",
          view: selectedReviewLane(),
          namespace: namespaceEl ? namespaceEl.value : "",
          risk: riskEl ? riskEl.value : "",
          status: statusEl ? statusEl.value : "",
          allowedStatuses: allowedStatuses(),
          queued: queuedEl ? queuedEl.value : "",
          includeResolved: showResolvedEl ? showResolvedEl.checked : false,
          sort: sortEl ? sortEl.value : "seen_desc"
        };
      }, reviewUrl = function(permission) {
        return pageUrl("permissions_review", {
          permission: permission || "",
          return: currentPageUrl()
        });
      }, evidenceUrl = function(permission) {
        return pageUrl("permissions_evidence", {
          permission: permission || "",
          return: currentPageUrl()
        });
      }, viewLabel = function(view) {
        return REVIEW_LANE_LABELS[String(view).toLowerCase()] || REVIEW_LANE_LABELS.active;
      }, selectedRows = function(view) {
        const lane = String(view || selectedReviewLane()).toLowerCase();
        if (lane === "governed") return governedReviewRows;
        if (lane === "ledger") return ledgerDiagnosticRows;
        return activeReviewRows;
      }, selectedPage = function(view) {
        const lane = String(view || selectedReviewLane()).toLowerCase();
        if (lane === "governed") return governedPage;
        if (lane === "ledger") return ledgerPage;
        return activePage;
      }, renderLaneHeaders = function(view) {
        if (!tableHeadEl) return;
        const lane = String(view).toLowerCase();
        if (lane === "ledger") {
          tableHeadEl.innerHTML = `
        <tr>
          <th>Permission name</th>
          <th>Diagnostic</th>
          <th>Historical ledger seen</th>
          <th>Evidence</th>
          <th>Current UNKNOWN samples</th>
          <th>Ledger last seen</th>
          <th>Action</th>
        </tr>
      `;
          return;
        }
        if (lane === "governed") {
          tableHeadEl.innerHTML = `
        <tr>
          <th>Permission name</th>
          <th>Lane</th>
          <th>Current governed samples</th>
          <th>Current governed obs</th>
          <th>Risk hint</th>
          <th>Last observed</th>
          <th>Action</th>
        </tr>
      `;
          return;
        }
        tableHeadEl.innerHTML = `
      <tr>
        <th>Permission name</th>
        <th>Lane</th>
        <th>Current UNKNOWN samples</th>
        <th>Current UNKNOWN obs</th>
        <th>Risk hint</th>
        <th>Last observed</th>
        <th>Action</th>
      </tr>
    `;
      }, updateReviewLaneNote = function(view) {
        if (!reviewLaneNoteEl) return;
        const lane = String(view).toLowerCase();
        if (lane === "governed") {
          reviewLaneNoteEl.textContent = "Governed current UNKNOWNs are already explained, dictionary-known, malformed, or missing ledger context. The counts here are current sample/observation footprint for governed residue, not live active-UNKNOWN backlog.";
          return;
        }
        if (lane === "ledger") {
          reviewLaneNoteEl.textContent = "Ledger diagnostics are historical workflow context. Treat high seen_count as residue pressure, then confirm real current-sample behavior in Evidence or Fusion before acting.";
          return;
        }
        reviewLaneNoteEl.textContent = "Active review is evidence-backed by default. Switch lanes to inspect governed rows or ledger diagnostics.";
      }, renderCurrentLane = function(filters) {
        const lane = selectedReviewLane();
        const rows = selectedRows(lane);
        const filtered = PermTriage2.applyFilters(rows, filters);
        const hasExplicitFilter = Boolean(filters.term || filters.namespace || filters.risk || filters.status || filters.queued);
        const pageForLane = selectedPage(lane);
        let emptyMessage = `No ${viewLabel(lane).toLowerCase()} rows in the current snapshot.`;
        if (hasExplicitFilter) {
          emptyMessage = "No permissions match the current filters.";
        }
        if (lane === "active" && !filters.includeResolved && !hasExplicitFilter) {
          const governedCount = Number(governedPage && governedPage.total_count || 0);
          if (governedCount > 0) {
            emptyMessage = `No current evidence-backed review rows in the active lane. ${PI.formatCount(governedCount)} governed current UNKNOWN row${governedCount === 1 ? " remains" : "s remain"}; switch to Governed current UNKNOWNs.`;
          } else {
            emptyMessage = "No current evidence-backed review rows in the active lane.";
          }
        }
        if (lane === "governed" && !hasExplicitFilter) {
          emptyMessage = "No governed current UNKNOWN rows in the current snapshot.";
        }
        if (lane === "ledger" && !hasExplicitFilter) {
          emptyMessage = "No ledger diagnostic rows in the current snapshot.";
        }
        renderLaneHeaders(lane);
        updateReviewLaneNote(lane);
        if (lane === "ledger") {
          PermTriage2.renderLedgerDiagnosticsTable(filtered, {
            bodyEl: unknownBody,
            diagnosticLabelMap: DIAGNOSTIC_LABELS,
            emptyMessage,
            onInspect: (row) => {
              const permission = fmt(row.permission_string, "");
              if (!permission) return;
              try {
                window.localStorage.setItem(storageKey, permission);
              } catch (_) {
              }
              window.location.href = reviewUrl(permission);
            }
          });
        } else {
          PermTriage2.renderCurrentEvidenceTable(filtered, {
            bodyEl: unknownBody,
            reviewLaneLabelMap: REVIEW_LANE_RENDER_LABELS,
            emptyMessage,
            showQueue: false,
            onReview: (row) => {
              const permission = fmt(row.permission_string, "");
              if (!permission) return;
              try {
                window.localStorage.setItem(storageKey, permission);
              } catch (_) {
              }
              window.location.href = reviewUrl(permission);
            },
            onEvidence: (row) => {
              const permission = fmt(row.permission_string, "");
              if (!permission) return;
              window.location.href = evidenceUrl(permission);
            }
          });
        }
        if (PermTriage2.renderFilterSummary) {
          PermTriage2.renderFilterSummary(filterSummaryEl, { ...filters, view: lane }, pageForLane, triageStatusMap);
        }
        pageMeta = pageForLane || pageMeta;
      }, applyInitialFilters = function() {
        if (reviewLaneEl && initialView) {
          const lane = String(initialView || "").toLowerCase();
          if (["active", "governed", "ledger"].includes(lane)) {
            reviewLaneEl.value = lane;
          }
        }
        if (statusEl && initialStatus) {
          statusEl.value = initialStatus;
          if (showResolvedEl) {
            const statusKey = initialStatus.toLowerCase();
            if (resolvedStatuses.includes(statusKey)) {
              showResolvedEl.checked = true;
            }
          }
        }
        if (riskEl && initialRisk) {
          riskEl.value = initialRisk;
        }
        if (queuedEl && initialQueued) {
          queuedEl.value = initialQueued;
        }
        if (sortEl && initialSort) {
          sortEl.value = initialSort;
        }
        if (searchEl && initialSearch) {
          searchEl.value = initialSearch;
        }
        if (showResolvedEl) {
          showResolvedEl.checked = initialShowResolved || showResolvedEl.checked;
        }
        pageMeta = selectedPage(initialView);
        pageMeta.page = initialPage;
      }, renderPagingControls = function() {
        const page = Number(pageMeta.page || 1);
        const totalPages = Math.max(1, Number(pageMeta.total_pages || 1));
        const total = Number(pageMeta.total_count || 0);
        const pageSize = Number(pageMeta.page_size || (limitEl ? Number(limitEl.value || defaultLimit) : Number(defaultLimit)));
        const start = total > 0 ? (page - 1) * pageSize + 1 : 0;
        const end = Math.min(total, page * pageSize);
        if (pageInfoEl) pageInfoEl.textContent = `Page ${page}/${totalPages} | Rows ${start}-${end} of ${total}`;
        if (pagePrevBtn) pagePrevBtn.disabled = page <= 1;
        if (pageNextBtn) pageNextBtn.disabled = page >= totalPages || !pageMeta.has_more;
      }, readLastReviewedPermission = function() {
        try {
          return window.localStorage.getItem(storageKey) || "";
        } catch (_) {
          return "";
        }
      }, setButtonEnabled = function(button, enabled, disabledTitle = "") {
        if (!button) return;
        button.disabled = !enabled;
        if (!enabled && disabledTitle) {
          button.setAttribute("title", disabledTitle);
          return;
        }
        button.removeAttribute("title");
      }, updateSessionControlState = function(activeRows) {
        const rows = Array.isArray(activeRows) ? activeRows : [];
        const hasActiveRows = rows.length > 0;
        const hasHighRiskNew = rows.some((row) => {
          const status = String(row.dict_unknown_triage_status || row.triage_status || "").toLowerCase();
          const risk = PI.riskHint(row.permission_string, row.namespace);
          return status === "new" && String(risk.label || "").toLowerCase() === "high";
        });
        const lastReviewed = readLastReviewedPermission();
        setButtonEnabled(
          sessionStartHighBtn,
          hasHighRiskNew,
          "No high-risk current evidence rows are available in the active lane."
        );
        setButtonEnabled(
          sessionReviewNextBtn,
          hasActiveRows,
          "No current evidence review rows are available in the active lane."
        );
        setButtonEnabled(
          sessionResumeBtn,
          Boolean(lastReviewed),
          "No previously reviewed permission is stored for this browser session."
        );
      }, openNextFromCurrentPage = function() {
        if (!Array.isArray(filteredRows) || !filteredRows.length) return;
        const next = PermTriage2.findNextRow(filteredRows);
        if (next) {
          window.location.href = reviewUrl(fmt(next.permission_string, ""));
        }
      };
      const limitEl = document.getElementById("perm-unknown-limit");
      const searchEl = document.getElementById("perm-unknown-search");
      const namespaceEl = document.getElementById("perm-unknown-namespace");
      const riskEl = document.getElementById("perm-unknown-risk");
      const statusEl = document.getElementById("perm-unknown-status");
      const reviewLaneEl = document.getElementById("perm-review-lane");
      const showResolvedEl = document.getElementById("perm-unknown-show-resolved");
      const queuedEl = document.getElementById("perm-unknown-queued");
      const sortEl = document.getElementById("perm-unknown-sort");
      const quickHighNewBtn = document.getElementById("perm-quick-high-new");
      const quickOemBtn = document.getElementById("perm-quick-oem");
      const quickQueuedBtn = document.getElementById("perm-quick-queued");
      const quickResetBtn = document.getElementById("perm-quick-reset");
      const pagePrevBtn = document.getElementById("perm-page-prev");
      const pageNextBtn = document.getElementById("perm-page-next");
      const pageInfoEl = document.getElementById("perm-page-info");
      const filterSummaryEl = document.getElementById("perm-filter-summary");
      const unknownBody = document.getElementById("perm-unknown-body");
      const errorEl = document.getElementById("perm-triage-error");
      const sessionHighEl = document.getElementById("perm-session-high");
      const sessionMediumEl = document.getElementById("perm-session-medium");
      const sessionLowEl = document.getElementById("perm-session-low");
      const sessionTotalEl = document.getElementById("perm-session-total");
      const sessionLastOkEl = document.getElementById("perm-session-last-ok");
      const sessionTaxonomyEl = document.getElementById("perm-session-taxonomy");
      const sessionNoteEl = document.getElementById("perm-session-note");
      const queueTotalEl = document.getElementById("perm-queue-total");
      const queueLastEl = document.getElementById("perm-queue-last");
      const queueAppliedEl = document.getElementById("perm-queue-applied");
      const queueAppliedCountEl = document.getElementById("perm-queue-applied-count");
      const queueErrorCountEl = document.getElementById("perm-queue-error-count");
      const queueErrorEl = document.getElementById("perm-queue-error");
      const sessionStartHighBtn = document.getElementById("perm-session-start-high");
      const sessionReviewNextBtn = document.getElementById("perm-session-review-next");
      const sessionResumeBtn = document.getElementById("perm-session-resume");
      const messageEl = document.getElementById("perm-triage-message");
      const shellContentEl = document.getElementById("perm-triage-shell-content");
      const loadingCardEl = document.getElementById("perm-triage-loading-card");
      const loadingTextEl = document.getElementById("perm-triage-loading-text");
      const tableHeadEl = document.querySelector("#perm-unknown-table thead");
      const reviewLaneNoteEl = document.getElementById("perm-review-lane-note");
      const esc = App.escapeHtml;
      const fmt = App.fmt;
      const pageUrl = App.pageUrl;
      const currentPageUrl = App.currentPageUrl;
      const REVIEW_LANE_LABELS = {
        active: "Active review",
        governed: "Governed current UNKNOWNs",
        ledger: "Ledger diagnostics"
      };
      const REVIEW_LANE_RENDER_LABELS = {
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
      let activeReviewRows = [];
      let governedReviewRows = [];
      let ledgerDiagnosticRows = [];
      let filteredRows = [];
      let activePage = { page: 1, page_size: Number(defaultLimit), total_count: 0, total_pages: 1, has_more: false };
      let governedPage = { page: 1, page_size: Number(defaultLimit), total_count: 0, total_pages: 1, has_more: false };
      let ledgerPage = { page: 1, page_size: Number(defaultLimit), total_count: 0, total_pages: 1, has_more: false };
      let pageMeta = activePage;
      let healthData = {};
      const triageStatuses = [];
      const triageStatusMap = /* @__PURE__ */ new Map();
      const actionableStatuses = [];
      const resolvedStatuses = [];
      const storageKey = "perm-triage-last";
      let initialView = "active";
      let initialNamespace = "";
      let initialStatus = "";
      let initialRisk = "";
      let initialQueued = "";
      let initialSort = "seen_desc";
      let initialPage = 1;
      let initialSearch = "";
      let initialShowResolved = false;
      let hasLoadedOnce = false;
      try {
        const params = new URLSearchParams(window.location.search);
        initialView = params.get("view") || params.get("lane") || "active";
        initialNamespace = params.get("namespace") || "";
        initialStatus = params.get("status") || "";
        initialRisk = params.get("risk") || "";
        const queuedParam = params.get("queued") || "";
        const queueStatuses = Array.isArray(PermTriage2.queueStatuses) ? PermTriage2.queueStatuses : ["queued", "claimed", "applied", "error", "rejected", "skipped"];
        if (queueStatuses.includes(queuedParam.toLowerCase())) {
          initialQueued = queuedParam;
        }
        const initialPageParam = Number(params.get("page") || "1");
        if (Number.isFinite(initialPageParam) && initialPageParam > 0) {
          initialPage = Math.floor(initialPageParam);
          pageMeta.page = initialPage;
        }
        const sortParam = params.get("sort") || "";
        if (sortParam) {
          initialSort = sortParam;
        }
        initialSearch = params.get("q") || params.get("search") || "";
        const showResolvedParam = params.get("show_resolved") || "";
        initialShowResolved = showResolvedParam === "1" || showResolvedParam.toLowerCase() === "true";
        const saved = params.get("saved");
        const queued = params.get("queued");
        if (saved && messageEl) {
          const queuedBanner = queued === "1" || queued === "true";
          messageEl.textContent = queuedBanner ? "Decision saved. Dictionary update queued for maintenance review." : "Decision saved. Review queue updated.";
          messageEl.style.display = "block";
        }
      } catch (_) {
        initialNamespace = "";
      }
      async function loadTriage(options = {}) {
        const opts = options || {};
        if (opts.resetPage) {
          pageMeta.page = 1;
        }
        const limit = limitEl ? limitEl.value || defaultLimit : defaultLimit;
        const filters = currentFilters();
        updateUrl(limit, filters, { page: pageMeta.page, sort: filters.sort || "seen_desc" });
        errorEl.textContent = "";
        if (!hasLoadedOnce) {
          setPageLoading("Loading triage workspace...");
        }
        unknownBody.innerHTML = '<tr><td colspan="7" class="muted">Loading review lane...</td></tr>';
        try {
          const qs = new URLSearchParams();
          qs.set("limit", String(limit));
          qs.set("page_size", String(limit));
          qs.set("page", String(pageMeta.page || 1));
          qs.set("sort", String(filters.sort || "seen_desc"));
          qs.set("include_resolved", filters.includeResolved ? "1" : "0");
          if (filters.term) qs.set("q", filters.term);
          if (filters.namespace) qs.set("namespace", filters.namespace);
          if (filters.risk) qs.set("risk", filters.risk);
          if (filters.status) qs.set("status", filters.status);
          if (filters.queued) qs.set("queued", filters.queued);
          if (filters.view) qs.set("view", filters.view);
          qs.set("mode", "triage");
          const url = new URL(endpoint, window.location.origin);
          url.search = qs.toString();
          const res = await App.fetchJson(url.toString());
          if (!res.ok) {
            if (!hasLoadedOnce) {
              setPageError("Failed to load triage workspace.");
            }
            errorEl.innerHTML = "<pre>Permission triage error.\n\nHTTP " + res.status + "\nerror: " + esc(res.error) + "</pre>";
            return;
          }
          const data = res.body.data || {};
          activeReviewRows = Array.isArray(data.current_evidence_review_rows) ? data.current_evidence_review_rows : [];
          governedReviewRows = Array.isArray(data.governed_current_unknown_rows) ? data.governed_current_unknown_rows : [];
          ledgerDiagnosticRows = Array.isArray(data.ledger_diagnostic_rows) ? data.ledger_diagnostic_rows : [];
          activePage = data.current_evidence_review_page || activePage;
          governedPage = data.governed_current_unknown_page || governedPage;
          ledgerPage = data.ledger_diagnostic_page || ledgerPage;
          pageMeta = selectedPage(filters.view);
          healthData = data.health || {};
          PermTriage2.populateStatusFilter(statusEl, triageStatuses);
          const laneRows = selectedRows(filters.view);
          const activeSnapshotFilters = {
            ...filters,
            view: "active"
          };
          const activeSnapshotRows = PermTriage2.applyFilters(activeReviewRows, activeSnapshotFilters);
          filteredRows = activeSnapshotRows;
          updateSessionControlState(activeSnapshotRows);
          initialNamespace = PermTriage2.populateNamespaceFilter(namespaceEl, laneRows, initialNamespace);
          const riskCounts = activeSnapshotRows.reduce((acc, row) => {
            const hint = PI.riskHint(row.permission_string, row.namespace);
            const key = String(hint.label || "").toLowerCase();
            if (key === "high" || key === "medium" || key === "low") {
              acc[key] = (acc[key] || 0) + 1;
            }
            return acc;
          }, { high: 0, medium: 0, low: 0 });
          PermTriage2.renderSessionHeader(data.triage_status_counts || {}, data.session || {}, data.health || {}, data.taxonomy || {}, {
            sessionHighEl,
            sessionMediumEl,
            sessionLowEl,
            sessionTotalEl,
            sessionLastOkEl,
            sessionTaxonomyEl,
            sessionNoteEl
          }, {
            ...data.metrics || {},
            current_evidence_risk_counts: riskCounts
          }, data.operator_summary || {}, activeSnapshotRows);
          const queue = data.queue || {};
          if (queueTotalEl) queueTotalEl.textContent = PI.formatCount(queue.queued_current_unknown_count ?? queue.queued_count ?? 0);
          if (queueLastEl) {
            const lastCurrentQueued = queue.last_current_unknown_queued_at_utc || null;
            queueLastEl.textContent = lastCurrentQueued ? App.formatUtc(lastCurrentQueued) : "--";
          }
          if (queueAppliedCountEl) queueAppliedCountEl.textContent = PI.formatCount(queue.applied_count || 0);
          if (queueAppliedEl) queueAppliedEl.textContent = queue.last_applied_at_utc ? App.formatUtc(queue.last_applied_at_utc) : "--";
          if (queueErrorCountEl) queueErrorCountEl.textContent = PI.formatCount(queue.error_count || 0);
          if (queueErrorEl) queueErrorEl.textContent = queue.last_error_at_utc ? App.formatUtc(queue.last_error_at_utc) : "--";
          renderCurrentLane(filters);
          renderPagingControls();
          if (opts.autoOpenNext) {
            openNextFromCurrentPage();
          }
          if (!hasLoadedOnce) {
            setPageReady();
          }
        } catch (e) {
          if (!hasLoadedOnce) {
            setPageError("Failed to load triage workspace.");
          }
          errorEl.innerHTML = "<pre>Permission triage error:\n" + esc(e && e.message ? e.message : String(e)) + "</pre>";
        }
      }
      if (limitEl) {
        limitEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (searchEl) {
        searchEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (namespaceEl) {
        namespaceEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (riskEl) {
        riskEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (statusEl) {
        statusEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (reviewLaneEl) {
        reviewLaneEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (showResolvedEl) {
        showResolvedEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (queuedEl) {
        queuedEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (sortEl) {
        sortEl.addEventListener("change", () => loadTriage({ resetPage: true }));
      }
      if (pagePrevBtn) {
        pagePrevBtn.addEventListener("click", () => {
          if (Number(pageMeta.page || 1) <= 1) return;
          pageMeta.page = Number(pageMeta.page || 1) - 1;
          loadTriage({ resetPage: false });
        });
      }
      if (pageNextBtn) {
        pageNextBtn.addEventListener("click", () => {
          const page = Number(pageMeta.page || 1);
          const totalPages = Math.max(1, Number(pageMeta.total_pages || 1));
          if (page >= totalPages) return;
          pageMeta.page = page + 1;
          loadTriage({ resetPage: false });
        });
      }
      if (quickHighNewBtn) {
        quickHighNewBtn.addEventListener("click", () => {
          if (reviewLaneEl) reviewLaneEl.value = "active";
          if (statusEl) statusEl.value = "new";
          if (riskEl) riskEl.value = "high";
          if (showResolvedEl) showResolvedEl.checked = false;
          if (namespaceEl) namespaceEl.value = "";
          if (queuedEl) queuedEl.value = "";
          if (searchEl) searchEl.value = "";
          loadTriage({ resetPage: true });
        });
      }
      if (quickOemBtn) {
        quickOemBtn.addEventListener("click", () => {
          if (reviewLaneEl) reviewLaneEl.value = "active";
          if (statusEl) statusEl.value = "oem_candidate";
          if (showResolvedEl) showResolvedEl.checked = false;
          if (riskEl) riskEl.value = "";
          if (namespaceEl) namespaceEl.value = "";
          if (queuedEl) queuedEl.value = "";
          if (searchEl) searchEl.value = "";
          loadTriage({ resetPage: true });
        });
      }
      if (quickQueuedBtn) {
        quickQueuedBtn.addEventListener("click", () => {
          if (reviewLaneEl) reviewLaneEl.value = "active";
          if (queuedEl) queuedEl.value = "queued";
          if (showResolvedEl) showResolvedEl.checked = true;
          if (statusEl) statusEl.value = "";
          if (riskEl) riskEl.value = "";
          if (namespaceEl) namespaceEl.value = "";
          if (searchEl) searchEl.value = "";
          loadTriage({ resetPage: true });
        });
      }
      if (quickResetBtn) {
        quickResetBtn.addEventListener("click", () => {
          if (reviewLaneEl) reviewLaneEl.value = "active";
          if (statusEl) statusEl.value = "";
          if (showResolvedEl) showResolvedEl.checked = false;
          if (riskEl) riskEl.value = "";
          if (namespaceEl) namespaceEl.value = "";
          if (queuedEl) queuedEl.value = "";
          if (searchEl) searchEl.value = "";
          if (sortEl) sortEl.value = "seen_desc";
          loadTriage({ resetPage: true });
        });
      }
      if (sessionStartHighBtn) {
        sessionStartHighBtn.addEventListener("click", async () => {
          if (reviewLaneEl) reviewLaneEl.value = "active";
          if (riskEl) riskEl.value = "high";
          if (statusEl) statusEl.value = "new";
          if (namespaceEl) namespaceEl.value = "";
          if (searchEl) searchEl.value = "";
          await loadTriage({ resetPage: true, autoOpenNext: true });
        });
      }
      if (sessionReviewNextBtn) {
        sessionReviewNextBtn.addEventListener("click", () => {
          openNextFromCurrentPage();
        });
      }
      if (sessionResumeBtn) {
        sessionResumeBtn.addEventListener("click", () => {
          const last = readLastReviewedPermission();
          if (!last) return;
          window.location.href = reviewUrl(last);
        });
      }
      parseTriageStatuses();
      parseStatusList(actionableStatusRaw, actionableStatuses);
      parseStatusList(resolvedStatusRaw, resolvedStatuses);
      PermTriage2.populateStatusFilter(statusEl, triageStatuses);
      applyInitialFilters();
      updateSessionControlState([]);
      setPageLoading("Loading triage workspace...");
      void loadTriage({ resetPage: false });
    }
  }
})();
