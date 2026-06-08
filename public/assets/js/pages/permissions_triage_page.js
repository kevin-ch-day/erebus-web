(() => {
  const root = document.getElementById('perm-triage-page');
  if (!root || !window.App || !window.PermissionIntel || !window.PermTriage) return;

  const endpoint = root.dataset.intelEndpoint || '';
  const defaultLimit = root.dataset.limit || '100';
  const actionableStatusRaw = root.dataset.actionableStatuses || '[]';
  const resolvedStatusRaw = root.dataset.resolvedStatuses || '[]';
  const triageStatusRaw = root.dataset.triageStatuses || '[]';
  if (!endpoint) return;

  const limitEl = document.getElementById('perm-unknown-limit');
  const searchEl = document.getElementById('perm-unknown-search');
  const namespaceEl = document.getElementById('perm-unknown-namespace');
  const riskEl = document.getElementById('perm-unknown-risk');
  const statusEl = document.getElementById('perm-unknown-status');
  const reviewLaneEl = document.getElementById('perm-review-lane');
  const showResolvedEl = document.getElementById('perm-unknown-show-resolved');
  const queuedEl = document.getElementById('perm-unknown-queued');
  const sortEl = document.getElementById('perm-unknown-sort');
  const quickHighNewBtn = document.getElementById('perm-quick-high-new');
  const quickOemBtn = document.getElementById('perm-quick-oem');
  const quickQueuedBtn = document.getElementById('perm-quick-queued');
  const quickResetBtn = document.getElementById('perm-quick-reset');
  const pagePrevBtn = document.getElementById('perm-page-prev');
  const pageNextBtn = document.getElementById('perm-page-next');
  const pageInfoEl = document.getElementById('perm-page-info');
  const filterSummaryEl = document.getElementById('perm-filter-summary');
  const unknownBody = document.getElementById('perm-unknown-body');
  const errorEl = document.getElementById('perm-triage-error');
  const sessionHighEl = document.getElementById('perm-session-high');
  const sessionMediumEl = document.getElementById('perm-session-medium');
  const sessionLowEl = document.getElementById('perm-session-low');
  const sessionTotalEl = document.getElementById('perm-session-total');
  const sessionLastOkEl = document.getElementById('perm-session-last-ok');
  const sessionTaxonomyEl = document.getElementById('perm-session-taxonomy');
  const sessionNoteEl = document.getElementById('perm-session-note');
  const queueTotalEl = document.getElementById('perm-queue-total');
  const queueLastEl = document.getElementById('perm-queue-last');
  const queueAppliedEl = document.getElementById('perm-queue-applied');
  const queueAppliedCountEl = document.getElementById('perm-queue-applied-count');
  const queueErrorCountEl = document.getElementById('perm-queue-error-count');
  const queueErrorEl = document.getElementById('perm-queue-error');
  const sessionStartHighBtn = document.getElementById('perm-session-start-high');
  const sessionReviewNextBtn = document.getElementById('perm-session-review-next');
  const sessionResumeBtn = document.getElementById('perm-session-resume');
  const messageEl = document.getElementById('perm-triage-message');
  const shellContentEl = document.getElementById('perm-triage-shell-content');
  const loadingCardEl = document.getElementById('perm-triage-loading-card');
  const loadingTextEl = document.getElementById('perm-triage-loading-text');
  const tableHeadEl = document.querySelector('#perm-unknown-table thead');
  const reviewLaneNoteEl = document.getElementById('perm-review-lane-note');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const PermTriage = window.PermTriage;
  const pageUrl = App.pageUrl;
  const currentPageUrl = App.currentPageUrl;

  const REVIEW_LANE_LABELS = {
    active: 'Active review',
    governed: 'Governed current UNKNOWNs',
    ledger: 'Ledger diagnostics',
  };
  const REVIEW_LANE_SELECT_LABELS = {
    active: 'Active review',
    governed: 'Governed current UNKNOWNs',
    ledger: 'Ledger diagnostics',
  };
  const REVIEW_LANE_RENDER_LABELS = {
    active_review_candidate: 'Active review',
    governed_launcher_ecosystem: 'Governed launcher ecosystem',
    governed_known_google: 'Governed known Google',
    malformed_or_conflict: 'Malformed or conflict',
    resolved_or_dictionary_known: 'Resolved or dictionary-known',
    missing_ledger_context: 'Missing ledger context',
  };
  const DIAGNOSTIC_LABELS = {
    ledger_only_no_evidence: 'Ledger only / no current UNKNOWNs',
    resolved_high_seen_historical: 'Resolved high-seen historical',
    recent_ledger_without_evidence: 'Recent ledger without evidence',
    governed_historical_residue: 'Governed historical residue',
    orphan_ledger_row: 'Orphan ledger row',
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
  const triageStatusMap = new Map();
  const actionableStatuses = [];
  const resolvedStatuses = [];
  const storageKey = 'perm-triage-last';
  let initialView = 'active';
  let initialNamespace = '';
  let initialStatus = '';
  let initialRisk = '';
  let initialQueued = '';
  let initialSort = 'seen_desc';
  let initialPage = 1;
  let initialSearch = '';
  let initialShowResolved = false;
  let hasLoadedOnce = false;

  function setPageLoading(message) {
    if (loadingTextEl) loadingTextEl.textContent = message || 'Loading triage workspace...';
    if (loadingCardEl) loadingCardEl.style.display = '';
    if (shellContentEl) shellContentEl.classList.add('pi-shell-content-hidden');
  }

  function setPageReady() {
    if (loadingCardEl) loadingCardEl.style.display = 'none';
    if (shellContentEl) shellContentEl.classList.remove('pi-shell-content-hidden');
    hasLoadedOnce = true;
  }

  function setPageError(message) {
    if (loadingTextEl) loadingTextEl.textContent = message || 'Failed to load triage workspace.';
    if (loadingCardEl) loadingCardEl.style.display = '';
    if (shellContentEl) shellContentEl.classList.add('pi-shell-content-hidden');
  }

  try {
    const params = new URLSearchParams(window.location.search);
    initialView = params.get('view') || params.get('lane') || 'active';
    initialNamespace = params.get('namespace') || '';
    initialStatus = params.get('status') || '';
    initialRisk = params.get('risk') || '';
    const queuedParam = params.get('queued') || '';
    const queueStatuses = Array.isArray(PermTriage.queueStatuses) ? PermTriage.queueStatuses : ['queued', 'claimed', 'applied', 'error', 'rejected', 'skipped'];
    if (queueStatuses.includes(queuedParam.toLowerCase())) {
      initialQueued = queuedParam;
    }
    const initialPageParam = Number(params.get('page') || '1');
    if (Number.isFinite(initialPageParam) && initialPageParam > 0) {
      initialPage = Math.floor(initialPageParam);
      pageMeta.page = initialPage;
    }
    const sortParam = params.get('sort') || '';
    if (sortParam) {
      initialSort = sortParam;
    }
    initialSearch = params.get('q') || params.get('search') || '';
    const showResolvedParam = params.get('show_resolved') || '';
    initialShowResolved = showResolvedParam === '1' || showResolvedParam.toLowerCase() === 'true';
    const saved = params.get('saved');
    const queued = params.get('queued');
    if (saved && messageEl) {
      const queuedBanner = queued === '1' || queued === 'true';
      messageEl.textContent = queuedBanner
        ? 'Decision saved. Dictionary update queued for maintenance review.'
        : 'Decision saved. Review queue updated.';
      messageEl.style.display = 'block';
    }
  } catch (_) {
    initialNamespace = '';
  }

  function updateUrl(limit, filters, paging) {
    const url = new URL(window.location.href);
    url.searchParams.set('limit', limit);
    url.searchParams.set('page_size', limit);
    url.searchParams.set('page', String((paging && paging.page) || 1));
    url.searchParams.set('sort', String((paging && paging.sort) || 'seen_desc'));
    const view = filters && filters.view ? filters.view : '';
    const term = filters && filters.term ? filters.term : '';
    const namespace = filters && filters.namespace ? filters.namespace : '';
    const risk = filters && filters.risk ? filters.risk : '';
    const status = filters && filters.status ? filters.status : '';
    const queued = filters && filters.queued ? filters.queued : '';
    const showResolved = showResolvedEl && showResolvedEl.checked;

    if (term) {
      url.searchParams.set('q', term);
    } else {
      url.searchParams.delete('q');
    }
    if (view) {
      url.searchParams.set('view', view);
    } else {
      url.searchParams.delete('view');
    }
    if (namespace) {
      url.searchParams.set('namespace', namespace);
    } else {
      url.searchParams.delete('namespace');
    }
    if (risk) {
      url.searchParams.set('risk', risk);
    } else {
      url.searchParams.delete('risk');
    }
    if (status) {
      url.searchParams.set('status', status);
    } else {
      url.searchParams.delete('status');
    }
    if (queued) {
      url.searchParams.set('queued', queued);
    } else {
      url.searchParams.delete('queued');
    }
    if (showResolved) {
      url.searchParams.set('show_resolved', '1');
    } else {
      url.searchParams.delete('show_resolved');
    }
    window.history.replaceState({}, '', url.toString());
  }

  function parseTriageStatuses() {
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
      const key = String(status.key || '').toLowerCase();
      if (key) {
        triageStatusMap.set(key, status.label || status.key);
      }
    });
  }

  function parseStatusList(raw, target) {
    target.length = 0;
    if (!raw) return;
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        parsed.forEach((item) => {
          const key = String(item || '').toLowerCase();
          if (key && !target.includes(key)) {
            target.push(key);
          }
        });
      }
    } catch (_) {
      // ignore
    }
  }

  function selectedReviewLane() {
    const value = reviewLaneEl ? String(reviewLaneEl.value || '').toLowerCase() : '';
    return value || 'active';
  }

  function allowedStatuses() {
    const selected = statusEl ? statusEl.value : '';
    if (selected) return [];
    if (selectedReviewLane() !== 'active') {
      return [];
    }
    const list = actionableStatuses.slice();
    if (showResolvedEl && showResolvedEl.checked) {
      resolvedStatuses.forEach((status) => {
        if (!list.includes(status)) list.push(status);
      });
    }
    return list;
  }

  function currentFilters() {
    return {
      term: searchEl ? searchEl.value.trim() : '',
      view: selectedReviewLane(),
      namespace: namespaceEl ? namespaceEl.value : '',
      risk: riskEl ? riskEl.value : '',
      status: statusEl ? statusEl.value : '',
      allowedStatuses: allowedStatuses(),
      queued: queuedEl ? queuedEl.value : '',
      includeResolved: showResolvedEl ? showResolvedEl.checked : false,
      sort: sortEl ? sortEl.value : 'seen_desc',
    };
  }

  function reviewUrl(permission) {
    return pageUrl('permissions_review', {
      permission: permission || '',
      return: currentPageUrl(),
    });
  }

  function evidenceUrl(permission) {
    return pageUrl('permissions_evidence', {
      permission: permission || '',
      return: currentPageUrl(),
    });
  }

  function viewLabel(view) {
    return REVIEW_LANE_LABELS[String(view || '').toLowerCase()] || REVIEW_LANE_LABELS.active;
  }

  function selectedRows(view) {
    const lane = String(view || selectedReviewLane()).toLowerCase();
    if (lane === 'governed') return governedReviewRows;
    if (lane === 'ledger') return ledgerDiagnosticRows;
    return activeReviewRows;
  }

  function selectedPage(view) {
    const lane = String(view || selectedReviewLane()).toLowerCase();
    if (lane === 'governed') return governedPage;
    if (lane === 'ledger') return ledgerPage;
    return activePage;
  }

  function renderLaneHeaders(view) {
    if (!tableHeadEl) return;
    const lane = String(view || selectedReviewLane()).toLowerCase();
    if (lane === 'ledger') {
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
    if (lane === 'governed') {
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
  }

  function updateReviewLaneNote(view) {
    if (!reviewLaneNoteEl) return;
    const lane = String(view || selectedReviewLane()).toLowerCase();
    if (lane === 'governed') {
      reviewLaneNoteEl.textContent = 'Governed current UNKNOWNs are already explained, dictionary-known, malformed, or missing ledger context. The counts here are current sample/observation footprint for governed residue, not live active-UNKNOWN backlog.';
      return;
    }
    if (lane === 'ledger') {
      reviewLaneNoteEl.textContent = 'Ledger diagnostics are historical workflow context. Treat high seen_count as residue pressure, then confirm real current-sample behavior in Evidence or Fusion before acting.';
      return;
    }
    reviewLaneNoteEl.textContent = 'Active review is evidence-backed by default. Switch lanes to inspect governed rows or ledger diagnostics.';
  }

  function renderCurrentLane(filters) {
    const lane = selectedReviewLane();
    const rows = selectedRows(lane);
    const filtered = PermTriage.applyFilters(rows, filters);
    const hasExplicitFilter = Boolean(filters.term || filters.namespace || filters.risk || filters.status || filters.queued);
    const pageForLane = selectedPage(lane);
    let emptyMessage = `No ${viewLabel(lane).toLowerCase()} rows in the current snapshot.`;
    if (hasExplicitFilter) {
      emptyMessage = 'No permissions match the current filters.';
    }
    if (lane === 'active' && !filters.includeResolved && !hasExplicitFilter) {
      const governedCount = Number((governedPage && governedPage.total_count) || 0);
      if (governedCount > 0) {
        emptyMessage = `No current evidence-backed review rows in the active lane. ${PermissionIntel.formatCount(governedCount)} governed current UNKNOWN row${governedCount === 1 ? ' remains' : 's remain'}; switch to Governed current UNKNOWNs.`;
      } else {
        emptyMessage = 'No current evidence-backed review rows in the active lane.';
      }
    }
    if (lane === 'governed' && !hasExplicitFilter) {
      emptyMessage = 'No governed current UNKNOWN rows in the current snapshot.';
    }
    if (lane === 'ledger' && !hasExplicitFilter) {
      emptyMessage = 'No ledger diagnostic rows in the current snapshot.';
    }
    renderLaneHeaders(lane);
    updateReviewLaneNote(lane);
    if (lane === 'ledger') {
      PermTriage.renderLedgerDiagnosticsTable(filtered, {
        bodyEl: unknownBody,
        diagnosticLabelMap: DIAGNOSTIC_LABELS,
        emptyMessage,
        onInspect: (row) => {
          const permission = fmt(row.permission_string, '');
          if (!permission) return;
          try {
            window.localStorage.setItem(storageKey, permission);
          } catch (_) {
            // no-op
          }
          window.location.href = reviewUrl(permission);
        },
      });
    } else {
      PermTriage.renderCurrentEvidenceTable(filtered, {
        bodyEl: unknownBody,
        reviewLaneLabelMap: REVIEW_LANE_RENDER_LABELS,
        emptyMessage,
        showQueue: false,
        onReview: (row) => {
          const permission = fmt(row.permission_string, '');
          if (!permission) return;
          try {
            window.localStorage.setItem(storageKey, permission);
          } catch (_) {
            // no-op
          }
          window.location.href = reviewUrl(permission);
        },
        onEvidence: (row) => {
          const permission = fmt(row.permission_string, '');
          if (!permission) return;
          window.location.href = evidenceUrl(permission);
        },
      });
    }
    if (PermTriage.renderFilterSummary) {
      PermTriage.renderFilterSummary(filterSummaryEl, { ...filters, view: lane }, pageForLane, triageStatusMap);
    }
    pageMeta = pageForLane || pageMeta;
  }

  function applyInitialFilters() {
    if (reviewLaneEl && initialView) {
      const lane = String(initialView || '').toLowerCase();
      if (['active', 'governed', 'ledger'].includes(lane)) {
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
  }

  function renderPagingControls() {
    const page = Number(pageMeta.page || 1);
    const totalPages = Math.max(1, Number(pageMeta.total_pages || 1));
    const total = Number(pageMeta.total_count || 0);
    const pageSize = Number(pageMeta.page_size || (limitEl ? Number(limitEl.value || defaultLimit) : Number(defaultLimit)));
    const start = total > 0 ? ((page - 1) * pageSize) + 1 : 0;
    const end = Math.min(total, page * pageSize);

    if (pageInfoEl) pageInfoEl.textContent = `Page ${page}/${totalPages} | Rows ${start}-${end} of ${total}`;
    if (pagePrevBtn) pagePrevBtn.disabled = page <= 1;
    if (pageNextBtn) pageNextBtn.disabled = page >= totalPages || !pageMeta.has_more;
  }

  function readLastReviewedPermission() {
    try {
      return window.localStorage.getItem(storageKey) || '';
    } catch (_) {
      return '';
    }
  }

  function setButtonEnabled(button, enabled, disabledTitle = '') {
    if (!button) return;
    button.disabled = !enabled;
    if (!enabled && disabledTitle) {
      button.setAttribute('title', disabledTitle);
      return;
    }
    button.removeAttribute('title');
  }

  function updateSessionControlState(activeRows) {
    const rows = Array.isArray(activeRows) ? activeRows : [];
    const hasActiveRows = rows.length > 0;
    const hasHighRiskNew = rows.some((row) => {
      const status = String(row.dict_unknown_triage_status || row.triage_status || '').toLowerCase();
      const risk = PermissionIntel.riskHint(row.permission_string, row.namespace);
      return status === 'new' && String(risk.label || '').toLowerCase() === 'high';
    });
    const lastReviewed = readLastReviewedPermission();

    setButtonEnabled(
      sessionStartHighBtn,
      hasHighRiskNew,
      'No high-risk current evidence rows are available in the active lane.'
    );
    setButtonEnabled(
      sessionReviewNextBtn,
      hasActiveRows,
      'No current evidence review rows are available in the active lane.'
    );
    setButtonEnabled(
      sessionResumeBtn,
      Boolean(lastReviewed),
      'No previously reviewed permission is stored for this browser session.'
    );
  }

  function openNextFromCurrentPage() {
    if (!Array.isArray(filteredRows) || !filteredRows.length) return;
    const next = PermTriage.findNextRow(filteredRows);
    if (next) {
      window.location.href = reviewUrl(fmt(next.permission_string, ''));
    }
  }

  async function loadTriage(options = {}) {
    const opts = options || {};
    if (opts.resetPage) {
      pageMeta.page = 1;
    }
    const limit = limitEl ? (limitEl.value || defaultLimit) : defaultLimit;
    const filters = currentFilters();
    updateUrl(limit, filters, { page: pageMeta.page, sort: filters.sort || 'seen_desc' });
    errorEl.textContent = '';
    if (!hasLoadedOnce) {
      setPageLoading('Loading triage workspace...');
    }
    unknownBody.innerHTML = '<tr><td colspan="7" class="muted">Loading review lane...</td></tr>';

    try {
      const qs = new URLSearchParams();
      qs.set('limit', String(limit));
      qs.set('page_size', String(limit));
      qs.set('page', String(pageMeta.page || 1));
      qs.set('sort', String(filters.sort || 'seen_desc'));
      qs.set('include_resolved', filters.includeResolved ? '1' : '0');
      if (filters.term) qs.set('q', filters.term);
      if (filters.namespace) qs.set('namespace', filters.namespace);
      if (filters.risk) qs.set('risk', filters.risk);
      if (filters.status) qs.set('status', filters.status);
      if (filters.queued) qs.set('queued', filters.queued);
      if (filters.view) qs.set('view', filters.view);

      qs.set('mode', 'triage');
      const url = new URL(endpoint, window.location.origin);
      url.search = qs.toString();
      const res = await App.fetchJson(url.toString());
      if (!res.ok) {
        if (!hasLoadedOnce) {
          setPageError('Failed to load triage workspace.');
        }
        errorEl.innerHTML = '<pre>Permission triage error.\n\nHTTP ' + res.status + '\nerror: ' +
          esc(res.error) + '</pre>';
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
      PermTriage.populateStatusFilter(statusEl, triageStatuses);
      const laneRows = selectedRows(filters.view);
      const activeSnapshotFilters = {
        ...filters,
        view: 'active',
      };
      const activeSnapshotRows = PermTriage.applyFilters(activeReviewRows, activeSnapshotFilters);
      filteredRows = activeSnapshotRows;
      updateSessionControlState(activeSnapshotRows);
      initialNamespace = PermTriage.populateNamespaceFilter(namespaceEl, laneRows, initialNamespace);
      const riskCounts = activeSnapshotRows.reduce((acc, row) => {
        const hint = PermissionIntel.riskHint(row.permission_string, row.namespace);
        const key = String(hint.label || '').toLowerCase();
        if (key === 'high' || key === 'medium' || key === 'low') {
          acc[key] = (acc[key] || 0) + 1;
        }
        return acc;
      }, { high: 0, medium: 0, low: 0 });
      PermTriage.renderSessionHeader(data.triage_status_counts || {}, data.session || {}, data.health || {}, data.taxonomy || {}, {
        sessionHighEl,
        sessionMediumEl,
        sessionLowEl,
        sessionTotalEl,
        sessionLastOkEl,
        sessionTaxonomyEl,
        sessionNoteEl,
      }, {
        ...(data.metrics || {}),
        current_evidence_risk_counts: riskCounts,
      }, data.operator_summary || {}, activeSnapshotRows);
      const queue = data.queue || {};
      if (queueTotalEl) queueTotalEl.textContent = PermissionIntel.formatCount(queue.queued_current_unknown_count ?? queue.queued_count ?? 0);
      if (queueLastEl) {
        const lastCurrentQueued = queue.last_current_unknown_queued_at_utc || null;
        queueLastEl.textContent = lastCurrentQueued ? App.formatUtc(lastCurrentQueued) : '--';
      }
      if (queueAppliedCountEl) queueAppliedCountEl.textContent = PermissionIntel.formatCount(queue.applied_count || 0);
      if (queueAppliedEl) queueAppliedEl.textContent = queue.last_applied_at_utc ? App.formatUtc(queue.last_applied_at_utc) : '--';
      if (queueErrorCountEl) queueErrorCountEl.textContent = PermissionIntel.formatCount(queue.error_count || 0);
      if (queueErrorEl) queueErrorEl.textContent = queue.last_error_at_utc ? App.formatUtc(queue.last_error_at_utc) : '--';
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
        setPageError('Failed to load triage workspace.');
      }
      errorEl.innerHTML = '<pre>Permission triage error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
    }
  }

  if (limitEl) {
    limitEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (searchEl) {
    searchEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (namespaceEl) {
    namespaceEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (riskEl) {
    riskEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (statusEl) {
    statusEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (reviewLaneEl) {
    reviewLaneEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (showResolvedEl) {
    showResolvedEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (queuedEl) {
    queuedEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (sortEl) {
    sortEl.addEventListener('change', () => loadTriage({ resetPage: true }));
  }
  if (pagePrevBtn) {
    pagePrevBtn.addEventListener('click', () => {
      if (Number(pageMeta.page || 1) <= 1) return;
      pageMeta.page = Number(pageMeta.page || 1) - 1;
      loadTriage({ resetPage: false });
    });
  }
  if (pageNextBtn) {
    pageNextBtn.addEventListener('click', () => {
      const page = Number(pageMeta.page || 1);
      const totalPages = Math.max(1, Number(pageMeta.total_pages || 1));
      if (page >= totalPages) return;
      pageMeta.page = page + 1;
      loadTriage({ resetPage: false });
    });
  }
  if (quickHighNewBtn) {
    quickHighNewBtn.addEventListener('click', () => {
      if (reviewLaneEl) reviewLaneEl.value = 'active';
      if (statusEl) statusEl.value = 'new';
      if (riskEl) riskEl.value = 'high';
      if (showResolvedEl) showResolvedEl.checked = false;
      if (namespaceEl) namespaceEl.value = '';
      if (queuedEl) queuedEl.value = '';
      if (searchEl) searchEl.value = '';
      loadTriage({ resetPage: true });
    });
  }
  if (quickOemBtn) {
    quickOemBtn.addEventListener('click', () => {
      if (reviewLaneEl) reviewLaneEl.value = 'active';
      if (statusEl) statusEl.value = 'oem_candidate';
      if (showResolvedEl) showResolvedEl.checked = false;
      if (riskEl) riskEl.value = '';
      if (namespaceEl) namespaceEl.value = '';
      if (queuedEl) queuedEl.value = '';
      if (searchEl) searchEl.value = '';
      loadTriage({ resetPage: true });
    });
  }
  if (quickQueuedBtn) {
    quickQueuedBtn.addEventListener('click', () => {
      if (reviewLaneEl) reviewLaneEl.value = 'active';
      if (queuedEl) queuedEl.value = 'queued';
      if (showResolvedEl) showResolvedEl.checked = true;
      if (statusEl) statusEl.value = '';
      if (riskEl) riskEl.value = '';
      if (namespaceEl) namespaceEl.value = '';
      if (searchEl) searchEl.value = '';
      loadTriage({ resetPage: true });
    });
  }
  if (quickResetBtn) {
    quickResetBtn.addEventListener('click', () => {
      if (reviewLaneEl) reviewLaneEl.value = 'active';
      if (statusEl) statusEl.value = '';
      if (showResolvedEl) showResolvedEl.checked = false;
      if (riskEl) riskEl.value = '';
      if (namespaceEl) namespaceEl.value = '';
      if (queuedEl) queuedEl.value = '';
      if (searchEl) searchEl.value = '';
      if (sortEl) sortEl.value = 'seen_desc';
      loadTriage({ resetPage: true });
    });
  }

  if (sessionStartHighBtn) {
    sessionStartHighBtn.addEventListener('click', async () => {
      if (reviewLaneEl) reviewLaneEl.value = 'active';
      if (riskEl) riskEl.value = 'high';
      if (statusEl) statusEl.value = 'new';
      if (namespaceEl) namespaceEl.value = '';
      if (searchEl) searchEl.value = '';
      await loadTriage({ resetPage: true, autoOpenNext: true });
    });
  }
  if (sessionReviewNextBtn) {
    sessionReviewNextBtn.addEventListener('click', () => {
      openNextFromCurrentPage();
    });
  }
  if (sessionResumeBtn) {
    sessionResumeBtn.addEventListener('click', () => {
      const last = readLastReviewedPermission();
      if (!last) return;
      window.location.href = reviewUrl(last);
    });
  }

  parseTriageStatuses();
  parseStatusList(actionableStatusRaw, actionableStatuses);
  parseStatusList(resolvedStatusRaw, resolvedStatuses);
  PermTriage.populateStatusFilter(statusEl, triageStatuses);
  applyInitialFilters();
  updateSessionControlState([]);
  setPageLoading('Loading triage workspace...');
  loadTriage({ resetPage: false });
})();
