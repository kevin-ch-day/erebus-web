import { App } from '../shared/app-core';
import { PermissionIntel } from '../shared/permission-intel';
import {
  backlogEffectLabel,
  queueActionLabel,
  setSelectOptions,
  suggestedQueueAction,
  suggestedQueueBucket,
  suggestedQueueClassification,
  summaryChip,
  type LovEntry,
  type QueueResponseData,
  type ReviewMeta,
  type ReviewRow,
} from './permissions-review-support';

const root = document.getElementById('perm-review-page') as HTMLElement | null;

if (root) {
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
  const statusSelect = document.getElementById('review-status') as HTMLSelectElement | null;
  const notesEl = document.getElementById('review-notes') as HTMLTextAreaElement | null;
  const classificationEl = document.getElementById('review-classification');
  const evidenceSummaryEl = document.getElementById('review-evidence-summary');
  const evidenceLinkEl = document.getElementById('review-evidence-link') as HTMLAnchorElement | null;
  const quickActionsEl = document.getElementById('review-quick-actions');
  const impactEl = document.getElementById('review-impact');
  const decisionSummaryEl = document.getElementById('review-decision-summary');
  const queueActionEl = document.getElementById('review-queue-action') as HTMLSelectElement | null;
  const queueBucketEl = document.getElementById('review-queue-bucket') as HTMLSelectElement | null;
  const queueClassificationEl = document.getElementById('review-queue-classification') as HTMLSelectElement | null;
  const queueNotesEl = document.getElementById('review-queue-notes') as HTMLTextAreaElement | null;
  const queueSubmitBtn = document.getElementById('review-queue-submit') as HTMLButtonElement | null;
  const queueNoteEl = document.getElementById('review-queue-note');
  const saveBtn = document.getElementById('review-save') as HTMLButtonElement | null;
  const statusNoteEl = document.getElementById('review-status-note');
  const errorEl = document.getElementById('review-error');
  const saveMessageEl = document.getElementById('review-save-message');
  const copyBtn = document.getElementById('review-copy') as HTMLButtonElement | null;
  const stepNotesEl = document.getElementById('review-step-notes');
  const stepQueueEl = document.getElementById('review-step-queue');
  const layoutEl = document.getElementById('review-layout');
  const sideEl = document.getElementById('review-side');
  const evidenceCardEl = document.getElementById('review-evidence-card');
  const ledgerNoteEl = document.getElementById('review-ledger-note');
  const shellContentEl = document.getElementById('review-shell-content');
  const loadingCardEl = document.getElementById('review-loading-card');
  const loadingTextEl = document.getElementById('review-loading-text');

  const fmt = App.fmt;
  const fmtCount = PermissionIntel.formatCount;
  const fmtUtc = App.formatUtc;
  const pageUrl = App.pageUrl;
  const appendQueryParam = App.appendQueryParam;

  let triageStatuses: LovEntry[] = [];
  let triageStatusMap = new Map<string, string>();
  let bucketLabelMap = new Map<string, string>();
  let bucketDefs: LovEntry[] = [];
  let currentRow: ReviewRow | null = null;
  let currentMeta: ReviewMeta = {};
  let activePermission = permissionValue;
  let queueActions: LovEntry[] = [];
  let classificationDefs: LovEntry[] = [];
  let queueActionTouched = false;
  let queueBucketTouched = false;
  let queueClassificationTouched = false;
  let queueSubmitted = false;

  function setPageLoading(message: string): void {
    if (loadingTextEl) loadingTextEl.textContent = message || 'Loading permission review...';
    if (loadingCardEl) loadingCardEl.setAttribute('style', '');
    shellContentEl?.classList.add('review-shell-content-hidden');
  }

  function setPageReady(): void {
    loadingCardEl?.setAttribute('style', 'display:none;');
    shellContentEl?.classList.remove('review-shell-content-hidden');
  }

  function setPageError(message: string): void {
    if (loadingTextEl) loadingTextEl.textContent = message || 'Failed to load permission review.';
    if (loadingCardEl) loadingCardEl.setAttribute('style', '');
    shellContentEl?.classList.add('review-shell-content-hidden');
  }

  function renderDecisionSummary(statusRaw: string): void {
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
    const queueLabel = queueAction ? queueActionLabel(queueActions, queueAction) : 'No default';
    const maintenancePath = queueBucket && queueClassification
      ? `${queueBucket} / ${queueClassification}`
      : 'Manual if needed';
    decisionSummaryEl.innerHTML = [
      summaryChip('Backlog effect', backlogEffectLabel(statusRaw), statusRaw === 'new' ? 'warn' : 'ok'),
      summaryChip('Queue suggestion', queueLabel, queueAction ? 'accent' : ''),
      summaryChip('Maintenance path', maintenancePath),
    ].join('');
  }

  function syncQueueSuggestion(statusRaw: string): void {
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
    if (stepQueueEl instanceof HTMLDetailsElement && suggested) {
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
    queueNoteEl.textContent = `Suggested queue action: ${queueActionLabel(queueActions, suggested)}.`;
    renderDecisionSummary(statusRaw);
  }

  function updateOutcomePreview(): void {
    if (!impactEl || !statusSelect) return;
    const statusRaw = String(statusSelect.value || '').toLowerCase();
    const label = triageStatusMap.get(statusRaw) || statusSelect.value || 'Unreviewed';
    const suffix = ' Bucket/classification remain backend-defined.';
    const statusHints: Record<string, string> = {
      aosp_missing: 'Use for Android permissions in the AOSP namespace that are missing from the public SDK / docs set.',
      gms_known: 'Use for Google-defined permissions, including Play Services / GMS cases.',
      oem_candidate: 'Use for vendor-specific namespaces that need OEM review.',
      malformed: 'Use when the permission string is invalid or non-standard.',
      deferred: 'Use when you need more evidence before deciding.',
      ignore: 'Use when no dictionary action should be taken and the permission should leave the active triage path.',
    };

    if (!statusRaw) {
      impactEl.textContent = `Select a triage status to record this decision.${suffix}`;
      return;
    }
    if (statusRaw === 'new') {
      impactEl.textContent = `Status set to ${label}. This stays in the actionable triage queue until you change it.${suffix}`;
      return;
    }
    let message = `Status set to ${label}. This will no longer appear in the default actionable triage filter.${suffix}`;
    if (statusHints[statusRaw]) {
      message += ` ${statusHints[statusRaw]}`;
    }
    if (statusRaw === 'launcher_ecosystem') {
      message += ' Use for launcher or platform ecosystem permissions that are known but not dictionary-worthy.';
    } else if (statusRaw === 'app_defined') {
      message += ' Use when the permission is app-defined and should not remain in the unknown workflow backlog.';
    } else if (statusRaw === 'resolved_aosp' || statusRaw === 'resolved_oem') {
      message += ' Use after the dictionary or registry workflow has already resolved the classification.';
    }
    impactEl.textContent = message;
    syncQueueSuggestion(statusRaw);
  }

  function setStepVisibility(hasStatus: boolean): void {
    stepNotesEl?.classList.toggle('review-step-hidden', !hasStatus);
    stepQueueEl?.classList.toggle('review-step-hidden', !hasStatus);
  }

  function renderQuickActions(): void {
    if (!quickActionsEl) return;
    quickActionsEl.innerHTML = '';
    if (!triageStatuses.length) {
      (quickActionsEl as HTMLElement).style.display = 'none';
      return;
    }
    const preferredKeys = ['new', 'aosp_missing', 'gms_known', 'oem_candidate', 'deferred'];
    const preferred = triageStatuses.filter((status) => preferredKeys.includes(String(status.key || '').toLowerCase()));
    const actions = preferred.length ? preferred : triageStatuses.slice(0, 5);
    if (!actions.length) {
      (quickActionsEl as HTMLElement).style.display = 'none';
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
      btn.textContent = String(status.label || key || 'Set');
      btn.addEventListener('click', () => {
        if (!statusSelect) return;
        statusSelect.value = key;
        updateOutcomePreview();
        setStepVisibility(Boolean(statusSelect.value));
      });
      quickActionsEl.appendChild(btn);
    });
    (quickActionsEl as HTMLElement).style.display = 'flex';
  }

  function populateQueueBucketSelect(): void {
    if (!queueBucketEl) return;
    queueBucketEl.innerHTML = '<option value="">No bucket</option>';
    bucketDefs.forEach((bucket) => {
      const key = String(bucket.key || '');
      if (!key) return;
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = String(bucket.label || bucket.key || key);
      queueBucketEl.appendChild(opt);
    });
  }

  function populateQueueClassificationSelect(): void {
    if (!queueClassificationEl) return;
    queueClassificationEl.innerHTML = '<option value="">No classification</option>';
    classificationDefs.forEach((entry) => {
      const key = String(entry.key || '');
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = String(entry.label || entry.key || key);
      queueClassificationEl.appendChild(opt);
    });
  }

  function renderReview(row: ReviewRow, meta: ReviewMeta): void {
    currentRow = row;
    currentMeta = meta || {};
    if (row.permission_string) activePermission = row.permission_string;

    const risk = PermissionIntel.riskHint(row.permission_string, row.namespace);
    const triage = fmt(row.triage_status, '--');
    const triageLabel = triageStatusMap.get(String(triage).toLowerCase()) || triage;
    const bucketKey = PermissionIntel.normalizeKey(row.bucket);
    const bucketLabel = bucketLabelMap.get(bucketKey) || fmt(row.bucket, 'Unknown / Unclassified');
    const classification = fmt(row.classification, '--');

    const eventCount = row.event_count ?? 0;
    const sampleCount = row.sample_count;
    if (permissionEl) permissionEl.textContent = fmt(row.permission_string, '--');
    if (namespaceEl) namespaceEl.textContent = fmt(row.namespace, '--');
    if (riskEl) riskEl.innerHTML = `<span class="${App.escapeHtml(risk.className)}">${App.escapeHtml(risk.label)}</span>`;
    if (seenEl) seenEl.textContent = fmtCount(eventCount);
    if (lastSeenEl) lastSeenEl.textContent = row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--';
    if (statusPillEl) statusPillEl.innerHTML = `<span class="badge muted">${App.escapeHtml(String(triageLabel))}</span>`;
    if (bucketEl) bucketEl.textContent = String(bucketLabel);
    if (classificationEl) classificationEl.textContent = String(classification);

    if (notesEl) notesEl.value = fmt(row.notes, '');
    setSelectOptions(statusSelect, triageStatuses, String(triage));
    updateOutcomePreview();
    setStepVisibility(Boolean(triage));

    if (evidenceSummaryEl) {
      const seen = fmtCount(eventCount);
      const samples = fmtCount(sampleCount);
      const lastSeen = row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--';
      const taxonomy = meta?.taxonomy_version ? `taxonomy ${meta.taxonomy_version}` : '';
      const hasEvidence = Number(sampleCount || 0) > 0 || Number(eventCount || 0) > 0;
      if (!hasEvidence) {
        evidenceSummaryEl.textContent = '';
        if (ledgerNoteEl) {
          ledgerNoteEl.textContent = `Workflow-only row. No VT event evidence is attached in the current ledger${taxonomy ? `; ${taxonomy}` : ''}.`;
          ledgerNoteEl.style.display = 'block';
        }
        evidenceCardEl?.setAttribute('style', 'display:none;');
        sideEl?.classList.add('review-side-hidden');
        layoutEl?.classList.add('review-layout-wide');
      } else {
        evidenceSummaryEl.textContent = `Evidence samples: ${samples}; VT events: ${seen}; last event seen ${lastSeen}${taxonomy ? `; ${taxonomy}` : ''}.`;
        if (ledgerNoteEl) {
          ledgerNoteEl.style.display = 'none';
          ledgerNoteEl.textContent = '';
        }
        evidenceCardEl?.removeAttribute('style');
        sideEl?.classList.remove('review-side-hidden');
        layoutEl?.classList.remove('review-layout-wide');
      }
    }

    if (evidenceLinkEl) {
      evidenceLinkEl.href = pageUrl('permissions_evidence', {
        permission: fmt(row.permission_string, ''),
        return: returnUrl || pageUrl('permissions_triage'),
      });
    }

    if (queueBucketEl && row.bucket) {
      queueBucketEl.value = PermissionIntel.normalizeKey(row.bucket);
    }
    if (queueClassificationEl && row.classification) {
      queueClassificationEl.value = String(row.classification || '').toUpperCase();
    }
    if (queueActionEl && !queueActionTouched) {
      queueActionEl.value = queueActionEl.value || '';
    }
    syncQueueSuggestion(String(triage));
  }

  function renderNotFound(): void {
    setPageError('Permission not found. Return to Triage and try another permission.');
    if (errorEl) errorEl.innerHTML = '<pre>Permission not found. Please return to Triage.</pre>';
    showSaveMessage('Permission not found. Return to Triage.', true);
    setControlsEnabled(false);
  }

  function renderNoSelection(): void {
    setPageError('No permission selected. Return to Triage and choose a permission.');
    if (errorEl) errorEl.innerHTML = '<pre>No permission selected. Return to Triage and choose a permission.</pre>';
    showSaveMessage('No permission selected. Open Permission Triage and choose a row first.', true);
    setControlsEnabled(false);
  }

  async function loadLov(): Promise<boolean> {
    if (!lovEndpoint) return false;
    const res = await App.fetchJson(lovEndpoint);
    if (!res.ok) {
      setPageError('Failed to load triage status options.');
      if (errorEl) errorEl.innerHTML = '<pre>Failed to load triage status list.</pre>';
      setControlsEnabled(false);
      return false;
    }
    const data = ((res.body as Record<string, unknown>).data || {}) as Record<string, unknown>;
    triageStatuses = Array.isArray(data.triage_statuses) ? (data.triage_statuses as LovEntry[]) : [];
    triageStatusMap = new Map<string, string>();
    triageStatuses.forEach((status) => {
      const key = String(status.key || '').toLowerCase();
      const label = String(status.label || status.key || '');
      if (key) triageStatusMap.set(key, label);
    });
    bucketDefs = Array.isArray(data.buckets) ? (data.buckets as LovEntry[]) : [];
    bucketLabelMap = new Map<string, string>();
    bucketDefs.forEach((bucket) => {
      const key = PermissionIntel.normalizeKey(bucket.key);
      const label = String(bucket.label || '');
      if (key && label) bucketLabelMap.set(key, label);
    });
    if (!bucketLabelMap.has('OEM_CANDIDATE')) bucketLabelMap.set('OEM_CANDIDATE', 'Unknown / OEM Candidate');
    classificationDefs = Array.isArray(data.classifications) ? (data.classifications as LovEntry[]) : [];
    queueActions = Array.isArray(data.queue_actions) ? (data.queue_actions as LovEntry[]) : [];
    if (!triageStatuses.length) {
      if (errorEl) errorEl.innerHTML = '<pre>No triage statuses available. Check LOV endpoint.</pre>';
      setControlsEnabled(false);
      setPageError('No triage statuses are available for review.');
      return false;
    } else {
      setControlsEnabled(true);
    }
    renderQuickActions();
    setSelectOptions(queueActionEl, queueActions, queueActionEl?.value || '');
    if (queueActionEl) queueActionEl.disabled = queueActions.length === 0;
    populateQueueBucketSelect();
    populateQueueClassificationSelect();
    return true;
  }

  async function loadReview(): Promise<boolean> {
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
      const body = res.body as Record<string, unknown>;
      renderReview((body.data || {}) as ReviewRow, (body.meta || {}) as ReviewMeta);
      return true;
    } catch {
      setPageError('Failed to load review details.');
      if (errorEl) errorEl.innerHTML = '<pre>Failed to load review details.</pre>';
      return false;
    }
  }

  function setControlsEnabled(enabled: boolean): void {
    if (saveBtn) saveBtn.disabled = !enabled;
    if (statusSelect) statusSelect.disabled = !enabled;
    if (notesEl) notesEl.disabled = !enabled;
    quickActionsEl?.querySelectorAll('button').forEach((btn) => {
      (btn as HTMLButtonElement).disabled = !enabled;
    });
    if (queueActionEl) queueActionEl.disabled = !enabled;
    if (queueBucketEl) queueBucketEl.disabled = !enabled;
    if (queueClassificationEl) queueClassificationEl.disabled = !enabled;
    if (queueNotesEl) queueNotesEl.disabled = !enabled;
    if (queueSubmitBtn) queueSubmitBtn.disabled = !enabled;
  }

  function showSaveMessage(message: string, isError = false): void {
    if (!saveMessageEl) return;
    saveMessageEl.textContent = message;
    saveMessageEl.style.display = 'block';
    saveMessageEl.classList.remove('error', 'success');
    saveMessageEl.classList.add(isError ? 'error' : 'success');
  }

  async function saveDecision(): Promise<void> {
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
      const payload = (res.body as Record<string, unknown>) || {};
      const data = ((payload.data || {}) as Record<string, unknown>) || {};
      const updated = Number(data.updated || 0);
      const warnings = Array.isArray(data.warnings) ? (data.warnings as string[]) : [];
      if (!updated) {
        const msg = 'No matching permission updated. Check the permission string and try again.';
        if (statusNoteEl) statusNoteEl.textContent = msg;
        showSaveMessage(msg, true);
        return;
      }
      const statusLabel = triageStatusMap.get(String(newStatus).toLowerCase()) || newStatus;
      let note = warnings.includes('no_change') ? 'No changes (already up to date).' : 'Saved.';
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
    } catch {
      if (statusNoteEl) statusNoteEl.textContent = 'Save failed.';
      showSaveMessage('Save failed.', true);
    } finally {
      if (!redirectTarget) {
        setControlsEnabled(true);
      }
    }
    if (redirectTarget) {
      window.setTimeout(() => {
        window.location.href = redirectTarget;
      }, 500);
    }
  }

  async function queueUpdate(): Promise<void> {
    if (!queueEndpoint || !queueActionEl) return;
    const action = queueActionEl.value;
    if (!action) {
      if (queueNoteEl) queueNoteEl.textContent = 'Select a queue action first.';
      return;
    }
    const requiresNotes = ['oem', 'reject'].includes(String(action).toLowerCase());
    const noteValue = queueNotesEl?.value.trim() ? queueNotesEl.value.trim() : (notesEl?.value.trim() || '');
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
      const payload = (res.body as Record<string, unknown>) || {};
      const data = ((payload.data || {}) as QueueResponseData) || {};
      const queued = Number(data.queued || 0);
      if (queued > 0) {
        const queueId = data.queue_id ? ` (id ${data.queue_id})` : '';
        const op = data.operation ? String(data.operation) : 'created';
        const opText = op === 'updated' ? 'Updated queued decision' : 'Queued for dictionary update';
        if (queueNoteEl) queueNoteEl.textContent = `${opText}${queueId}.`;
        queueSubmitted = true;
      } else if (queueNoteEl) {
        queueNoteEl.textContent = 'Queue not created.';
      }
    } catch {
      if (queueNoteEl) queueNoteEl.textContent = 'Queue failed.';
    } finally {
      if (queueSubmitBtn) queueSubmitBtn.disabled = false;
    }
  }

  saveBtn?.addEventListener('click', () => {
    void saveDecision();
  });

  copyBtn?.addEventListener('click', () => {
    if (!activePermission) return;
    App.copyText(activePermission);
    showSaveMessage('Permission copied.', false);
    window.setTimeout(() => {
      if (saveMessageEl) saveMessageEl.style.display = 'none';
    }, 1200);
  });

  statusSelect?.addEventListener('change', () => {
    updateOutcomePreview();
    setStepVisibility(Boolean(statusSelect.value));
  });

  queueActionEl?.addEventListener('change', () => {
    queueActionTouched = true;
    if (queueNoteEl && queueActionEl.value) {
      queueNoteEl.textContent = `Selected queue action: ${queueActionLabel(queueActions, queueActionEl.value)}.`;
    }
  });

  queueBucketEl?.addEventListener('change', () => {
    queueBucketTouched = true;
  });

  queueClassificationEl?.addEventListener('change', () => {
    queueClassificationTouched = true;
  });

  queueSubmitBtn?.addEventListener('click', () => {
    void queueUpdate();
  });

  setPageLoading('Loading permission review...');
  void loadLov()
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
      if (errorEl) errorEl.innerHTML = '<pre>Failed to load permission review.</pre>';
    });
}
