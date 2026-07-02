import type { AppJsonFailure, AppJsonSuccess, AppSurface, JsonRecord } from '../types/app-globals';
import { asRows, toRecord } from '../shared/dom';
import { initPipelineEnginePanelLive } from '../shared/pipeline-engine-panel-live';

type QueueSummaryPayload = {
  accepted?: unknown;
  failed?: unknown;
  duplicates_known?: unknown;
  duplicates_queued?: unknown;
  warnings?: unknown;
  row_results?: unknown;
  errors?: unknown;
};

type LookupRecord = JsonRecord & {
  sample_id?: unknown;
  sample_label?: unknown;
  family_label?: unknown;
  record_created_at_utc?: unknown;
  sha256?: unknown;
  md5?: unknown;
  sha1?: unknown;
  vt_status_code?: unknown;
  attempt_count?: unknown;
  next_eligible_at_utc?: unknown;
  last_attempt_at_utc?: unknown;
  last_http_status?: unknown;
  last_error_category?: unknown;
  last_error_message?: unknown;
  last_run_id?: unknown;
  source_url?: unknown;
};

type LookupPayload = JsonRecord & {
  found?: unknown;
  match_column?: unknown;
  record?: unknown;
  queue_status?: unknown;
  queued_at_utc?: unknown;
};

type ActionLink = {
  href: string;
  label: string;
  primary?: boolean;
  newTab?: boolean;
};

const root = document.getElementById('check-hash-page') as HTMLElement | null;

