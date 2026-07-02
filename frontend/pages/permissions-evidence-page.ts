import type { AppSurface, JsonRecord, PermissionIntelSurface } from '../types/app-globals';

type EvidenceRow = JsonRecord & {
  sample_id?: unknown;
  sample_label?: unknown;
  triage_status?: unknown;
  run_id?: unknown;
  last_run_id?: unknown;
  observed_at_utc?: unknown;
  observed_at?: unknown;
  package_name?: unknown;
  android_package_name?: unknown;
  stopped_reason?: unknown;
  ok_count?: unknown;
  perm_taxonomy_version?: unknown;
  vt_status_code?: unknown;
};

const root = document.getElementById('perm-evidence-page') as HTMLElement | null;

if (root && window.App && window.PermissionIntel) {
  const App = window.App as AppSurface;
  const PI = window.PermissionIntel as PermissionIntelSurface;
  const endpoint = root.dataset.evidenceEndpoint || '';
  const initialPermission = root.dataset.permission || '';
  const returnUrl = root.dataset.returnUrl || App.pageUrl('permissions_triage');
  const initialLimit = Number(root.dataset.limit || '25') || 25;

  const inputEl = document.getElementById('perm-evidence-input') as HTMLInputElement | null;
  const limitEl = document.getElementById('perm-evidence-limit') as HTMLSelectElement | null;
  const searchBtn = document.getElementById('perm-evidence-search');
  const reviewLinkEl = document.getElementById('perm-evidence-review-link') as HTMLAnchorElement | null;
  const titleEl = document.getElementById('perm-evidence-title');
  const metaEl = document.getElementById('perm-evidence-meta');
  const bodyEl = document.getElementById('perm-evidence-body');
  const errorEl = document.getElementById('perm-evidence-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const { formatCount, formatUtcDual } = PI;
  const formatUtc = App.formatUtc;
  const pageUrl = App.pageUrl;
  const displayTz = App.getDisplayTz();
  const secondaryTz = App.getSecondaryTz();

  function updateReviewLink(permission: string): void {
    if (!reviewLinkEl) return;
    const perm = permission.trim();
    if (!perm) {
      reviewLinkEl.setAttribute('href', '#');
      reviewLinkEl.setAttribute('aria-disabled', 'true');
      reviewLinkEl.classList.add('btn-disabled');
      return;
    }
    reviewLinkEl.setAttribute('href', pageUrl('permissions_review', { permission: perm, return: returnUrl }));
    reviewLinkEl.removeAttribute('aria-disabled');
    reviewLinkEl.classList.remove('btn-disabled');
  }

  function updateUrl(permission: string, limit: number): void {
    const url = new URL(window.location.href);
    if (permission) url.searchParams.set('permission', permission);
    else url.searchParams.delete('permission');
    if (limit) url.searchParams.set('limit', String(limit));
    else url.searchParams.delete('limit');
    window.history.replaceState({}, '', url.toString());
  }

  async function loadEvidence(permission: string): Promise<void> {
    if (!endpoint || !titleEl || !metaEl || !bodyEl) return;
    const perm = permission.trim();
    const limit = limitEl ? Number(limitEl.value || '25') || 25 : initialLimit;
    updateReviewLink(perm);
    if (!perm) {
      titleEl.textContent = 'Select a permission to view evidence';
      metaEl.textContent = '--';
      bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No evidence loaded yet.</td></tr>';
      return;
    }

    updateUrl(perm, limit);
    if (errorEl) errorEl.textContent = '';
    titleEl.textContent = `Evidence for ${perm}`;
    metaEl.textContent = 'Loading evidence...';
    bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Loading evidence...</td></tr>';

    try {
      const res = await App.fetchJson(`${endpoint}?permission=${encodeURIComponent(perm)}&limit=${encodeURIComponent(String(limit))}`);
      if (!res.ok) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Evidence unavailable. This can occur if VT is on hold or no OK runs exist.</td></tr>';
        metaEl.textContent = `Error: ${fmt(res.error)}`;
        return;
      }

      const rows = Array.isArray(res.body.data) ? res.body.data as EvidenceRow[] : [];
      const meta = (res.body.meta && typeof res.body.meta === 'object') ? res.body.meta as JsonRecord : {};
      const totalCount = Number(meta.total_count || rows.length || 0);
      metaEl.textContent = `Showing ${formatCount(rows.length)} of ${formatCount(totalCount)} evidence rows (limit ${limit})`;
      bodyEl.innerHTML = '';
      if (!rows.length) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No evidence available yet. This can occur if VT is on hold or no OK runs exist.</td></tr>';
        return;
      }

      rows.forEach((row) => {
        const sampleId = fmt(row.sample_id, '');
        const sampleLabel = fmt(row.sample_label, '');
        const sampleText = sampleLabel ? `${sampleLabel} (#${sampleId})` : `Sample #${sampleId}`;
        const sampleUrl = sampleId ? pageUrl('sample', { sample_id: sampleId }) : '';
        const triageStatus = String(row.triage_status || '').toLowerCase();
        const triageBadge = triageStatus === 'oem_candidate' ? ' <span class="badge warn">OEM candidate</span>' : '';
        const runId = fmt(row.run_id || row.last_run_id, '--');
        const observed = formatUtcDual(row.observed_at_utc || row.observed_at);
        const packageName = fmt(row.package_name || row.android_package_name);
        const runStatus = row.stopped_reason ? `Stopped: ${fmt(row.stopped_reason)}`
          : (Number(row.ok_count || 0) > 0 ? 'OK' : '--');
        const taxonomy = fmt(row.perm_taxonomy_version, '--');
        const observedPrimary = observed.primary || formatUtc(row.observed_at_utc || row.observed_at);
        const observedSecondary = observed.secondary;
        const observedHtml = observedSecondary
          ? `<div>${esc(observedPrimary)} (${esc(displayTz)})</div><div class="muted">${esc(observedSecondary)} (${esc(secondaryTz)})</div>`
          : `<div>${esc(observedPrimary)} (${esc(displayTz)})</div>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${sampleUrl
            ? `<a class="table-link" href="${esc(sampleUrl)}">${esc(sampleText)}</a>${triageBadge}`
            : `${esc(sampleText)}${triageBadge}`}</td>
          <td class="mono">${esc(packageName)}</td>
          <td class="mono">${esc(runId)}</td>
          <td>${esc(runStatus)}</td>
          <td class="mono">${esc(taxonomy)}</td>
          <td>${esc(fmt(row.vt_status_code))}</td>
          <td>${observedHtml}</td>
        `;
        bodyEl.appendChild(tr);
      });
    } catch (error) {
      bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Evidence unavailable. This can occur if VT is on hold or no OK runs exist.</td></tr>';
      metaEl.textContent = `Error: ${fmt(error instanceof Error ? error.message : String(error))}`;
    }
  }

  searchBtn?.addEventListener('click', () => { void loadEvidence(inputEl?.value || ''); });
  inputEl?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      void loadEvidence(inputEl.value);
    }
  });
  limitEl?.addEventListener('change', () => { void loadEvidence(inputEl?.value || ''); });
  reviewLinkEl?.addEventListener('click', (event) => {
    if (reviewLinkEl.getAttribute('aria-disabled') === 'true') event.preventDefault();
  });

  if (initialPermission) void loadEvidence(initialPermission);
  else if (limitEl) limitEl.value = String(initialLimit);
  updateReviewLink(initialPermission);
}
