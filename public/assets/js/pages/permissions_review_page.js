(() => {
  const root = document.getElementById('perm-review-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const reviewEndpoint = root.dataset.reviewEndpoint || '';
  const updateEndpoint = root.dataset.updateEndpoint || '';
  const queueEndpoint = root.dataset.queueEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const permissionValue = root.dataset.permission || '';
  const returnUrl = root.dataset.returnUrl || App.pageUrl('permissions_triage');

  const permissionEl = document.getElementById('review-permission');
  const namespaceEl = document.getElementById('review-namespace');
  const riskEl = document.getElementById('review-risk');
  const seenEl = document.getElementById('review-seen');
  const lastSeenEl = document.getElementById('review-last-seen');
  const statusPillEl = document.getElementById('review-status-pill');
  const bucketEl = document.getElementById('review-bucket');
  const statusSelect = document.getElementById('review-status');
  const notesEl = document.getElementById('review-notes');
  const classificationEl = document.getElementById('review-classification');
  const evidenceSummaryEl = document.getElementById('review-evidence-summary');
  const evidenceLinkEl = document.getElementById('review-evidence-link');
  const quickActionsEl = document.getElementById('review-quick-actions');
  const impactEl = document.getElementById('review-impact');
  const decisionSummaryEl = document.getElementById('review-decision-summary');
  const queueActionEl = document.getElementById('review-queue-action');
  const queueBucketEl = document.getElementById('review-queue-bucket');
  const queueClassificationEl = document.getElementById('review-queue-classification');
  const queueNotesEl = document.getElementById('review-queue-notes');
  const queueSubmitBtn = document.getElementById('review-queue-submit');
  const queueNoteEl = document.getElementById('review-queue-note');
  const saveBtn = document.getElementById('review-save');
  const statusNoteEl = document.getElementById('review-status-note');
  const errorEl = document.getElementById('review-error');
  const saveMessageEl = document.getElementById('review-save-message');
  const copyBtn = document.getElementById('review-copy');
  const stepNotesEl = document.getElementById('review-step-notes');
  const stepQueueEl = document.getElementById('review-step-queue');
  const layoutEl = document.getElementById('review-layout');
  const sideEl = document.getElementById('review-side');
  const evidenceCardEl = document.getElementById('review-evidence-card');
  const ledgerNoteEl = document.getElementById('review-ledger-note');
  const shellContentEl = document.getElementById('review-shell-content');
  const loadingCardEl = document.getElementById('review-loading-card');
  const loadingTextEl = document.getElementById('review-loading-text');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const fmtCount = PermissionIntel.formatCount;
  const fmtUtc = App.formatUtc;
  const pageUrl = App.pageUrl;
  const appendQueryParam = App.appendQueryParam;

  let triageStatuses = [];
  let triageStatusMap = new Map();
  let triageStatusMetaMap = new Map();
  let bucketLabelMap = new Map();
  let bucketDefs = [];
  let currentRow = null;
  let currentMeta = {};
  let activePermission = permissionValue;
  let queueActions = [];
  let classificationDefs = [];
  let queueActionTouched = false;
  let queueBucketTouched = false;
  let queueClassificationTouched = false;
  let queueSubmitted = false;

  function triageMeta(statusRaw) {
    const key = String(statusRaw || '').toLowerCase();
    return key ? (triageStatusMetaMap.get(key) || null) : null;
  }

  function setPageLoading(message) {
    if (loadingTextEl) {
      loadingTextEl.textContent = message || 'Loading permission review...';
    }
    if (loadingCardEl) {
      loadingCardEl.style.display = '';
    }
    if (shellContentEl) {
      shellContentEl.classList.add('review-shell-content-hidden');
    }
  }

  function setPageReady() {
    if (loadingCardEl) {
      loadingCardEl.style.display = 'none';
    }
    if (shellContentEl) {
      shellContentEl.classList.remove('review-shell-content-hidden');
    }
  }

  function setPageError(message) {
    if (loadingTextEl) {
      loadingTextEl.textContent = message || 'Failed to load permission review.';
    }
    if (loadingCardEl) {
      loadingCardEl.style.display = '';
    }
    if (shellContentEl) {
      shellContentEl.classList.add('review-shell-content-hidden');
    }
  }

  function suggestedQueueAction(statusRaw) {
    const meta = triageMeta(statusRaw);
    return meta && meta.suggested_queue_action ? String(meta.suggested_queue_action) : '';
  }

  function queueActionLabel(actionKey) {
    const key = String(actionKey || '').toLowerCase();
    const match = queueActions.find((entry) => String(entry.key || '').toLowerCase() === key);
    return match ? (match.label || match.key || actionKey) : actionKey;
  }

  function suggestedQueueBucket(statusRaw) {
    const meta = triageMeta(statusRaw);
    return meta && meta.suggested_queue_bucket ? String(meta.suggested_queue_bucket) : '';
  }

  function suggestedQueueClassification(statusRaw) {
    const meta = triageMeta(statusRaw);
    return meta && meta.suggested_queue_classification ? String(meta.suggested_queue_classification) : '';
  }

  function backlogEffectLabel(statusRaw) {
    const meta = triageMeta(statusRaw);
    if (meta && meta.backlog_effect) return String(meta.backlog_effect);
    const key = String(statusRaw || '').toLowerCase();
    if (!key || key === 'new') return 'Stays in active review backlog';
    return 'Leaves default review backlog';
  }

  function summaryChip(label, value, tone = '') {
    const toneClass = tone ? ` review-summary-chip-${tone}` : '';
    return `<span class="review-summary-chip${toneClass}"><span class="review-summary-chip-label">${App.escapeHtml(label)}</span><span class="review-summary-chip-value">${App.escapeHtml(value)}</span></span>`;
  }

  function renderDecisionSummary(statusRaw) {
    if (!decisionSummaryEl) return;
    if (!statusRaw) {
      decisionSummaryEl.innerHTML = [
        summaryChip('Backlog effect', 'Choose a status'),
        summaryChip('Queue suggestion', 'Optional'),
      ].join('');
      return;
    }
    const queueAction = suggestedQueueAction(statusRaw);
    const queueBucket = suggestedQueueBucket(statusRaw);
    const queueClassification = suggestedQueueClassification(statusRaw);
    const queueLabel = queueAction ? queueActionLabel(queueAction) : 'No default';
    const maintenancePath = queueBucket && queueClassification
      ? `${queueBucket} / ${queueClassification}`
      : 'Manual if needed';
    decisionSummaryEl.innerHTML = [
      summaryChip('Backlog effect', backlogEffectLabel(statusRaw), statusRaw === 'new' ? 'warn' : 'ok'),
      summaryChip('Queue suggestion', queueLabel, queueAction ? 'accent' : ''),
      summaryChip('Maintenance path', maintenancePath),
    ].join('');
  }

  function syncQueueSuggestion(statusRaw) {
    if (!queueActionEl) return;
    const suggested = suggestedQueueAction(statusRaw);
    if (!queueActionTouched && suggested) {
      queueActionEl.value = suggested;
    }
    const suggestedBucket = suggestedQueueBucket(statusRaw);
    if (queueBucketEl && !queueBucketTouched && suggestedBucket) {
      queueBucketEl.value = suggestedBucket;
    }
    const suggestedClassification = suggestedQueueClassification(statusRaw);
    if (queueClassificationEl && !queueClassificationTouched && suggestedClassification) {
      queueClassificationEl.value = suggestedClassification;
    }
    if (stepQueueEl && suggested) {
      stepQueueEl.open = true;
    }
    if (!queueNoteEl) return;
    if (!statusRaw) {
      queueNoteEl.textContent = 'Queue update is optional. Use it only when you want a dictionary maintenance decision recorded now.';
      renderDecisionSummary(statusRaw);
      return;
    }
    if (!suggested) {
      queueNoteEl.textContent = 'No queue action suggested for this status. Queue only if you need to record a manual dictionary decision.';
      renderDecisionSummary(statusRaw);
      return;
    }
    queueNoteEl.textContent = `Suggested queue action: ${queueActionLabel(suggested)}.`;
    renderDecisionSummary(statusRaw);
  }


  function populateStatusSelect(statuses, current) {
    if (!statusSelect) return;
    statusSelect.innerHTML = '';
    const currentKey = String(current || '').toLowerCase();
    if (!Array.isArray(statuses) || statuses.length === 0) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Unavailable';
      statusSelect.appendChild(opt);
      return;
    }
    statuses.forEach((status) => {
      const opt = document.createElement('option');
      opt.value = status.key;
      opt.textContent = status.label || status.key;
      if (currentKey && currentKey === String(status.key).toLowerCase()) {
        opt.selected = true;
      }
      statusSelect.appendChild(opt);
    });
  }

  function updateOutcomePreview() {
    if (!impactEl || !statusSelect) return;
    const statusRaw = String(statusSelect.value || '').toLowerCase();
    const label = triageStatusMap.get(statusRaw) || statusSelect.value || 'Unreviewed';
    const meta = triageMeta(statusRaw);
    const suffix = ' Bucket/classification remain backend-defined.';
    if (!statusRaw) {
      impactEl.textContent = 'Select a triage status to record this decision.' + suffix;
      return;
    }
    if (statusRaw === 'new') {
      impactEl.textContent = `Status set to ${label}. This stays in the actionable triage queue until you change it.` + suffix;
      return;
    }
    let message = `Status set to ${label}. This will no longer appear in the default actionable triage filter.` + suffix;
    if (meta && meta.help_text) {
      message += ` ${String(meta.help_text)}`;
    }
    impactEl.textContent = message;
    syncQueueSuggestion(statusRaw);
  }

  function setStepVisibility(hasStatus) {
    if (stepNotesEl) stepNotesEl.classList.toggle('review-step-hidden', !hasStatus);
    if (stepQueueEl) stepQueueEl.classList.toggle('review-step-hidden', !hasStatus);
  }

  function renderQuickActions() {
    if (!quickActionsEl) return;
    quickActionsEl.innerHTML = '';
    if (!Array.isArray(triageStatuses) || triageStatuses.length === 0) {
      quickActionsEl.style.display = 'none';
      return;
    }
    const preferredKeys = [
      ...triageStatuses
        .filter((status) => status && status.recommended_quick_action)
        .map((status) => String(status.key || '').toLowerCase()),
    ];
    const preferred = triageStatuses.filter((status) =>
      preferredKeys.includes(String(status.key || '').toLowerCase())
    );
    const actions = preferred.length ? preferred : triageStatuses.slice(0, 5);
    if (!actions.length) {
      quickActionsEl.style.display = 'none';
      return;
    }
    const label = document.createElement('span');
    label.className = 'review-quick-label muted';
    label.textContent = 'Common choices:';
    quickActionsEl.appendChild(label);
    actions.forEach((status) => {
      const key = String(status.key || '');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-small btn-muted';
      btn.textContent = status.label || key || 'Set';
      btn.addEventListener('click', () => {
        if (!statusSelect) return;
        statusSelect.value = key;
        updateOutcomePreview();
        setStepVisibility(Boolean(statusSelect.value));
      });
      quickActionsEl.appendChild(btn);
    });
    quickActionsEl.style.display = 'flex';
  }

  function populateQueueActionSelect(actions, current) {
    if (!queueActionEl) return;
    queueActionEl.innerHTML = '';
    if (!Array.isArray(actions) || actions.length === 0) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Unavailable';
      queueActionEl.appendChild(opt);
      queueActionEl.disabled = true;
      return;
    }
    actions.forEach((action) => {
      const opt = document.createElement('option');
      opt.value = action.key;
      opt.textContent = action.label || action.key;
      if (current && String(current).toLowerCase() === String(action.key).toLowerCase()) {
        opt.selected = true;
      }
      queueActionEl.appendChild(opt);
    });
    queueActionEl.disabled = false;
  }

  function populateQueueBucketSelect() {
    if (!queueBucketEl) return;
    queueBucketEl.innerHTML = '<option value="">No bucket</option>';
    bucketDefs.forEach((bucket) => {
      const key = String(bucket.key || '');
      const label = String(bucket.label || bucket.key || '');
      if (!key) return;
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = label || key;
      queueBucketEl.appendChild(opt);
    });
  }

  function populateQueueClassificationSelect() {
    if (!queueClassificationEl) return;
    queueClassificationEl.innerHTML = '<option value="">No classification</option>';
    classificationDefs.forEach((entry) => {
      const opt = document.createElement('option');
      opt.value = entry.key;
      opt.textContent = entry.label || entry.key;
      queueClassificationEl.appendChild(opt);
    });
  }

  function renderReview(row, meta) {
    currentRow = row;
    if (meta) currentMeta = meta;
    if (row && row.permission_string) {
      activePermission = row.permission_string;
    }
    const risk = PermissionIntel.riskHint(row.permission_string, row.namespace);
    const triage = fmt(row.triage_status, '--');
    const triageLabel = triageStatusMap.get(triage.toLowerCase()) || triage;
    const bucketKey = PermissionIntel.normalizeKey(row.bucket);
    const bucketLabel = bucketLabelMap.get(bucketKey) || fmt(row.bucket, 'Unknown / Unclassified');
    const classification = fmt(row.classification, '--');
    const eventCount = row.event_count ?? 0;
    const sampleCount = row.sample_count;

    if (permissionEl) permissionEl.textContent = fmt(row.permission_string, '--');
    if (namespaceEl) namespaceEl.textContent = fmt(row.namespace, '--');
    if (riskEl) riskEl.innerHTML = `<span class="${esc(risk.className)}">${esc(risk.label)}</span>`;
    if (seenEl) seenEl.textContent = fmtCount(eventCount);
    if (lastSeenEl) lastSeenEl.textContent = row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--';
    if (statusPillEl) statusPillEl.innerHTML = `<span class="badge muted">${esc(triageLabel)}</span>`;
    if (bucketEl) bucketEl.textContent = bucketLabel;
    if (classificationEl) classificationEl.textContent = classification;

    if (notesEl) notesEl.value = fmt(row.notes, '');
    populateStatusSelect(triageStatuses, triage);
    updateOutcomePreview();
    setStepVisibility(Boolean(triage));

    if (evidenceSummaryEl) {
      const seen = fmtCount(eventCount);
      const samples = fmtCount(sampleCount);
      const lastSeen = row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--';
      const taxonomy = meta && meta.taxonomy_version ? `taxonomy ${meta.taxonomy_version}` : '';
      const taxonomyText = taxonomy ? `; ${taxonomy}` : '';
      const hasEvidence = Number(sampleCount || 0) > 0 || Number(eventCount || 0) > 0;
      if (!hasEvidence) {
        evidenceSummaryEl.textContent = '';
        if (ledgerNoteEl) {
          ledgerNoteEl.textContent = `Workflow-only row. No VT event evidence is attached in the current ledger${taxonomyText}.`;
          ledgerNoteEl.style.display = 'block';
        }
        if (evidenceCardEl) evidenceCardEl.style.display = 'none';
        if (sideEl) sideEl.classList.add('review-side-hidden');
        if (layoutEl) layoutEl.classList.add('review-layout-wide');
      } else {
        evidenceSummaryEl.textContent = `Evidence samples: ${samples}; VT events: ${seen}; last event seen ${lastSeen}${taxonomyText}.`;
        if (ledgerNoteEl) {
          ledgerNoteEl.style.display = 'none';
          ledgerNoteEl.textContent = '';
        }
        if (evidenceCardEl) evidenceCardEl.style.display = '';
        if (sideEl) sideEl.classList.remove('review-side-hidden');
        if (layoutEl) layoutEl.classList.remove('review-layout-wide');
      }
    }
    if (evidenceLinkEl) {
      const url = pageUrl('permissions_evidence', {
        permission: fmt(row.permission_string, ''),
        return: returnUrl || pageUrl('permissions_triage'),
      });
      evidenceLinkEl.setAttribute('href', url);
    }

    if (queueBucketEl && row.bucket) {
      const bucketKey = PermissionIntel.normalizeKey(row.bucket);
      queueBucketEl.value = bucketKey;
    }
    if (queueClassificationEl && row.classification) {
      queueClassificationEl.value = String(row.classification || '').toUpperCase();
    }

    if (queueActionEl && !queueActionTouched) {
      queueActionEl.value = queueActionEl.value || '';
    }
    syncQueueSuggestion(triage);
  }

  function renderNotFound() {
    setPageError('Permission not found. Return to Triage and try another permission.');
    if (errorEl) {
      errorEl.innerHTML = '<pre>Permission not found. Please return to Triage.</pre>';
    }
    showSaveMessage('Permission not found. Return to Triage.', true);
    setControlsEnabled(false);
  }

  function renderNoSelection() {
    setPageError('No permission selected. Return to Triage and choose a permission.');
    if (errorEl) {
      errorEl.innerHTML = '<pre>No permission selected. Return to Triage and choose a permission.</pre>';
    }
    showSaveMessage('No permission selected. Open Permission Triage and choose a row first.', true);
    setControlsEnabled(false);
  }

  async function loadLov() {
    if (!lovEndpoint) return;
    const res = await App.fetchJson(lovEndpoint);
    if (!res.ok) {
      setPageError('Failed to load triage status options.');
      if (errorEl) {
        errorEl.innerHTML = '<pre>Failed to load triage status list.</pre>';
      }
      setControlsEnabled(false);
      return false;
    }
    const data = res.body.data || {};
    triageStatuses = Array.isArray(data.triage_statuses) ? data.triage_statuses : [];
    triageStatusMap = new Map();
    triageStatusMetaMap = new Map();
    triageStatuses.forEach((status) => {
      const key = String(status.key || '').toLowerCase();
      const label = String(status.label || status.key || '');
      if (key) {
        triageStatusMap.set(key, label);
        triageStatusMetaMap.set(key, status);
      }
    });
    const buckets = Array.isArray(data.buckets) ? data.buckets : [];
    bucketDefs = buckets;
    bucketLabelMap = new Map();
    buckets.forEach((bucket) => {
      const key = PermissionIntel.normalizeKey(bucket.key);
      const label = String(bucket.label || '');
      if (key && label) bucketLabelMap.set(key, label);
    });
    if (!bucketLabelMap.has('OEM_CANDIDATE')) {
      bucketLabelMap.set('OEM_CANDIDATE', 'Unknown / OEM Candidate');
    }
    classificationDefs = Array.isArray(data.classifications) ? data.classifications : [];
    queueActions = Array.isArray(data.queue_actions) ? data.queue_actions : [];
    if (!triageStatuses.length) {
      if (errorEl) {
        errorEl.innerHTML = '<pre>No triage statuses available. Check LOV endpoint.</pre>';
      }
      setControlsEnabled(false);
      setPageError('No triage statuses are available for review.');
      return false;
    } else {
      setControlsEnabled(true);
    }
    renderQuickActions();
    populateQueueActionSelect(queueActions, queueActionEl ? queueActionEl.value : '');
    populateQueueBucketSelect();
    populateQueueClassificationSelect();
    return true;
  }

  async function loadReview() {
    if (!permissionValue) {
      renderNoSelection();
      return false;
    }
    if (!reviewEndpoint) {
      renderNotFound();
      return false;
    }
    if (errorEl) errorEl.textContent = '';
    try {
      const res = await App.fetchJson(`${reviewEndpoint}?permission=${encodeURIComponent(permissionValue)}`);
      if (!res.ok) {
        renderNotFound();
        return false;
      }
      renderReview(res.body.data || {}, res.body.meta || {});
      return true;
    } catch (e) {
      setPageError('Failed to load review details.');
      if (errorEl) {
        errorEl.innerHTML = '<pre>Failed to load review details.</pre>';
      }
      return false;
    }
  }

  function setControlsEnabled(enabled) {
    if (saveBtn) saveBtn.disabled = !enabled;
    if (statusSelect) statusSelect.disabled = !enabled;
    if (notesEl) notesEl.disabled = !enabled;
    if (quickActionsEl) {
      quickActionsEl.querySelectorAll('button').forEach((btn) => {
        btn.disabled = !enabled;
      });
    }
    if (queueActionEl) queueActionEl.disabled = !enabled;
    if (queueBucketEl) queueBucketEl.disabled = !enabled;
    if (queueClassificationEl) queueClassificationEl.disabled = !enabled;
    if (queueNotesEl) queueNotesEl.disabled = !enabled;
    if (queueSubmitBtn) queueSubmitBtn.disabled = !enabled;
  }

  function showSaveMessage(message, isError = false) {
    if (!saveMessageEl) return;
    saveMessageEl.textContent = message;
    saveMessageEl.style.display = 'block';
    saveMessageEl.classList.remove('error');
    saveMessageEl.classList.remove('success');
    saveMessageEl.classList.add(isError ? 'error' : 'success');
  }

  async function saveDecision() {
    if (!updateEndpoint || !statusSelect) return;
    const newStatus = statusSelect.value;
    if (!newStatus) {
      if (statusNoteEl) statusNoteEl.textContent = 'Select a triage status first.';
      return;
    }
    const priorStatus = currentRow ? String(currentRow.triage_status || '') : '';
    let redirectTarget = '';
    setControlsEnabled(false);
    if (statusNoteEl) statusNoteEl.textContent = 'Saving...';
    try {
      const res = await App.postForm(updateEndpoint, {
        permission: activePermission,
        triage_status: newStatus,
        notes: notesEl ? notesEl.value.trim() : '',
      });
      if (!res.ok) {
        if (statusNoteEl) statusNoteEl.textContent = res.error || 'Save failed.';
        showSaveMessage(res.error || 'Save failed.', true);
        return;
      }
      const payload = res.body || {};
      const updated = payload.data && Number(payload.data.updated || 0);
      const warnings = payload.data && Array.isArray(payload.data.warnings) ? payload.data.warnings : [];
      if (!updated) {
        const msg = 'No matching permission updated. Check the permission string and try again.';
        if (statusNoteEl) statusNoteEl.textContent = msg;
        showSaveMessage(msg, true);
        return;
      }
      const statusLabel = triageStatusMap.get(String(newStatus).toLowerCase()) || newStatus;
      let note = warnings.includes('no_change')
        ? 'No changes (already up to date).'
        : 'Saved.';
      if (!warnings.includes('no_change') && newStatus && newStatus !== priorStatus) {
        note = `Saved. Status set to ${statusLabel}.`;
      }
      if (statusNoteEl) statusNoteEl.textContent = note;
      showSaveMessage(note, false);
      if (currentRow) {
        currentRow.triage_status = newStatus;
        if (notesEl) currentRow.notes = notesEl.value.trim();
        renderReview(currentRow, currentMeta);
      }
      redirectTarget = appendQueryParam(returnUrl || pageUrl('permissions_triage'), 'saved', '1');
      if (queueSubmitted) {
        redirectTarget = appendQueryParam(redirectTarget, 'queued', '1');
      }
    } catch (e) {
      if (statusNoteEl) statusNoteEl.textContent = 'Save failed.';
      showSaveMessage('Save failed.', true);
    } finally {
      if (!redirectTarget) {
        setControlsEnabled(true);
      }
    }
    if (redirectTarget) {
      setTimeout(() => {
        window.location.href = redirectTarget;
      }, 500);
    }
  }

  async function queueUpdate() {
    if (!queueEndpoint || !queueActionEl) return;
    const action = queueActionEl.value;
    if (!action) {
      if (queueNoteEl) queueNoteEl.textContent = 'Select a queue action first.';
      return;
    }
    const requiresNotes = ['oem', 'reject'].includes(String(action).toLowerCase());
    const noteValue = queueNotesEl && queueNotesEl.value.trim()
      ? queueNotesEl.value.trim()
      : (notesEl ? notesEl.value.trim() : '');
    if (requiresNotes && noteValue.length < 10) {
      if (queueNoteEl) queueNoteEl.textContent = 'Add a short note (10+ chars) for this queue action.';
      return;
    }
    if (queueNoteEl) queueNoteEl.textContent = 'Queueing...';
    if (queueSubmitBtn) queueSubmitBtn.disabled = true;
    try {
      const res = await App.postForm(queueEndpoint, {
        permission: activePermission,
        queue_action: action,
        triage_status: statusSelect ? statusSelect.value : '',
        notes: noteValue,
        proposed_bucket: queueBucketEl ? queueBucketEl.value : '',
        proposed_classification: queueClassificationEl ? queueClassificationEl.value : '',
      });
      if (!res.ok) {
        if (queueNoteEl) queueNoteEl.textContent = res.error || 'Queue failed.';
        return;
      }
      const payload = res.body || {};
      const queued = payload.data && Number(payload.data.queued || 0);
      if (queued > 0) {
        const queueId = payload.data.queue_id ? ` (id ${payload.data.queue_id})` : '';
        const op = payload.data.operation ? String(payload.data.operation) : 'created';
        const opText = op === 'updated' ? 'Updated queued decision' : 'Queued for dictionary update';
        if (queueNoteEl) queueNoteEl.textContent = `${opText}${queueId}.`;
        queueSubmitted = true;
      } else {
        if (queueNoteEl) queueNoteEl.textContent = 'Queue not created.';
      }
    } catch (e) {
      if (queueNoteEl) queueNoteEl.textContent = 'Queue failed.';
    } finally {
      if (queueSubmitBtn) queueSubmitBtn.disabled = false;
    }
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', saveDecision);
  }
  if (copyBtn) {
    copyBtn.addEventListener('click', () => {
      if (!activePermission) return;
      App.copyText(activePermission);
      showSaveMessage('Permission copied.', false);
      setTimeout(() => {
        if (saveMessageEl) saveMessageEl.style.display = 'none';
      }, 1200);
    });
  }

  if (statusSelect) {
    statusSelect.addEventListener('change', () => {
      updateOutcomePreview();
      setStepVisibility(Boolean(statusSelect.value));
    });
  }
  if (queueActionEl) {
    queueActionEl.addEventListener('change', () => {
      queueActionTouched = true;
      if (queueNoteEl && queueActionEl.value) {
        queueNoteEl.textContent = `Selected queue action: ${queueActionLabel(queueActionEl.value)}.`;
      }
    });
  }
  if (queueBucketEl) {
    queueBucketEl.addEventListener('change', () => {
      queueBucketTouched = true;
    });
  }
  if (queueClassificationEl) {
    queueClassificationEl.addEventListener('change', () => {
      queueClassificationTouched = true;
    });
  }
  if (queueSubmitBtn) {
    queueSubmitBtn.addEventListener('click', queueUpdate);
  }

  setPageLoading('Loading permission review...');
  loadLov()
    .then((lovOk) => {
      if (!lovOk) return false;
      return loadReview();
    })
    .then((reviewOk) => {
      if (reviewOk) {
        setPageReady();
      }
    })
    .catch(() => {
      setPageError('Failed to load permission review.');
      if (errorEl) {
        errorEl.innerHTML = '<pre>Failed to load permission review.</pre>';
      }
    });
})();
