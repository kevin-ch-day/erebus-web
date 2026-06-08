(() => {
  if (!window.App || !window.PermissionIntel) return;
  const PermTriage = window.PermTriage || (window.PermTriage = {});
  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;
  const formatUtcFixed = (value) => App.formatUtc(value, { timeZone: 'UTC' });
  const { formatCount, queueStatusBadge, queueStatusLabel, riskHint, riskReasonLabel } = PermissionIntel;
  const queueStatuses = ['queued', 'claimed', 'applied', 'error', 'rejected', 'skipped'];

  function statusLabel(triageStatusMap, statusKey) {
    const key = String(statusKey || '').toLowerCase();
    return triageStatusMap && triageStatusMap.get(key) ? triageStatusMap.get(key) : String(statusKey || '');
  }

  function queueBadgeHtml(queueStatus) {
    const meta = queueStatusBadge(queueStatus);
    return `<span class="${meta.className}">${esc(meta.label)}</span>`;
  }

  function laneBadgeHtml(label, fallback = '--') {
    const text = String(label || '').trim();
    return `<span class="badge muted">${esc(text || fallback)}</span>`;
  }

  function currentEvidenceActionMeta(laneKey) {
    const key = String(laneKey || '').toLowerCase();
    if (key === 'active_review_candidate') {
      return {
        reviewLabel: 'Review',
        reviewClass: 'btn-primary',
        evidenceLabel: 'Evidence',
      };
    }
    return {
      reviewLabel: 'Inspect',
      reviewClass: 'btn-muted',
      evidenceLabel: 'View Evidence',
    };
  }

  PermTriage.applyFilters = (rows, filters) => {
    const term = filters.term ? filters.term.toLowerCase() : '';
    const namespace = filters.namespace ? filters.namespace.toLowerCase() : '';
    const risk = filters.risk ? filters.risk.toLowerCase() : '';
    const status = filters.status ? filters.status.toLowerCase() : '';
    const allowedStatuses = Array.isArray(filters.allowedStatuses)
      ? filters.allowedStatuses.map((item) => String(item).toLowerCase())
      : [];
    const queued = filters.queued ? filters.queued.toLowerCase() : '';
    return rows.filter((row) => {
      const perm = String(row.permission_string ?? '').toLowerCase();
      const ns = String(row.namespace ?? '').toLowerCase();
      const triageStatus = String(
        row.triage_status ??
        row.dict_unknown_triage_status ??
        row.review_lane_label ??
        row.diagnostic_label ??
        ''
      ).toLowerCase();
      const triageDisplay = String(
        row.triage_status_display ??
        row.dict_unknown_triage_status_display ??
        row.review_lane_label ??
        row.diagnostic_label ??
        ''
      ).toLowerCase();
      const queueStatus = String(row.queue_status ?? '').toLowerCase();
      const matchesTerm = term
        ? (perm.includes(term)
          || ns.includes(term)
          || triageStatus.includes(term)
          || triageDisplay.includes(term))
        : true;
      const matchesNamespace = namespace ? ns === namespace : true;
      const hint = riskHint(row.permission_string, row.namespace);
      const matchesRisk = risk ? hint.label.toLowerCase() === risk : true;
      const matchesStatus = status
        ? triageStatus === status
        : (allowedStatuses.length ? allowedStatuses.includes(triageStatus) : true);
      const matchesQueued = queued ? queueStatus === queued : true;
      return matchesTerm && matchesNamespace && matchesRisk && matchesStatus && matchesQueued;
    });
  };

  PermTriage.emptyMessage = (unknownRows, healthData) => {
    const total = Number(healthData.total_count ?? 0);
    const unknownCount = Number(healthData.unknown_count ?? 0);
    if (unknownCount > 0 && unknownRows.length === 0) {
      return 'Current evidence exists, but no rows match the selected lane or filters.';
    }
    if (total === 0) {
      return 'No permissions available yet. Last run may have processed no OK payloads.';
    }
    return 'No unknown permissions detected - taxonomy fully covers current data.';
  };

  PermTriage.populateNamespaceFilter = (namespaceEl, rows, initialNamespace) => {
    if (!namespaceEl) return '';
    if (initialNamespace && !namespaceEl.value) {
      namespaceEl.value = initialNamespace;
    }
    const current = namespaceEl.value;
    const namespaces = Array.from(new Set(rows.map((row) => String(row.namespace || '').trim())))
      .filter((value) => value !== '')
      .sort((a, b) => a.localeCompare(b));
    namespaceEl.innerHTML = '<option value="">All namespaces</option>';
    namespaces.forEach((ns) => {
      const opt = document.createElement('option');
      opt.value = ns;
      opt.textContent = ns;
      namespaceEl.appendChild(opt);
    });
    if (current && namespaces.includes(current)) {
      namespaceEl.value = current;
      return '';
    }
    return initialNamespace;
  };

  PermTriage.populateStatusFilter = (statusEl, triageStatuses) => {
    if (!statusEl) return;
    const current = statusEl.value;
    statusEl.innerHTML = '<option value="">All statuses</option>';
    triageStatuses.forEach((status) => {
      const opt = document.createElement('option');
      opt.value = status.key;
      opt.textContent = status.label;
      statusEl.appendChild(opt);
    });
    if (current) {
      statusEl.value = current;
    }
  };

  PermTriage.renderUnknownTable = (rows, options) => {
    const { bodyEl, triageStatusMap, onReview, onEvidence, onQueue, showQueue, emptyMessage } = options;
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage)}</td></tr>`;
      return;
    }

    rows.forEach((row) => {
      const risk = riskHint(row.permission_string, row.namespace);
      const triage = fmt(row.triage_status, '');
      const triageKey = triage.toLowerCase();
      const triageLabel = fmt(row.triage_status_display, '') || triageStatusMap.get(triageKey) || triage;
      const queueStatus = String(row.queue_status || '').toLowerCase();
      const badges = [];
      if (triage) {
        badges.push(`<span class="badge muted">${esc(triageLabel)}</span>`);
      }
      if (queueStatus) {
        const queueBadge = queueStatusBadge(queueStatus);
        badges.push(`<span class="${queueBadge.className}">${esc(queueBadge.label)}</span>`);
      } else {
        const queueBadge = queueStatusBadge('');
        badges.push(`<span class="${queueBadge.className}">${esc(queueBadge.label)}</span>`);
      }
      let appliedNote = '';
      if (queueStatus === 'applied') {
        const appliedAt = row.queue_processed_at_utc || row.queue_updated_at_utc;
        if (appliedAt) {
          appliedNote = `<div class="muted" style="margin-top:4px;">Applied at ${esc(formatUtcFixed(appliedAt))} UTC</div>`;
        }
      }
      const triageHtml = badges.length ? badges.join(' ') + appliedNote : '--';
      const tr = document.createElement('tr');
      const permissionValue = fmt(row.permission_string, '');
      tr.dataset.permission = permissionValue;
      tr.classList.add('triage-row');
      if (triageKey === 'new') {
        tr.classList.add('triage-row-new');
      }
      if (risk.label.toLowerCase() === 'high') {
        tr.classList.add('triage-row-high-risk');
      }
      const queueButton = showQueue
        ? '<button class="btn btn-small btn-muted" type="button" data-action="queue">Queue</button>'
        : '';
      tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, '--'))}</div>
        </td>
        <td class="mono">${esc(fmt(row.namespace))}</td>
        <td class="triage-status-cell">${triageHtml}</td>
        <td>${esc(formatCount(row.seen_count))}</td>
        <td><span class="${risk.className}">${esc(risk.label)}</span><div class="muted">${esc(riskReasonLabel(row.risk_reason))}</div></td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : '--')}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small btn-primary" type="button" data-action="review">Review</button>
          <button class="btn btn-small btn-muted" type="button" data-action="evidence">Evidence</button>
          ${queueButton}
        </td>
      `;
      tr.querySelector('button[data-action="review"]').addEventListener('click', () => {
        if (typeof onReview === 'function') onReview(row);
      });
      tr.querySelector('button[data-action="evidence"]').addEventListener('click', () => {
        if (typeof onEvidence === 'function') onEvidence(row);
      });
      if (showQueue) {
        const queueBtn = tr.querySelector('button[data-action="queue"]');
        if (queueBtn) {
          queueBtn.addEventListener('click', () => {
            if (typeof onQueue === 'function') onQueue(row);
          });
        }
      }
      bodyEl.appendChild(tr);
    });
  };

  PermTriage.renderCurrentEvidenceTable = (rows, options) => {
    const {
      bodyEl,
      onReview,
      onEvidence,
      onQueue,
      showQueue,
      emptyMessage,
      reviewLaneLabelMap,
    } = options || {};
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    const list = Array.isArray(rows) ? rows : [];
    if (!list.length) {
      bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage || 'No current evidence rows in this lane.')}</td></tr>`;
      return;
    }

    list.forEach((row) => {
      const risk = riskHint(row.permission_string, row.namespace);
      const laneKey = String(row.review_lane_label || row.dict_unknown_triage_status || '').toLowerCase();
      const laneLabel = (reviewLaneLabelMap && reviewLaneLabelMap[laneKey]) || row.review_lane_label || row.dict_unknown_triage_status || '--';
      const queueStatus = String(row.queue_status || '').toLowerCase();
      const actionMeta = currentEvidenceActionMeta(laneKey);
      const tr = document.createElement('tr');
      tr.dataset.permission = fmt(row.permission_string, '');
      tr.classList.add('triage-row');
      if (laneKey === 'active_review_candidate') {
        tr.classList.add('triage-row-new');
      }
      if (risk.label.toLowerCase() === 'high') {
        tr.classList.add('triage-row-high-risk');
      }
      const queueButton = showQueue
        ? '<button class="btn btn-small btn-muted" type="button" data-action="queue">Queue</button>'
        : '';
      let queueNote = '';
      if (queueStatus === 'applied') {
        const appliedAt = row.queue_processed_at_utc || row.queue_updated_at_utc;
        if (appliedAt) {
          queueNote = `<div class="muted" style="margin-top:4px;">Applied at ${esc(formatUtcFixed(appliedAt))} UTC</div>`;
        }
      }
      tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, '--'))}</div>
        </td>
        <td class="triage-status-cell">${laneBadgeHtml(laneLabel)} ${queueBadgeHtml(queueStatus)}${queueNote}</td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(formatCount(row.current_unknown_obs_rows))}</td>
        <td><span class="${risk.className}">${esc(risk.label)}</span></td>
        <td>${esc(row.last_observed_at_utc ? formatUtc(row.last_observed_at_utc) : '--')}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small ${actionMeta.reviewClass}" type="button" data-action="review">${esc(actionMeta.reviewLabel)}</button>
          <button class="btn btn-small btn-muted" type="button" data-action="evidence">${esc(actionMeta.evidenceLabel)}</button>
          ${queueButton}
        </td>
      `;
      tr.querySelector('button[data-action="review"]').addEventListener('click', () => {
        if (typeof onReview === 'function') onReview(row);
      });
      tr.querySelector('button[data-action="evidence"]').addEventListener('click', () => {
        if (typeof onEvidence === 'function') onEvidence(row);
      });
      if (showQueue) {
        const queueBtn = tr.querySelector('button[data-action="queue"]');
        if (queueBtn) {
          queueBtn.addEventListener('click', () => {
            if (typeof onQueue === 'function') onQueue(row);
          });
        }
      }
      bodyEl.appendChild(tr);
    });
  };

  PermTriage.renderLedgerDiagnosticsTable = (rows, options) => {
    const {
      bodyEl,
      onInspect,
      emptyMessage,
      diagnosticLabelMap,
    } = options || {};
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    const list = Array.isArray(rows) ? rows : [];
    if (!list.length) {
      bodyEl.innerHTML = `<tr><td colspan="7" class="muted">${esc(emptyMessage || 'No ledger diagnostic rows in the current lane.')}</td></tr>`;
      return;
    }

    list.forEach((row) => {
      const risk = riskHint(row.permission_string, row.namespace);
      const diagKey = String(row.diagnostic_label || '').toLowerCase();
      const diagLabel = (diagnosticLabelMap && diagnosticLabelMap[diagKey]) || row.diagnostic_label || '--';
      const queueStatus = String(row.queue_status || '').toLowerCase();
      const tr = document.createElement('tr');
      tr.dataset.permission = fmt(row.permission_string, '');
      tr.classList.add('triage-row');
      if (risk.label.toLowerCase() === 'high') {
        tr.classList.add('triage-row-high-risk');
      }
      const evidenceParts = [];
      if (row.has_obs_sample) evidenceParts.push('<span class="badge ok">obs</span>');
      if (row.has_vt_event) evidenceParts.push('<span class="badge ok">vt</span>');
      if (!evidenceParts.length) evidenceParts.push('<span class="badge muted">no evidence</span>');
      tr.innerHTML = `
        <td class="cell-wrap mono triage-permission-cell">
          <div class="triage-permission-main">${esc(fmt(row.permission_string))}</div>
          <div class="triage-permission-sub muted">${esc(fmt(row.namespace, '--'))}</div>
        </td>
        <td class="triage-status-cell">${laneBadgeHtml(diagLabel)} ${queueBadgeHtml(queueStatus)}</td>
        <td>${esc(formatCount(row.historical_ledger_seen_count))}</td>
        <td>${evidenceParts.join(' ')}<div class="muted">${esc(riskReasonLabel(row.risk_reason))}</div></td>
        <td>${esc(formatCount(row.current_unknown_samples))}</td>
        <td>${esc(row.last_seen_at_utc ? formatUtc(row.last_seen_at_utc) : '--')}</td>
        <td class="triage-actions-cell">
          <button class="btn btn-small btn-primary" type="button" data-action="inspect">Inspect</button>
        </td>
      `;
      tr.querySelector('button[data-action="inspect"]').addEventListener('click', () => {
        if (typeof onInspect === 'function') onInspect(row);
      });
      bodyEl.appendChild(tr);
    });
  };

  PermTriage.queueStatuses = queueStatuses;
  PermTriage.statusLabel = statusLabel;
  PermTriage.queueStatusLabel = queueStatusLabel;

  PermTriage.renderFilterSummary = (summaryEl, filters, paging, triageStatusMap) => {
    if (!summaryEl) return;
    const chips = [];
    const viewMap = {
      active: 'View: Active review',
      governed: 'View: Governed current UNKNOWNs',
      ledger: 'View: Ledger diagnostics',
    };
    if (filters.view) chips.push(viewMap[String(filters.view).toLowerCase()] || `View: ${filters.view}`);
    if (filters.term) chips.push(`Search: ${filters.term}`);
    if (filters.namespace) chips.push(`Namespace: ${filters.namespace}`);
    if (filters.risk) chips.push(`Risk: ${filters.risk}`);
    if (filters.status) chips.push(`Status: ${statusLabel(triageStatusMap, filters.status)}`);
    if (filters.queued) chips.push(`Queue: ${queueStatusLabel(filters.queued)}`);
    const viewModeMap = {
      active: 'Mode: current evidence review',
      governed: 'Mode: governed current UNKNOWNs',
      ledger: 'Mode: ledger diagnostics',
    };
    if (filters.view && viewModeMap[String(filters.view).toLowerCase()]) {
      chips.push(viewModeMap[String(filters.view).toLowerCase()]);
    } else {
      chips.push(filters.includeResolved ? 'Mode: expanded incl. resolved' : 'Mode: current evidence review');
    }
    if (filters.sort && filters.sort !== 'seen_desc') {
      const sortMap = {
        seen_asc: 'Seen low -> high',
        last_seen_desc: 'Last seen newest',
        last_seen_asc: 'Last seen oldest',
        risk_desc: 'Risk high -> low',
        risk_asc: 'Risk low -> high',
        permission_asc: 'Permission A -> Z',
        permission_desc: 'Permission Z -> A',
        namespace_asc: 'Namespace A -> Z',
        namespace_desc: 'Namespace Z -> A',
      };
      chips.push(`Sort: ${sortMap[filters.sort] || filters.sort}`);
    }
    const total = Number((paging && paging.total_count) || 0);
    const page = Number((paging && paging.page) || 1);
    const totalPages = Math.max(1, Number((paging && paging.total_pages) || 1));
    const head = `<span class="triage-filter-summary-label">Current view</span><span class="triage-filter-summary-meta muted">${total} rows across ${totalPages} page${totalPages === 1 ? '' : 's'}; page ${page}</span>`;
    if (!chips.length) {
      summaryEl.innerHTML = `${head}<div class="triage-chip-row"><span class="triage-chip triage-chip-default">Default actionable review queue</span></div>`;
      return;
    }
    summaryEl.innerHTML = `${head}<div class="triage-chip-row">${chips.map((chip) => `<span class="triage-chip">${esc(chip)}</span>`).join('')}</div>`;
  };
})();