if (root && window.App) {
  const App = window.App as AppSurface;

  const lookupEndpoint = root.dataset.lookupEndpoint || '';
  const ingestEndpoint = root.dataset.ingestEndpoint || '';
  const sampleBase = root.dataset.sampleBase || '';
  const backlogBase = root.dataset.backlogBase || '';

  const hashInput = document.getElementById('hash-input') as HTMLInputElement | null;
  const checkBtn = document.getElementById('hash-check-btn') as HTMLButtonElement | null;
  const resultBox = document.getElementById('hash-result') as HTMLElement | null;
  const errorBox = document.getElementById('hash-error') as HTMLElement | null;
  const actionBox = document.getElementById('hash-actions') as HTMLElement | null;
  const previewRow = document.getElementById('hash-preview-row') as HTMLElement | null;
  const intakeSection = document.getElementById('artifact-intake') as HTMLElement | null;
  const detailGrid = document.getElementById('hash-detail-grid') as HTMLElement | null;
  const queueBtn = document.getElementById('artifact-queue-btn') as HTMLButtonElement | null;
  const queueStatus = document.getElementById('artifact-queue-status') as HTMLElement | null;
  const queueError = document.getElementById('artifact-queue-error') as HTMLElement | null;
  const sourceSelect = document.getElementById('artifact-source') as HTMLSelectElement | null;
  const sourceOther = document.getElementById('artifact-source-other') as HTMLInputElement | null;

  const esc = App.escapeHtml;
  const localDateFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    month: 'numeric',
    day: 'numeric',
    year: 'numeric',
  });

  function normalizeHash(rawValue: unknown): string {
    return String(rawValue || '').replace(/\s+/g, '').trim().toLowerCase();
  }

  function hasValue(value: unknown): boolean {
    if (value === null || value === undefined) return false;
    return !(typeof value === 'string' && value.trim() === '');
  }

  function detectHashType(rawValue: unknown): string {
    const value = normalizeHash(rawValue);
    if (!value) return '--';
    if (!/^[a-fA-F0-9]+$/.test(value)) return 'Invalid';
    if (value.length === 32) return 'MD5';
    if (value.length === 40) return 'SHA-1';
    if (value.length === 64) return 'SHA-256';
    return 'Invalid';
  }

  function normalizeHashType(type: string): string | null {
    return ['MD5', 'SHA-1', 'SHA-256'].includes(type) ? type : null;
  }

  function bindHint(inputId: string, hintId: string): void {
    const input = document.getElementById(inputId) as HTMLInputElement | null;
    const hint = document.getElementById(hintId) as HTMLElement | null;
    if (!input || !hint) return;
    const update = (): void => {
      hint.textContent = detectHashType(input.value);
    };
    input.addEventListener('input', update);
    update();
  }

  function showBox(el: HTMLElement | null, message: string): void {
    if (!el) return;
    el.textContent = message;
    el.style.display = 'block';
  }

  function clearBox(el: HTMLElement | null): void {
    if (!el) return;
    el.textContent = '';
    el.style.display = 'none';
  }

  function clearDetails(): void {
    if (!detailGrid) return;
    detailGrid.innerHTML = '';
    detailGrid.style.display = 'none';
  }

  function clearActionLinks(): void {
    if (!actionBox) return;
    actionBox.innerHTML = '';
    actionBox.style.display = 'none';
  }

  function setPreviewVisible(visible: boolean): void {
    if (!previewRow) return;
    previewRow.style.display = visible ? 'block' : 'none';
  }

  function setIntakeVisible(visible: boolean): void {
    if (!intakeSection) return;
    intakeSection.style.display = visible ? 'block' : 'none';
  }

  function formatMaybeDate(value: unknown): string {
    if (!hasValue(value)) return '--';
    const raw = String(value);
    const normalized = raw.includes('T') ? raw : `${raw.replace(' ', 'T')}Z`;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return raw;
    return localDateFormatter.format(date);
  }

  function actionButton(link: ActionLink): HTMLAnchorElement {
    const a = document.createElement('a');
    a.className = link.primary ? 'btn btn-primary check-hash-action-btn' : 'btn check-hash-action-btn';
    a.href = link.href;
    if (link.newTab) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }
    a.textContent = link.label;
    return a;
  }

  function setActionLinks(links: ActionLink[]): void {
    if (!actionBox || links.length === 0) {
      clearActionLinks();
      return;
    }
    actionBox.innerHTML = '';
    links.forEach((link) => actionBox.appendChild(actionButton(link)));
    actionBox.style.display = 'flex';
  }

  function sampleLink(sampleId: unknown, sampleLabel: unknown): ActionLink | null {
    if (!sampleBase || !hasValue(sampleId)) return null;
    const id = String(sampleId);
    return {
      href: App.appendQueryParam(sampleBase, 'sample_id', id),
      label: hasValue(sampleLabel) ? `Open sample ${id} (${String(sampleLabel)})` : `Open sample ${id}`,
      primary: true,
      newTab: true,
    };
  }

  function addDetailCard(title: string, rows: Array<[string, unknown]>): void {
    if (!detailGrid || rows.length === 0) return;
    const card = document.createElement('div');
    card.className = 'detail-card';
    const body = rows.map(([label, value]) => (
      `<div class="detail-row"><div class="detail-label">${esc(label)}</div><div class="detail-value">${esc(String(value ?? '--'))}</div></div>`
    )).join('');
    card.innerHTML = `<div class="detail-card-title">${esc(title)}</div>${body}`;
    detailGrid.appendChild(card);
    detailGrid.style.display = 'grid';
  }

  function summarizeQueueResponse(payload: QueueSummaryPayload): { statusText: string; errorText: string; accepted: number } {
    const accepted = Number(payload.accepted || 0);
    const failed = Number(payload.failed || 0);
    const duplicatesKnown = asRows<unknown>(payload.duplicates_known).length;
    const duplicatesQueued = asRows<unknown>(payload.duplicates_queued).length;
    const warnings = asRows<string>(payload.warnings);
    const rowResults = asRows<JsonRecord>(payload.row_results);

    const statusParts: string[] = [];
    const errorParts: string[] = [];

    if (accepted > 0) statusParts.push(`Queued ${accepted} artifact(s).`);
    if (duplicatesKnown > 0) errorParts.push(`${duplicatesKnown} already known in registry.`);
    if (duplicatesQueued > 0) errorParts.push(`${duplicatesQueued} already queued.`);
    if (failed > 0) errorParts.push(`${failed} row(s) were not queued.`);
    if (warnings.length > 0) statusParts.push(warnings.join(' '));

    const detailMessages = rowResults
      .filter((row) => hasValue(row.status) && row.status !== 'accepted')
      .map((row) => String(row.message || '').trim())
      .filter(Boolean);
    if (detailMessages.length > 0) {
      errorParts.push(detailMessages.join(' '));
    } else if (asRows<string>(payload.errors).length > 0) {
      errorParts.push(asRows<string>(payload.errors).join(' '));
    }

    return {
      statusText: statusParts.join(' ').trim(),
      errorText: errorParts.join(' ').trim(),
      accepted,
    };
  }

  function updateLookupButton(): void {
    if (!checkBtn || !hashInput) return;
    checkBtn.disabled = !normalizeHashType(detectHashType(hashInput.value));
  }

  function validateQueueInput(): { ok: true } | { ok: false; message: string } {
    const hashEl = document.getElementById('artifact-hash') as HTMLInputElement | null;
    const sourceEl = document.getElementById('artifact-source') as HTMLSelectElement | null;
    const hashValue = hashEl ? normalizeHash(hashEl.value) : '';
    if (hashEl && hashEl.value !== hashValue) hashEl.value = hashValue;
    const hashType = normalizeHashType(detectHashType(hashValue));
    if (!hashType) return { ok: false, message: 'Provide a valid hash.' };
    if (!sourceEl || !sourceEl.value) return { ok: false, message: 'Select an artifact source.' };
    return { ok: true };
  }

  async function lookupHash(): Promise<void> {
    clearBox(resultBox);
    clearBox(errorBox);
    clearActionLinks();
    clearDetails();
    const hashValue = hashInput ? normalizeHash(hashInput.value) : '';
    if (hashInput && hashInput.value !== hashValue) hashInput.value = hashValue;
    const hashType = normalizeHashType(detectHashType(hashValue));
    if (!hashType || !lookupEndpoint) {
      showBox(errorBox, 'Provide a valid MD5, SHA-1, or SHA-256 hash.');
      return;
    }

    try {
      const res = await App.fetchJson(`${lookupEndpoint}?hash=${encodeURIComponent(hashValue)}`);
      if (!res.ok) {
        const err = res as AppJsonFailure;
        showBox(errorBox, err.error || `Lookup failed (HTTP ${err.status}).`);
        return;
      }

      const body = (res as AppJsonSuccess<JsonRecord>).body;
      const data = toRecord((body as JsonRecord).data ?? body) as LookupPayload;
      const record = toRecord(data.record) as LookupRecord;

      if (Boolean(data.found)) {
        const statusText = hasValue(record.vt_status_code) ? ` Current VT state: ${String(record.vt_status_code)}.` : '';
        showBox(resultBox, `Known artifact found in the catalog.${statusText}`);
        const links: ActionLink[] = [];
        const sampleAction = sampleLink(record.sample_id, record.sample_label);
        if (sampleAction) links.push(sampleAction);
        if (backlogBase) links.push({ href: backlogBase, label: 'Open ingest backlog' });
        setActionLinks(links);
        setPreviewVisible(false);

        const catalogRows: Array<[string, unknown]> = [
          ['Matched by', data.match_column || 'hash'],
          ['Sample ID', record.sample_id],
          ['Sample label', record.sample_label],
          ['Family label', record.family_label],
          ['Catalog created', formatMaybeDate(record.record_created_at_utc)],
          ['SHA-256', record.sha256],
          ['MD5', record.md5],
          ['SHA-1', record.sha1],
        ];
        addDetailCard('Catalog record', catalogRows.filter((entry) => hasValue(entry[1])));

        const stateRows: Array<[string, unknown]> = [
          ['Status', record.vt_status_code],
          ['Attempt count', record.attempt_count],
          ['Next eligible', formatMaybeDate(record.next_eligible_at_utc)],
          ['Last attempt', formatMaybeDate(record.last_attempt_at_utc)],
          ['Last HTTP', record.last_http_status],
          ['Last error category', record.last_error_category],
          ['Last error message', record.last_error_message],
          ['Last run id', record.last_run_id],
          ['Source URL', record.source_url],
        ];
        addDetailCard('VirusTotal state', stateRows.filter((entry) => hasValue(entry[1]) && entry[1] !== '--'));

        setIntakeVisible(false);
        return;
      }

      if (hasValue(data.queue_status)) {
        showBox(resultBox, 'This artifact is already in intake and does not need to be queued again.');
        setPreviewVisible(false);
        const links: ActionLink[] = [];
        if (backlogBase) links.push({ href: backlogBase, label: 'Open ingest backlog', primary: true });
        links.push({ href: '#hash-lookup', label: 'Check another hash' });
        setActionLinks(links);
        addDetailCard('Queue', [
          ['Queue status', data.queue_status],
          ['Queued at', formatMaybeDate(data.queued_at_utc)],
        ]);
        setIntakeVisible(false);
        return;
      }

      showBox(resultBox, 'No catalog match was found. If this artifact should enter the system, queue it below.');
      setPreviewVisible(true);
      setActionLinks([
        { href: '#artifact-intake', label: 'Queue this artifact', primary: true },
        ...(backlogBase ? [{ href: backlogBase, label: 'Review intake backlog first' }] : []),
      ]);
      setIntakeVisible(true);

      const artifactHash = document.getElementById('artifact-hash') as HTMLInputElement | null;
      if (artifactHash) {
        artifactHash.value = hashValue;
        artifactHash.dispatchEvent(new Event('input'));
      }
    } catch (error) {
      setPreviewVisible(true);
      showBox(errorBox, error instanceof Error ? error.message : 'Lookup failed.');
    }
  }

  async function queueArtifact(): Promise<void> {
    clearBox(queueStatus);
    clearBox(queueError);
    const validation = validateQueueInput();
    if (!validation.ok) {
      showBox(queueError, validation.message);
      return;
    }
    if (!ingestEndpoint) {
      showBox(queueError, 'Ingest endpoint not configured.');
      return;
    }

    const getValue = (id: string): string => {
      const el = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null;
      return el ? el.value.trim() : '';
    };

    const payload = {
      items: [{
        artifact_hash: normalizeHash(getValue('artifact-hash')),
        artifact_name: getValue('artifact-name'),
        artifact_family: getValue('artifact-family'),
        artifact_category: getValue('artifact-category'),
        artifact_subtype: getValue('artifact-subtype'),
        artifact_source: getValue('artifact-source'),
        artifact_source_other: getValue('artifact-source-other'),
      }],
    };

    try {
      const res = await App.postJson(ingestEndpoint, payload);
      if (!res.ok) {
        const err = res as AppJsonFailure;
        showBox(queueError, err.error || `Queue submission failed (HTTP ${err.status}).`);
        return;
      }
      const body = (res as AppJsonSuccess<JsonRecord>).body;
      const data = toRecord((body as JsonRecord).data ?? body);
      const summary = summarizeQueueResponse(data);
      if (summary.errorText) showBox(queueError, summary.errorText);
      if (summary.statusText) showBox(queueStatus, summary.statusText);
      if (summary.accepted > 0) {
        setPreviewVisible(false);
        setIntakeVisible(false);
        setActionLinks([
          ...(backlogBase ? [{ href: backlogBase, label: 'Open ingest backlog', primary: true }] : []),
          { href: '#hash-lookup', label: 'Check another hash' },
        ]);
        showBox(resultBox, 'Artifact queued successfully. Review intake backlog instead of re-submitting the same hash.');
      }
    } catch (error) {
      showBox(queueError, error instanceof Error ? error.message : 'Queue submission failed.');
    }
  }

  bindHint('hash-input', 'hash-type-hint');
  bindHint('artifact-hash', 'artifact-hash-hint');

  if (hashInput && checkBtn) {
    hashInput.addEventListener('input', updateLookupButton);
    updateLookupButton();
    checkBtn.addEventListener('click', () => { void lookupHash(); });
    hashInput.addEventListener('keydown', (evt) => {
      if (evt.key === 'Enter') {
        evt.preventDefault();
        if (!checkBtn.disabled) {
          void lookupHash();
        }
      }
    });
  }

  if (sourceSelect && sourceOther) {
    const field = sourceOther.closest('.filter-field') as HTMLElement | null;
    const updateSource = (): void => {
      const show = sourceSelect.value === 'other';
      sourceOther.disabled = !show;
      if (field) field.style.display = show ? 'flex' : 'none';
      if (!field) sourceOther.style.display = show ? 'block' : 'none';
      if (!show) sourceOther.value = '';
    };
    sourceSelect.addEventListener('change', updateSource);
    updateSource();
  }

  if (queueBtn) {
    const updateQueueBtn = (): void => {
      queueBtn.disabled = !validateQueueInput().ok;
    };
    ['artifact-hash', 'artifact-name', 'artifact-family', 'artifact-category', 'artifact-subtype', 'artifact-source', 'artifact-source-other']
      .forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', updateQueueBtn);
          el.addEventListener('change', updateQueueBtn);
        }
      });
    updateQueueBtn();
    queueBtn.addEventListener('click', () => { void queueArtifact(); });
  }

  const pipelineEndpoint = root.dataset.pipelineEndpoint || '';
  if (pipelineEndpoint) {
    initPipelineEnginePanelLive(App, {
      endpoint: pipelineEndpoint,
      idPrefix: root.dataset.pipelinePrefix || 'check-hash-engine',
      liveMetaId: root.dataset.pipelineLiveMeta || undefined,
      refreshSeconds: Number(root.dataset.pipelineRefreshSeconds || '30') || 30,
    });
  }
}
