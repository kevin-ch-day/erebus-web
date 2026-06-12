import type { AppSurface } from '../../types/app-globals';
import type {
  SamplesPageNamespace,
  SamplesPagePayload,
  SamplesPagePayloadRow,
  SamplesPageRenderMetaOptions,
  SamplesPageRenderRowsOptions,
} from '../../types/samples-page-globals';

const App = window.App as AppSurface | undefined;
const SamplesPage = (window.SamplesPage || (window.SamplesPage = {} as SamplesPageNamespace)) as SamplesPageNamespace;

if (App) {
  const esc = App.escapeHtml;
  const fmt = App.fmt;

  function statusBadge(rawStatus: unknown): string {
    const status = String(rawStatus || '').toUpperCase();
    let cls = 'muted';
    if (status === '') {
      return '<span class="badge muted">NO_STATE</span>';
    }
    if (status === 'ERROR') {
      cls = 'err';
    } else if (['RETRY_WAIT', 'PROCESSING', 'NEW', 'REANALYZE'].includes(status)) {
      cls = 'warn';
    } else if (['LOOKED_UP', 'NO_DATA'].includes(status)) {
      cls = 'ok';
    }

    const label = status === '' ? '--' : status;
    return `<span class="badge ${cls}">${esc(label)}</span>`;
  }

  function truncate(value: unknown, maxLen: number): string {
    const raw = String(value ?? '');
    if (raw.length <= maxLen) return raw;
    if (maxLen <= 3) return raw.slice(0, maxLen);
    return raw.slice(0, maxLen - 3) + '...';
  }

  function familyAlignmentBadge(rawAlignment: unknown): string {
    const alignment = String(rawAlignment || '').toLowerCase();
    let cls = 'muted';
    if (alignment === 'aligned') {
      cls = 'ok';
    } else if (alignment === 'mismatch') {
      cls = 'err';
    } else if (alignment === 'signal_only' || alignment === 'catalog_only') {
      cls = 'warn';
    }
    const labelMap: Record<string, string> = {
      aligned: 'Aligned',
      mismatch: 'Mismatch',
      signal_only: 'Signal only',
      catalog_only: 'Catalog only',
      unlabeled: 'Unlabeled',
      generic_label: 'Generic label',
    };
    const label = alignment === '' ? '--' : (labelMap[alignment] || alignment.replaceAll('_', ' '));
    return `<span class="badge ${cls}">${esc(label)}</span>`;
  }

  SamplesPage.applyColumnsView = (tableEl: Element | null, value: string): void => {
    if (!tableEl) return;
    tableEl.classList.toggle('simple', value === 'simple');
    tableEl.classList.toggle('detailed', value === 'detailed');
  };

  SamplesPage.renderRows = (rows: SamplesPagePayloadRow[], options: SamplesPageRenderRowsOptions): void => {
    const bodyEl = options.bodyEl;
    const detailBase = options.detailBase;
    if (!bodyEl) return;

    bodyEl.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      bodyEl.innerHTML = '<tr><td colspan="16" class="muted">No samples match these filters.</td></tr>';
      return;
    }

    rows.forEach((row) => {
      const sampleId = row.sample_id;
      const sha = row.sha256 || '';
      const shaSuffix = row.sha8 || (sha ? String(sha).slice(-8) : '--');
      const label = row.sample_label || 'Unknown';
      const family = row.family_label || '--';
      const signalFamily = row.popular_threat_name || '--';
      const familyAlignment = row.family_alignment_status || '--';
      const primary = row.classification_primary || '--';
      const status = row.vt_status_code || '';
      const attempts = row.attempt_count ?? '--';
      const lastAttempt = row.last_attempt_at_utc ? App.formatUtc(row.last_attempt_at_utc) : '--';
      const lastHttp = row.last_http_status ?? '--';
      const lastError = row.last_error_category || '--';
      const lastRun = row.last_run_id ?? '--';
      const lastKey = row.last_key_id ?? '--';
      const malicious = row.malicious ?? '--';
      const suspicious = row.suspicious ?? '--';
      const undetected = row.undetected ?? '--';
      const harmless = row.harmless ?? '--';

      const link = detailBase + (detailBase.includes('?') ? '&' : '?') + 'sample_id=' + encodeURIComponent(String(sampleId));

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><a class="table-link" href="${esc(link)}" target="_blank" rel="noopener noreferrer">${esc(sampleId)}</a></td>
        <td><span class="cell-truncate" title="${esc(label)}">${esc(truncate(label, 25))}</span></td>
        <td>${familyAlignmentBadge(familyAlignment)}<br><span class="muted">Signal: ${esc(signalFamily)}</span></td>
        <td><span class="cell-truncate" title="${esc(family)}">${esc(truncate(family, 25))}</span></td>
        <td>${esc(fmt(primary))}</td>
        <td>
          <span class="mono" title="${esc(sha)}">${esc(shaSuffix)}</span>
          <button class="copy-btn" type="button" data-copy="${esc(sha)}" title="Copy full SHA256">Copy</button>
        </td>
        <td class="col-vt">${esc(fmt(malicious))}</td>
        <td class="col-vt">${esc(fmt(suspicious))}</td>
        <td class="col-vt">${esc(fmt(undetected))}</td>
        <td class="col-vt">${esc(fmt(harmless))}</td>
        <td class="col-detail">${esc(fmt(attempts))}</td>
        <td class="col-detail">${esc(lastAttempt)}</td>
        <td class="col-detail">${esc(fmt(lastHttp))}</td>
        <td class="col-detail">${esc(fmt(lastError))}</td>
        <td class="col-detail">${esc(fmt(lastRun))} / ${esc(fmt(lastKey))}</td>
        <td>${statusBadge(status)}</td>
      `;
      bodyEl.appendChild(tr);
    });
  };

  SamplesPage.renderMeta = (payload: SamplesPagePayload, options: SamplesPageRenderMetaOptions): void => {
    const metaEl = options.metaEl;
    const pagesEl = options.pagesEl;
    const pageEl = options.pageEl;
    const prevBtn = options.prevBtn;
    const nextBtn = options.nextBtn;

    if (!metaEl || !pagesEl || !pageEl || !prevBtn || !nextBtn) return;
    const total = payload.total_count ?? 0;
    const page = payload.page ?? 1;
    const pageSize = payload.page_size ?? 0;
    const pages = payload.total_pages ?? 1;
    const activeFilters = Number(options.activeFilters || 0);
    const activeText = activeFilters > 0 ? ` | Active filters: ${activeFilters}` : '';
    metaEl.textContent = `Total samples: ${total} | Page size: ${pageSize}${activeText}`;
    pagesEl.textContent = String(pages);
    pageEl.value = String(page);
    prevBtn.disabled = Number(page) <= 1;
    nextBtn.disabled = Number(page) >= Number(pages);
  };
}
