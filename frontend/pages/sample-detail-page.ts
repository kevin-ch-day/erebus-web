import type { AppSurface } from '../types/app-globals';
import type { SampleDetailNamespace } from '../types/sample-detail-globals';

const root = document.getElementById('sample-detail-page') as HTMLElement | null;

if (root && window.App && window.SampleDetail) {
  const App = window.App as AppSurface;
  const SampleDetail = window.SampleDetail as SampleDetailNamespace;

  const endpoint = root.dataset.detailEndpoint || '';
  const permSummaryEndpoint = root.dataset.permSummaryEndpoint || '';
  const permDetailEndpoint = root.dataset.permDetailEndpoint || '';
  const updateEndpoint = root.dataset.updateEndpoint || '';
  const sampleId = root.dataset.sampleId || '';
  const sha256 = root.dataset.sha256 || '';

  if (endpoint) {
    const summaryEl = document.getElementById('sample-summary') as HTMLElement | null;
    const errorEl = document.getElementById('sample-error') as HTMLElement | null;
    const androidSectionEl = document.getElementById('android-permissions-section') as HTMLElement | null;
    const androidWorkflowEl = document.getElementById('android-permissions-workflow') as HTMLElement | null;
    const permSummaryList = document.getElementById('perm-summary-list') as HTMLElement | null;
    const permSummaryEmpty = document.getElementById('perm-summary-empty') as HTMLElement | null;
    const permDetailBody = document.getElementById('perm-detail-body') as HTMLElement | null;
    const permErrorEl = document.getElementById('perm-error') as HTMLElement | null;
    const permNonAndroid = document.getElementById('perm-non-android') as HTMLElement | null;
    const permTilesEl = document.getElementById('perm-tiles') as HTMLElement | null;
    const permFilterBucket = document.getElementById('perm-filter-bucket') as HTMLSelectElement | null;
    const permFilterKnown = document.getElementById('perm-filter-known') as HTMLSelectElement | null;
    const permUnknownList = document.getElementById('perm-unknown-list') as HTMLElement | null;
    const permTaxonomyEl = document.getElementById('perm-taxonomy') as HTMLElement | null;
    const permRunLinkEl = document.getElementById('perm-run-link') as HTMLElement | null;
    const permStatusNote = document.getElementById('perm-status-note') as HTMLElement | null;
    const permPipeline = document.getElementById('perm-pipeline') as HTMLElement | null;
    const permExportBtn = document.getElementById('perm-export') as HTMLButtonElement | null;
    const sampleReloadBtn = document.getElementById('sample-reload') as HTMLButtonElement | null;
    const permReloadBtn = document.getElementById('perm-reload') as HTMLButtonElement | null;

    const editModal = document.getElementById('sample-edit-modal') as HTMLElement | null;
    const editCloseBtn = document.getElementById('sample-edit-close') as HTMLButtonElement | null;
    const editSaveBtn = document.getElementById('sample-edit-save') as HTMLButtonElement | null;
    const editMetaEl = document.getElementById('sample-edit-meta') as HTMLElement | null;
    const editIdEl = document.getElementById('sample-edit-id') as HTMLElement | null;
    const editShaEl = document.getElementById('sample-edit-sha') as HTMLElement | null;
    const editPackageEl = document.getElementById('sample-edit-package') as HTMLElement | null;
    const editLabelEl = document.getElementById('sample-edit-label') as HTMLInputElement | null;
    const editFamilyEl = document.getElementById('sample-edit-family') as HTMLInputElement | null;
    const editPrimaryEl = document.getElementById('sample-edit-primary') as HTMLInputElement | null;
    const editSubtypeEl = document.getElementById('sample-edit-subtype') as HTMLInputElement | null;
    const editStatusEl = document.getElementById('sample-edit-status') as HTMLElement | null;

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

    const surface = SampleDetail.createSurface({
      headerTitleEl: document.getElementById('sample-header-title'),
      headerSubtitleEl: document.getElementById('sample-header-subtitle'),
      headerPlatformEl: document.getElementById('sample-header-platform'),
      headerVtStatusEl: document.getElementById('sample-header-vt-status'),
      headerAlignmentEl: document.getElementById('sample-header-alignment'),
      headerLastAnalysisEl: document.getElementById('sample-header-last-analysis'),
      headerShaEl: document.getElementById('sample-header-sha256'),
      coreGridEl: document.getElementById('sample-core-grid'),
      interpretationSectionEl: document.getElementById('sample-interpretation-section'),
      opsGridEl: document.getElementById('sample-ops-grid'),
      opsSectionEl: document.getElementById('sample-ops-section'),
      platformGridEl: document.getElementById('sample-platform-grid'),
      platformNoteEl: document.getElementById('sample-platform-note'),
      platformSectionEl: document.getElementById('sample-platform-section'),
      platformEvidenceGridEl: document.getElementById('sample-platform-evidence-grid'),
      advancedGridEl: document.getElementById('sample-advanced-grid'),
      advancedSectionEl: document.getElementById('sample-advanced-section'),
      errorSectionEl: document.getElementById('sample-error-section'),
      errorBodyEl: document.getElementById('sample-error-body'),
      androidSectionEl,
    });

    function updatePermissionMeta(lastRun: Record<string, unknown> | null): void {
      if (permTaxonomyEl) {
        const taxonomyValue = lastRun ? App.fmt(lastRun.perm_taxonomy_version, '--') : '--';
        permTaxonomyEl.textContent = `Taxonomy: ${taxonomyValue}`;
      }
      if (permRunLinkEl) {
        const runId = lastRun ? App.fmt(lastRun.run_id, '') : '';
        if (runId) {
          const runUrl = App.pageUrl('runs', { q: runId });
          permRunLinkEl.innerHTML = `Run: <a class="table-link" href="${App.escapeHtml(runUrl)}">${App.escapeHtml(runId)}</a>`;
        } else {
          permRunLinkEl.textContent = 'Run: --';
        }
      }
    }

    function updatePermissionStatusNote(sample: Record<string, unknown>, lastRun: Record<string, unknown> | null): void {
      let statusNote = '';
      if (lastRun && Number(lastRun.ok_count || 0) === 0) {
        const reason = App.fmt(lastRun.stopped_reason, '').trim();
        statusNote = 'No permissions persisted because no OK payload was processed in this run.';
        if (reason) statusNote += ` Stopped reason: ${reason}.`;
      } else if (String(sample.vt_status_code || '').toUpperCase() === 'RETRY_WAIT') {
        statusNote = 'No permissions persisted because the sample is in retry wait.';
      }
      permController.setStatus(statusNote);
    }

    async function loadSample(): Promise<void> {
      if (!sampleId && !sha256) {
        if (errorEl) {
          errorEl.innerHTML = '<pre>Provide sample_id or sha256 in the query string.</pre>';
        }
        return;
      }

      const params = new URLSearchParams();
      if (sampleId) params.set('sample_id', sampleId);
      if (sha256) params.set('sha256', sha256);

      try {
        if (errorEl) errorEl.textContent = '';
        if (permErrorEl) permErrorEl.textContent = '';

        const res = await App.fetchJson(`${endpoint}?${params.toString()}`);
        if (!res.ok) {
          if (errorEl) {
            errorEl.innerHTML = '<pre>Sample detail error.\n\nHTTP ' + res.status + '\nerror: ' +
              App.escapeHtml(res.error) + '</pre>';
          }
          return;
        }

        const data = res.body as {
          ok?: boolean;
          error?: unknown;
          sample?: Record<string, unknown>;
          last_run?: Record<string, unknown> | null;
          platform_context?: Record<string, unknown> | null;
        };

        if (!data || data.ok !== true || !data.sample) {
          if (errorEl) {
            errorEl.innerHTML = '<pre>Sample detail error.\n\nHTTP ' + res.status + '\nerror: ' +
              App.escapeHtml(typeof data?.error === 'string' ? data.error : 'unknown') + '</pre>';
          }
          return;
        }

        const sample = data.sample;
        const lastRun = data.last_run || null;

        SampleDetail.renderSummary(summaryEl, sample, {
          showEditButton: true,
          onEdit: updateEndpoint ? (currentSample) => editor.open(currentSample) : null,
        });

        if (!summaryCopyBound) {
          SampleDetail.bindSummaryCopy(summaryEl);
          summaryCopyBound = true;
        }

        if (!updateEndpoint) {
          const editOpenBtn = summaryEl ? summaryEl.querySelector('#sample-edit-open') as HTMLButtonElement | null : null;
          if (editOpenBtn) {
            editOpenBtn.disabled = true;
            editOpenBtn.title = 'Update endpoint not configured.';
          }
        }

        const rendered = surface.renderSample({
          sample,
          last_run: lastRun,
          platform_context: data.platform_context || null,
        });
        updatePermissionMeta(lastRun);
        updatePermissionStatusNote(sample, lastRun);

        loadedSampleId = String(sample.sample_id || '');
        loadedIsAndroid = rendered.isAndroid;
        permController.setExportSampleId(sample.sample_id || '');

        if (!rendered.isAndroid) {
          if (androidWorkflowEl) androidWorkflowEl.style.display = 'none';
          permController.showNonAndroid();
          return;
        }

        if (androidSectionEl) androidSectionEl.style.display = '';
        if (androidWorkflowEl) androidWorkflowEl.style.display = '';
        if (permNonAndroid) permNonAndroid.style.display = 'none';
        await permController.loadPermissions(sample.sample_id);
      } catch (e) {
        if (errorEl) {
          errorEl.innerHTML = '<pre>Sample detail error:\n' +
            App.escapeHtml(e instanceof Error ? e.message : String(e)) + '</pre>';
        }
      }
    }

    if (sampleReloadBtn) {
      sampleReloadBtn.addEventListener('click', () => { void loadSample(); });
    }

    if (permReloadBtn) {
      permReloadBtn.addEventListener('click', () => {
        if (!loadedSampleId) return;
        if (!loadedIsAndroid) {
          permController.showNonAndroid();
          return;
        }
        void permController.loadPermissions(loadedSampleId);
      });
    }

    void loadSample();
  }
}
