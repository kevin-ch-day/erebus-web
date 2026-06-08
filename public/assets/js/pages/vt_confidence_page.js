(() => {
  const root = document.getElementById('vt-confidence-page');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  const classificationGapsEndpoint = root.dataset.classificationGapsEndpoint || '';
  const limit = root.dataset.limit || '25';
  if (!endpoint) return;

  const bucketsEl = document.getElementById('vt-confidence-buckets');
  const metaEl = document.getElementById('vt-confidence-meta');
  const fpSummaryEl = document.getElementById('vt-fp-summary-list');
  const candidatesBodyEl = document.getElementById('vt-confidence-candidates-body');
  const evidenceGapCardsEl = document.getElementById('vt-evidence-gap-cards');
  const evidenceGapSummaryEl = document.getElementById('vt-evidence-gap-summary');
  const evidenceGapBodyEl = document.getElementById('vt-evidence-gap-body');
  const errorEl = document.getElementById('vt-confidence-error');
  const vendorRowsEl = document.getElementById('vt-confidence-vendor-rows');
  const projectionRowsEl = document.getElementById('vt-confidence-projection-rows');
  const reliabilityRowsEl = document.getElementById('vt-confidence-reliability-rows');
  const driftRowsEl = document.getElementById('vt-confidence-drift-rows');
  const signalRowsEl = document.getElementById('vt-confidence-signal-rows');
  const confidenceRowsEl = document.getElementById('vt-confidence-confidence-rows');
  const parseVersionEl = document.getElementById('vt-confidence-parse-version');
  const fpTendencyEl = document.getElementById('vt-confidence-fp-tendency');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;
  const pageUrl = App.pageUrl;

  function fmtFixed(value, digits = 3) {
    return value === null || value === undefined || Number.isNaN(Number(value))
      ? '--'
      : Number(value).toFixed(digits);
  }

  function badgeForBucket(bucket) {
    const key = String(bucket || '').toLowerCase();
    if (key === 'high' || key === 'strong') return 'badge ok';
    if (key === 'moderate') return 'badge warn';
    if (key === 'review' || key === 'weak') return 'badge err';
    return 'badge muted';
  }

  function renderUnavailable(data, meta) {
    if (metaEl) {
      metaEl.textContent = `Primary: ${meta.primary_database || '--'} | schema unavailable`;
    }
    const missing = Array.isArray(data.schema_missing) ? data.schema_missing : [];
    if (bucketsEl) {
      bucketsEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Schema unavailable</div>
          <div class="muted">Apply the VT confidence database surface before using this page.</div>
          <div class="muted" style="margin-top:8px;">Missing items: ${esc(String(missing.length))}</div>
        </div>
      `;
    }
    if (fpSummaryEl) fpSummaryEl.innerHTML = '<li class="muted">Unavailable until schema is present.</li>';
    if (candidatesBodyEl) candidatesBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No candidates available.</td></tr>';
    if (evidenceGapCardsEl) {
      evidenceGapCardsEl.innerHTML = '<div class="detail-card"><div class="muted">Unavailable until classification-gap schema is present.</div></div>';
    }
    if (evidenceGapBodyEl) {
      evidenceGapBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No evidence-completion rows available.</td></tr>';
    }
  }

  function renderBuckets(rows) {
    if (!bucketsEl) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      bucketsEl.innerHTML = '<div class="detail-card"><div class="muted">No confidence bucket rows found.</div></div>';
      return;
    }
    bucketsEl.innerHTML = rows.map((row) => {
      const bucket = row.confidence_bucket || '--';
      const action = row.recommended_action || '--';
      return `
        <div class="detail-card">
          <div class="detail-card-title">
            <span class="${badgeForBucket(bucket)}">${esc(bucket)}</span>
            ${esc(action)}
          </div>
          <div class="detail-row">
            <div class="detail-label">Samples</div>
            <div class="detail-value">${esc(fmt(row.sample_count))}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Score range</div>
            <div class="detail-value">${esc(row.min_confidence_score ?? '--')} - ${esc(row.max_confidence_score ?? '--')}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Average</div>
            <div class="detail-value">${esc(row.avg_confidence_score ?? '--')}</div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderFpSummary(rows) {
    if (!fpSummaryEl) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      fpSummaryEl.innerHTML = '<li class="muted">No false-positive review buckets found.</li>';
      return;
    }
    fpSummaryEl.innerHTML = rows.map((row) => {
      return `<li>${esc(row.review_reason || '--')}: <strong>${esc(fmt(row.sample_count))}</strong></li>`;
    }).join('');
  }

  function renderCandidates(rows) {
    if (!candidatesBodyEl) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      candidatesBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No review candidates found.</td></tr>';
      return;
    }
    candidatesBodyEl.innerHTML = rows.map((row) => {
      const hash = row.sha256 ? String(row.sha256).slice(0, 16) : '--';
      const score = row.confidence_score ?? '--';
      const bucket = row.confidence_bucket || '--';
      const detailUrl = row.sha256
        ? pageUrl('sample_detail', { sha256: row.sha256 })
        : pageUrl('sample_detail', { sample_id: row.sample_id || '' });
      return `
        <tr>
          <td><code>${esc(hash)}</code><br><span class="muted">sample ${esc(row.sample_id || '--')}</span><br><a class="btn btn-small btn-muted" href="${esc(detailUrl)}">Open sample</a></td>
          <td>${esc(row.android_package_name || row.sample_label || '--')}<br><span class="muted">${esc(row.family_label || row.platform || '')}</span></td>
          <td>mal=${esc(row.vt_malicious_count ?? '--')} susp=${esc(row.vt_suspicious_count ?? '--')}<br><span class="muted">harmless=${esc(row.vt_harmless_count ?? '--')} total=${esc(row.vt_total_engines ?? '--')}</span></td>
          <td><span class="${badgeForBucket(bucket)}">${esc(bucket)}</span><br><span class="muted">score=${esc(score)}</span></td>
          <td>${esc(row.review_reason || '--')}<br><span class="muted">${esc(row.recommended_action || '--')}</span></td>
        </tr>
      `;
    }).join('');
  }

  function evidenceWorkflowMeta(key) {
    const value = String(key || '').toLowerCase();
    if (value === 'behavior_strong_vt_missing') {
      return {
        label: 'Strong behavior, VT missing',
        className: 'badge err',
        hint: 'Permission Intel shows strong Android behavior, but VT evidence is absent.',
      };
    }
    if (value === 'behavior_vt_conflict') {
      return {
        label: 'Behavior/VT conflict',
        className: 'badge warn',
        hint: 'Permission behavior is strong, but VT confidence or action disagrees.',
      };
    }
    if (value === 'evidence_missing') {
      return {
        label: 'Evidence missing',
        className: 'badge muted',
        hint: 'Cross-signal evidence is incomplete and needs enrichment.',
      };
    }
    return {
      label: value || 'Review context',
      className: 'badge muted',
      hint: '',
    };
  }

  function dedupeEvidenceGapRows(rows) {
    const map = new Map();
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const sampleKey = row.sha256
        ? `sha:${String(row.sha256)}`
        : `id:${String(row.sample_id || '')}`;
      if (!sampleKey || sampleKey === 'id:') return;
      const current = map.get(sampleKey);
      const currentScore = current ? Number(current.sample_strong_attack_surface_rows || 0) : -1;
      const nextScore = Number(row.sample_strong_attack_surface_rows || 0);
      if (!current || nextScore > currentScore) {
        map.set(sampleKey, row);
      }
    });
    return Array.from(map.values()).sort((a, b) => {
      const aStrength = Number(a.sample_strong_attack_surface_rows || 0);
      const bStrength = Number(b.sample_strong_attack_surface_rows || 0);
      if (bStrength !== aStrength) return bStrength - aStrength;
      return Number(a.sample_id || 0) - Number(b.sample_id || 0);
    });
  }

  function renderEvidenceGapCards(rows) {
    if (!evidenceGapCardsEl) return;
    const grouped = {
      behavior_strong_vt_missing: 0,
      behavior_vt_conflict: 0,
      evidence_missing: 0,
    };
    rows.forEach((row) => {
      const key = String(row.workflow_state || '').toLowerCase();
      if (Object.prototype.hasOwnProperty.call(grouped, key)) {
        grouped[key] += 1;
      }
    });
    evidenceGapCardsEl.innerHTML = Object.entries(grouped).map(([key, count]) => {
      const meta = evidenceWorkflowMeta(key);
      return `
        <div class="detail-card">
          <div class="detail-card-title"><span class="${meta.className}">${esc(meta.label)}</span></div>
          <div class="detail-row">
            <div class="detail-label">Samples</div>
            <div class="detail-value">${esc(fmt(count))}</div>
          </div>
          <div class="muted">${esc(meta.hint)}</div>
        </div>
      `;
    }).join('');
  }

  function renderEvidenceGapRows(rows) {
    if (!evidenceGapBodyEl) return;
    if (!rows.length) {
      evidenceGapBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No evidence-completion rows found.</td></tr>';
      return;
    }
    evidenceGapBodyEl.innerHTML = rows.map((row) => {
      const workflow = evidenceWorkflowMeta(row.workflow_state);
      const hash = row.sha256 ? String(row.sha256).slice(0, 16) : '--';
      const detailUrl = row.sha256
        ? pageUrl('sample_detail', { sha256: row.sha256 })
        : pageUrl('sample_detail', { sample_id: row.sample_id || '' });
      return `
        <tr>
          <td><code>${esc(hash)}</code><br><span class="muted">sample ${esc(row.sample_id || '--')} | ${esc(row.package_name || '--')}</span></td>
          <td><span class="${workflow.className}">${esc(workflow.label)}</span><br><span class="muted">${esc(row.workflow_reason_label || row.workflow_label || '--')}</span></td>
          <td>${esc(row.attack_technique_id || '--')}<br><span class="muted">${esc(row.permissions || '--')}</span><br><span class="muted">${esc(row.sample_strong_attack_surface_rows || '--')} strong behavior row(s)</span></td>
          <td>bucket=${esc(row.confidence_bucket || 'missing')}<br><span class="muted">action=${esc(row.recommended_action || 'missing')}</span></td>
          <td><a class="btn btn-small btn-muted" href="${esc(detailUrl)}">Open sample</a></td>
        </tr>
      `;
    }).join('');
  }

  async function loadEvidenceGaps() {
    if (!classificationGapsEndpoint) return;
    try {
      const url = new URL(classificationGapsEndpoint, window.location.origin);
      url.searchParams.set('limit', '50');
      const res = await App.fetchJson(url.toString());
      if (!res.ok) {
        if (evidenceGapSummaryEl) evidenceGapSummaryEl.textContent = 'Failed to load evidence-completion priorities.';
        if (evidenceGapBodyEl) {
          evidenceGapBodyEl.innerHTML = `<tr><td colspan="5" class="err">HTTP ${esc(res.status)} loading evidence gaps.</td></tr>`;
        }
        return;
      }
      const body = res.body || {};
      const data = body.data || {};
      const meta = body.meta || {};
      if (meta.schema_available === false) {
        if (evidenceGapSummaryEl) evidenceGapSummaryEl.textContent = 'Evidence-completion workflow is unavailable until classification-gap schema is present.';
        if (evidenceGapCardsEl) {
          evidenceGapCardsEl.innerHTML = '<div class="detail-card"><div class="muted">Classification-gap schema is unavailable.</div></div>';
        }
        if (evidenceGapBodyEl) {
          evidenceGapBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No evidence-completion rows available.</td></tr>';
        }
        return;
      }
      const rows = dedupeEvidenceGapRows(data.gaps || []);
      const focusedRows = rows.filter((row) => {
        const key = String(row.workflow_state || '').toLowerCase();
        return key === 'behavior_strong_vt_missing' || key === 'behavior_vt_conflict' || key === 'evidence_missing';
      });
      if (evidenceGapSummaryEl) {
        evidenceGapSummaryEl.textContent = focusedRows.length
          ? `Showing ${fmt(focusedRows.length)} priority sample row${focusedRows.length === 1 ? '' : 's'} from classification-gap workflow.`
          : 'No priority evidence-completion rows found.';
      }
      renderEvidenceGapCards(focusedRows);
      renderEvidenceGapRows(focusedRows.slice(0, 8));
    } catch (err) {
      if (evidenceGapSummaryEl) evidenceGapSummaryEl.textContent = 'Failed to load evidence-completion priorities.';
      if (evidenceGapBodyEl) {
        evidenceGapBodyEl.innerHTML = `<tr><td colspan="5" class="err">${esc(err && err.message ? err.message : String(err))}</td></tr>`;
      }
    }
  }

  function renderSurfaceContext(vendorModel, signalSurface) {
    if (vendorRowsEl) vendorRowsEl.textContent = fmt(vendorModel.canonical_vendor_rows);
    if (projectionRowsEl) projectionRowsEl.textContent = fmt(vendorModel.projection_rows);
    if (reliabilityRowsEl) reliabilityRowsEl.textContent = fmt(vendorModel.reliability_rows);
    if (driftRowsEl) driftRowsEl.textContent = fmt(vendorModel.changed_engines_sum_30d);
    if (signalRowsEl) signalRowsEl.textContent = fmt(signalSurface.signal_current_rows);
    if (confidenceRowsEl) confidenceRowsEl.textContent = fmt(signalSurface.confidence_rows);
    if (fpTendencyEl) fpTendencyEl.textContent = fmtFixed(vendorModel.avg_false_positive_tendency);
    if (parseVersionEl) {
      const parseVersions = Array.isArray(signalSurface.parse_versions) ? signalSurface.parse_versions : [];
      const top = parseVersions[0] || null;
      parseVersionEl.textContent = top ? `${fmt(top.parse_version)} (${fmt(top.row_count)})` : '--';
    }
  }

  async function load() {
    if (errorEl) errorEl.textContent = '';
    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('limit', String(limit));
      const res = await App.fetchJson(url.toString());
      if (!res.ok) {
        if (errorEl) errorEl.textContent = `VT confidence API error: HTTP ${res.status} ${res.error || ''}`;
        return;
      }
      const body = res.body || {};
      const data = body.data || {};
      const meta = body.meta || {};
      if (meta.schema_available === false) {
        renderUnavailable(data, meta);
        return;
      }
      if (metaEl) {
        const generated = meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : '--';
        metaEl.textContent = `Primary: ${meta.primary_database || '--'} | Updated: ${generated}`;
      }
      renderSurfaceContext(data.vendor_model_summary || {}, data.signal_surface_summary || {});
      renderBuckets(data.summary || []);
      renderFpSummary(data.false_positive_review_summary || []);
      renderCandidates(data.false_positive_review_candidates || []);
      loadEvidenceGaps();
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = `VT confidence load failed: ${err && err.message ? err.message : String(err)}`;
      }
    }
  }

  load();
})();
