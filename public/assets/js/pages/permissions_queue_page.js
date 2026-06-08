(() => {
  const root = document.getElementById('permission-queue-page');
  if (!root || !window.App) return;

  const endpoint = root.dataset.endpoint || '';
  const defaultLimit = Number(root.dataset.defaultLimit || 50);

  const searchEl = document.getElementById('permission-queue-search');
  const statusEl = document.getElementById('permission-queue-status');
  const actionEl = document.getElementById('permission-queue-action');
  const limitEl = document.getElementById('permission-queue-limit');
  const refreshBtn = document.getElementById('permission-queue-refresh');
  const compactBtn = document.getElementById('permission-queue-compact-toggle');
  const pageIndexEl = document.getElementById('permission-queue-page-index');

  const summaryEl = document.getElementById('permission-queue-summary');
  const populationSummaryEl = document.getElementById('permission-queue-population-summary');
  const summaryNoteEl = document.getElementById('permission-queue-summary-note');
  const unavailableEl = document.getElementById('permission-queue-unavailable');
  const bodyEl = document.getElementById('permission-queue-body');
  const metaEl = document.getElementById('permission-queue-meta');
  const errorEl = document.getElementById('permission-queue-error');
  const bannerEl = document.getElementById('permission-queue-api-banner');
  const pageRoot = document.getElementById('permission-queue-root');
  const prevBtn = document.getElementById('permission-queue-prev');
  const nextBtn = document.getElementById('permission-queue-next');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const readonly = App.readonly || {};
  const formatUtcFixed = readonly.formatUtcFixed || ((value) => App.formatUtc(value, { timeZone: 'UTC' }));
  const fetchPayload = readonly.fetchPayload || App.fetchPayload;
  const renderUnknown = readonly.renderUnknown || ((value) => (value ? `unknown:${value}` : '--'));
  const setTableMessage = readonly.setTableMessage || ((el, colSpan, message, className = 'muted') => {
    if (!el) return;
    el.innerHTML = `<tr><td colspan="${colSpan}" class="${className}">${esc(message)}</td></tr>`;
  });
  const pager = readonly.createCursorPager ? readonly.createCursorPager() : null;
  const offlineBanner = 'Enhanced API unavailable - showing DB-backed read-only state.';
  const compactKey = 'perm_queue_compact';

  const STATUS_LABELS = {
    queued: { label: 'Queued', className: 'badge warn' },
    claimed: { label: 'Claimed', className: 'badge warn' },
    applied: { label: 'Applied', className: 'badge ok' },
    error: { label: 'Error', className: 'badge err' },
    rejected: { label: 'Rejected', className: 'badge muted' },
    skipped: { label: 'Skipped', className: 'badge muted' },
  };

  const ACTION_LABELS = {
    aosp: 'AOSP',
    oem: 'OEM',
    google: 'Google',
    reject: 'Reject / no action',
    defer: 'Defer',
    skip: 'Skip',
    app_defined: 'App Defined',
    apply: 'Apply',
  };

  const POPULATION_LABELS = {
    imported_static_candidate_no_anchor: { label: 'Imported static candidate', className: 'badge warn' },
    already_resolved_aosp_duplicate: { label: 'Superseded duplicate', className: 'badge muted' },
    malformed_ledger_conflict: { label: 'Malformed conflict', className: 'badge err' },
    evidence_backed_queue_work: { label: 'Evidence-backed queue work', className: 'badge ok' },
    web_triage_queue: { label: 'Web triage queue', className: 'badge ok' },
    other_queue_state: { label: 'Other queue state', className: 'badge muted' },
  };

  const CONFLICT_LABELS = {
    missing_ledger_anchor: { label: 'No ledger anchor', className: 'badge warn' },
    already_resolved_duplicate: { label: 'Already resolved', className: 'badge muted' },
    malformed_ledger: { label: 'Malformed ledger', className: 'badge err' },
    none: null,
  };

  let pendingTimer = null;

  function setUnavailable(message) {
    if (!unavailableEl) return;
    unavailableEl.textContent = message;
    unavailableEl.style.display = 'block';
  }

  function clearUnavailable() {
    if (!unavailableEl) return;
    unavailableEl.textContent = '';
    unavailableEl.style.display = 'none';
  }

  function setCompact(enabled) {
    if (!pageRoot) return;
    pageRoot.classList.toggle('compact', enabled);
    if (compactBtn) {
      compactBtn.textContent = enabled ? 'Expanded view' : 'Compact view';
    }
    try {
      localStorage.setItem(compactKey, enabled ? '1' : '0');
    } catch (_) {
      // ignore storage failures
    }
  }

  function loadCompact() {
    if (!pageRoot) return;
    let enabled = false;
    try {
      enabled = localStorage.getItem(compactKey) === '1';
    } catch (_) {
      enabled = false;
    }
    setCompact(enabled);
  }

  function setBannerApiRunning() {
    if (!bannerEl) return;
    bannerEl.className = 'notice info';
    bannerEl.textContent = 'API server: RUNNING';
  }

  function setBannerFallback() {
    if (!bannerEl) return;
    bannerEl.className = 'notice warn';
    bannerEl.textContent = offlineBanner;
  }

  function shouldFallback(res) {
    if (!res || res.ok) return false;
    if (res.code === 'ERR_SCHEMA_MISSING') return false;
    if (res.status === 0 || res.status === 404 || res.status >= 500) return true;
    if (res.error === 'Non-JSON response') return true;
    return false;
  }

  function formatActionCell(row) {
    const normalized = row.normalized_action || row.queue_action || row.queue_action_normalized || row.queue_action_raw || '';
    if (!normalized) return '--';
    const key = String(normalized).toLowerCase();
    const label = ACTION_LABELS[key] ? ACTION_LABELS[key] : renderUnknown(normalized);
    let extra = '';
    if (row.queue_action_raw && row.queue_action && String(row.queue_action_raw).toLowerCase() !== String(row.queue_action).toLowerCase()) {
      extra = `<div class="muted">raw: ${esc(row.queue_action_raw)}</div>`;
    }
    return `<div>${esc(label)}</div>${extra}`;
  }

  function formatStatusCell(row) {
    const normalized = row.status || row.queue_status || row.status_normalized || row.status_raw || '';
    if (!normalized) return '--';
    const key = String(normalized).toLowerCase();
    const known = STATUS_LABELS[key];
    let detail = '';
    if (row.status_raw && row.status && String(row.status_raw).toLowerCase() !== String(row.status).toLowerCase()) {
      detail = `<div class="muted">raw: ${esc(row.status_raw)}</div>`;
    }
    if (known) {
      return `<span class="${known.className}">${esc(known.label)}</span>${detail}`;
    }
    return `<span class="badge muted">${esc(renderUnknown(normalized))}</span>${detail}`;
  }

  function renderSummary(counts) {
    if (!summaryEl || !summaryNoteEl) return;
    summaryEl.innerHTML = '';
    if (!counts || typeof counts !== 'object') {
      summaryNoteEl.style.display = 'block';
      return;
    }
    const entries = Object.entries(counts);
    if (!entries.length) {
      summaryNoteEl.style.display = 'block';
      return;
    }
    summaryNoteEl.style.display = 'none';
    entries.forEach(([key, value]) => {
      const label = STATUS_LABELS[key] ? STATUS_LABELS[key].label : renderUnknown(key);
      const row = document.createElement('div');
      row.className = 'detail-row';
      row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, '0'))}</div>
      `;
      summaryEl.appendChild(row);
    });
  }

  function renderPopulationSummary(counts) {
    if (!populationSummaryEl) return;
    populationSummaryEl.innerHTML = '';
    if (!counts || typeof counts !== 'object') return;
    const entries = Object.entries(counts)
      .filter(([, value]) => Number(value || 0) > 0)
      .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0));
    if (!entries.length) return;
    entries.forEach(([key, value]) => {
      const known = POPULATION_LABELS[key];
      const label = known ? known.label : renderUnknown(key);
      const row = document.createElement('div');
      row.className = 'detail-row';
      row.innerHTML = `
        <div class="detail-label">${esc(label)}</div>
        <div class="detail-value">${esc(fmt(value, '0'))}</div>
      `;
      populationSummaryEl.appendChild(row);
    });
  }

  function renderBadge(label, className = 'badge muted') {
    return `<span class="${className}">${esc(label)}</span>`;
  }

  function renderPopulationCell(row) {
    const key = row.queue_population_label || 'other_queue_state';
    const known = POPULATION_LABELS[key];
    const label = known ? known.label : renderUnknown(key);
    const badge = renderBadge(label, known ? known.className : 'badge muted');
    const source = row.source_system ? `<div class="muted">source: ${esc(row.source_system)}</div>` : '';
    return `${badge}${source}`;
  }

  function renderSignalsCell(row) {
    const badges = [];
    const source = row.source_system ? String(row.source_system) : 'unknown';
    badges.push(renderBadge(source, source === 'web' ? 'badge ok' : (source === 'static-analysis' ? 'badge warn' : 'badge muted')));

    if (row.has_obs_sample && row.has_vt_event) {
      badges.push(renderBadge('obs + vt', 'badge ok'));
    } else if (row.has_obs_sample) {
      badges.push(renderBadge('obs', 'badge ok'));
    } else if (row.has_vt_event) {
      badges.push(renderBadge('vt', 'badge ok'));
    } else {
      badges.push(renderBadge('no evidence', 'badge muted'));
    }

    badges.push(renderBadge(row.has_ledger_anchor ? 'anchored' : 'no ledger', row.has_ledger_anchor ? 'badge ok' : 'badge warn'));
    badges.push(renderBadge(row.already_in_aosp ? 'in AOSP' : 'not in AOSP', row.already_in_aosp ? 'badge muted' : 'badge warn'));

    const conflict = CONFLICT_LABELS[row.conflict_label || 'none'];
    if (conflict) {
      badges.push(renderBadge(conflict.label, conflict.className));
    }

    return badges.join(' ');
  }

  function renderTriageCell(row) {
    const queueTriage = row.queue_triage_status_display || row.triage_status_display || row.queue_triage_status || row.triage_status || '--';
    const ledgerTriage = row.dict_unknown_triage_status_display || row.dict_unknown_triage_status || '';
    if (!ledgerTriage || String(ledgerTriage) === '-' || ledgerTriage === queueTriage) {
      return esc(queueTriage);
    }
    return `
      <div>${esc(queueTriage)}</div>
      <div class="muted">ledger: ${esc(ledgerTriage)}</div>
    `;
  }

  function renderTimelineCell(row) {
    const queuedAt = row.queued_at_utc ? formatUtcFixed(row.queued_at_utc) : '--';
    const processedAt = row.processed_at_utc ? formatUtcFixed(row.processed_at_utc) : '--';
    const updatedAt = row.updated_at_utc ? formatUtcFixed(row.updated_at_utc) : '--';
    return `
      <div>Queued: ${esc(queuedAt)}</div>
      <div class="muted">Processed: ${esc(processedAt)}</div>
      <div class="muted">Updated: ${esc(updatedAt)}</div>
    `;
  }

  function renderSummaryNote(payload) {
    if (!summaryNoteEl) return;
    const countsByAction = payload && payload.counts_by_action_active && typeof payload.counts_by_action_active === 'object'
      ? payload.counts_by_action_active
      : (payload && payload.counts_by_action && typeof payload.counts_by_action === 'object'
        ? payload.counts_by_action
        : null);
    const countsByStatusActive = payload && payload.counts_by_status_active && typeof payload.counts_by_status_active === 'object'
      ? payload.counts_by_status_active
      : null;
    const legacyQueueActions = Array.isArray(payload && payload.legacy_queue_actions_active)
      ? payload.legacy_queue_actions_active
      : [];
    const parts = [];
    if (countsByAction) {
      const actionSummary = Object.entries(countsByAction)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${ACTION_LABELS[String(key).toLowerCase()] || renderUnknown(key)} ${fmt(value, '0')}`);
      if (actionSummary.length) {
        parts.push(`Active action mix: ${actionSummary.join(' | ')}`);
      }
    }
    if (countsByStatusActive) {
      const activeStatusSummary = Object.entries(countsByStatusActive)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${STATUS_LABELS[String(key).toLowerCase()] ? STATUS_LABELS[String(key).toLowerCase()].label : renderUnknown(key)} ${fmt(value, '0')}`);
      if (activeStatusSummary.length) {
        parts.push(`Active queue state: ${activeStatusSummary.join(' | ')}`);
      }
    }
    const countsByPopulation = payload && payload.counts_by_population && typeof payload.counts_by_population === 'object'
      ? payload.counts_by_population
      : null;
    if (countsByPopulation) {
      const populationSummary = Object.entries(countsByPopulation)
        .filter(([, value]) => Number(value || 0) > 0)
        .sort((a, b) => Number(b[1] || 0) - Number(a[1] || 0))
        .map(([key, value]) => `${POPULATION_LABELS[key] ? POPULATION_LABELS[key].label : renderUnknown(key)} ${fmt(value, '0')}`);
      if (populationSummary.length) {
        parts.push(`Populations: ${populationSummary.join(' | ')}`);
      }
    }
    if (legacyQueueActions.length) {
      const legacySummary = legacyQueueActions
        .map((item) => `${item.raw} -> ${item.normalized} (${fmt(item.count, '0')})`)
        .join(' | ');
      parts.push(`Legacy raw actions still active: ${legacySummary}`);
    }
    if (parts.length) {
      summaryNoteEl.style.display = 'block';
      summaryNoteEl.textContent = parts.join(' ');
      return;
    }
    summaryNoteEl.style.display = 'block';
    summaryNoteEl.textContent = 'Counts unavailable (backend not providing summary).';
  }

  function renderRows(rows) {
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      setTableMessage(bodyEl, 10, 'No queue entries found.');
      return;
    }

    rows.forEach((row) => {
      const error = row.error_message ? String(row.error_message) : '--';
      const permission = fmt(row.permission_string);

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="cell-wrap"><span class="copyable mono" data-copy="${esc(permission)}">${esc(permission)}</span></td>
        <td class="cell-wrap">${renderPopulationCell(row)}</td>
        <td class="cell-wrap">${renderSignalsCell(row)}</td>
        <td>${formatActionCell(row)}</td>
        <td>${formatStatusCell(row)}</td>
        <td class="cell-wrap">${renderTriageCell(row)}</td>
        <td>${esc(fmt(row.proposed_classification))}</td>
        <td>${esc(fmt(row.proposed_bucket))}</td>
        <td class="cell-wrap">${renderTimelineCell(row)}</td>
        <td class="cell-wrap">${esc(error)}</td>
      `;
      bodyEl.appendChild(tr);
    });

    if (readonly.bindCopy) readonly.bindCopy(bodyEl);
  }

  function renderMeta(payload, meta) {
    if (!metaEl) return;
    const rows = payload && (payload.items || payload.rows) ? (payload.items || payload.rows) : [];
    const generated = payload && payload.generated_at_utc
      ? formatUtcFixed(payload.generated_at_utc)
      : (meta && meta.generated_at_utc ? formatUtcFixed(meta.generated_at_utc) : '--');
    const total = payload && payload.total_count !== undefined ? `Total: ${payload.total_count} | ` : '';
    metaEl.textContent = `${total}Showing: ${rows.length} | Last refresh: ${generated}`;
  }

  function renderPaging() {
    if (!pager) return;
    if (pageIndexEl) pageIndexEl.textContent = String(pager.getPageIndex());
    if (prevBtn) prevBtn.disabled = pager.getStackSize() === 0;
    if (nextBtn) nextBtn.disabled = !pager.getHasMore() || !pager.getNext();
  }

  function buildQuery() {
    const params = new URLSearchParams();
    params.set('include_population_counts', '0');
    if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
    if (statusEl && statusEl.value) params.set('status', statusEl.value);
    if (actionEl && actionEl.value) params.set('queue_action', actionEl.value);
    const limitValue = limitEl && limitEl.value ? limitEl.value : defaultLimit;
    if (limitValue) params.set('limit', limitValue);
    if (pager && pager.getCurrent()) params.set('cursor', pager.getCurrent());
    return params;
  }

  function updateUrl(params) {
    const url = new URL(window.location.href);
    const keep = new URLSearchParams();
    ['search', 'status', 'queue_action', 'limit'].forEach((key) => {
      if (params.has(key)) keep.set(key, params.get(key));
    });
    url.search = keep.toString();
    window.history.replaceState({}, '', url.toString());
  }

  async function loadQueue() {
    if (!endpoint) return;
    if (errorEl) errorEl.textContent = '';
    setTableMessage(bodyEl, 10, 'Loading queue...');
    clearUnavailable();

    const params = buildQuery();
    updateUrl(params);
    const url = endpoint + '?' + params.toString();

    try {
      const res = await fetchPayload(url);
      if (!res.ok) {
        if (res.code === 'ERR_SCHEMA_MISSING' || res.status === 404) {
          setUnavailable('Backend capability not enabled for the permission queue yet.');
          setBannerApiRunning();
          setTableMessage(bodyEl, 10, 'Not available.');
          renderSummary(null);
          renderPopulationSummary(null);
          return;
        }
        if (shouldFallback(res)) {
          setBannerFallback();
        } else {
          setBannerApiRunning();
        }
        const detail = res.raw ? `\n\n${String(res.raw).slice(0, 2000)}` : '';
        if (errorEl) {
          errorEl.innerHTML = '<pre>Permission queue API error.\n\nHTTP ' + res.status + '\nerror: ' +
            esc(res.error || 'Request failed') + detail + '</pre>';
        }
        return;
      }

      setBannerApiRunning();

      const payload = res.data || {};
      const rows = payload.items || payload.rows || [];
      if (pager) {
        pager.updateFromPayload(payload);
      }
      renderRows(rows);
      renderSummary(payload.counts_by_status || payload.countsByStatus || null);
      renderPopulationSummary(payload.counts_by_population || null);
      renderSummaryNote(payload);
      renderMeta(payload, res.meta || {});
      renderPaging();
    } catch (e) {
      if (errorEl) {
        errorEl.innerHTML = '<pre>Permission queue API error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
      }
    }
  }

  function resetPaging() {
    if (!pager) return;
    pager.reset();
    renderPaging();
  }

  function scheduleReload() {
    if (pendingTimer) clearTimeout(pendingTimer);
    pendingTimer = setTimeout(() => {
      resetPaging();
      loadQueue();
    }, 200);
  }

  if (limitEl && !limitEl.value) {
    limitEl.value = String(defaultLimit);
  }

  [searchEl, statusEl, actionEl, limitEl].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', scheduleReload);
    el.addEventListener('change', scheduleReload);
  });

  if (refreshBtn) refreshBtn.addEventListener('click', () => loadQueue());

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (!pager || !pager.getHasMore() || !pager.getNext()) return;
      pager.push();
      pager.setCurrent(pager.getNext());
      loadQueue();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (!pager || pager.getStackSize() === 0) return;
      pager.pop();
      loadQueue();
    });
  }

  resetPaging();
  loadQueue();

  if (compactBtn) {
    compactBtn.addEventListener('click', () => {
      const enabled = pageRoot ? pageRoot.classList.contains('compact') : false;
      setCompact(!enabled);
    });
  }
  loadCompact();
})();
