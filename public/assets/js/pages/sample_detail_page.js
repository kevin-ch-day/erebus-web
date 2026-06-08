(() => {
  const root = document.getElementById('sample-detail-page');
  if (!root || !window.App || !window.SampleDetail) return;

  const endpoint = root.dataset.detailEndpoint || '';
  const permSummaryEndpoint = root.dataset.permSummaryEndpoint || '';
  const permDetailEndpoint = root.dataset.permDetailEndpoint || '';
  const updateEndpoint = root.dataset.updateEndpoint || '';
  const sampleId = root.dataset.sampleId || '';
  const sha256 = root.dataset.sha256 || '';

  if (!endpoint) return;

  const summaryEl = document.getElementById('sample-summary');
  const coreGridEl = document.getElementById('sample-core-grid');
  const opsGridEl = document.getElementById('sample-ops-grid');
  const platformGridEl = document.getElementById('sample-platform-grid');
  const platformNoteEl = document.getElementById('sample-platform-note');
  const advancedGridEl = document.getElementById('sample-advanced-grid');
  const errorSectionEl = document.getElementById('sample-error-section');
  const errorBodyEl = document.getElementById('sample-error-body');
  const errorEl = document.getElementById('sample-error');
  const permSummaryList = document.getElementById('perm-summary-list');
  const permSummaryEmpty = document.getElementById('perm-summary-empty');
  const permDetailBody = document.getElementById('perm-detail-body');
  const permErrorEl = document.getElementById('perm-error');
  const permNonAndroid = document.getElementById('perm-non-android');
  const permTilesEl = document.getElementById('perm-tiles');
  const permFilterBucket = document.getElementById('perm-filter-bucket');
  const permFilterKnown = document.getElementById('perm-filter-known');
  const permUnknownList = document.getElementById('perm-unknown-list');
  const permTaxonomyEl = document.getElementById('perm-taxonomy');
  const permRunLinkEl = document.getElementById('perm-run-link');
  const permStatusNote = document.getElementById('perm-status-note');
  const permPipeline = document.getElementById('perm-pipeline');
  const permExportBtn = document.getElementById('perm-export');
  const sampleReloadBtn = document.getElementById('sample-reload');
  const permReloadBtn = document.getElementById('perm-reload');
  const editModal = document.getElementById('sample-edit-modal');
  const editCloseBtn = document.getElementById('sample-edit-close');
  const editSaveBtn = document.getElementById('sample-edit-save');
  const editMetaEl = document.getElementById('sample-edit-meta');
  const editIdEl = document.getElementById('sample-edit-id');
  const editShaEl = document.getElementById('sample-edit-sha');
  const editPackageEl = document.getElementById('sample-edit-package');
  const editLabelEl = document.getElementById('sample-edit-label');
  const editFamilyEl = document.getElementById('sample-edit-family');
  const editPrimaryEl = document.getElementById('sample-edit-primary');
  const editSubtypeEl = document.getElementById('sample-edit-subtype');
  const editStatusEl = document.getElementById('sample-edit-status');

  const SampleDetail = window.SampleDetail;
  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const fmtUtc = SampleDetail.fmtUtc;

  let summaryCopyBound = false;
  let loadedSampleId = '';
  let loadedIsAndroid = false;

  const permController = SampleDetail.createPermissionsController({
    permSummaryList,
    permSummaryEmpty,
    permDetailBody,
    permErrorEl,
    permNonAndroid,
    permTilesEl,
    permFilterBucket,
    permFilterKnown,
    permUnknownList,
    permStatusNote,
    permPipeline,
    permExportBtn,
  }, {
    permSummaryEndpoint,
    permDetailEndpoint,
  });

  const editor = SampleDetail.createEditor({
    modalEl: editModal,
    closeBtn: editCloseBtn,
    saveBtn: editSaveBtn,
    metaEl: editMetaEl,
    idEl: editIdEl,
    shaEl: editShaEl,
    packageEl: editPackageEl,
    labelEl: editLabelEl,
    familyEl: editFamilyEl,
    primaryEl: editPrimaryEl,
    subtypeEl: editSubtypeEl,
    statusEl: editStatusEl,
    updateEndpoint,
    onSaved: () => loadSample(),
  });

  function addCard(target, title, rows, options = {}) {
    if (!target) return;
    const card = document.createElement('div');
    card.className = 'detail-card';
    if (options.className) {
      card.classList.add(options.className);
    }
    const rowClass = options.rowClass ? ` detail-row-vertical` : '';
    const list = rows.map(([label, value]) => {
      return `<div class="detail-row${rowClass}"><div class="detail-label">${esc(label)}</div><div class="detail-value">${esc(fmt(value))}</div></div>`;
    }).join('');
    card.innerHTML = `<div class="detail-card-title">${esc(title)}</div>${list}`;
    target.appendChild(card);
  }

  function backoffNote(statusRaw, nextEligibleRaw) {
    const status = String(statusRaw || '').toUpperCase();
    const nextMs = App.parseUtcToMs ? App.parseUtcToMs(nextEligibleRaw) : null;
    if (nextMs && nextMs > Date.now()) {
      return `Blocked until ${fmtUtc(nextEligibleRaw)}`;
    }
    if (status === 'RETRY_WAIT') {
      return 'Retry wait (timestamp missing).';
    }
    return '--';
  }

  function boolLabel(value, yes = 'Yes', no = 'No') {
    return value ? yes : no;
  }

  function normalizeFamilyValue(value) {
    return String(value || '')
      .trim()
      .toLowerCase();
  }

  function familySignalStatus(sample) {
    const family = normalizeFamilyValue(sample.family_label);
    const signalName = normalizeFamilyValue(sample.popular_threat_name);
    if (!family && !signalName) return 'unlabeled';
    if (family && !signalName) return 'catalog_only';
    if (!family && signalName) return 'signal_only';
    if (family === signalName) return 'aligned';
    return 'mismatch';
  }

  function renderPlatformContext(platformContext) {
    if (!platformGridEl) return;
    platformGridEl.innerHTML = '';
    if (platformNoteEl) {
      platformNoteEl.style.display = 'none';
      platformNoteEl.textContent = '';
    }
    if (!platformContext || typeof platformContext !== 'object') {
      return;
    }

    addCard(platformGridEl, 'Catalog Alignment', [
      ['Primary catalog', platformContext.primary_catalog],
      ['Permission Intel catalog', platformContext.permission_intel_catalog],
      ['Split enabled', boolLabel(platformContext.split_enabled)],
      ['Primary schema head', platformContext.primary_schema_head],
      ['Permission Intel schema head', platformContext.permission_intel_schema_head],
      ['Schema heads match', boolLabel(platformContext.schema_heads_match)],
    ], { className: 'detail-card-wide' });

    addCard(platformGridEl, 'Sample Run Alignment', [
      ['Sample last-run catalog', platformContext.sample_last_run_db_name],
      ['Sample last-run schema version', platformContext.sample_last_run_schema_version],
      ['Sample last-run taxonomy version', platformContext.sample_last_run_perm_taxonomy_version],
      ['Against current primary', boolLabel(platformContext.sample_last_run_against_current_primary)],
      ['Against known catalog', boolLabel(platformContext.sample_last_run_against_known_catalog)],
      ['Schema matches current primary head', boolLabel(platformContext.sample_last_run_schema_matches_primary_head)],
      ['Taxonomy matches latest', boolLabel(platformContext.sample_last_run_perm_taxonomy_matches_latest)],
    ], { className: 'detail-card-wide' });

    addCard(platformGridEl, 'Latest Platform Taxonomy', [
      ['Latest taxonomy version', platformContext.latest_perm_taxonomy_version],
      ['Latest taxonomy finished', fmtUtc(platformContext.latest_perm_taxonomy_finished_at_utc)],
      ['Sample has last run', boolLabel(platformContext.sample_has_last_run)],
      ['Platform mismatch detected', boolLabel(platformContext.sample_platform_state_mismatch)],
    ]);

    if (!platformNoteEl) {
      return;
    }
    if (!platformContext.sample_has_last_run) {
      platformNoteEl.textContent = 'This sample has no recorded run yet, so platform drift cannot be compared.';
      platformNoteEl.style.display = 'block';
      return;
    }
    if (platformContext.sample_platform_state_mismatch) {
      platformNoteEl.textContent = 'This sample was last processed under a platform state that does not fully match the current primary catalog, schema head, or permission taxonomy.';
      platformNoteEl.style.display = 'block';
      return;
    }
    if (!platformContext.schema_heads_match) {
      platformNoteEl.textContent = 'Primary and Permission Intel schema heads differ. That can be valid, but operators should compare sample results against the correct catalog role.';
      platformNoteEl.style.display = 'block';
    }
  }

  function renderLastError(row) {
    if (!errorSectionEl || !errorBodyEl) return;
    const hasError = row.last_http_status || row.last_error_category || row.last_error_message;
    if (!hasError) {
      errorSectionEl.style.display = 'none';
      return;
    }

    errorSectionEl.style.display = 'block';
    errorBodyEl.innerHTML = '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(fmt(row.last_http_status))}</td>
      <td>${esc(fmt(row.last_error_category))}</td>
      <td>${esc(fmt(row.last_error_message))}</td>
      <td>${esc(fmtUtc(row.last_attempt_at_utc))}</td>
    `;
    errorBodyEl.appendChild(tr);
  }

  async function loadSample() {
    if (!sampleId && !sha256) {
      errorEl.innerHTML = '<pre>Provide sample_id or sha256 in the query string.</pre>';
      return;
    }

    const params = new URLSearchParams();
    if (sampleId) params.set('sample_id', sampleId);
    if (sha256) params.set('sha256', sha256);

    try {
      if (errorEl) errorEl.textContent = '';
      if (permErrorEl) permErrorEl.textContent = '';
      const res = await App.fetchJson(endpoint + '?' + params.toString());
      if (!res.ok) {
        errorEl.innerHTML = '<pre>Sample detail error.\n\nHTTP ' + res.status + '\nerror: ' +
          esc(res.error) + '</pre>';
        return;
      }

      const data = res.body;
      if (!data || data.ok !== true || !data.sample) {
        errorEl.innerHTML = '<pre>Sample detail error.\n\nHTTP ' + res.status + '\nerror: ' +
          esc(data && data.error ? data.error : 'unknown') + '</pre>';
        return;
      }

      const s = data.sample;
      SampleDetail.renderSummary(summaryEl, s, {
        showEditButton: true,
        onEdit: updateEndpoint ? (sample) => editor.open(sample) : null,
      });
      if (!summaryCopyBound) {
        SampleDetail.bindSummaryCopy(summaryEl);
        summaryCopyBound = true;
      }
      if (!updateEndpoint) {
        const editOpenBtn = summaryEl ? summaryEl.querySelector('#sample-edit-open') : null;
        if (editOpenBtn) {
          editOpenBtn.disabled = true;
          editOpenBtn.title = 'Update endpoint not configured.';
        }
      }

      if (coreGridEl) coreGridEl.innerHTML = '';
      if (opsGridEl) opsGridEl.innerHTML = '';
      if (platformGridEl) platformGridEl.innerHTML = '';
      if (advancedGridEl) advancedGridEl.innerHTML = '';

      addCard(coreGridEl, 'Android Metadata', [
        ['Package name', s.android_package_name],
        ['Launcher activity', s.android_launcher_activity],
        ['Min SDK', s.android_min_sdk],
        ['Target SDK', s.android_target_sdk],
        ['Receiver', s.android_receiver_count],
        ['Activity', s.android_activity_count],
        ['Service', s.android_service_count],
        ['Provider', s.android_provider_count],
        ['Library', s.android_library_count],
        ['Permission', s.android_permission_count],
      ], { className: 'detail-card-wide' });

      addCard(coreGridEl, 'VirusTotal History', [
        ['First submission', fmtUtc(s.vt_first_submission_at_utc)],
        ['Last analysis', fmtUtc(s.vt_last_analysis_at_utc)],
        ['Summary updated', fmtUtc(s.vt_summary_updated_at_utc)],
        ['First seen ITW', s.vt_first_seen_itw_date],
        ['Times submitted', s.vt_times_submitted],
        ['Unique sources', s.vt_unique_sources],
      ]);

      addCard(coreGridEl, 'Family & Signal', [
        ['Catalog family', s.family_label],
        ['VT suggested label', s.vt_suggested_label],
        ['VT popular threat label', s.popular_threat_label],
        ['VT popular threat category', s.popular_threat_category],
        ['VT popular threat name', s.popular_threat_name],
        ['Signal parse version', s.vt_signal_parse_version],
        ['Alignment status', familySignalStatus(s)],
      ], { className: 'detail-card-wide' });

      if (data.last_run) {
        const r = data.last_run;
        addCard(opsGridEl, 'Last Run', [
          ['Run ID', r.run_id],
          ['Started', fmtUtc(r.started_at_utc)],
          ['Finished', fmtUtc(r.finished_at_utc)],
          ['Processed', r.processed_count],
          ['OK', r.ok_count],
          ['No data', r.no_data_count],
          ['Retry wait', r.retry_wait_count],
          ['Error', r.error_count],
          ['Stopped reason', r.stopped_reason],
          ['Perm taxonomy version', r.perm_taxonomy_version],
        ]);
      }

      renderPlatformContext(data.platform_context || null);

      addCard(opsGridEl, 'VT State', [
        ['Status', s.vt_status_code],
        ['Reason', s.reason_code],
        ['Backoff', backoffNote(s.vt_status_code, s.next_eligible_at_utc)],
        ['Next eligible', fmtUtc(s.next_eligible_at_utc)],
        ['Attempt count', s.attempt_count],
        ['Last attempt', fmtUtc(s.last_attempt_at_utc)],
        ['Last HTTP status', s.last_http_status],
        ['Last error category', s.last_error_category],
        ['Last error message', s.last_error_message],
      ], { className: 'detail-card-wide' });

      if (advancedGridEl) {
        addCard(advancedGridEl, 'State Diagnostics', [
          ['Last run id', s.last_run_id],
          ['Last key id', s.last_key_id],
          ['Claim token', s.claim_token],
          ['Claimed at', fmtUtc(s.claimed_at_utc)],
          ['Claimed by host', s.claimed_by_host],
          ['Claimed by user', s.claimed_by_user],
          ['Claimed by ip', s.claimed_by_ip],
          ['Claimed by pid', s.claimed_by_pid],
          ['State created', fmtUtc(s.state_created_at_utc)],
          ['State updated', fmtUtc(s.state_updated_at_utc)],
        ]);

        if (data.last_run) {
          const r = data.last_run;
          addCard(advancedGridEl, 'Run Diagnostics', [
            ['DB name', r.db_name],
            ['Key ID', r.key_id],
            ['Tool version', r.tool_version],
            ['Schema version', r.schema_version],
          ]);
        }
      }

      renderLastError(s);

      permController.setExportSampleId(s.sample_id || '');
      const lastRun = data.last_run || null;
      if (permTaxonomyEl) {
        const taxonomyValue = lastRun ? fmt(lastRun.perm_taxonomy_version, '--') : '--';
        permTaxonomyEl.textContent = `Taxonomy: ${taxonomyValue}`;
      }
      if (permRunLinkEl) {
        const runId = lastRun ? fmt(lastRun.run_id, '') : '';
        if (runId) {
          const runUrl = App.pageUrl('runs', { q: runId });
          permRunLinkEl.innerHTML = `Run: <a class="table-link" href="${esc(runUrl)}">${esc(runId)}</a>`;
        } else {
          permRunLinkEl.textContent = 'Run: --';
        }
      }

      let statusNote = '';
      if (lastRun && Number(lastRun.ok_count || 0) === 0) {
        const reason = fmt(lastRun.stopped_reason, '').trim();
        statusNote = 'No permissions persisted because no OK payload was processed in this run.';
        if (reason) statusNote += ' Stopped reason: ' + reason + '.';
      } else if (String(s.vt_status_code || '').toUpperCase() === 'RETRY_WAIT') {
        statusNote = 'No permissions persisted because the sample is in retry wait.';
      }
      permController.setStatus(statusNote);

      const platform = String(s.platform || '').toLowerCase();
      const fileExt = String(s.file_extension || '').toLowerCase();
      const mimeType = String(s.mime_type || '').toLowerCase();
      const hasPackageName = String(s.android_package_name || '').trim() !== '';
      const isAndroid =
        platform.includes('android') ||
        fileExt === 'apk' ||
        mimeType.includes('android.package-archive') ||
        hasPackageName;
      loadedSampleId = String(s.sample_id || '');
      loadedIsAndroid = isAndroid;
      if (!isAndroid) {
        permController.showNonAndroid();
      } else {
        if (permNonAndroid) permNonAndroid.style.display = 'none';
        permController.loadPermissions(s.sample_id);
      }
    } catch (e) {
      errorEl.innerHTML = '<pre>Sample detail error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
    }
  }

  if (sampleReloadBtn) {
    sampleReloadBtn.addEventListener('click', loadSample);
  }
  if (permReloadBtn) {
    permReloadBtn.addEventListener('click', () => {
      if (!loadedSampleId) return;
      if (!loadedIsAndroid) {
        permController.showNonAndroid();
        return;
      }
      permController.loadPermissions(loadedSampleId);
    });
  }

  loadSample();
})();
