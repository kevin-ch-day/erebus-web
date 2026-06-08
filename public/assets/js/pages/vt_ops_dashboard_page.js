(() => {
  const root = document.getElementById('vt-ops-dashboard-page');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  const refreshSeconds = Number(root.dataset.refreshSeconds || '15') || 15;
  const refreshMs = Math.max(5, refreshSeconds) * 1000;
  if (!endpoint) return;

  const nextPathEl = document.getElementById('vt-ops-next-path');
  const primaryHeadEl = document.getElementById('vt-ops-primary-head');
  const piHeadEl = document.getElementById('vt-ops-pi-head');
  const headsMatchEl = document.getElementById('vt-ops-heads-match');
  const confidenceSchemaEl = document.getElementById('vt-ops-confidence-schema');
  const vtSurfacesEl = document.getElementById('vt-ops-vt-surfaces');
  const vendorCanonicalRowsEl = document.getElementById('vt-ops-vendor-canonical-rows');
  const vendorProjectionRowsEl = document.getElementById('vt-ops-vendor-projection-rows');
  const vendorReliabilityRowsEl = document.getElementById('vt-ops-vendor-reliability-rows');
  const vendorProfileRowsEl = document.getElementById('vt-ops-vendor-profile-rows');
  const vendorCollisionRowsEl = document.getElementById('vt-ops-vendor-collision-rows');
  const vendorAvgWeightEl = document.getElementById('vt-ops-vendor-avg-weight');
  const vendorAvgFpEl = document.getElementById('vt-ops-vendor-avg-fp');
  const vendorAvgInstabilityEl = document.getElementById('vt-ops-vendor-avg-instability');
  const vendorAvgFillEl = document.getElementById('vt-ops-vendor-avg-fill');
  const vendorLowFillEl = document.getElementById('vt-ops-vendor-low-fill');
  const deltaRowsEl = document.getElementById('vt-ops-delta-rows');
  const deltaChangedEnginesEl = document.getElementById('vt-ops-delta-changed-engines');
  const deltaNewEnginesEl = document.getElementById('vt-ops-delta-new-engines');
  const deltaRemovedEnginesEl = document.getElementById('vt-ops-delta-removed-engines');
  const deltaLabelCategoryEl = document.getElementById('vt-ops-delta-label-category');
  const signalRowsEl = document.getElementById('vt-ops-signal-rows');
  const confidenceRowsEl = document.getElementById('vt-ops-confidence-rows');
  const signalParseVersionEl = document.getElementById('vt-ops-signal-parse-version');
  const metaEl = document.getElementById('vt-ops-meta');
  const errorEl = document.getElementById('vt-ops-error');

  const esc = App.escapeHtml;

  function fmt(value) {
    return value === null || value === undefined || value === '' ? '--' : String(value);
  }

  function fmtFixed(value, digits = 3) {
    return value === null || value === undefined || Number.isNaN(Number(value))
      ? '--'
      : Number(value).toFixed(digits);
  }

  function setNextPath(message, className = 'info') {
    if (!nextPathEl) return;
    nextPathEl.className = `notice ${className}`;
    nextPathEl.textContent = message;
  }

  function render(data) {
    const schemaHeads = data.schema_heads || {};
    const confidenceSchema = data.confidence_schema || {};
    const vtSurfaceSummary = data.vt_surface_summary || {};
    const vendorModel = data.vendor_model_summary || {};
    const signalSurface = data.signal_surface_summary || {};

    if (primaryHeadEl) primaryHeadEl.textContent = fmt(schemaHeads.primary_head);
    if (piHeadEl) piHeadEl.textContent = fmt(schemaHeads.permission_intel_head);
    if (headsMatchEl) headsMatchEl.textContent = schemaHeads.heads_match ? 'yes' : 'no';
    if (confidenceSchemaEl) {
      confidenceSchemaEl.textContent = confidenceSchema.available
        ? 'available'
        : `missing (${fmt(confidenceSchema.missing_count)})`;
    }
    if (vtSurfacesEl) {
      vtSurfacesEl.textContent = `${fmt(vtSurfaceSummary.available_count)}/${fmt(vtSurfaceSummary.known_count)} available`;
    }
    if (vendorCanonicalRowsEl) vendorCanonicalRowsEl.textContent = fmt(vendorModel.canonical_vendor_rows);
    if (vendorProjectionRowsEl) vendorProjectionRowsEl.textContent = fmt(vendorModel.projection_rows);
    if (vendorReliabilityRowsEl) vendorReliabilityRowsEl.textContent = fmt(vendorModel.reliability_rows);
    if (vendorProfileRowsEl) vendorProfileRowsEl.textContent = fmt(vendorModel.projection_profile_rows);
    if (vendorCollisionRowsEl) vendorCollisionRowsEl.textContent = fmt(vendorModel.collision_rows);
    if (vendorAvgWeightEl) vendorAvgWeightEl.textContent = fmtFixed(vendorModel.avg_reliability_weight);
    if (vendorAvgFpEl) vendorAvgFpEl.textContent = fmtFixed(vendorModel.avg_false_positive_tendency);
    if (vendorAvgInstabilityEl) vendorAvgInstabilityEl.textContent = fmtFixed(vendorModel.avg_instability_score);
    if (vendorAvgFillEl) vendorAvgFillEl.textContent = fmtFixed(vendorModel.avg_projection_populated_ratio);
    if (vendorLowFillEl) vendorLowFillEl.textContent = fmt(vendorModel.low_fill_candidates);
    if (deltaRowsEl) deltaRowsEl.textContent = fmt(vendorModel.delta_rows_30d);
    if (deltaChangedEnginesEl) deltaChangedEnginesEl.textContent = fmt(vendorModel.changed_engines_sum_30d);
    if (deltaNewEnginesEl) deltaNewEnginesEl.textContent = fmt(vendorModel.engines_new_sum_30d);
    if (deltaRemovedEnginesEl) deltaRemovedEnginesEl.textContent = fmt(vendorModel.engines_removed_sum_30d);
    if (deltaLabelCategoryEl) {
      deltaLabelCategoryEl.textContent = `${fmt(vendorModel.labels_changed_sum_30d)} / ${fmt(vendorModel.categories_changed_sum_30d)}`;
    }
    if (signalRowsEl) signalRowsEl.textContent = fmt(signalSurface.signal_current_rows);
    if (confidenceRowsEl) confidenceRowsEl.textContent = fmt(signalSurface.confidence_rows);
    if (signalParseVersionEl) {
      const parseVersions = Array.isArray(signalSurface.parse_versions) ? signalSurface.parse_versions : [];
      const top = parseVersions[0] || null;
      signalParseVersionEl.textContent = top ? `${fmt(top.parse_version)} (${fmt(top.row_count)})` : '--';
    }

    if (Number(vtSurfaceSummary.missing_count || 0) > 0) {
      setNextPath('Next path: web and DB VT evidence surfaces are not fully aligned. Fix schema drift before trusting vendor, delta, or signal diagnostics.', 'warn');
    } else if (!confidenceSchema.available) {
      setNextPath('Next path: confidence views are incomplete. Treat VT confidence and false-positive review surfaces as degraded until the schema is repaired.', 'warn');
    } else if (Number(vendorModel.collision_rows || 0) > 0) {
      setNextPath('Next path: vendor identity collisions exist. Review vendor catalog semantics before trusting wide projection or per-vendor drift interpretations.', 'warn');
    } else if (Number(vendorModel.low_fill_candidates || 0) > 0) {
      setNextPath('Next path: low-fill vendor projections exist. Treat projection-heavy downstream summaries carefully and review VT Confidence before trusting weak surfaces.', 'warn');
    } else {
      setNextPath('Next path: evidence surfaces look aligned. Use VT Confidence or VT Snapshot Inventory for deeper inspection, and Health for pipeline state.', 'info');
    }

    if (metaEl) metaEl.textContent = `Last refresh: ${new Date().toISOString().replace('T', ' ').replace('Z', ' UTC')}`;
  }

  async function load() {
    try {
      if (errorEl) errorEl.textContent = '';
      const res = await App.fetchJson(endpoint);
      if (!res.ok) {
        const raw = res.raw ? String(res.raw).slice(0, 2000) : '';
        const detail = raw ? `\n\n${esc(raw)}` : '';
        if (errorEl) {
          errorEl.innerHTML = `<pre>VT ops summary failed.\n\nHTTP ${res.status}\nerror: ${esc(res.error)}${detail}</pre>`;
        }
        return;
      }
      render(res.body || {});
    } catch (error) {
      if (errorEl) {
        errorEl.innerHTML = `<pre>VT ops summary error:\n${esc(error && error.message ? error.message : String(error))}</pre>`;
      }
    }
  }

  load();
  setInterval(load, refreshMs);
})();
