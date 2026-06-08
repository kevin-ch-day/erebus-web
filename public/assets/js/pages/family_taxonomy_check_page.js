(function() {
  "use strict";
  const root = document.getElementById("family-taxonomy-page");
  function asRows(value) {
    return Array.isArray(value) ? value : [];
  }
  function toRecord(value) {
    return value && typeof value === "object" ? value : {};
  }
  function mapEntries(value) {
    return Object.entries(toRecord(value));
  }
  if (root && window.App) {
    const pageRoot = root;
    const App = window.App;
    const endpoint = pageRoot.dataset.endpoint || "";
    if (endpoint) {
      let badgeClassForAlignment = function(value) {
        const key = String(value || "").toLowerCase();
        if (key === "aligned") return "badge ok";
        if (key === "mismatch") return "badge err";
        if (key === "signal_only" || key === "catalog_only") return "badge warn";
        if (key === "semantic_conflict" || key === "placeholder_catalog") return "badge err";
        if (key === "alias_resolved") return "badge ok";
        if (key === "alias_candidate" || key === "generic_signal" || key === "short_signal_token") return "badge warn";
        return "badge muted";
      }, badgeClassForSeverity = function(value) {
        const key = String(value || "").toLowerCase();
        if (key === "critical") return "badge err";
        if (key === "warn") return "badge warn";
        if (key === "info") return "badge muted";
        return "badge muted";
      }, pageLink = function(page, params = {}) {
        if (!App.pageUrl) return "#";
        return App.pageUrl(page, params);
      }, queueParams = function(extra = {}) {
        const params = {
          limit: limitSelect ? limitSelect.value : pageRoot.dataset.limit || "100"
        };
        if (alignmentSelect?.value) params.alignment = alignmentSelect.value;
        if (platformSelect?.value) params.platform = platformSelect.value;
        if (patternSelect?.value) params.pattern = patternSelect.value;
        if (searchInput?.value.trim()) params.q = searchInput.value.trim();
        Object.entries(extra).forEach(([key, value]) => {
          if (value === "" || value == null) delete params[key];
          else params[key] = value;
        });
        return params;
      }, renderSummary = function(rows) {
        if (!summaryEl) return;
        if (!rows.length) {
          summaryEl.innerHTML = '<div class="detail-card"><div class="muted">No family taxonomy summary rows found.</div></div>';
          return;
        }
        summaryEl.innerHTML = rows.map((row) => `
        <div class="detail-card">
          <div class="detail-card-title"><span class="${badgeClassForAlignment(row.alignment_status)}">${esc(row.alignment_status || "--")}</span></div>
          <div class="detail-row">
            <div class="detail-label">Rows</div>
            <div class="detail-value">${esc(fmt(row.row_count))}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Generic labels</div>
            <div class="detail-value">${esc(fmt(row.generic_label_count))}</div>
          </div>
        </div>
      `).join("");
      }, renderPresets = function(presets) {
        if (!presetsEl) return;
        if (!presets.length) {
          presetsEl.innerHTML = '<div class="detail-card"><div class="muted">No actionable queue presets available.</div></div>';
          return;
        }
        presetsEl.innerHTML = presets.map((preset) => {
          const href = pageLink("family_taxonomy_queue", queueParams({
            alignment: preset.alignment || void 0,
            pattern: preset.pattern || void 0,
            decision_mode: preset.decision_mode || void 0
          }));
          const buttonClass = preset.button_tone === "primary" ? "btn btn-primary" : "btn";
          return `
          <div class="detail-card">
            <div class="detail-card-title">${esc(preset.title || "--")}</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(preset.count || 0))}</div></div>
            <p class="muted">${esc(preset.description || "--")}</p>
            <div style="margin-top: 10px;">
              <a class="${buttonClass}" href="${esc(href)}">${esc(preset.button_label || "Open queue")}</a>
            </div>
          </div>
        `;
        }).join("");
      }, renderOperationalSplit = function(inventory) {
        if (!operationalEl) return;
        const modeCounts = mapEntries(inventory.decision_mode_counts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0));
        const priorityCounts = mapEntries(inventory.decision_priority_counts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0));
        const currentLimit = limitSelect ? limitSelect.value : "100";
        if (!modeCounts.length) {
          operationalEl.innerHTML = '<div class="detail-card"><div class="muted">No operational split available.</div></div>';
          return;
        }
        const modeList = modeCounts.map(([label, count]) => {
          const href = pageLink("family_taxonomy_queue", {
            limit: currentLimit,
            decision_mode: label
          });
          return `<li><a class="table-link" href="${esc(href)}">${esc(label)}</a> <span class="muted">(${esc(fmt(count))})</span></li>`;
        }).join("");
        const priorityList = priorityCounts.map(([label, count]) => `<li>${esc(label)} <span class="muted">(${esc(fmt(count))})</span></li>`).join("");
        operationalEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Decision modes</div>
          <div class="detail-row"><div class="detail-label">Rows in slice</div><div class="detail-value">${esc(fmt(inventory.total_rows || 0))}</div></div>
          <ul class="maintenance-list">${modeList}</ul>
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Priority bands</div>
          <ul class="maintenance-list">${priorityList}</ul>
        </div>
      `;
      }, renderPlatformScope = function(inventory) {
        if (!platformEl) return;
        const platformCounts = mapEntries(inventory.platform_counts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 6);
        if (!platformCounts.length) {
          platformEl.innerHTML = '<div class="detail-card"><div class="muted">No platform split available.</div></div>';
          return;
        }
        const platformCards = platformCounts.map(([platform, count]) => {
          const alignmentCounts = toRecord(toRecord(inventory.platform_alignment_counts)[platform]);
          const decisionCounts = toRecord(toRecord(inventory.platform_decision_counts)[platform]);
          const heldMismatch = Number(toRecord(inventory.platform_held_mismatch_counts)[platform] || 0);
          const repairNow = Number(toRecord(inventory.platform_repair_now_counts)[platform] || 0);
          const topAlignment = mapEntries(alignmentCounts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 3).map(([label, value]) => `${label}=${fmt(value)}`).join(", ") || "--";
          const topDecision = mapEntries(decisionCounts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 2).map(([label, value]) => `${label}=${fmt(value)}`).join(", ") || "--";
          return `
          <div class="detail-card">
            <div class="detail-card-title">${esc(platform)}</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(count))}</div></div>
            <div class="detail-row"><div class="detail-label">Top alignments</div><div class="detail-value">${esc(topAlignment)}</div></div>
            <div class="detail-row"><div class="detail-label">Top decisions</div><div class="detail-value">${esc(topDecision)}</div></div>
            <div class="detail-row"><div class="detail-label">Held mismatches</div><div class="detail-value">${esc(fmt(heldMismatch))}</div></div>
            <div class="detail-row"><div class="detail-label">Repair-now</div><div class="detail-value">${esc(fmt(repairNow))}</div></div>
          </div>
        `;
        }).join("");
        platformEl.innerHTML = platformCards;
      }, renderAskWhyBreakdown = function(inventory) {
        if (!askWhyEl) return;
        const issueCounts = mapEntries(inventory.issue_kind_counts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 8);
        const platformCounts = mapEntries(inventory.platform_counts).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 5);
        const issuePlatformCounts = toRecord(inventory.issue_platform_counts);
        const currentLimit = limitSelect ? limitSelect.value : "100";
        const currentPlatform = platformSelect ? platformSelect.value : "";
        if (!issueCounts.length) {
          askWhyEl.innerHTML = '<div class="detail-card"><div class="muted">No ask-why-first rows in the current slice.</div></div>';
          return;
        }
        const issueList = issueCounts.map(([label, count]) => {
          const platformMix = mapEntries(toRecord(issuePlatformCounts[label])).sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0)).slice(0, 3).map(([platform, value]) => `${platform}=${fmt(value)}`).join(", ") || "--";
          const href = pageLink("family_taxonomy_queue", {
            limit: currentLimit,
            decision_mode: "ask_why_first",
            pattern: label,
            platform: currentPlatform || void 0
          });
          return `<li><a class="table-link" href="${esc(href)}">${esc(label)}</a> <span class="muted">(${esc(fmt(count))}; ${esc(platformMix)})</span></li>`;
        }).join("");
        const platformList = platformCounts.map(([label, count]) => {
          const href = pageLink("family_taxonomy_queue", {
            limit: currentLimit,
            decision_mode: "ask_why_first",
            platform: label
          });
          return `<li><a class="table-link" href="${esc(href)}">${esc(label)}</a> <span class="muted">(${esc(fmt(count))})</span></li>`;
        }).join("");
        askWhyEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Issue kinds inside ask-why-first</div>
          <div class="detail-row"><div class="detail-label">Rows in lane</div><div class="detail-value">${esc(fmt(inventory.total_rows || 0))}</div></div>
          <ul class="maintenance-list">${issueList}</ul>
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Platform mix inside ask-why-first</div>
          <ul class="maintenance-list">${platformList}</ul>
        </div>
      `;
      }, renderRemediation = function(summary) {
        if (!remediationEl) return;
        const lanes = asRows(summary.priority_lanes);
        const math = toRecord(summary.math);
        const pairClasses = toRecord(summary.mismatch_pair_classes);
        const rowPatterns = toRecord(summary.row_pattern_summary);
        if (!lanes.length) {
          remediationEl.innerHTML = '<div class="detail-card"><div class="muted">No remediation summary available.</div></div>';
          return;
        }
        const mathCard = `
        <div class="detail-card">
          <div class="detail-card-title">Distribution math</div>
          <div class="detail-row"><div class="detail-label">Visibility gap</div><div class="detail-value">${esc(fmt(math.resolvable_visibility_gap_rows || 0))} (${esc(String(math.resolvable_visibility_gap_pct ?? "--"))}%)</div></div>
          <div class="detail-row"><div class="detail-label">Naming conflict</div><div class="detail-value">${esc(String(math.true_naming_conflict_pct ?? "--"))}%</div></div>
          <div class="detail-row"><div class="detail-label">High conflict / non-aligned</div><div class="detail-value">${esc(String(math.high_conflict_within_non_aligned_pct ?? "--"))}%</div></div>
          <div class="detail-row"><div class="detail-label">Entropy</div><div class="detail-value">${esc(String(math.entropy_bits ?? "--"))} bits</div></div>
          <div class="detail-row"><div class="detail-label">Normalized entropy</div><div class="detail-value">${esc(String(math.normalized_entropy ?? "--"))}</div></div>
          <div class="detail-row"><div class="detail-label">HHI</div><div class="detail-value">${esc(String(math.hhi ?? "--"))}</div></div>
        </div>
      `;
        const pairCard = `
        <div class="detail-card">
          <div class="detail-card-title">Top mismatch pair classes</div>
          <div class="detail-row"><div class="detail-label">Alias candidates</div><div class="detail-value">${esc(fmt(pairClasses.alias_candidate_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Generic signal</div><div class="detail-value">${esc(fmt(pairClasses.generic_signal_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Generic catalog</div><div class="detail-value">${esc(fmt(pairClasses.generic_catalog_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Semantic conflict</div><div class="detail-value">${esc(fmt(pairClasses.semantic_conflict_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Top-pair mass share</div><div class="detail-value">${esc(String(math.top_pair_share_of_sampled_mismatch_mass ?? "--"))}%</div></div>
        </div>
      `;
        const patternCard = `
        <div class="detail-card">
          <div class="detail-card-title">Regex pattern diagnostics</div>
          <div class="detail-row"><div class="detail-label">Unknown catalog rows</div><div class="detail-value">${esc(fmt(rowPatterns.unknown_catalog_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Generic catalog rows</div><div class="detail-value">${esc(fmt(rowPatterns.generic_catalog_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Generic signal rows</div><div class="detail-value">${esc(fmt(rowPatterns.generic_signal_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Short signal tokens</div><div class="detail-value">${esc(fmt(rowPatterns.short_signal_token_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Spy/bank/loader signals</div><div class="detail-value">${esc(fmt(rowPatterns.spy_bank_loader_signal_rows || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">Issue kinds</div><div class="detail-value">${esc(String(mapEntries(rowPatterns.issue_kind_counts).length))}</div></div>
        </div>
      `;
        const laneCards = lanes.map((lane) => {
          const targetPage = String(lane.page || "family_taxonomy_check");
          const drillParams = { limit: limitSelect ? limitSelect.value : "100" };
          if (lane.alignment) drillParams.alignment = lane.alignment;
          if (lane.pattern) drillParams.pattern = lane.pattern;
          if (lane.query) drillParams.q = lane.query;
          const drillUrl = pageLink(targetPage, drillParams);
          return `
          <div class="detail-card">
            <div class="detail-card-title">
              <span class="${badgeClassForSeverity(lane.severity)}">${esc(lane.severity || "info")}</span>
              ${esc(lane.title || "--")}
            </div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(lane.rows || 0))}${lane.pct != null ? ` (${esc(String(lane.pct))}%)` : ""}</div></div>
            <div class="detail-row"><div class="detail-label">Why it matters</div><div class="detail-value">${esc(lane.why || "--")}</div></div>
            <div class="detail-row"><div class="detail-label">Next path</div><div class="detail-value">${esc(lane.next_path || "--")}</div></div>
            <div class="detail-row"><div class="detail-label">Drill down</div><div class="detail-value"><a class="table-link" href="${esc(drillUrl)}">Open queue</a></div></div>
          </div>
        `;
        }).join("");
        remediationEl.innerHTML = mathCard + pairCard + patternCard + laneCards;
      }, applyUrl = function(limit, alignment, platform, pattern, query, pairCatalog, pairSignal) {
        const url = new URL(window.location.href);
        url.searchParams.set("p", "family_taxonomy_check");
        url.searchParams.set("limit", String(limit));
        if (alignment) url.searchParams.set("alignment", alignment);
        else url.searchParams.delete("alignment");
        if (platform) url.searchParams.set("platform", platform);
        else url.searchParams.delete("platform");
        if (pattern) url.searchParams.set("pattern", pattern);
        else url.searchParams.delete("pattern");
        if (query) url.searchParams.set("q", query);
        else url.searchParams.delete("q");
        if (pairCatalog) url.searchParams.set("pair_catalog", pairCatalog);
        else url.searchParams.delete("pair_catalog");
        if (pairSignal) url.searchParams.set("pair_signal", pairSignal);
        else url.searchParams.delete("pair_signal");
        window.history.replaceState({}, "", url.toString());
      };
      const searchInput = document.getElementById("family-taxonomy-search");
      const alignmentSelect = document.getElementById("family-taxonomy-alignment");
      const platformSelect = document.getElementById("family-taxonomy-platform");
      const patternSelect = document.getElementById("family-taxonomy-pattern");
      const limitSelect = document.getElementById("family-taxonomy-limit");
      const refreshBtn = document.getElementById("family-taxonomy-refresh");
      const metaEl = document.getElementById("family-taxonomy-meta");
      const operationalEl = document.getElementById("family-taxonomy-operational");
      const presetsEl = document.getElementById("family-taxonomy-presets");
      const platformEl = document.getElementById("family-taxonomy-platform");
      const askWhyEl = document.getElementById("family-taxonomy-ask-why");
      const summaryEl = document.getElementById("family-taxonomy-summary");
      const remediationEl = document.getElementById("family-taxonomy-remediation");
      const errorEl = document.getElementById("family-taxonomy-error");
      const esc = App.escapeHtml;
      const fmt = App.fmt;
      const formatUtc = App.formatUtc;
      async function load() {
        const limit = limitSelect ? limitSelect.value : pageRoot.dataset.limit || "100";
        const alignment = alignmentSelect ? alignmentSelect.value : pageRoot.dataset.alignment || "";
        const platform = platformSelect ? platformSelect.value : pageRoot.dataset.platform || "";
        const pattern = patternSelect ? patternSelect.value : pageRoot.dataset.pattern || "";
        const query = searchInput ? searchInput.value.trim() : pageRoot.dataset.query || "";
        const currentUrl = new URL(window.location.href);
        const pairCatalog = currentUrl.searchParams.get("pair_catalog") || pageRoot.dataset.pairCatalog || "";
        const pairSignal = currentUrl.searchParams.get("pair_signal") || pageRoot.dataset.pairSignal || "";
        applyUrl(limit, alignment, platform, pattern, query, pairCatalog, pairSignal);
        App.clearPageError(errorEl);
        try {
          const url = new URL(endpoint, window.location.origin);
          url.searchParams.set("limit", String(limit));
          if (alignment) url.searchParams.set("alignment", alignment);
          if (platform) url.searchParams.set("platform", platform);
          if (pattern) url.searchParams.set("pattern", pattern);
          if (query) url.searchParams.set("q", query);
          if (pairCatalog) url.searchParams.set("pair_catalog", pairCatalog);
          if (pairSignal) url.searchParams.set("pair_signal", pairSignal);
          const res = await App.fetchJson(url.toString());
          if (!res.ok) {
            App.renderPageError(errorEl, {
              title: "Family taxonomy check unavailable",
              summary: "The alignment overview could not load, so mismatch math and review drivers are incomplete.",
              detail: res.error,
              status: res.status,
              raw: res.raw,
              hint: "Retry this overview first. If it still fails, verify the family taxonomy API and schema-health surfaces.",
              primaryActionHref: App.currentPageUrl(),
              primaryActionLabel: "Retry overview",
              secondaryActionHref: App.pageUrl("family_taxonomy_queue"),
              secondaryActionLabel: "Open repair queue"
            });
            return;
          }
          const body = toRecord(res.body);
          const data = toRecord(body.data);
          const meta = toRecord(body.meta);
          if (meta.schema_available === false) {
            if (metaEl) metaEl.textContent = `Primary: ${meta.primary_database || "--"} | schema unavailable`;
            if (presetsEl) presetsEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
            if (operationalEl) operationalEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
            if (platformEl) platformEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
            if (askWhyEl) askWhyEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
            if (summaryEl) {
              summaryEl.innerHTML = `<div class="detail-card"><div class="detail-card-title">Schema unavailable</div><div class="muted">Missing items: ${esc(String(asRows(data.schema_missing).length))}</div></div>`;
            }
            if (remediationEl) remediationEl.innerHTML = '<div class="detail-card"><div class="muted">Schema unavailable.</div></div>';
            return;
          }
          if (metaEl) {
            const generated = meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : "--";
            const pairFocus = meta.pair_catalog || meta.pair_signal ? ` | Pair focus: ${String(meta.pair_catalog || "(empty)")} vs ${String(meta.pair_signal || "(empty)")}` : "";
            const platformFocus = meta.platform ? ` | Platform: ${String(meta.platform)}` : "";
            metaEl.textContent = `Primary: ${meta.primary_database || "--"} | Updated: ${generated}${platformFocus}${pairFocus}`;
          }
          renderPresets(asRows(data.queue_presets));
          renderOperationalSplit(toRecord(data.decision_inventory));
          renderPlatformScope(toRecord(data.platform_inventory));
          renderAskWhyBreakdown(toRecord(data.ask_why_inventory));
          renderSummary(asRows(data.summary));
          renderRemediation(toRecord(data.remediation_summary));
        } catch (err) {
          App.renderPageError(errorEl, {
            title: "Family taxonomy check load failed",
            summary: "The browser hit an unexpected failure while rendering the family alignment overview.",
            detail: err instanceof Error ? err.message : String(err),
            hint: "Reload the page once. If this keeps failing, inspect the API response and recent frontend changes.",
            primaryActionHref: App.currentPageUrl(),
            primaryActionLabel: "Reload overview",
            secondaryActionHref: App.pageUrl("landing"),
            secondaryActionLabel: "Back to landing"
          });
        }
      }
      if (refreshBtn) refreshBtn.addEventListener("click", () => {
        void load();
      });
      if (searchInput) {
        searchInput.addEventListener("keydown", (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            void load();
          }
        });
      }
      [searchInput, alignmentSelect, platformSelect, patternSelect, limitSelect].filter((node) => node !== null).forEach((node) => {
        if (node instanceof HTMLSelectElement) {
          node.addEventListener("change", () => {
            void load();
          });
        }
      });
      void load();
    }
  }
})();
