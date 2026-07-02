import type { AppSurface, JsonRecord, PermissionIntelSurface } from '../types/app-globals';

const root = document.getElementById('perm-oem-page') as HTMLElement | null;

if (root && window.App && window.PermissionIntel) {
  const App = window.App as AppSurface;
  const PI = window.PermissionIntel as PermissionIntelSurface;
  const endpoint = root.dataset.permissionsEndpoint || '';
  const lovEndpoint = root.dataset.lovEndpoint || '';
  const pageSize = Number(root.dataset.pageSize || 200);

  const searchEl = document.getElementById('perm-oem-search') as HTMLInputElement | null;
  const statusEl = document.getElementById('perm-oem-status') as HTMLSelectElement | null;
  const searchBtn = document.getElementById('perm-oem-search-btn');
  const bodyEl = document.getElementById('perm-oem-body');
  const metaEl = document.getElementById('perm-oem-meta');
  const errorEl = document.getElementById('perm-oem-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const fmtCount = PI.formatCount;
  const fmtUtc = App.formatUtc;
  const pageUrl = App.pageUrl;

  let statusLabelMap = new Map<string, string>();

  const triageLink = (namespaceValue: string) => pageUrl('permissions_triage', { namespace: namespaceValue });
  const evidenceLink = (permissionValue: string) => pageUrl('permissions_evidence', { permission: permissionValue });

  if (!endpoint || !bodyEl || !metaEl || !errorEl) {
    // page shell not ready
  } else {
    const page = PI.createReadonlyCatalogPage({
      endpoint,
      lovEndpoint,
      pageSize,
      bodyEl,
      metaEl,
      errorEl,
      colSpan: 6,
      loadMessage: 'Loading OEM permissions...',
      emptyMessage: 'No OEM permissions found.',
      emptyMeta: 'Check OEM prefixes in config or android_permission_dict_unknown for data.',
      renderMetaText: (meta: JsonRecord) => `Showing ${fmtCount(meta.total_count)} permissions.`,
      buildParams: () => ({
        q: searchEl?.value.trim() || '',
        status: statusEl?.value || '',
      }),
      renderRow: (row: JsonRecord) => {
        const permissionValue = fmt(row.permission_string);
        const namespaceValue = fmt(row.namespace);
        const statusKey = String(row.triage_status || '').toLowerCase();
        const statusLabel = statusLabelMap.get(statusKey) || fmt(row.triage_status);
        return `
          <td class="mono">${esc(permissionValue)}</td>
          <td class="mono">${esc(namespaceValue)}</td>
          <td>${esc(statusLabel)}</td>
          <td title="Historical seen count">${esc(fmtCount(row.seen_count))}</td>
          <td>${esc(row.last_seen_at_utc ? fmtUtc(row.last_seen_at_utc) : '--')}</td>
          <td>
            <a class="table-link" href="${esc(triageLink(namespaceValue))}">Triage</a>
            <span class="muted"> | </span>
            <a class="table-link" href="${esc(evidenceLink(permissionValue))}">Evidence</a>
          </td>
        `;
      },
      loadLov: (lov: unknown) => {
        if (!statusEl) return;
        const body = (lov && typeof lov === 'object') ? lov as Record<string, unknown> : {};
        const statuses = Array.isArray(body.triage_statuses) ? body.triage_statuses as Array<Record<string, unknown>> : [];
        if (!statuses.length) return;
        const options = ['<option value="">All</option>'];
        statusLabelMap = new Map();
        statuses.forEach((item) => {
          const key = String(item.key || '').toLowerCase();
          const label = String(item.label || '');
          if (!key || !label) return;
          statusLabelMap.set(key, label);
          options.push(`<option value="${esc(key)}">${esc(label)}</option>`);
        });
        statusEl.innerHTML = options.join('');
      },
    });

    searchBtn?.addEventListener('click', () => { void page.resetAndLoad(); });
    PI.bindEnterReload([searchEl], () => { void page.resetAndLoad(); });
    statusEl?.addEventListener('change', () => { void page.resetAndLoad(); });
    void page.primeLov().finally(() => { void page.loadPage(); });
  }
}
